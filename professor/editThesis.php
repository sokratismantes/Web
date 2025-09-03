<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: loginn.php");
    exit();
}

// PDO
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$dbusername = "root";
$dbpassword = "";
try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης: " . $e->getMessage());
}

// Βρες τον τρέχοντα επιβλέποντα (από το login)
$stmt = $pdo->prepare("
    SELECT p.professor_id
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$professor_id = (int)$stmt->fetchColumn();
if ($professor_id <= 0) {
    die("Δεν βρέθηκε επιβλέπων για τον χρήστη.");
}

// ---- Ποιο thesis επεξεργάζομαι;
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($thesis_id <= 0) {
    die("Άκυρο αίτημα.");
}

// Φέρε τα στοιχεία της διπλωματικής ΜΟΝΟ αν:
// - ανήκει στον τρέχοντα επιβλέποντα ΚΑΙ
// - είναι 'Υπό Ανάθεση'
$sql = "
    SELECT thesis_id, title, description, status, start_date, end_date, topic_pdf
    FROM theses
    WHERE thesis_id = :tid
      AND supervisor_id = :pid
      AND status = 'Υπό Ανάθεση'
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
$thesis = $st->fetch();

if (!$thesis) {
    // Είτε δεν σου ανήκει, είτε δεν είναι προς ανάθεση
    http_response_code(403);
    die("Δεν επιτρέπεται η επεξεργασία.");
}

$message = "";

// ---- Υποβολή αλλαγών
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date  = $_POST['start_date'] ?? '';
    $end_date    = empty($_POST['end_date']) ? null : $_POST['end_date'];

    // Απλά validations
    if ($title === '' || $description === '' || $start_date === '') {
        $message = "Συμπλήρωσε τα υποχρεωτικά πεδία.";
    } else {
        // Διαχείριση ΠΡΟΑΙΡΕΤΙΚΗΣ αντικατάστασης PDF
        $newPdfPath = null;
        $deleteOld  = false;

        if (!empty($_FILES['topic_pdf']['name'])) {
            if ($_FILES['topic_pdf']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['topic_pdf']['size'] > 10 * 1024 * 1024) {
                    $message = "Το PDF υπερβαίνει τα 10MB.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($_FILES['topic_pdf']['tmp_name']);
                    if ($mime !== 'application/pdf') {
                        $message = "Επιτρέπονται μόνο αρχεία PDF.";
                    } else {
                        $uploadDir = __DIR__ . '/uploads/theses_pdfs';
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                        $baseName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['topic_pdf']['name']));
                        $unique   = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                        $rel      = "uploads/theses_pdfs/{$unique}_{$baseName}";
                        $abs      = __DIR__ . '/' . $rel;

                        if (move_uploaded_file($_FILES['topic_pdf']['tmp_name'], $abs)) {
                            $newPdfPath = $rel;
                            $deleteOld  = !empty($thesis['topic_pdf']); // αν υπήρχε παλιό
                        } else {
                            $message = "Αποτυχία αποθήκευσης του PDF.";
                        }
                    }
                }
            } else {
                $message = "Σφάλμα ανεβάσματος PDF (code: " . (int)$_FILES['topic_pdf']['error'] . ").";
            }
        }

        if ($message === '') {
            try {
                // Ενημέρωση βασικών πεδίων (όχι status)
                $upd = $pdo->prepare("
                    UPDATE theses
                    SET title = :t, description = :d, start_date = :sd, end_date = :ed
                    WHERE thesis_id = :id AND supervisor_id = :pid AND status = 'Υπό Ανάθεση'
                    LIMIT 1
                ");
                $upd->execute([
                    ':t'=>$title, ':d'=>$description,
                    ':sd'=>$start_date, ':ed'=>$end_date,
                    ':id'=>$thesis_id, ':pid'=>$professor_id
                ]);

                // Αν υπάρχει νέο PDF, ενημέρωσε topic_pdf
                if ($newPdfPath) {
                    try {
                        $pdfUpd = $pdo->prepare("UPDATE theses SET topic_pdf = :p WHERE thesis_id = :id LIMIT 1");
                        $pdfUpd->execute([':p'=>$newPdfPath, ':id'=>$thesis_id]);

                        // Προαιρετικά: σβήσε το παλιό από τον δίσκο
                        if ($deleteOld) {
                            $oldAbs = __DIR__ . '/' . $thesis['topic_pdf'];
                            if (is_file($oldAbs)) { @unlink($oldAbs); }
                        }
                    } catch (PDOException $e) {
                        // Αν δεν υπάρχει στήλη topic_pdf, το αγνοούμε σιωπηρά
                    }
                }

                $_SESSION['success_message'] = "Η διπλωματική ενημερώθηκε επιτυχώς.";
                header("Location: listaDiplomatikon.php");
                exit();
            } catch (PDOException $e) {
                $message = "Σφάλμα ενημέρωσης: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Επεξεργασία Θέματος</title>
<style>
    html, body{
    height: 100%;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    }
            body {
                font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
                background: linear-gradient(to right, #e2e2e2, #c9d6ff);
                color: #333;
                font-size: 0.96rem;
                min-height: 100vh;
            }
            body::before {
                content: "";
                position: fixed;
                inset: 0;
                background-color: hsla(211, 32.3%, 51.4%, 0.35);
                z-index: -1;
            }
    .container{max-width:700px;margin:40px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 10px rgba(0,0,0,.08)}
    h1{margin:0 0 16px;color:#0056b3}
    form{display:flex;flex-direction:column;gap:12px}
    input,textarea,button{padding:10px;font-size:1rem;border:1px solid #ccc;border-radius:4px}
    button{background:#0056b3;color:#fff;border:none;cursor:pointer}
    button:hover{background:#00408a}
    .muted{color:#666;font-size:.9rem}
    .alert{margin-bottom:10px;font-weight:700}
    .error{color:#b30000}
    .success{color:green}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    a.link{color:#0056b3;text-decoration:none}
    .site-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background-color: rgba(0, 51, 102, 0.92); color: white; box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); font-family: 'Segoe UI', sans-serif; margin-bottom: 24px; height: 80px; position: relative; z-index: 10; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;}
    .site-header .left { display: flex; align-items: center; gap: 10px;}
    .site-header .logo { width:95px; height: 80px;}
    .system-name { font-size: 20px; font-weight: 600;}
    .site-header .right { display: flex; align-items: center; gap: 20px;}
    .site-header .right nav a { color: white; text-decoration: none; margin-right: 15px;}
    .site-header .user-info { font-weight: 500;}
    footer { flex-shrink: 0; width: 100%; background-color: rgba(0, 51, 102, 0.92); color: white; text-align: center; padding: 30px; margin-top: 20px; height:80px;}
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
            <a href="professor_home.php">Αρχική</a>
            <a href="listaDiplomatikon.php">Οι Διπλωματικές Μου</a>
            <a href="proskliseis.php">Προσκλήσεις</a>
            <a href="statistika.php">Στατιστικά</a>
        </nav>
        <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
    </div>
</header>
<div class="container">
    <h1>Επεξεργασία Θέματος</h1>

    <?php if ($message): ?>
      <div class="alert <?php echo (stripos($message,'σφάλ')!==false || stripos($message,'error')!==false)?'error':'success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Τίτλος</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($thesis['title']??'', ENT_QUOTES); ?>" required>

        <label>Περιγραφή</label>
        <textarea name="description" rows="6" required><?php echo htmlspecialchars($thesis['description']??'', ENT_QUOTES); ?></textarea>

        <div class="row">
            <div>
                <label>Ημ/νία Έναρξης</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($thesis['start_date']??'', ENT_QUOTES); ?>" required>
            </div>
            <div>
                <label>Ημ/νία Λήξης</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($thesis['end_date']??'', ENT_QUOTES); ?>">
            </div>
        </div>

        <div>
            <label>Τρέχον PDF (αν υπάρχει)</label><br>
            <?php if (!empty($thesis['topic_pdf'])): ?>
                <a class="link" href="<?php echo htmlspecialchars($thesis['topic_pdf'], ENT_QUOTES); ?>" target="_blank">Προβολή PDF</a>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </div>

        <label>Αντικατάσταση PDF (προαιρετικό)</label>
        <input type="file" name="topic_pdf" accept="application/pdf">

        <button type="submit">Αποθήκευση Αλλαγών</button>
    </form>

    <p class="muted">* Η επεξεργασία επιτρέπεται μόνο για θέματα με κατάσταση <strong>Υπό Ανάθεση</strong>.</p>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>
</body>
</html>
