<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: loginn.php");
    exit();
}

// Σύνδεση με τη βάση δεδομένων μέσω PDO
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

// Βρες τον professor_id από το email του session (ώστε να ΜΗΝ τον διαλέγει από φόρμα)
$supervisor_id = 0;
try {
    $stmt = $pdo->prepare("
        SELECT p.professor_id
        FROM professors p
        JOIN users u ON u.user_id = p.professor_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['email']]);
    $supervisor_id = (int)$stmt->fetchColumn();
    if ($supervisor_id <= 0) {
        die("Δεν βρέθηκε επιβλέπων για τον χρήστη.");
    }
} catch (PDOException $e) {
    die("Σφάλμα κατά την ανάκτηση επιβλέποντα: " . $e->getMessage());
}

$message = "";
$status  = null; // 'success' | 'error' για το UI/redirect

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // Δεν υπάρχει πλέον επιλογή κατάστασης στη φόρμα – θέτουμε default server-side:
    $statusThesis = 'Υπό Ανάθεση';
    $start_date  = $_POST['start_date'] ?? '';
    $end_date    = empty($_POST['end_date']) ? null : $_POST['end_date'];

    // --- ΠΡΟΤΥΠΟ ΟΝΟΜΑΤΟΣ PDF από τη φόρμα (π.χ. "thesis_{id}") ---
    $pdfNamePattern = trim($_POST['pdf_name_pattern'] ?? '');
    if ($pdfNamePattern === '') {
        $pdfNamePattern = 'thesis_{id}';
    }
    // αν δεν περιέχει {id}, πρόσθεσέ το στο τέλος για να είναι σίγουρο
    if (strpos($pdfNamePattern, '{id}') === false) {
        $pdfNamePattern .= '_{id}';
    }

    // --- Χειρισμός PDF (προαιρετικό) ---
    $uploadedPdfPath = null; // ΤΕΛΙΚΟ σχετικό path που θα μπει στη στήλη topic_pdf
    $pdfTempRel = null;      // προσωρινό σχετικό path (μέχρι να πάρουμε thesis_id)
    $pdfTempAbs = null;      // προσωρινό απόλυτο path

    if (!empty($_FILES['topic_pdf']['name'])) {
        if ($_FILES['topic_pdf']['error'] === UPLOAD_ERR_OK) {
            $maxBytes = 10 * 1024 * 1024; // 10MB
            if ($_FILES['topic_pdf']['size'] > $maxBytes) {
                $message = "Το PDF υπερβαίνει το επιτρεπτό μέγεθος (10MB).";
                $status  = 'error';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($_FILES['topic_pdf']['tmp_name']);
                if ($mime !== 'application/pdf') {
                    $message = "Επιτρέπονται μόνο αρχεία PDF.";
                    $status  = 'error';
                } else {
                    $uploadDir = __DIR__ . '/uploads/theses_pdfs';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    // Ανεβάζουμε ΠΡΟΣΩΡΙΝΑ με ένα ασφαλές, μοναδικό όνομα
                    $tmpBase   = 'tmp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $pdfTempRel = 'uploads/theses_pdfs/' . $tmpBase;
                    $pdfTempAbs = __DIR__ . '/' . $pdfTempRel;

                    if (!move_uploaded_file($_FILES['topic_pdf']['tmp_name'], $pdfTempAbs)) {
                        $message = "Αποτυχία αποθήκευσης του αρχείου PDF.";
                        $status  = 'error';
                    } else {
                        @chmod($pdfTempAbs, 0644);
                    }
                }
            }
        } else {
            $message = "Σφάλμα ανεβάσματος αρχείου (code: " . (int)$_FILES['topic_pdf']['error'] . ").";
            $status  = 'error';
        }
    }

    // Αν δεν έχει ήδη παραχθεί μήνυμα λάθους από το upload, προχώρησε με την SP + αποθήκευση PDF path
    if ($title !== '' && $description !== '' && $start_date !== '' && empty($message)) {
        try {
            // Κλήση Stored Procedure για εισαγωγή δεδομένων
            $sql = "CALL AddThesis(:title, :description, :status, :start_date, :end_date, :supervisor_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $statusThesis);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':supervisor_id', $supervisor_id, PDO::PARAM_INT);

            $stmt->execute();
            // ΣΗΜΑΝΤΙΚΟ για MySQL + CALL
            $stmt->closeCursor();

            // Πάρε το id της διπλωματικής που δημιουργήθηκε (αν η SP κάνει INSERT σε auto_increment)
            $newThesisId = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn();

            // Fallback: αν 0, βρες το πιο πρόσφατο ταίριασμα γι’ αυτόν τον supervisor
            if ($newThesisId <= 0) {
                $fb = $pdo->prepare("
                    SELECT thesis_id
                    FROM theses
                    WHERE supervisor_id = :sid
                      AND title = :title
                      AND start_date = :sd
                    ORDER BY thesis_id DESC
                    LIMIT 1
                ");
                $fb->execute([':sid'=>$supervisor_id, ':title'=>$title, ':sd'=>$start_date]);
                $newThesisId = (int)$fb->fetchColumn();
            }

            // Αν ανέβηκε PDF προσωρινά, τώρα φτιάξε ΤΕΛΙΚΟ όνομα που περιέχει το thesis_id
            if ($pdfTempAbs && is_file($pdfTempAbs)) {
                // εφαρμογή προτύπου: αντικατάσταση {id} με το πραγματικό thesis_id
                $base = str_replace('{id}', (string)$newThesisId, $pdfNamePattern);
                // καθάρισμα σε ασφαλές filename
                $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base);
                // βεβαιώσου ότι τελειώνει σε .pdf
                if (!preg_match('/\.pdf$/i', $base)) {
                    $base .= '.pdf';
                }

                $finalRel = 'uploads/theses_pdfs/' . $base;
                $finalAbs = __DIR__ . '/' . $finalRel;

                // Αν υπάρχει, βάλε -v2, -v3, ...
                if (file_exists($finalAbs)) {
                    $n = 2;
                    $nameOnly = preg_replace('/\.pdf$/i', '', $base);
                    do {
                        $candidate = $nameOnly . '-v' . $n . '.pdf';
                        $finalRel  = 'uploads/theses_pdfs/' . $candidate;
                        $finalAbs  = __DIR__ . '/' . $finalRel;
                        $n++;
                    } while (file_exists($finalAbs));
                }

                // Μετονομασία/μετακίνηση από προσωρινό σε τελικό
                if (@rename($pdfTempAbs, $finalAbs)) {
                    @chmod($finalAbs, 0644);
                    $uploadedPdfPath = str_replace('\\', '/', $finalRel);
                } else {
                    // αν κάτι πάει στραβά, τουλάχιστον κράτα το προσωρινό
                    $uploadedPdfPath = str_replace('\\', '/', $pdfTempRel);
                }
            }

            // Αν υπάρχει τελικό (ή προσωρινό fallback) path, σύνδεσέ το με τη διπλωματική
            if ($uploadedPdfPath && $newThesisId > 0) {
                $upd = $pdo->prepare("UPDATE theses SET topic_pdf = :p WHERE thesis_id = :id");
                $upd->execute([':p' => $uploadedPdfPath, ':id' => $newThesisId]);
            }

            // ΕΜΦΑΝΙΣΗ ΜΗΝΥΜΑΤΟΣ + AUTO REDIRECT (όχι άμεσο header redirect)
            $message = "Το θέμα προστέθηκε επιτυχώς!" . ($uploadedPdfPath ? " (Το PDF καταχωρήθηκε ως «{$uploadedPdfPath}».)" : "");
            $status  = 'success';

        } catch (PDOException $e) {
            $message = "Σφάλμα κατά την εισαγωγή: " . $e->getMessage();
            $status  = 'error';
        }
    } elseif (empty($message)) {
        $message = "Συμπλήρωσε όλα τα υποχρεωτικά πεδία.";
        $status  = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Νέου Θέματος</title>
    <style>
        :root{
            --ok:#198754; --ok-ghost:#e9f7ef;
            --err:#dc3545; --err-ghost:#fdecee;
            --ink:#0b2e59;
            --shadow: 0 12px 30px rgba(0,0,0,.10);
        }
        @keyframes fadeSlideIn{
            from{opacity:0; transform: translateY(12px) scale(.98);}
            to{opacity:1; transform: translateY(0) scale(1);}
        }
        @keyframes scalePop{
            0%{transform: scale(.6); opacity:0}
            60%{transform: scale(1.05); opacity:1}
            100%{transform: scale(1)}
        }

        html, body {
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

        .container {
            margin: 50px auto;
            padding: 20px;
            max-width: 600px;
            background-color: #fff;
            border-radius: 16px; /* πιο curvy */
            box-shadow: var(--shadow);
            animation: fadeSlideIn .5s ease-out both; /* animation εισόδου */
        }

        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }

        form {
            display: flex; flex-direction: column; gap: 15px;
        }

        label { font-weight: bold; }
        input, textarea, select, button {
            padding: 10px; font-size: 1rem;
            border: 1px solid #ccc; border-radius: 8px;
        }

        button {
            background-color: #0056b3; color: white; cursor: pointer; border: none;
        }
        button:hover { background-color: #003f7f; }

        .hint { color:#666; font-size:.9rem; }

        /* Site header */
        .site-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background-color: rgba(0, 51, 102, 0.92); color: white; box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); font-family: 'Segoe UI', sans-serif; margin-bottom: 24px; height: 80px; position: relative; z-index: 10; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;}
        .site-header .left { display: flex; align-items: center; gap: 10px;}
        .site-header .logo { width:95px; height: 80px;}
        .system-name { font-size: 20px; font-weight: 600;}
        .site-header .right { display: flex; align-items: center; gap: 20px;}
        .site-header .right nav a { color: white; text-decoration: none; margin-right: 15px;}
        .site-header .user-info { font-weight: 500;}

        /* Όμορφο alert */
        .alert{
            display:flex; gap:12px; align-items:flex-start;
            border-radius:14px; padding:14px 16px; margin: 0 0 16px 0;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,.03);
            animation: fadeSlideIn .45s ease-out both .05s;
        }
        .alert svg{ flex:0 0 auto; width:22px; height:22px; animation: scalePop .35s ease-out .05s both; }
        .alert.ok{ background: var(--ok-ghost); color: #0e5135; border:1px solid #bfe3d1;}
        .alert.err{ background: var(--err-ghost); color: #7b1b23; border:1px solid #f3c5cb;}
        .alert strong{ display:block; margin-bottom:2px; }

        .meta{
            margin: -8px 0 12px 0; font-size: 12.5px; color:#6b7a8c;
        }
        .meta .countdown{ font-weight: 700; color:#0d6efd; }

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
    <h1>Προσθήκη Νέου Θέματος</h1>

    <!-- Όμορφο μήνυμα -->
    <?php
      $isSuccess = ($status === 'success');
      if (!empty($message) || $status) {
        $iconOK = '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#198754" stroke-width="2"/><path d="M7 12l3 3 7-7" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $iconERR= '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#dc3545" stroke-width="2"/><path d="M8 8l8 8M16 8l-8 8" stroke="#dc3545" stroke-width="2" stroke-linecap="round"/></svg>';
        echo '<div class="alert '.($isSuccess ? 'ok' : 'err').'">';
        echo $isSuccess ? $iconOK : $iconERR;
        echo '<div><strong>'.($isSuccess ? 'Επιτυχία' : 'Μήνυμα').'</strong>';
        echo '<div>'.htmlspecialchars($message ?? '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</div></div></div>';
        if ($isSuccess) {
            echo '<div class="meta">Θα μεταφερθείς αυτόματα στην αρχική σε <span class="countdown" id="cd">2</span>…</div>';
        }
      }
    ?>

    <!-- Φόρμα Προσθήκης -->
    <form method="POST" action="addThesis.php" enctype="multipart/form-data">
        <label for="title">Τίτλος Θέματος:</label>
        <input type="text" id="title" name="title" placeholder="Εισαγάγετε τον τίτλο" required>

        <label for="description">Περιγραφή:</label>
        <textarea id="description" name="description" placeholder="Εισαγάγετε περιγραφή" rows="5" required></textarea>

        <!-- ΑΦΑΙΡΕΘΗΚΑΝ: Κατάσταση & Επιβλέπων Καθηγητής (γίνονται server-side) -->

        <label for="start_date">Ημερομηνία Έναρξης:</label>
        <input type="date" id="start_date" name="start_date" required>

        <label for="end_date">Ημερομηνία Λήξης:</label>
        <input type="date" id="end_date" name="end_date">

        <label for="topic_pdf">Επισύναψη PDF (προαιρετικό):</label>
        <input type="file" id="topic_pdf" name="topic_pdf" accept="application/pdf">
        <div class="hint">Μόνο PDF, έως 10MB. Θα αποθηκευτεί στη διπλωματική.</div>

        <!-- ΝΕΟ: Πρότυπο ονομασίας PDF -->
        <label for="pdf_name_pattern">Όνομα αρχείου PDF (πρότυπο):</label>
        <input type="text" id="pdf_name_pattern" name="pdf_name_pattern" placeholder="π.χ. thesis_{id} ή topic_{id}">
        <div class="hint">
            Το <code>{id}</code> θα αντικατασταθεί αυτόματα από το <strong>thesis_id</strong> που δημιουργείται (default: <code>thesis_{id}</code>).
        </div>

        <button type="submit">Προσθήκη Θέματος</button>
    </form>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
// Auto-redirect μόνο στην επιτυχία
(function(){
  var success = <?php echo json_encode($isSuccess ?? false); ?>;
  if(!success) return;
  var cd = document.getElementById('cd');
  var n = 2;
  var t = setInterval(function(){
    n -= 1;
    if (cd) cd.textContent = String(Math.max(n,0));
    if (n <= 0) {
      clearInterval(t);
      window.location.href = 'professor_home.php';
    }
  }, 2000);
})();
</script>

</body>
</html>
