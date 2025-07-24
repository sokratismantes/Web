<?php
session_start();
header('Content-Type: application/json');

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "Δεν έχετε συνδεθεί"]);
    exit();
}

// Σύνδεση με τη βάση δεδομένων μέσω PDO
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$pdo = new PDO($dsn, "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ανάκτηση του user_id του καθηγητή
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'professor'");
$stmt->execute([$_SESSION['email']]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$professor) {
    echo json_encode(["error" => "Δεν βρέθηκε καθηγητής με αυτό το email"]);
    exit();
}

$professor_id = $professor['user_id'];

// Λήψη παραμέτρων
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

// Βασικό query
$sql = "SELECT DISTINCT t.*
        FROM Theses t
        LEFT JOIN Committees c ON t.thesis_id = c.thesis_id
        WHERE 1=1 ";

$params = [];

// Ανά ρόλο
if ($role === 'supervisor') {
    $sql .= " AND t.supervisor_id = :pid";
    $params['pid'] = $professor_id;
} elseif ($role === 'committee') {
    $sql .= " AND (c.member1_id = :pid OR c.member2_id = :pid)";
    $params['pid'] = $professor_id;
} elseif ($role === '') {
    // Δεν φιλτράρουμε με βάση ρόλο: Δείξε όλες τις διπλωματικές (αφαιρούμε τελείως το φίλτρο professor)
    // Δεν προσθέτουμε τίποτα εδώ!
} else {
    // Από προεπιλογή, δείξε μόνο τις δικές του (αν μπει ποτέ άκυρη τιμή στο role)
    $sql .= " AND (t.supervisor_id = :pid OR c.member1_id = :pid OR c.member2_id = :pid)";
    $params['pid'] = $professor_id;
}


// Αν επιλέχθηκε κατάσταση
if (!empty($status)) {
    $sql .= " AND t.status = :status";
    $params['status'] = $status;
}

// Αν υπάρχει αναζήτηση
if (!empty($search)) {
    $sql .= " AND (t.title LIKE :search OR t.status LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY t.thesis_id ASC";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Επιστροφή ως JSON
echo json_encode($results);
