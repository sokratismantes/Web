<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

ob_start();
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);

set_error_handler(function ($sev, $msg, $file, $line) {
    if (ob_get_length()) { ob_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>"PHP error: $msg"], JSON_UNESCAPED_UNICODE);
    exit();
});
set_exception_handler(function ($e) {
    if (ob_get_length()) { ob_clean(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Exception: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
});

if (!isset($_SESSION['email'])) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Μη εξουσιοδοτημένη πρόσβαση.'], JSON_UNESCAPED_UNICODE);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρη μέθοδος.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$thesis_id = isset($_POST['thesis_id']) ? (int)$_POST['thesis_id'] : 0;
$cancellation_reason = isset($_POST['cancellation_reason'])
    ? trim((string)$_POST['cancellation_reason'])
    : (isset($_POST['cancelation_reason']) ? trim((string)$_POST['cancelation_reason']) : '');
$cancel_gs_number = isset($_POST['cancel_gs_number']) ? (int)$_POST['cancel_gs_number'] : 0;
$cancel_gs_year   = isset($_POST['cancel_gs_year']) ? (int)$_POST['cancel_gs_year'] : 0;

$errors = [];
if ($thesis_id <= 0) $errors[] = 'Άκυρο thesis_id.';
if ($cancellation_reason === '') $errors[] = 'Ο λόγος ακύρωσης είναι υποχρεωτικός.';
if ($cancel_gs_number <= 0) $errors[] = 'Ο αριθμός ΓΣ πρέπει να είναι θετικός ακέραιος.';
if ($cancel_gs_year < 1900 || $cancel_gs_year > 2100) $errors[] = 'Το έτος ΓΣ πρέπει να είναι στην μορφή YYYY (1900–2100).';

if ($errors) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit();
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare("CALL cancelThesis(?, ?, ?, ?)");
if (!$stmt) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Αποτυχία προετοιμασίας κλήσης: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}
$stmt->bind_param("isii", $thesis_id, $cancellation_reason, $cancel_gs_number, $cancel_gs_year);

if (!$stmt->execute()) {
    $err = $stmt->error ?: $conn->error ?: 'Άγνωστο σφάλμα εκτέλεσης.';
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ακύρωση: ' . $err], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();
do {
    if ($res = $conn->store_result()) { $res->free(); }
} while ($conn->more_results() && $conn->next_result());

$conn->close();

if (ob_get_length()) { ob_clean(); }
echo json_encode(['success' => true, 'cancellation_reason' => $cancellation_reason], JSON_UNESCAPED_UNICODE);
exit();
