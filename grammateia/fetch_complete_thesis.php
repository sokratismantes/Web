<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη μέθοδος.']);
    exit();
}

$thesis_id = intval($_POST['thesis_id']);

$conn = new mysqli("localhost", "root", "", "vasst");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης: ' . $conn->connect_error]);
    exit();
}

$stmt = $conn->prepare("CALL completeThesis(?)");
$stmt->bind_param("i", $thesis_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ολοκλήρωση: ' . $conn->error]);
}

$conn->close();
?>
