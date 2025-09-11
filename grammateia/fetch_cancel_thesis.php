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
$cancelation_reason = trim($_POST['cancelation_reason'] ?? '');
$cancel_gs_number = intval($_POST['cancel_gs_number']);
$cancel_gs_year = intval($_POST['cancel_gs_year']);

if (empty($cancelation_reason)) {
    echo json_encode(['success' => false, 'message' => 'Ο λόγος ακύρωσης είναι υποχρεωτικός.']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "vasst");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης: ' . $conn->connect_error]);
    exit();
}

// --- Κλήση stored procedure cancelThesis ---
$stmt = $conn->prepare("CALL cancelThesis(?, ?, ?, ?)");
$stmt->bind_param("isii", $thesis_id, $cancelation_reason, $cancel_gs_number, $cancel_gs_year);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'cancellation_reason' => $cancelation_reason
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Σφάλμα κατά την ακύρωση: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>
