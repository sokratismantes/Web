<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση.'], JSON_UNESCAPED_UNICODE);
    exit();
}


if (!isset($_GET['thesis_id'])) {
    echo json_encode(['success' => false, 'message' => 'Δεν δόθηκε ID θέματος.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$thesis_id = (int) $_GET['thesis_id'];


$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα σύνδεσης στη βάση: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit();
}
$conn->set_charset('utf8mb4'); 

// Βασικά στοιχεία διπλωματικής 
$stmt = $conn->prepare("
    SELECT *
    FROM theses
    WHERE thesis_id = ?
");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Αποτυχία προετοιμασίας ερωτήματος: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}
$stmt->bind_param("i", $thesis_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα εκτέλεσης ερωτήματος: ' . $stmt->error], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit();
}

$result = $stmt->get_result();
if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Αποτυχία ανάκτησης αποτελεσμάτων. Ελέγξτε την υποστήριξη mysqlnd.'], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit();
}

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Η διπλωματική δεν βρέθηκε.'], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit();
}

$thesis = $result->fetch_assoc();
$stmt->close();

// Υπολογισμός χρόνου από έναρξη 
$elapsed_time = null;
$status = $thesis['status'] ?? null;
$start_date_raw = $thesis['start_date'] ?? null;
$final_grade = array_key_exists('final_grade', $thesis) ? $thesis['final_grade'] : null; // μπορεί να είναι NULL

$invalid_dates = ['0000-00-00', '0000-00-00 00:00:00', '', null];
if (!in_array($start_date_raw, $invalid_dates, true) && ($status !== 'Υπό Εξέταση')) {
    try {
        $start_date = new DateTime($start_date_raw);
        $current_date = new DateTime('now');
        $interval = $start_date->diff($current_date);
        $elapsed_time = $interval->format('%y έτη, %m μήνες, %d ημέρες');
    } catch (Exception $e) {
        $elapsed_time = null;
    }
}

// Επιτροπή 
$stmt_committee = $conn->prepare("
    SELECT 'Supervisor' AS role, p1.professor_id, p1.name, p1.surname
    FROM committees c
    JOIN professors p1 ON p1.professor_id = c.supervisor_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 1' AS role, p2.professor_id, p2.name, p2.surname
    FROM committees c
    JOIN professors p2 ON p2.professor_id = c.member1_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 2' AS role, p3.professor_id, p3.name, p3.surname
    FROM committees c
    JOIN professors p3 ON p3.professor_id = c.member2_id
    WHERE c.thesis_id = ?
");
if (!$stmt_committee) {
    echo json_encode([
        'success' => true,
        'thesis' => [
            'thesis_id'           => (int)$thesis['thesis_id'],
            'title'               => $thesis['title'] ?? null,
            'description'         => $thesis['description'] ?? null,
            'status'              => $status,
            'start_date'          => $start_date_raw,
            'end_date'            => $thesis['end_date'] ?? null,
            // Επιστροφή final_grade 
            'final_grade'         => $final_grade,
            'grade'               => $final_grade,
            'repository_link'     => $thesis['repository_link'] ?? null,
            'elapsed_time'        => $elapsed_time,
            'cancellation_reason' => $thesis['cancellation_reason'] ?? null,
            'cancel_gs_number'    => $thesis['cancel_gs_number'] ?? null,
            'cancel_gs_year'      => $thesis['cancel_gs_year'] ?? null,
            'assign_gs_number'    => $thesis['assign_gs_number'] ?? null
        ],
        'committee' => [],
        'warning' => 'Αποτυχία ανάκτησης επιτροπής: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$stmt_committee->bind_param("iii", $thesis_id, $thesis_id, $thesis_id);
$committee = [];
if ($stmt_committee->execute()) {
    if ($result_committee = $stmt_committee->get_result()) {
        while ($row = $result_committee->fetch_assoc()) {
            $committee[] = $row;
        }
    }
}
$stmt_committee->close();
$conn->close();

// Απάντηση JSON 
echo json_encode([
    'success' => true,
    'thesis' => [
        'thesis_id'           => (int)$thesis['thesis_id'],
        'title'               => $thesis['title'] ?? null,
        'description'         => $thesis['description'] ?? null,
        'status'              => $status,
        'start_date'          => $start_date_raw,
        'end_date'            => $thesis['end_date'] ?? null,
        // Επιστροφή final_grade 
        'final_grade'         => $final_grade,
        'grade'               => $final_grade,
        'repository_link'     => $thesis['repository_link'] ?? null,
        'elapsed_time'        => $elapsed_time,
        'cancellation_reason' => $thesis['cancellation_reason'] ?? null,
        'cancel_gs_number'    => $thesis['cancel_gs_number'] ?? null,
        'cancel_gs_year'      => $thesis['cancel_gs_year'] ?? null,
        'assign_gs_number'    => $thesis['assign_gs_number'] ?? null
    ],
    'committee' => $committee
], JSON_UNESCAPED_UNICODE);
