<?php
session_start(); 

// Σύνδεση με τη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $e->getMessage());
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $thesisId = (int)($_POST['thesis_id'] ?? 0);

    if ($action === 'submit_draft') {
        try {
            if ($thesisId <= 0) {
                throw new RuntimeException("Λείπει/λάθος thesis_id.");
            }

            // 1) Βρες student_id από session ή (fallback) από τη διπλωματική
            $student_id = 0;
            if (!empty($_SESSION['email'])) {
                $st = $pdo->prepare("
                    SELECT s.student_id
                    FROM students s
                    JOIN users u ON u.user_id = s.student_id
                    WHERE u.email = ?
                    LIMIT 1
                ");
                $st->execute([$_SESSION['email']]);
                $student_id = (int)$st->fetchColumn();
            }
            if ($student_id <= 0) {
                $st = $pdo->prepare("SELECT student_id FROM theses WHERE thesis_id = ? LIMIT 1");
                $st->execute([$thesisId]);
                $student_id = (int)$st->fetchColumn();
                if ($student_id <= 0) {
                    throw new RuntimeException("Δεν βρέθηκε student_id (session/thesis).");
                }
            }

            $didSomething = false;

            // 2) Ανέβασμα PDF (προαιρετικό)
            if (!empty($_FILES['draft_file']['name'])) {
                $tmpPath  = $_FILES['draft_file']['tmp_name'];
                $origName = $_FILES['draft_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new RuntimeException("Επιτρέπεται μόνο αρχείο PDF.");
                }

                $uploadDirFs = __DIR__ . '/uploads/drafts/';
                if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true)) {
                    throw new RuntimeException("Αδυναμία δημιουργίας φακέλου uploads/drafts.");
                }

                $finalName = sprintf("draft_t%ds%d_%s.pdf", $thesisId, $student_id, date('Ymd_His'));
                $destFs    = $uploadDirFs . $finalName;

                if (!move_uploaded_file($tmpPath, $destFs)) {
                    throw new RuntimeException("Αποτυχία αποθήκευσης αρχείου.");
                }

                // Εισαγωγή στον attachments (όπως ζητήθηκε)
                $ins = $pdo->prepare("
                    INSERT INTO attachments (thesis_id, student_id, filename, uploaded_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $ins->execute([$thesisId, $student_id, $finalName]);

                $didSomething = true;
            }

            // 3) Σύνδεσμος πρόχειρου (προαιρετικός) — ενημέρωση του theses.links (JSON/κείμενο)
            $draftLink = trim($_POST['draft_link'] ?? '');
            if ($draftLink !== '') {
                if (!filter_var($draftLink, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException("Μη έγκυρος σύνδεσμος.");
                }

                // Διάβασε τα υπάρχοντα links
                $stmt = $pdo->prepare("SELECT links FROM theses WHERE thesis_id = ?");
                $stmt->execute([$thesisId]);
                $linksRaw = (string)$stmt->fetchColumn();
                $linksArr = json_decode($linksRaw ?: '[]', true);
                if (!is_array($linksArr)) { $linksArr = []; }

                // Απόφυγε διπλότυπα
                if (!in_array($draftLink, $linksArr, true)) {
                    $linksArr[] = $draftLink;
                }

                // Αποθήκευση πίσω (ως JSON)
                $stmt = $pdo->prepare("UPDATE theses SET links = ? WHERE thesis_id = ?");
                $stmt->execute([ json_encode($linksArr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $thesisId ]);

                $didSomething = true;
            }

            if (!$didSomething) {
                throw new RuntimeException("Δεν επιλέχθηκε ούτε PDF ούτε σύνδεσμος.");
            }

            // 4) (Προαιρετικά) ενημέρωσε status αν δεν είναι ήδη Περατωμένη
            $pdo->prepare("
                UPDATE theses 
                SET status = 'Υπο Εξέταση'
                WHERE thesis_id = ? AND (status IS NULL OR status <> 'Περατωμένη')
            ")->execute([$thesisId]);

            $message = "✅ Το πρόχειρο καταχωρήθηκε (αρχείο/σύνδεσμος).";
            $messageType = "success";

        } catch (Throwable $e) {
            $message = "❌ " . $e->getMessage();
            $messageType = "error";
            error_log("[submit_draft] " . $e->getMessage());
        }
    }

if ($action == 'submit_exam') {
        $examDate = $_POST['exam_date'] ?? null;
        $examTime = $_POST['exam_time'] ?? null;
        $examMode = $_POST['exam_type'] ?? null;
        $examRoom = $_POST['exam_room'] ?? null;
        $examLink = $_POST['exam_link'] ?? null;

        if ($examMode === 'διά ζώσης') {
            $examLink = null;
        } elseif ($examMode === 'διαδικτυακά') {
            $examRoom = null;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO examinations (thesis_id, exam_date, exam_time, exam_mode, room, link) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$thesisId, $examDate, $examTime, $examMode, $examRoom, $examLink]);

            $message = "✅ Η εξέταση καταχωρήθηκε επιτυχώς!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "❌ Σφάλμα κατά την αποθήκευση εξέτασης: " . $e->getMessage();
            $messageType = "error";
        }

        echo "<script>
            window.onload = function() {
                showNotification('$message', '$messageType');
            };
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ανάρτηση Προχείρου</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: hsla(211, 32.30%, 51.40%, 0.35); 
            z-index: -1;
        }

        .site-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2);
            font-family: 'Segoe UI', sans-serif;
            margin-bottom: 60px;
            height: 120px;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }

        .site-header .left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .site-header .logo {
            width: 95px;
            height: 80px;
            object-fit: contain;
        }

        .system-name {
            font-size: 20px;
            font-weight: 600;
            white-space: nowrap;
        }

        .site-header .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .site-header .right nav a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }

        .site-header .user-info a {
            font-weight: 500;
        }

        .site-header .user-info a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .site-header {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
                padding: 20px;
                gap: 10px;
            }
            .site-header .right {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .top-header {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px;
            color: #333;
        }

        .top-header h1 {
            font-size: 26px;
            margin-bottom: 5px;
        }

        .top-header p {
            font-size: 14px;
            color: #555;
        }

        .form-wrapper {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 20px;
        }

        .form-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 30px 25px;
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-container h2 {
            text-align: center;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-submit, .btn-back {
            display: inline-block;
            width: auto;
            background-color: transparent;
            color: #007bff;
            font-size: 16px;
            padding: 10px 20px;
            border: 2px solid #007bff;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #0b5ed7;
            color: #fff;
        }

        .btn-back:hover {
            background-color: #0056b3;
            color: #fff;
        }

        .form-wrapper.btn-back-wrapper {
            justify-content: center;
            margin-bottom: 80px;
            margin-top: 40px;
        }

        .form-group.button-center {
    display: flex;
    justify-content: center;
    margin-top: 35px;
}

        footer {
        flex-shrink: 0;
        width: 100%;
        background-color: rgba(0, 51, 102, 0.92);
        background-color:;
        color: white;
        text-align: center;
        padding: 30px;
        margin-top: 20px;
    }

            .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .thesis-title {
    color:rgba(0, 51, 102, 0.92);
}
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
                <a href="view_student.php">Η Διπλωματική Μου</a>
            </nav>
            <span class="user-info"> <a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
        </div>
    </header>
    <div class="container">
        <h1 class="mb-0 fs-4 fw-semibold thesis-title">📄 Σύστημα Καταχώρησης Εξέτασης</h1>
        <p class="mb-0 fs-4 fw-semibold thesis-title">Συμπλήρωσε τις απαραίτητες πληροφορίες για την παρουσίαση της διπλωματικής σου.</p>
    </div>

    <div class="form-wrapper">
        <div class="form-container">
            <h2>Ανάρτηση Προχείρου</h2>
            <form id="form1" method="POST" action="student_action.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
                    <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
                </div>

                <div class="form-group">
                    <label for="draft_file">Ανέβασμα Προχείρου:</label>
                    <input type="file" id="draft_file" name="draft_file" accept="application/pdf,.pdf" required>
                </div>

                <div class="form-group">
                    <label for="draft_link">Σύνδεσμος Υλικού:</label>
                    <input type="url" id="draft_link" name="draft_link" placeholder="π.χ. https://example.com">
                </div>

                <div class="form-group button-center">
                    <button type="submit" class="btn-submit btn-soft-blue" name="action" value="submit_draft">Υποβολή</button>
                </div>
            </form>
        </div>

        <div class="form-container">
            <h2>Πληροφορίες Εξέτασης</h2>
            <form method="POST" action="student_action.php">
                <input type="hidden" id="hidden_thesis_id" name="thesis_id">

                <div class="form-group">
                    <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
                    <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
                </div>
                
                <div class="form-group">
                    <label for="exam_date">Ημερομηνία Εξέτασης:</label>
                    <input type="date" id="exam_date" name="exam_date" required>
                </div>

                <div class="form-group">
                    <label for="exam_time">Ώρα Εξέτασης:</label>
                    <input type="time" id="exam_time" name="exam_time" required>
                </div>

                <div class="form-group">
                    <label for="exam_type">Τρόπος Εξέτασης:</label>
                    <select id="exam_type" name="exam_type" onchange="toggleExamDetails()" required>
                        <option value="">-- Επιλογή --</option>
                        <option value="διά ζώσης">Δια ζώσης</option>
                        <option value="διαδικτυακά">Διαδικτυακά</option>
                    </select>
                </div>

                <div class="form-group" id="physical_details" style="display:none;">
                    <label for="exam_room">Αίθουσα Εξέτασης:</label>
                    <input type="text" id="exam_room" name="exam_room" placeholder="π.χ. Αμφιθέατρο 2">
                </div>

                <div class="form-group" id="online_details" style="display:none;">
                    <label for="exam_link">Σύνδεσμος Παρουσίασης:</label>
                    <input type="url" id="exam_link" name="exam_link" placeholder="π.χ. https://zoom.us/...">
                </div>

                <div class="form-group button-center">
                    <button type="submit" class="btn-submit btn-soft-blue" name="action" value="submit_exam">Υποβολή</button>
                </div>
            </form>
        </div>
    </div>

    <div class="form-wrapper btn-back-wrapper"style="justify-content: center; margin-bottom: 40px;">
            <a href="student_home.php" class="btn-back">Επιστροφή στην Αρχική Σελίδα</a>
        </div>
    </div>

    <footer>
        <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
        <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
    </footer>

    <script>
        function showNotification(message, type) {
            var notification = document.getElementById('notification');
            notification.innerHTML = message;
            notification.className = type;
            notification.style.display = 'block';
            setTimeout(() => { notification.style.display = 'none'; }, 4000);
        }

        function toggleExamDetails() {
            var type = document.getElementById('exam_type').value;
            document.getElementById('physical_details').style.display = (type === 'διά ζώσης') ? 'block' : 'none';
            document.getElementById('online_details').style.display = (type === 'διαδικτυακά') ? 'block' : 'none';
        }
    </script>

    <script>
    
    document.getElementById('thesis_id').addEventListener('input', function () {
        document.getElementById('hidden_thesis_id').value = this.value;
    });
</script>

<?php if (!empty($message)): ?>
<script>
window.onload = function(){ alert(<?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>); };
</script>
<?php endif; ?>

</body>
</html>
