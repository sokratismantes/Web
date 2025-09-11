<?php
session_start();
header('Content-Type: application/json');

// Έλεγχος login
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit();
}

// Έλεγχος παραμέτρου
if (!isset($_GET['thesis_id'])) {
    echo json_encode(['success' => false, 'message' => 'Δεν δόθηκε ID θέματος.']);
    exit();
}

$thesis_id = intval($_GET['thesis_id']);

// Σύνδεση με ΒΔ
$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα σύνδεσης στη βάση: ' . $conn->connect_error]);
    exit();
}

// Βασικά στοιχεία διπλωματικής
$stmt = $conn->prepare("
    SELECT thesis_id, title, description, status, start_date, end_date, grade, repository_link,
           cancellation_reason, cancel_gs_number, cancel_gs_year, assign_gs_number
    FROM Theses 
    WHERE thesis_id = ?
");
$stmt->bind_param("i", $thesis_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Η διπλωματική δεν βρέθηκε.']);
    $stmt->close();
    $conn->close();
    exit();
}

$thesis = $result->fetch_assoc();
$stmt->close();

// Υπολογισμός χρόνου από έναρξη (μόνο αν δεν είναι "Υπό Εξέταση")
$elapsed_time = null;
if (!empty($thesis['start_date']) && $thesis['status'] !== 'Υπό Εξέταση') {
    $start_date = new DateTime($thesis['start_date']);
    $current_date = new DateTime();
    $interval = $start_date->diff($current_date);
    $elapsed_time = $interval->format('%y έτη, %m μήνες, %d ημέρες');
}

// Επιτροπή
$stmt_committee = $conn->prepare("
    SELECT 'Supervisor' AS role, p1.professor_id, p1.name, p1.surname
    FROM committees c
    JOIN professors p1 ON p1.professor_id = c.supervisor_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 1', p2.professor_id, p2.name, p2.surname
    FROM committees c
    JOIN professors p2 ON p2.professor_id = c.member1_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 2', p3.professor_id, p3.name, p3.surname
    FROM committees c
    JOIN professors p3 ON p3.professor_id = c.member2_id
    WHERE c.thesis_id = ?
");
$stmt_committee->bind_param("iii", $thesis_id, $thesis_id, $thesis_id);
$stmt_committee->execute();
$result_committee = $stmt_committee->get_result();

$committee = [];
while ($row = $result_committee->fetch_assoc()) {
    $committee[] = $row;
}

$stmt_committee->close();
$conn->close();

// Απάντηση JSON
echo json_encode([
    'success' => true,
    'thesis' => [
        'thesis_id'           => $thesis['thesis_id'],
        'title'               => $thesis['title'],
        'description'         => $thesis['description'],
        'status'              => $thesis['status'],
        'start_date'          => $thesis['start_date'],
        'end_date'            => $thesis['end_date'],
        'grade'               => $thesis['status'] === 'Υπό Εξέταση' ? $thesis['grade'] : null,
        'repository_link'     => $thesis['repository_link'],
        'elapsed_time'        => $elapsed_time,
        'cancellation_reason' => $thesis['cancellation_reason'],
        'cancel_gs_number'    => $thesis['cancel_gs_number'],
        'cancel_gs_year'      => $thesis['cancel_gs_year'],
        'assign_gs_number'    => $thesis['assign_gs_number'] 
    ],
    'committee' => $committee
]);
