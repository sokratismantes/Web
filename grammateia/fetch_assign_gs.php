<?php
session_start();
header('Content-Type: application/json');

// Έλεγχος login
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit();
}

// Έλεγχος αιτήματος
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο αίτημα.']);
    exit();
}

if (!isset($_POST['thesis_id']) || !isset($_POST['assign_gs_number'])) {
    echo json_encode(['success' => false, 'message' => 'Λείπουν δεδομένα.']);
    exit();
}

$thesis_id = intval($_POST['thesis_id']);
$assign_gs_number = intval($_POST['assign_gs_number']);

// Σύνδεση με βάση
$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα σύνδεσης στη βάση: ' . $conn->connect_error]);
    exit();
}

// Κλήση της stored procedure
$stmt = $conn->prepare("CALL update_assign_gs(?, ?)");
$stmt->bind_param("ii", $thesis_id, $assign_gs_number);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'assign_gs_number' => $assign_gs_number]);
} else {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση.']);
}

$stmt->close();
$conn->close();
?>
