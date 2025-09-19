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
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο αίτημα.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$thesis_id = isset($_POST['thesis_id']) ? (int)$_POST['thesis_id'] : 0;
$assign_gs_number = isset($_POST['assign_gs_number']) ? (int)$_POST['assign_gs_number'] : 0;

$errors = [];
if ($thesis_id <= 0)        $errors[] = 'Άκυρο thesis_id.';
if ($assign_gs_number <= 0) $errors[] = 'Ο αριθμός ΓΣ πρέπει να είναι θετικός ακέραιος.';
if ($errors) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Σφάλμα σύνδεσης στη βάση: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit();
}
$conn->set_charset('utf8mb4');


$stmt = $conn->prepare("CALL update_assign_gs(?, ?)");
if (!$stmt) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Αποτυχία προετοιμασίας κλήσης: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}
$stmt->bind_param("ii", $thesis_id, $assign_gs_number);

if (!$stmt->execute()) {
    $err = $stmt->error ?: $conn->error ?: 'Άγνωστο σφάλμα εκτέλεσης.';
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την αποθήκευση: ' . $err], JSON_UNESCAPED_UNICODE);
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
echo json_encode(['success' => true, 'assign_gs_number' => $assign_gs_number], JSON_UNESCAPED_UNICODE);
exit();
