<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

// Σύνδεση με βάση (PDO)
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$pdo = new PDO($dsn, $user, $pass, $options);

// --- Βρες/κάνε cache professor_id από το session ή από το email του login ---
if (empty($_SESSION['professor_id']) || (int)$_SESSION['professor_id'] <= 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.professor_id
            FROM professors p
            JOIN users u ON u.user_id = p.professor_id
            WHERE u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['email']]);
        $pid = (int)$stmt->fetchColumn();
        if ($pid > 0) {
            $_SESSION['professor_id'] = $pid; // αποθήκευση στο session
        }
    } catch (Throwable $e) {
        // αν αποτύχει, απλά δεν βάζει professor_id
    }
}

// === Επεξεργασία AJAX αιτημάτων ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'assign') {
        header('Content-Type: application/json');

        $thesis_id    = (int)($_POST['thesis_id'] ?? 0);
        $student_id   = (int)($_POST['student_id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $professor_id = (int)($_SESSION['professor_id'] ?? 0);

        if ($thesis_id <= 0 || $student_id <= 0) {
            echo json_encode(["status"=>"error","message"=>"Λείπουν υποχρεωτικά πεδία."]);
            exit;
        }
        if ($professor_id <= 0) {
            echo json_encode(["status"=>"error","message"=>"Δεν υπάρχει καθηγητής στη συνεδρία. Κάνε ξανά είσοδο."]);
            exit;
        }

        try {
            // Κατοχύρωση προσωρινά του θέματος στον φοιτητή (ΜΟΝΟ αν ανήκει στον επιβλέποντα και δεν έχει ήδη student)
            $sql = "
                UPDATE theses
                SET student_id = :sid
                WHERE thesis_id = :tid
                  AND supervisor_id = :pid
                  AND (student_id IS NULL OR student_id = 0)
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sid' => $student_id,
                ':tid' => $thesis_id,
                ':pid' => $professor_id
            ]);

            if ($stmt->rowCount() === 1) {
                echo json_encode(["status"=>"success","message"=>"Η διπλωματική κατοχυρώθηκε προσωρινά στον φοιτητή."]);
            } else {
                echo json_encode(["status"=>"error","message"=>"Αποτυχία ανάθεσης. Ελέγξτε ότι το θέμα σας είναι διαθέσιμο και δεν έχει ήδη φοιτητή."]);
            }
        } catch (PDOException $e) {
            echo json_encode(["status"=>"error","message"=>"Σφάλμα ανάθεσης: ".$e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'revoke') {
        $thesis_id = intval($_POST['thesis_id']);

        $stmt = $pdo->prepare("CALL RevokeThesis(?)");
        $stmt->execute([$thesis_id]);

        echo json_encode(["status" => "success", "message" => "Η ανάθεση αναιρέθηκε."]);
        exit;
    }
}

// === Αναζήτηση φοιτητών με AJAX ===
if (isset($_GET['action']) && $_GET['action'] === 'search_student') {
    header('Content-Type: application/json');
    $q = "%".($_GET['q'] ?? "")."%";
    $sql = "SELECT student_id, am, CONCAT(name,' ',surname) AS fullname 
            FROM students 
            WHERE am LIKE ? OR CONCAT(name,' ',surname) LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $q]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
    exit;
}

// === Ανάκτηση ΜΟΝΟ ελεύθερων διπλωματικών του συνδεδεμένου επιβλέποντα ===
if (isset($_GET['action']) && $_GET['action'] === 'fetch_theses') {
    // NO-CACHE headers για να μην «κολλάνε» τα αποτελέσματα ανά login
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    $search = "%".($_GET['search'] ?? "")."%";
    $professor_id = (int)($_SESSION['professor_id'] ?? 0);
    if ($professor_id <= 0) { echo json_encode([]); exit; }

    $sql = "SELECT thesis_id, title, description, status, start_date, end_date, supervisor_id
            FROM theses
            WHERE supervisor_id = :pid
              AND (student_id IS NULL OR student_id = 0)
              AND (title LIKE :q OR status LIKE :q)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $professor_id, ':q' => $search]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($theses);
    exit;
}

// === Ανάκτηση λίστας φοιτητών ===
if (isset($_GET['action']) && $_GET['action'] === 'fetch_students') {
    header('Content-Type: application/json');
    $sql = "SELECT student_id, student_number, name, surname 
            FROM students";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
    exit;
}

// === Έλεγχος αν ο φοιτητής έχει ήδη ανάθεση ===
if (isset($_GET['action']) && $_GET['action'] === 'check_student_assignment') {
    header('Content-Type: application/json');
    $student_id = intval($_GET['student_id'] ?? 0);

    $sql = "SELECT thesis_id 
            FROM theses 
            WHERE student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        echo json_encode(["assigned" => true, "thesis_id" => $exists['thesis_id']]);
    } else {
        echo json_encode(["assigned" => false]);
    }
    exit;
}

// Εμφάνιση ειδοποίησης (HTML μέρος)
$success_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// === Λίστες: Διπλωματικές που επιβλέπω / συμμετέχω ===
if (isset($_GET['action']) && $_GET['action'] === 'my_theses_overview') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $professor_id = (int)($_SESSION['professor_id'] ?? 0);
    if ($professor_id <= 0) {
        echo json_encode(['supervised'=>[], 'participating'=>[]]);
        exit;
    }

    // Διπλωματικές που επιβλέπω
    $stmt = $pdo->prepare("
        SELECT t.thesis_id, t.title
        FROM theses t
        WHERE t.supervisor_id = :pid
        ORDER BY COALESCE(t.start_date, '0000-00-00') DESC, t.thesis_id DESC
    ");
    $stmt->execute([':pid' => $professor_id]);
    $supervised = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Διπλωματικές που συμμετέχω (ως μέλος), εξαιρώντας όσες ήδη επιβλέπω
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.thesis_id, t.title
        FROM committees c
        JOIN theses t ON t.thesis_id = c.thesis_id
        WHERE (c.member1_id = :pid OR c.member2_id = :pid)
          AND (t.supervisor_id IS NULL OR t.supervisor_id <> :pid)
        ORDER BY COALESCE(t.start_date, '0000-00-00') DESC, t.thesis_id DESC
    ");
    $stmt->execute([':pid' => $professor_id]);
    $participating = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'supervised'    => $supervised ?: [],
        'participating' => $participating ?: []
    ]);
    exit;
}

// === Λεπτομέρειες μίας διπλωματικής για modal ===
if (isset($_GET['action']) && $_GET['action'] === 'thesis_details') {
    header('Content-Type: application/json; charset=utf-8');
    $thesis_id = (int)($_GET['thesis_id'] ?? 0);
    if ($thesis_id <= 0) { echo json_encode(['error'=>'invalid']); exit; }

    // Βασικά στοιχεία + φοιτητής + επιβλέπων ΚΑΙ τα νέα πεδία από theses
    $stmt = $pdo->prepare("
        SELECT 
            t.thesis_id, t.title, t.description, t.status,
            t.final_grade, t.repository_link,
            sup.professor_id AS supervisor_id,
            CONCAT(sup.name, ' ', sup.surname) AS supervisor_name,
            s.student_id, s.name AS student_name, s.surname AS student_surname, s.student_number
        FROM theses t
        LEFT JOIN students s   ON s.student_id = t.student_id
        LEFT JOIN professors sup ON sup.professor_id = t.supervisor_id
        WHERE t.thesis_id = :tid
        LIMIT 1
    ");
    $stmt->execute([':tid'=>$thesis_id]);
    $thesisRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // Τριμελής (ονόματα)
    $stmt = $pdo->prepare("
        SELECT 
            c.member1_id, CONCAT(p1.name, ' ', p1.surname) AS member1_name,
            c.member2_id, CONCAT(p2.name, ' ', p2.surname) AS member2_name
        FROM committees c
        LEFT JOIN professors p1 ON p1.professor_id = c.member1_id
        LEFT JOIN professors p2 ON p2.professor_id = c.member2_id
        WHERE c.thesis_id = :tid
        LIMIT 1
    ");
    $stmt->execute([':tid'=>$thesis_id]);
    $committeeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'member1_id'=>null,'member1_name'=>null,
        'member2_id'=>null,'member2_name'=>null
    ];

    // Πρακτικό βαθμολόγησης (report) από exam_results (τελευταία εγγραφή)
    $reportRow = null;
    try {
        $stmt = $pdo->prepare("
            SELECT report
            FROM exam_results
            WHERE thesis_id = :tid
            ORDER BY exam_id DESC
            LIMIT 1
        ");
        $stmt->execute([':tid'=>$thesis_id]);
        $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Throwable $e) { $reportRow = null; }

    // === ΝΕΟ: Προσκλήσεις μελών (professors_notifications) για τα μέλη της τριμελούς ===
    $invites = [];
    $mid1 = isset($committeeRow['member1_id']) ? (int)$committeeRow['member1_id'] : 0;
    $mid2 = isset($committeeRow['member2_id']) ? (int)$committeeRow['member2_id'] : 0;

    if ($mid1 > 0 || $mid2 > 0) {
        $sqlInv = "
            SELECT
                pn.professor_id,
                CONCAT(p.name, ' ', p.surname) AS professor_name,
                pn.status,
                pn.sent_at,
                pn.responded_at
            FROM professors_notifications pn
            JOIN professors p ON p.professor_id = pn.professor_id
            WHERE pn.thesis_id = :tid
              AND (pn.professor_id = :m1 OR pn.professor_id = :m2)
            ORDER BY pn.sent_at DESC
        ";
        $stInv = $pdo->prepare($sqlInv);
        $stInv->execute([
            ':tid' => $thesis_id,
            ':m1'  => $mid1 ?: -1,
            ':m2'  => $mid2 ?: -1
        ]);
        $invites = $stInv->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- ΝΕΟ: DRAFT από uploads/drafts με ημερομηνία από attachments.uploaded_at ---
    $draft = null;
    try {
        $stD = $pdo->prepare("
            SELECT filename, uploaded_at
            FROM attachments
            WHERE thesis_id = :tid
            ORDER BY uploaded_at DESC
        ");
        $stD->execute([':tid' => $thesis_id]);
        $rows = $stD->fetchAll(PDO::FETCH_ASSOC);

        $draftsDirFs = __DIR__ . '/uploads/drafts/';
        $webBase     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

        foreach ($rows as $r) {
            $fn = (string)$r['filename'];
            $abs = $draftsDirFs . $fn;
            if (is_file($abs)) {
                $draft = [
                    'filename'   => $fn,
                    'uploaded_at'=> $r['uploaded_at'] ?? null,
                    'url'        => $webBase . '/uploads/drafts/' . rawurlencode($fn)
                ];
                break;
            }
        }
    } catch (Throwable $e) {
        $draft = null;
    }

    echo json_encode([
        'thesis'    => $thesisRow ?: (object)[],
        'committee' => $committeeRow,
        'student'   => $thesisRow ? [
            'student_id'     => $thesisRow['student_id'] ?? null,
            'name'           => $thesisRow['student_name'] ?? null,
            'surname'        => $thesisRow['student_surname'] ?? null,
            'student_number' => $thesisRow['student_number'] ?? null,
        ] : (object)[],
        // Εδώ επιστρέφουμε ό,τι χρειάζεται για την «Περατωμένη»
        'final'     => [
            'final_grade'     => $thesisRow['final_grade']     ?? null,
            'repository_link' => $thesisRow['repository_link'] ?? null,
            'report'          => $reportRow['report']          ?? null
        ],
        'invites'   => $invites,
        // --- ΝΕΟ: DRAFT
        'draft'     => $draft
    ]);
    exit;
}

// === Στατιστικά ΜΟΝΟ για Περατωμένες διπλωματικές του συνδεδεμένου διδάσκοντα ===
if (isset($_GET['action']) && $_GET['action'] === 'prof_finished_stats') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    try {
        $professor_id = (int)($_SESSION['professor_id'] ?? 0);
        if ($professor_id <= 0 && !empty($_SESSION['email'])) {
            $st = $pdo->prepare("
                SELECT p.professor_id
                FROM professors p
                JOIN users u ON u.user_id = p.professor_id
                WHERE u.email = ?
                LIMIT 1
            ");
            $st->execute([$_SESSION['email']]);
            $professor_id = (int)$st->fetchColumn();
            if ($professor_id > 0) { $_SESSION['professor_id'] = $professor_id; }
        }
        if ($professor_id <= 0) { echo json_encode(['error'=>'no_professor']); exit; }

        $qSupDays = $pdo->prepare("
            SELECT AVG(DATEDIFF(t.end_date, t.start_date))
            FROM theses t
            WHERE t.supervisor_id = :pid AND t.status = 'Περατωμένη'
              AND t.start_date IS NOT NULL AND t.end_date IS NOT NULL
        "); $qSupDays->execute([':pid'=>$professor_id]); $supDays=(float)$qSupDays->fetchColumn();

        $qSupGrade = $pdo->prepare("
            SELECT AVG(t.final_grade) FROM theses t
            WHERE t.supervisor_id = :pid AND t.status='Περατωμένη' AND t.final_grade IS NOT NULL
        "); $qSupGrade->execute([':pid'=>$professor_id]); $supGrade=(float)$qSupGrade->fetchColumn();

        $qSupCount = $pdo->prepare("
            SELECT COUNT(*) FROM theses t
            WHERE t.supervisor_id = :pid AND t.status='Περατωμένη'
        "); $qSupCount->execute([':pid'=>$professor_id]); $supCount=(int)$qSupCount->fetchColumn();

        $qMemDays = $pdo->prepare("
            SELECT AVG(DATEDIFF(t.end_date, t.start_date))
            FROM committees c JOIN theses t ON t.thesis_id=c.thesis_id
            WHERE (c.member1_id=:pid OR c.member2_id=:pid)
              AND t.status='Περατωμένη'
              AND t.start_date IS NOT NULL AND t.end_date IS NOT NULL
        "); $qMemDays->execute([':pid'=>$professor_id]); $memDays=(float)$qMemDays->fetchColumn();

        $qMemGrade = $pdo->prepare("
            SELECT AVG(t.final_grade)
            FROM committees c JOIN theses t ON t.thesis_id=c.thesis_id
            WHERE (c.member1_id=:pid OR c.member2_id=:pid)
              AND t.status='Περατωμένη' AND t.final_grade IS NOT NULL
        "); $qMemGrade->execute([':pid'=>$professor_id]); $memGrade=(float)$qMemGrade->fetchColumn();

        $qMemCount = $pdo->prepare("
            SELECT COUNT(*)
            FROM committees c JOIN theses t ON t.thesis_id=c.thesis_id
            WHERE (c.member1_id=:pid OR c.member2_id=:pid) AND t.status='Περατωμένη'
        "); $qMemCount->execute([':pid'=>$professor_id]); $memCount=(int)$qMemCount->fetchColumn();

        echo json_encode([
            'supervised'=>[
                'avg_completion_days'=>$supDays ?: 0,
                'avg_grade'=>$supGrade ?: 0,
                'total_count'=>$supCount
            ],
            'member'=>[
                'avg_completion_days'=>$memDays ?: 0,
                'avg_grade'=>$memGrade ?: 0,
                'total_count'=>$memCount
            ]
        ]);
    } catch (Throwable $e) {
        echo json_encode(['error'=>'server','message'=>$e->getMessage()]);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λίστα Διπλωματικών</title>
    <style>
    
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(to right, #e2e2e2, #c9d6ff);
    color: #333;
}

body::before { 
    content: ""; 
    position: fixed; 
    inset: 0; 
    background-color: hsla(211, 32.3%, 51.4%, 0.35); 
    z-index: -1;
}

.container {
    margin: 30px auto;
    padding: 25px;
    max-width: 1200px;
    background-color: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

h1, h2 {
    text-align: center;
    color: #004085;
    margin-bottom: 20px;
    font-weight: 600;
}

.search-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 25px;
    gap: 10px;
    flex-wrap: wrap;
}

.search-bar input {
    padding: 12px;
    width: 45%;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 1rem;
    transition: 0.3s;
}
.search-bar input:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px #007bff66;
    outline: none;
}

button, .add-button, .back-button {
    padding: 10px 18px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.add-button {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
    display: block;
    margin: 20px auto;
    width: 200px;
    text-align: center;
    text-decoration: none;
}
.add-button:hover { opacity: 0.85; }

.back-button {
    background: linear-gradient(135deg, #0056b3, #004494);
    color: white;
    display: block;
    margin: 30px auto 0;
    width: 260px;
    text-align: center;
    text-decoration: none;
}
.back-button:hover { opacity: 0.85; }

.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    margin-top: 20px;

}

table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

table th, table td {
    padding: 14px;
    text-align: left;
    border: 1px solid #e6e6e6;
}

table th {
    background-color: #007bff;
    color: white;
    font-weight: 600;
}

table tr:nth-child(even) {
    background-color: #f8f9fa;
}

table tr:hover {
    background-color: #eaf4ff;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    color: white;
    transition: 0.2s;
}
.assign-btn { background: #28a745; }
.assign-btn:hover { background: #218838; }
.revoke-btn { background: #dc3545; }
.revoke-btn:hover { background: #b52b38; }

.message {
    text-align: center;
    margin-bottom: 20px;
    font-weight: bold;
    color: green;
}

/* Modal Form */
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center; align-items: center;
}
.modal-content {
    background: white;
    padding: 25px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
.modal-content h3 {
    margin-top: 0;
    color: #004085;
}
.modal-content input, .modal-content textarea {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

.cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.thesis-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.thesis-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.thesis-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #004085;
}
.thesis-status {
    font-size: 0.9rem;
    margin-bottom: 10px;
    color: #666;
}
.thesis-dates {
    font-size: 0.85rem;
    margin-bottom: 10px;
    color: #444;
}
.card-actions {
    margin-top: 10px;
}

.card-actions {
    margin-top: 15px;
    display: flex;
    justify-content: flex-end; /* τοποθέτηση δεξιά */
}

.assign-btn {
    background: #28a745;
    color: white;
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.3s;
}
.assign-btn:hover {
    background: #218838;
}

.modal-field {
    margin-bottom: 12px;
}
.modal-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #333;
}
.modal-field input, 
.modal-field textarea, 
.modal-field select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
}

.my-theses-grid{
    display:grid; gap:20px;
    grid-template-columns: repeat(auto-fit, minmax(320px, 720px));
    margin-top:10px;
}
.my-theses-col{
    background:#fafbfc; border:1px solid #e6e9ef; border-radius:12px; padding:16px;
}
.my-col-title{
    margin:0 0 10px 0; color:#0b5394; font-weight:600; font-size:1.05rem;
}
.my-theses-list{
    list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;
}
.my-item{
    padding:10px 12px; background:#fff; border:1px solid #e8eef7; border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.03);
}
.my-item a{ text-decoration:none; color:#17375e; font-weight:600; }
.my-item a:hover{ text-decoration:underline; }
.my-empty{ color:#7b8894; font-style:italic; }

.thesis-card {
  transition: transform .15s ease, box-shadow .15s ease;
}
.thesis-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 14px rgba(0,0,0,.12);
}

/* Κράτα το modal μέσα στο ύψος της οθόνης */
#thesisDetailsModal .modal-content {
  max-height: 90vh;
  overflow: auto;
}

/* Πιο μικρό scrollable πλαίσιο για το PDF */
#draft-preview {
  height: 45vh;         /* ρύθμισέ το ανάλογα: 35–50vh */
  max-height: 45vh;
  overflow: auto;
}

/* Το PDF να προσαρμόζεται στο νέο ύψος */
#draft-preview iframe,
#draft-preview embed {
  width: 100%;
  height: 100%;
  border: 0;
}

/* Ακόμα πιο μικρό σε μικρές οθόνες */
@media (max-width: 768px) {
  #draft-preview {
    height: 35vh;
    max-height: 35vh;
  }
}

/* ==== Panels (wrapper) ==== */
.panel{
  width: min(1100px, 92%);   
  margin: 22px auto;         
  background:#fff;
  border:1px solid #e6e9ef;
  border-radius:14px;
  padding:18px;
  box-shadow:0 6px 16px rgba(0,0,0,.06);
}
.panel-header{
  display:flex; align-items:center; justify-content:space-between;
  padding:6px 2px 10px; border-bottom:1px solid #eef2f7; margin-bottom:14px;
}
.panel-header h2{
  margin:0; font-weight:700; color:#0b4ba6; letter-spacing:.2px; font-size:1.2rem;
}

/* ==== Οι Διπλωματικές μου ==== */
#my-theses .my-theses-grid{
  display:grid; gap:18px;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}
#my-theses .my-theses-col{
  background:#fafbff;
  border:1px solid #e6ebf5;
  border-radius:12px;
  padding:14px;
}
#my-theses .my-col-title{
  margin:0 0 10px; color:#143d73; font-weight:700; font-size:1.05rem;
}
#my-theses .my-theses-list{
  list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;
}
#my-theses .my-item{
  padding:10px 12px;
  background:#fff;
  border:1px solid #e8eef7;
  border-radius:10px;
  box-shadow:0 2px 8px rgba(0,0,0,.03);
}
#my-theses .my-empty{ color:#7b8894; font-style:italic; }

/* κουμπί που δημιουργεί το JS: <button class="my-link">… */
#my-theses .my-item .my-link{
  display:block; width:100%;
  text-align:left;
  background:transparent; border:none; cursor:pointer;
  font: inherit; color:#17375e; font-weight:600; padding:0;
}
#my-theses .my-item .my-link:hover{ text-decoration:underline; }

/* ==== Διαθέσιμες Διπλωματικές (cards grid) ==== */
#available-theses #theses-cards{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap:18px;
}
#available-theses #theses-cards .thesis-card{
  background:#fff;
  border:1px solid #e8eef7;
  border-radius:12px;
  padding:16px;
  box-shadow:0 4px 12px rgba(0,0,0,.06);
  transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  animation: card-in .25s ease;
}
#available-theses #theses-cards .thesis-card:hover{
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(0,0,0,.12);
  border-color: #d8e3f9;
}
#available-theses .thesis-title{
  font-size:1.05rem; font-weight:700; color:#004085; margin:0 0 6px 0;
}
#available-theses .thesis-status{
  font-size:.92rem; color:#6b7a90; margin-bottom:6px;
}
#available-theses .thesis-dates{
  font-size:.88rem; color:#394b66; opacity:.9;
}
#available-theses .card-actions{
  margin-top:12px; display:flex; justify-content:flex-end;
}

/* κουμπί ανάθεσης που προσθέτει το JS */
#available-theses .assign-btn{
  background: linear-gradient(135deg, #28a745, #218838);
  color:#fff; padding:8px 14px; border:none; border-radius:10px;
  font-weight:600; cursor:pointer; transition:filter .2s ease;
}
#available-theses .assign-btn:hover{ filter:brightness(.95); }

@keyframes card-in { from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:none} }

/* Responsive tweaks */
@media (max-width: 600px){
  #my-theses .my-theses-grid{ grid-template-columns: 1fr; }
  #available-theses #theses-cards{ grid-template-columns: 1fr; }
}

/* Panel legend & counters */
.panel-legend { display:flex; gap:8px; align-items:center; }
.legend-pill{
  font-size:.8rem; padding:4px 8px; border-radius:999px; border:1px solid #e6ebf5;
  background:#f7f9ff; color:#284a7d;
}
.legend-super{ background:#eef7ff; border-color:#d6e8ff; color:#0b4ba6; }
.legend-part{ background:#f5f7fb; color:#42526e; }

.count-badge{
  display:inline-block; margin-left:6px; padding:2px 8px; border-radius:999px;
  font-size:.8rem; font-weight:700; color:#0b4ba6; background:#eef4ff; border:1px solid #d7e4ff;
}

/* Κλικ-άξια items με μικρό βελάκι */
#my-theses .my-item{
  display:flex; align-items:center; justify-content:space-between;
  gap:10px; transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
}
#my-theses .my-item:hover{ 
  transform: translateY(-1px);
  box-shadow:0 4px 12px rgba(0,0,0,.07);
  border-color:#dfe6f5;
}
#my-theses .my-item .my-link{
  flex:1 1 auto; display:block; text-align:left; background:transparent; border:none; 
  cursor:pointer; font:inherit; color:#17375e; font-weight:600; padding:0;
}
#my-theses .my-item::after{
  content:"›"; font-size:1.1rem; line-height:1; color:#97a6ba; flex:0 0 auto;
}

.site-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background-color: rgba(0, 51, 102, 0.92); color: white; box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); font-family: 'Segoe UI', sans-serif; margin-bottom: 80px; height: 80px; position: relative; z-index: 10; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;}
.site-header .left { display: flex; align-items: center; gap: 10px;}
.site-header .logo { width:95px; height: 80px;}
.system-name { font-size: 20px; font-weight: 600;}
.site-header .right { display: flex; align-items: center; gap: 20px;}
.site-header .right nav a { color: white; text-decoration: none; margin-right: 15px;}
.site-header .user-info { font-weight: 500;}
footer { flex-shrink: 0; width: 100%; background-color: rgba(0, 51, 102, 0.92); color: white; text-align: center; padding: 30px; margin-top: 20px; height:80px;}
.export-wrapper{ position: relative; display: inline-block; }
.export-btn{
  padding: 10px 16px; border-radius: 10px; border: 1px solid #cfe0f5;
  background: #0b4ba6; color:#fff; font-weight:600; cursor:pointer;
  box-shadow: 0 6px 16px rgba(10,30,60,.15);
}
.export-btn:hover{ filter: brightness(0.95); }

.export-menu{
  position: absolute; top: calc(100% + 6px); left: 0;
  min-width: 220px; background:#fff; border:1px solid #e7eef7; border-radius: 10px;
  box-shadow: 0 14px 28px rgba(10,30,60,.18);
  padding: 6px; display: none; z-index: 1000;
}
.export-item{
  width: 100%; text-align: left; background: transparent; border: 0;
  padding: 10px 12px; border-radius: 8px; cursor: pointer; font-weight: 500; color:#1f2937;
}
.export-item:hover{ background: #f2f7ff; }
.export-menu.show{ display:block; }
</style>
</head>
<body>    
        <?php if (!empty($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php endif; ?>

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

        <h1>Λίστα Διπλωματικών</h1>

        <div class="search-bar">
            <input type="text" id="search" placeholder="Αναζήτηση διπλωματικών..." onkeyup="fetchTheses()">
            <button onclick="fetchTheses()">Αναζήτηση</button>
        </div>

        <!-- Οι Διπλωματικές μου -->
        <div id="my-theses" class="panel">
        <div class="panel-header">
            <h2>Οι Διπλωματικές μου</h2>
            <div class="panel-legend">
            <span class="legend-pill legend-super">Επιβλέπω</span>
            <span class="legend-pill legend-part">Συμμετέχω</span>
            </div>

            <!-- === ΚΟΥΜΠΙ ΕΞΑΓΩΓΗΣ === -->
            <div class="export-wrapper">
            <button type="button" class="export-btn" id="exportToggle" aria-haspopup="true" aria-expanded="false">
                Εξαγωγή ▾
            </button>
            <div class="export-menu" id="exportMenu" role="menu" aria-labelledby="exportToggle">
                <button type="button" role="menuitem" class="export-item" onclick="exportThesesSimple('csv')">
                Εξαγωγή σε CSV
                </button>
                <button type="button" role="menuitem" class="export-item" onclick="exportThesesSimple('json')">
                Εξαγωγή σε JSON
                </button>
            </div>
            </div>
            <!-- ======================= -->
        </div>

        <div class="my-theses-grid">
            <section class="my-theses-col">
            <h3 class="my-col-title">📘 Διπλωματικές που επιβλέπω
                <span id="count-supervised" class="count-badge">0</span>
            </h3>
            <ul id="list-supervised" class="my-theses-list">
                <li class="my-empty">Φορτώνει...</li>
            </ul>
            </section>

            <section class="my-theses-col">
            <h3 class="my-col-title">👥 Διπλωματικές που συμμετέχω
                <span id="count-participating" class="count-badge">0</span>
            </h3>
            <ul id="list-participating" class="my-theses-list">
                <li class="my-empty">Φορτώνει...</li>
            </ul>
            </section>
        </div>
        </div>

        <!-- Διαθέσιμες Διπλωματικές -->
        <div id="available-theses" class="panel">
        <div class="panel-header">
            <h2>Διαθέσιμες Διπλωματικές</h2>
        </div>

        <div id="theses-cards" class="cards-container">
            <div class="thesis-card">
            <h3 class="thesis-title">Φορτώνει δεδομένα...</h3>
            </div>
        </div>
        </div>

            <!-- Modal Φόρμα -->
        <div id="assignModal" class="modal">
        <div class="modal-content">
            <h3>Ανάθεση Θέματος</h3>
            
            <!-- Κρυφό ID διπλωματικής -->
            <input type="hidden" id="assignThesisId">

            <!-- Πεδίο Θέματος -->
            <div class="modal-field">
                <label>Θέμα:</label>
                <input type="text" id="assignThesisTitle" readonly>
            </div>

            <div class="modal-field">
                <label>Περιγραφή:</label>
                <textarea id="assignThesisDescription" rows="3" readonly></textarea>
            </div>

            <!-- Επιλογή Φοιτητή -->
            <div class="modal-field">
                <label>Φοιτητής:</label>
                <select id="assignStudentSelect">
                    <option value="">-- Επιλέξτε φοιτητή --</option>
                </select>
            </div>

            <!-- Στοιχεία Φοιτητή -->
            <div id="studentInfo" style="display:none; margin-top:10px; font-size:0.9rem; color:#444;">
                <p><strong>Όνομα:</strong> <span id="studentName"></span></p>
                <p><strong>Email:</strong> <span id="studentEmail"></span></p>
            </div>

            <div class="modal-actions">
            <button type="button" class="revoke-btn" onclick="closeModal()">✖ Ακύρωση</button>
            <button type="button" class="assign-btn" onclick="submitAssign()">✔ Ανάθεση</button>
            </div>
        </div>
        </div>

        <!-- Modal λεπτομερειών διπλωματικής -->
        <div id="thesisDetailsModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:720px;">
            <h3 id="td-title">Λεπτομέρειες Διπλωματικής</h3>

            <div class="modal-field">
            <label>Περιγραφή:</label>
            <div id="td-description" style="white-space:pre-wrap;"></div>
            </div>

            <div class="modal-field">
            <label>Φοιτητής:</label>
            <div><span id="td-student"></span></div>
            </div>

            <div class="modal-field">
            <label>Επιβλέπων:</label>
            <div id="td-supervisor"></div>
            </div>

            <div class="modal-field">
            <label>Μέλη Επιτροπής:</label>
            <div>Μέλος 1: <span id="td-member1"></span> &nbsp; | &nbsp; Μέλος 2: <span id="td-member2"></span></div>
            </div>

            <!-- ΝΕΟ: Προσκλήσεις μελών όταν status = Υπό Ανάθεση -->
            <section id="invites-section" style="display:none; margin-top:16px;">
            <h4 style="margin:0 0 8px;">Προσκλήσεις μελών</h4>
            <div style="overflow:auto;">
                <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                    <th style="text-align:left; border-bottom:1px solid #eee; padding:6px 8px;">Μέλος</th>
                    <th style="text-align:left; border-bottom:1px solid #eee; padding:6px 8px;">Κατάσταση</th>
                    <th style="text-align:left; border-bottom:1px solid #eee; padding:6px 8px;">Ημ/νία Πρόσκλησης</th>
                    <th style="text-align:left; border-bottom:1px solid #eee; padding:6px 8px;">Ημ/νία Απόκρισης</th>
                    </tr>
                </thead>
                <tbody id="invites-tbody">
                    <tr><td colspan="4" style="padding:8px;">—</td></tr>
                </tbody>
                </table>
            </div>
            </section>

            <div class="modal-field">
            <label>Κατάσταση:</label>
            <div id="td-status"></div>
            </div>

            <div class="modal-field" id="td-final-block" style="display:none;">
            <label>Τελικός Βαθμός:</label>
            <div id="td-final-grade"></div>
            <div style="margin-top:6px;">
                <a id="td-repo-link" href="#" target="_blank" rel="noopener" style="display:none;">Αρχείο τελικού κειμένου</a>
                &nbsp; &nbsp;
                <a id="td-report-link" href="#" target="_blank" rel="noopener" style="display:none;">Πρακτικό βαθμολόγησης</a>
            </div>
            </div>

            <div id="final-section" style="display:none; margin-top:16px; padding-top:12px; border-top:1px solid #eee;">
            <h4 style="margin:0 0 8px 0;">Τελικά στοιχεία</h4>
            <p><strong>Τελικός Βαθμός:</strong> <span id="final-grade">—</span></p>
            <p><strong>Αποθετήριο:</strong> <a id="final-repo" href="#" target="_blank" rel="noopener">—</a></p>
            <p><strong>Πρακτικό Βαθμολόγησης:</strong> <a id="final-report" href="#" target="_blank" rel="noopener">—</a></p>
            </div>

            <!-- --- ΝΕΟ: Εμφάνιση DRAFT από uploads/drafts --- -->
            <div class="modal-field" id="td-draft-block" style="display:none; margin-top:16px; padding-top:12px; border-top:1px solid #eee;">
                <label>Πρόχειρο κείμενο διπλωματικής (draft):</label>
                <div id="td-draft-meta" style="font-size:0.85rem; color:#555; margin-bottom:6px;">—</div>
                <div id="td-draft-viewer" style="height:420px; border:1px solid #e6e6e6; border-radius:8px; overflow:hidden; background:#f9fafb;"></div>
                <div id="td-draft-download" style="margin-top:6px; display:none;">
                    <a id="td-draft-link" href="#" target="_blank" rel="noopener">Άνοιγμα σε νέα καρτέλα</a>
                </div>
            </div>
            <!-- --- ΤΕΛΟΣ ΝΕΟΥ BLOCK --- -->

            <div class="modal-actions">
            <button type="button" class="revoke-btn" onclick="closeThesisDetailsModal()">✖ Κλείσιμο</button>
            </div>
        </div>
        </div>

     <a href="addThesis.php" class="add-button">Προσθήκη Νέου Θέματος</a>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
        
// === Φόρτωση διπλωματικών ===
function fetchTheses() {
  const qEl = document.getElementById('search');
  const q = qEl ? qEl.value.trim() : '';
  const url = 'listaDiplomatikon.php?action=fetch_theses'
            + '&search=' + encodeURIComponent(q)
            + '&_=' + Date.now(); // cache-buster

  fetch(url, { cache: 'no-store' })
    .then(r => r.json())
    .then(data => {
      const c = document.getElementById('theses-cards');
      c.innerHTML = '';
      if (!data || data.length === 0) { c.textContent = 'Δεν βρέθηκαν.'; return; }

      data.forEach(t => {
        const id = Number(t.thesis_id || t.id); // προσαρμογή αν το πεδίο λέγεται id
        const d = document.createElement('div');
        d.className = 'thesis-card';
        d.setAttribute('role', 'button');
        d.setAttribute('tabindex', '0');
        d.style.cursor = 'pointer';

        // ΚΛΙΚ στο card -> πάει στο editThesis.php?id=...
        const goEdit = () => { if (id) window.location.href = 'editThesis.php?id=' + id; };
        d.addEventListener('click', goEdit);
        d.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); goEdit(); }
        });

        d.innerHTML = `
          <div class="thesis-title">${t.title || ''}</div>
          <div class="thesis-status">${t.status || ''}</div>
          <div class="thesis-dates">Έναρξη: ${t.start_date || '—'} | Λήξη: ${t.end_date || '—'}</div>
          <div class="card-actions">
            <button type="button" class="assign-btn">Ανάθεση</button>
          </div>
        `;

        // Σημαντικό: να μην “περνάει” το click του κουμπιού στο card
        const assignBtn = d.querySelector('.assign-btn');
        assignBtn.addEventListener('click', (ev) => {
          ev.stopPropagation();
          openAssignModal(t);
        });

        c.appendChild(d);
      });
    });
}

// === Άνοιγμα modal ===
function openAssignModal(thesis){
    document.getElementById('assignThesisId').value = thesis.thesis_id;
    document.getElementById('assignThesisTitle').value = thesis.title || '';
    document.getElementById('assignThesisDescription').value = thesis.description || '';
    loadStudents();
    document.getElementById('assignModal').style.display='flex';
}
function closeModal(){ document.getElementById('assignModal').style.display='none'; }

// === Γέμισμα φοιτητών ===
function loadStudents(){
    fetch('listaDiplomatikon.php?action=fetch_students&_=' + Date.now(), { cache: 'no-store' })
    .then(r=>r.json())
    .then(students=>{
        const sel=document.getElementById('assignStudentSelect');
        sel.innerHTML='<option value="">-- Επιλέξτε φοιτητή --</option>';
        students.forEach(s=>{
            const o=document.createElement('option');
            o.value=s.student_id;
            o.textContent=`${s.student_number} - ${s.name} ${s.surname}`;
            sel.appendChild(o);
        });
    });
}

// === Έλεγχος αν έχει ήδη ανάθεση ===
document.getElementById('assignStudentSelect').addEventListener('change',function(){
    const studentId=this.value;
    if(!studentId) return;
    fetch(`listaDiplomatikon.php?action=check_student_assignment&student_id=${encodeURIComponent(studentId)}&_=${Date.now()}`, { cache: 'no-store' })
    .then(r=>r.json())
    .then(d=>{
        if(d.assigned){
            alert("Αδυναμία Ανάθεσης: Ο φοιτητής έχει ήδη διπλωματική.");
            this.value="";
        }
    });
});

// === Υποβολή ανάθεσης ===
function submitAssign(){
    const thesisId=document.getElementById('assignThesisId').value;
    const studentId=document.getElementById('assignStudentSelect').value;
    const title=document.getElementById('assignThesisTitle').value;
    const description=document.getElementById('assignThesisDescription').value;

    if(!studentId){ alert("Επιλέξτε φοιτητή."); return; }

    fetch('listaDiplomatikon.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:
          `action=assign`+
          `&thesis_id=${encodeURIComponent(thesisId)}`+
          `&student_id=${encodeURIComponent(studentId)}`+
          `&title=${encodeURIComponent(title)}`+
          `&description=${encodeURIComponent(description)}`
    })
    .then(r=>r.text())              // βοηθά στο debug αν ο server δεν στείλει JSON
    .then(txt=>{
        try {
            const d = JSON.parse(txt);
            alert(d.message || 'Ολοκληρώθηκε.');
            if (d.status === 'success') {
                closeModal();
                fetchTheses();
            }
        } catch(e){
            console.error('Μη έγκυρο JSON από server:', txt);
            alert('Σφάλμα εξυπηρετητή.');
        }
    })
    .catch(err=>{
        console.error(err);
        alert('Σφάλμα δικτύου.');
    });
}

document.addEventListener('DOMContentLoaded',fetchTheses);

// === Επισκόπηση: «Επιβλέπω / Συμμετέχω» ===
function fetchMyThesesOverview(){
  const url = 'listaDiplomatikon.php?action=my_theses_overview&_=' + Date.now();
  fetch(url, { cache:'no-store' })
  .then(r=>r.json())
  .then(data=>{
    const supUL  = document.getElementById('list-supervised');
    const partUL = document.getElementById('list-participating');

    if (supUL) {
      supUL.innerHTML = '';
      const arr = (data && Array.isArray(data.supervised)) ? data.supervised : [];
      // NEW: counter
      const supCount = document.getElementById('count-supervised');
      if (supCount) supCount.textContent = String(arr.length);

      if (arr.length === 0) {
        supUL.innerHTML = '<li class="my-empty">Δεν βρέθηκαν διπλωματικές που επιβλέπεις.</li>';
      } else {
        arr.forEach(row=>{
          const li = document.createElement('li');
          li.className = 'my-item';
          li.innerHTML = `
            <button type="button" class="my-link" onclick="openThesisDetailsModal(${Number(row.thesis_id)})">
              ${row.title ? row.title.replace(/</g,'&lt;') : '—'}
            </button>`;
          supUL.appendChild(li);
        });
      }
    }

    if (partUL) {
      partUL.innerHTML = '';
      const arr = (data && Array.isArray(data.participating)) ? data.participating : [];
      // NEW: counter
      const partCount = document.getElementById('count-participating');
      if (partCount) partCount.textContent = String(arr.length);

      if (arr.length === 0) {
        partUL.innerHTML = '<li class="my-empty">Δεν βρέθηκαν διπλωματικές στις οποίες συμμετέχεις.</li>';
      } else {
        arr.forEach(row=>{
          const li = document.createElement('li');
          li.className = 'my-item';
          li.innerHTML = `
            <button type="button" class="my-link" onclick="openThesisDetailsModal(${Number(row.thesis_id)})">
              ${row.title ? row.title.replace(/</g,'&lt;') : '—'}
            </button>`;
          partUL.appendChild(li);
        });
      }
    }
  })
  .catch(()=>{
    const supUL  = document.getElementById('list-supervised');
    const partUL = document.getElementById('list-participating');
    if (supUL)  supUL.innerHTML  = '<li class="my-empty">Σφάλμα φόρτωσης.</li>';
    if (partUL) partUL.innerHTML = '<li class="my-empty">Σφάλμα φόρτωσης.</li>';
    // προαιρετικά μηδενισμοί counters
    const supCount = document.getElementById('count-supervised');
    const partCount = document.getElementById('count-participating');
    if (supCount) supCount.textContent = '0';
    if (partCount) partCount.textContent = '0';
  });
}

// Κάλεσέ το μαζί με την αρχική φόρτωση
document.addEventListener('DOMContentLoaded', function(){
    fetchTheses();
    fetchMyThesesOverview();
});

function openThesisDetailsModal(thesisId){
  const url = 'listaDiplomatikon.php?action=thesis_details&thesis_id=' + encodeURIComponent(thesisId) + '&_=' + Date.now();

  fetch(url, { cache:'no-store' })
    .then(r => r.json())
    .then(d => {
      const t   = d && d.thesis    ? d.thesis    : {};
      const c   = d && d.committee ? d.committee : {};
      const s   = d && d.student   ? d.student   : {};
      const fin = d && d.final     ? d.final     : {};
      const dr  = d && d.draft     ? d.draft     : null; // --- ΝΕΟ

      document.getElementById('td-title').textContent       = t.title || 'Διπλωματική';
      document.getElementById('td-description').textContent = t.description || '—';
      document.getElementById('td-status').textContent      = t.status || '—';

      let studentTxt = '—';
      if (s && (s.name || s.surname || s.student_number)) {
        studentTxt = `${s.name || ''} ${s.surname || ''}`.trim();
        if (s.student_number) studentTxt += ` (ΑΜ: ${s.student_number})`;
      }
      document.getElementById('td-student').textContent = studentTxt;

      document.getElementById('td-supervisor').textContent = t.supervisor_name || '—';
      document.getElementById('td-member1').textContent   = c.member1_name   || '—';
      document.getElementById('td-member2').textContent   = c.member2_name   || '—';

      // === ΝΕΟ: Προσκλήσεις μελών όταν status = Υπό Ανάθεση ===
        const invSec  = document.getElementById('invites-section');
        const invBody = document.getElementById('invites-tbody');

        if (invSec && invBody) {
        invSec.style.display = ''; // πάντα ορατό

        const invites = Array.isArray(d.invites) ? d.invites : [];

        if (!invites.length) {
            invBody.innerHTML = `<tr><td colspan="4" style="padding:8px;">Δεν βρέθηκαν προσκλήσεις για τα μέλη.</td></tr>`;
        } else {
            invBody.innerHTML = '';
            invites.forEach(row => {
            const tr = document.createElement('tr');
            const name = row.professor_name || '—';
            const status = row.status || '—';
            const sentAt = row.sent_at || '—';
            const respondedAt = row.responded_at || '—';

            tr.innerHTML = `
                <td style="padding:6px 8px; border-bottom:1px solid #f2f2f2;">${name}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #f2f2f2;">${status}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #f2f2f2;">${sentAt}</td>
                <td style="padding:6px 8px; border-bottom:1px solid #f2f2f2;">${respondedAt}</td>
            `;
            invBody.appendChild(tr);
            });
        }
        }

      // === Περατωμένη: τελικός βαθμός & σύνδεσμοι ===
      const finBlock   = document.getElementById('td-final-block');
      const finGradeEl = document.getElementById('td-final-grade');
      const repoLink   = document.getElementById('td-repo-link');
      const reportLink = document.getElementById('td-report-link');

      const isFinished = (t && t.status && t.status.trim() === 'Περατωμένη');
      if (finBlock) {
        if (isFinished) {
          finBlock.style.display = '';
          finGradeEl.textContent = (fin && fin.final_grade != null) ? fin.final_grade : '—';

          if (fin && fin.repository_link) {
            repoLink.href = fin.repository_link;
            repoLink.textContent = 'Άνοιγμα αποθετηρίου';
            repoLink.style.display = '';
          } else {
            repoLink.style.display = 'none';
          }

          if (fin && fin.report) {
            reportLink.href = fin.report;
            reportLink.textContent = 'Λήψη πρακτικού';
            reportLink.style.display = '';
          } else {
            reportLink.style.display = 'none';
          }
        } else {
          finBlock.style.display = 'none';
        }
      }

      // --- ΝΕΟ: Ενσωμάτωση DRAFT PDF σε scrollable viewer ---
      const draftBlock    = document.getElementById('td-draft-block');
      const draftMeta     = document.getElementById('td-draft-meta');
      const draftViewer   = document.getElementById('td-draft-viewer');
      const draftDlWrap   = document.getElementById('td-draft-download');
      const draftLink     = document.getElementById('td-draft-link');

      if (draftBlock && draftMeta && draftViewer && draftDlWrap && draftLink) {
        if (dr && dr.url) {
          draftBlock.style.display = '';
          const uploadedAt = dr.uploaded_at ? dr.uploaded_at : '—';
          draftMeta.textContent = 'Ανέβηκε: ' + uploadedAt;

          // καθάρισε viewer
          draftViewer.innerHTML = '';
          draftDlWrap.style.display = 'none';

          const isPdf = /\.pdf(\?|$)/i.test(dr.url) || /\.pdf$/i.test(dr.filename || '');
          if (isPdf) {
            draftViewer.innerHTML = `<embed src="${dr.url}" type="application/pdf" width="100%" height="100%" style="border:none;">`;
          } else {
            // για μη-PDF αρχεία: εμφάνισε link
            draftViewer.innerHTML = `<div style="padding:10px;">Δεν είναι PDF. Χρησιμοποίησε τον παρακάτω σύνδεσμο για άνοιγμα/λήψη.</div>`;
            draftDlWrap.style.display = '';
            draftLink.href = dr.url;
          }
        } else {
          draftBlock.style.display = 'none';
        }
      }
      // --- ΤΕΛΟΣ ΝΕΟΥ ΚΩΔΙΚΑ ---

      document.getElementById('thesisDetailsModal').style.display = 'flex';
    })
    .catch(()=>{
      alert('Σφάλμα φόρτωσης λεπτομερειών.');
    });
}

function closeThesisDetailsModal(){
  document.getElementById('thesisDetailsModal').style.display = 'none';
}

(function(){
  const toggle = document.getElementById('exportToggle');
  const menu   = document.getElementById('exportMenu');

  function closeMenu(){ menu.classList.remove('show'); toggle.setAttribute('aria-expanded','false'); }
  function openMenu(){ menu.classList.add('show'); toggle.setAttribute('aria-expanded','true'); }

  toggle.addEventListener('click', (e)=>{
    e.stopPropagation();
    if(menu.classList.contains('show')) closeMenu(); else openMenu();
  });

  document.addEventListener('click', (e)=>{
    if(!menu.contains(e.target) && e.target !== toggle){ closeMenu(); }
  });

  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape'){ closeMenu(); }
  });
})();

// Απλή εξαγωγή χωρίς επιπλέον φίλτρα
function exportThesesSimple(format){
  const fmt = (String(format||'').toLowerCase() === 'json') ? 'json' : 'csv';
  const url = 'export_theses.php?format=' + fmt;
  window.open(url, '_blank', 'noopener');
}
</script>
</body>
</html>
