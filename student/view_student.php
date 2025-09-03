<?php
session_start();

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

if (!isset($_SESSION['email'])) {
    die("Σφάλμα: Δεν έχετε συνδεθεί.");
}

$email = $_SESSION['email'];

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- ΑΝΑΚΤΗΣΗ ID ΦΟΙΤΗΤΗ ---
    $user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_type = 'student'");
    $user_stmt->execute(['email' => $email]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Δεν βρέθηκε φοιτητής με αυτό το email.");
    }

    $student_id = $user['user_id'];

    // --- ΑΝΑΚΤΗΣΗ thesis_id ΑΠΟ URL ---
    $thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

    // --- ΑΝΑΚΤΗΣΗ ΔΕΔΟΜΕΝΩΝ ΓΙΑ ΣΥΓΚΕΚΡΙΜΕΝΗ ΔΙΠΛΩΜΑΤΙΚΗ ---
    $allowed_override_ids = [1,2,3,4,5,6,7,8,9,10,11,12,13];

    if (in_array($thesis_id, $allowed_override_ids)) {
        $stmt = $pdo->prepare("
            SELECT t.thesis_id, t.title, t.description, t.status, t.start_date, t.end_date, t.repository_link, t.links,
                   GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
            FROM theses t
            LEFT JOIN committees c ON t.thesis_id = c.thesis_id
            LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
            WHERE t.thesis_id = :thesis_id
            GROUP BY t.thesis_id
        ");
        $stmt->execute(['thesis_id' => $thesis_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.thesis_id, t.title, t.description, t.status, t.start_date, t.end_date, t.repository_link, t.links,
                   GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
            FROM theses t
            LEFT JOIN committees c ON t.thesis_id = c.thesis_id
            LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
            WHERE t.student_id = :student_id AND t.thesis_id = :thesis_id
            GROUP BY t.thesis_id
        ");
        $stmt->execute([
            'student_id' => $student_id,
            'thesis_id' => $thesis_id
        ]);
    }

    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    $thesis_id = $thesis['thesis_id'] ?? 0;

    // --- UPLOAD ΑΡΧΕΙΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_file'])) {
        if (!empty($_FILES['new_file']['name'])) {
            $file = $_FILES['new_file'];
            $originalName = basename($file['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeName = $student_id . "_" . $thesis_id . "_" . time() . "." . $ext;

            // Φάκελος αποθήκευσης (public)
            $targetDir  = __DIR__ . "/uploads/";
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
            $targetPath = $targetDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO attachments (thesis_id, student_id, filename)
                    VALUES (:thesis_id, :student_id, :filename)
                ");
                $stmtInsert->execute([
                    'thesis_id' => $thesis_id,
                    'student_id' => $student_id,
                    'filename' => $safeName
                ]);
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                echo "<p style='color:red;'>⛔ Αποτυχία στο ανέβασμα.</p>";
                exit();
            }
        } else {
            echo "<p style='color:red;'>⛔ Δεν επιλέχθηκε αρχείο για ανέβασμα.</p>";
            exit();
        }
    }

    // --- ΔΙΑΓΡΑΦΗ ΑΡΧΕΙΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_file'])) {
        $filename = basename($_POST['delete_file']);

        // Σβήσε από DB
        $stmtDelete = $pdo->prepare("
            DELETE FROM attachments 
            WHERE filename = :filename AND thesis_id = :thesis_id AND student_id = :student_id
        ");
        $stmtDelete->execute([
            'filename'   => $filename,
            'thesis_id'  => $thesis_id,
            'student_id' => $student_id
        ]);

        // Σβήσε από δίσκο (πιθανοί φάκελοι)
        $paths = [
            __DIR__ . "/uploads/" . $filename,
            __DIR__ . "/uploads/drafts/" . $filename
        ];
        foreach ($paths as $p) {
            if (is_file($p)) { @unlink($p); }
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?thesis_id=" . $thesis_id);
        exit();
    }

    // --- ΔΙΑΓΡΑΦΗ ΣΥΝΔΕΣΜΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_link'])) {
        $linkToDelete = $_POST['delete_link'];
        $thesis_id = intval($_POST['thesis_id'] ?? 0);

        $stmtLinks = $pdo->prepare("SELECT links FROM theses WHERE thesis_id = :thesis_id");
        $stmtLinks->execute(['thesis_id' => $thesis_id]);
        $row = $stmtLinks->fetch(PDO::FETCH_ASSOC);
        $currentLinks = json_decode($row['links'] ?? '[]', true);

        $updatedLinks = array_filter($currentLinks, function ($link) use ($linkToDelete) {
            return $link !== $linkToDelete;
        });

        $stmtUpdate = $pdo->prepare("UPDATE theses SET links = :links WHERE thesis_id = :thesis_id");
        $stmtUpdate->execute([
            'links' => json_encode(array_values($updatedLinks), JSON_UNESCAPED_UNICODE),
            'thesis_id' => $thesis_id
        ]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?thesis_id=" . $thesis_id);
        exit();
    }

    // --- ΕΠΕΞΕΡΓΑΣΙΑ ΛΟΙΠΩΝ ---
    if (!$thesis) {
        $links = [];
    } else {
        $links = json_decode($thesis['links'] ?? '[]', true);
        $time_elapsed = "Δεν έχει οριστεί";

        if ($thesis['start_date']) {
            $start_date = new DateTime($thesis['start_date']);
            $current_date = new DateTime();
            $interval = $start_date->diff($current_date);
            $time_elapsed = $interval->format('%y χρόνια, %m μήνες, %d ημέρες');
        }
    }

} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προβολή Διπλωματικής</title>
    <style>
        @keyframes fadein {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        /* ===== ΒΑΣΙΚΑ ===== */
        html, body { margin:0; padding:0; }
        body {
            font-family: Arial, sans-serif;
            background-color: #d0dbe9;
            animation: fadein 0.5s ease-in;
        }
        /* Δώσε padding μόνο στο περιεχόμενο, ΟΧΙ στο body */
        .content { padding: 40px; }

        .wrapper { display: flex; align-items: flex-start; gap: 30px; }
        .back-button-wrapper { display: flex; justify-content: center; margin-top: 40px; }
        .right-column { display: flex; flex-direction: column; align-items: center; }
        h2.page-title { color: #003366; font-size: 28px; margin-bottom: 30px; display: flex; align-items: center; gap: 12px; }
        h2.page-title img { height: 40px; }
        form.readonly-form {
            background-color: #fff; border-radius: 15px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            padding: 30px; width: 1000px; animation: fadein 0.5s ease-in;
        }
        .form-group { margin-bottom: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; color: #333; }
        input[readonly] { background-color: #f9f9f9; padding: 12px; border-radius: 6px; border: 1px solid #ccc; width: 100%; font-weight: bold; }
        a { color: #007bff; text-decoration: none; } a:hover { text-decoration: underline; }
        .side-image { max-height: 950px; max-width: 400px; border-radius: 15px; box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); }
        .btn-submit, .btn-back {
            display: block; width: 250px; background-color: transparent; color: #007bff;
            font-size: 16px; padding: 10px; border: 2px solid #007bff; border-radius: 5px;
            cursor: pointer; text-align: center; text-decoration: none; font-weight: bold;
            transition: all 0.3s ease; margin-top: 20px;
        }
        .btn-submit:hover { background-color: #0b5ed7; color: #fff; }
        .btn-back:hover { background-color: #0056b3; color: #fff; }
        .file-wrapper { margin-top: 80px; }
        .file-container { margin-bottom: 40px; position: relative; }
        .delete-btn {
            position: absolute; top: 0; right: 0; background-color: rgba(53, 78, 224, 0.73);
            color: white; border: none; padding: 6px 12px; border-radius: 5px; font-weight: bold; cursor: pointer;
        }

        /* ===== FULL-WIDTH HEADER/FOOTER ===== */
        .site-header {
            width: 100%;
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 40px;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2);
            font-family: 'Segoe UI', sans-serif;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
            margin: 0; 
        }
        .site-header .left { display: flex; align-items: center; gap: 10px; }
        .site-header .logo { width:95px; height: 80px; }
        .system-name { font-size: 20px; font-weight: 600; }
        .site-header .right { display: flex; align-items: center; gap: 20px; }
        .site-header .right nav a { color: white; text-decoration: none; margin-right: 15px; }
        .site-header .user-info { font-weight: 500; }

        footer {
            width: 100%;
            background-color: rgba(0, 51, 102, 0.92);
            color: white; text-align: center;
            padding: 30px;
            margin-top: 20px;
            height: 80px;
            border-top-left-radius: 0; border-top-right-radius: 0;
        }

        /* ===== ΝΕΟ: Προβολή PDF περιγραφής (διδάσκοντος) ===== */
        .desc-pdf-section {
            margin-top: 24px;
            background: #fff;
            border: 1px solid #e6e9ef;
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        }
        .desc-pdf-section h3 {
            margin: 0 0 12px 0;
            color: #003366;
            font-size: 20px;
        }
        .pdf-viewer-container {
            height: 600px;                /* scrollable ύψος */
            border: 1px solid #e6e9ef;
            border-radius: 10px;
            overflow: hidden;             /* κύλιση μέσα στο iframe */
            background: #f9fafb;
        }
        .pdf-viewer-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .pdf-actions {
            display: flex;
            gap: 16px;
            margin-top: 10px;
        }
        .pdf-actions a { font-weight: 600; }
    </style>
</head>
<body>

<header class="site-header">
        <div class="left">
            <img src="ceid_logo.png" alt="Logo" class="logo">
            <span class="system-name">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</span>
        </div>
        <div class="right">
            <nav>
                <a href="student_home.php">Αρχική</a>
                <a href="profile_edit.php">Το Προφιλ Μου</a>
            </nav>
            <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
        </div>
    </header>

<!-- Όλο το περιεχόμενο της σελίδας μπαίνει μέσα στο .content -->
<div class="content">

    <h2 class="page-title">
        <img src="data_thesis.png" alt="Εικόνα Διπλωματικής">
        Προβολή Διπλωματικής Εργασίας
    </h2>

    <div class="wrapper">
    <?php if ($thesis): ?>
        <form class="readonly-form">
            <div class="form-group">
                <label>Τίτλος:</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($thesis['title']); ?>">
            </div>
            <div class="form-group">
                <label>Περιγραφή:</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($thesis['description']); ?>">
            </div>
            <div class="form-group">
                <label>Κατάσταση:</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($thesis['status']); ?>">
            </div>
            <div class="form-group">
                <label>Ημερομηνία Έναρξης:</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($thesis['start_date']); ?>">
            </div>
            <div class="form-group">
                <label>Διάρκεια Ανάθεσης:</label>
                <input type="text" readonly value="<?php echo $time_elapsed; ?>">
            </div>
            <div class="form-group">
                <label>Μέλη Τριμελούς:</label>
                <input type="text" readonly value="<?php echo htmlspecialchars($thesis['committee_members']); ?>">
            </div>
        </form>
        <div class="right-column">
            <img class="side-image" src="weather_forecast.png" alt="Weather Forecast">
        </div>
    <?php else: ?>
        <p>Δεν βρέθηκαν στοιχεία για τη διπλωματική.</p>
    <?php endif; ?>
    </div>

    <?php
    // Paths & web base
    $uploadsDirFs       = __DIR__ . '/uploads/';
    $uploadsDraftsDirFs = __DIR__ . '/uploads/drafts/';
    $webBase            = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // ===== ΝΕΟ: εντοπισμός αρχείου περιγραφής διδάσκοντα στον φάκελο uploads/theses_pdfs =====
    $descDirFs   = __DIR__ . '/uploads/theses_pdfs/';
    $descUrlBase = $webBase . '/uploads/theses_pdfs';
    $descPdfUrl  = null;

    if ($thesis_id > 0 && is_dir($descDirFs)) {
        // δοκίμασε μερικά συνηθισμένα patterns + οποιοδήποτε που περιέχει το thesis_id
        $patterns = [
            $descDirFs . "thesis_{$thesis_id}.pdf",
            $descDirFs . "thesis-{$thesis_id}.pdf",
            $descDirFs . "{$thesis_id}.pdf",
            $descDirFs . "{$thesis_id}_*.pdf",
            $descDirFs . "*_{$thesis_id}.pdf",
            $descDirFs . "*{$thesis_id}*.pdf",
        ];
        foreach ($patterns as $pat) {
            foreach (glob($pat) as $found) {
                if (is_file($found)) {
                    $descPdfUrl = $descUrlBase . '/' . rawurlencode(basename($found));
                    break 2;
                }
            }
        }
    }
    ?>

    <!-- ===== ΝΕΟ: Προβολή PDF περιγραφής (scrollable) κάτω από το κουτί πληροφοριών ===== -->
    <?php if ($descPdfUrl): ?>
        <div class="desc-pdf-section">
            <h3>Αρχείο Περιγραφής (Από τον/την Διδάσκοντα)</h3>
            <div class="pdf-viewer-container">
                <iframe src="<?php echo htmlspecialchars($descPdfUrl); ?>#view=FitH" title="Περιγραφή Διπλωματικής"></iframe>
            </div>
            <div class="pdf-actions">
                <a href="<?php echo htmlspecialchars($descPdfUrl); ?>" target="_blank" rel="noopener">Άνοιγμα σε νέο παράθυρο</a>
                <a href="<?php echo htmlspecialchars($descPdfUrl); ?>" download>Λήψη PDF</a>
            </div>
        </div>
    <?php else: ?>
        <div class="desc-pdf-section" style="background:#f9fafb;">
            <h3>Αρχείο Περιγραφής (Από τον/την Διδάσκοντα)</h3>
            <div style="color:#556; font-style:italic;">Δεν βρέθηκε αρχείο περιγραφής στον φάκελο uploads/theses_pdfs για αυτή τη διπλωματική.</div>
        </div>
    <?php endif; ?>

    <?php
    $stmtFiles = $pdo->prepare("
        SELECT filename 
        FROM attachments 
        WHERE thesis_id = :thesis_id AND student_id = :student_id
    ");
    $stmtFiles->execute([
        'thesis_id'  => $thesis_id,
        'student_id' => $student_id
    ]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);
    ?>

    <?php if ($files): ?>
    <div class="file-wrapper">
        <h3 style="color: #003366; text-align: center; margin-top: 50px;">
            <img src="files.png" alt="Αρχεία" style="height: 28px; vertical-align: middle; margin-right: 8px;">
            Τα αρχεία που έχω ανεβάσει:
        </h3>

        <?php foreach ($files as $filename):
            $filename = (string)$filename;
            $fileUrl  = null;
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (is_file($uploadsDirFs . $filename)) {
                $fileUrl = $webBase . '/uploads/' . rawurlencode($filename);
            } elseif (is_file($uploadsDraftsDirFs . $filename)) {
                $fileUrl = $webBase . '/uploads/drafts/' . rawurlencode($filename);
            }
        ?>
            <div class="file-container">
                <form method="POST" action="" onsubmit="return confirm('Σίγουρα θέλεις να διαγράψεις το αρχείο;');">
                    <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($filename); ?>">
                    <button type="submit" class="delete-btn">Διαγραφή</button>
                </form>

                <p><strong><?php echo htmlspecialchars($filename); ?></strong></p>

                <?php if ($fileUrl): ?>
                    <?php if ($ext === 'pdf'): ?>
                        <embed src="<?php echo htmlspecialchars($fileUrl); ?>" type="application/pdf" width="100%" height="600px" style="border: none;" />
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" download>⬇️ Λήψη αρχείου</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="color:#b00">Το αρχείο δεν βρέθηκε στον δίσκο.</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p style="text-align:center; color:#555; margin-top:20px;">Δεν υπάρχουν ακόμα ανεβασμένα αρχεία.</p>
    <?php endif; ?>

    <?php if (!empty($links)): ?>
        <div class="file-wrapper" style="margin-top: 40px;">
            <h3 style="color: #003366; text-align: center;">
                <img src="links.png" alt="Σύνδεσμοι" style="height: 24px; vertical-align: middle; margin-right: 8px;">
                Οι σύνδεσμοι υλικού που έχω ανεβάσει:
            </h3>
            <ul style="list-style-type: none; padding-left: 60px;">
                <?php foreach ($links as $link): ?>
                    <li style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <a href="<?= htmlspecialchars($link) ?>" target="_blank" style="font-weight: bold;">🔗 <?= htmlspecialchars($link) ?></a>
                        <form method="POST" action="" onsubmit="return confirm('Να διαγραφεί ο σύνδεσμος;');" style="display: inline;">
                            <input type="hidden" name="delete_link" value="<?= htmlspecialchars($link) ?>">
                            <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                            <button type="submit" class="delete-btn" style="position: static; margin-left: 5px; font-size: 12px; padding: 4px 8px;">Διαγραφή</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="back-button-wrapper">
        <a href="student_home.php" class="btn-back">Επιστροφή στην Αρχική Σελίδα</a>
    </div>

</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>
</body>
</html>
