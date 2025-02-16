<?php
session_start();

if (!isset($_SESSION['email']) || !isset($_POST['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ενημέρωση κατάστασης σε "Read"
    $stmt = $pdo->prepare("UPDATE professors_notifications SET status = 'Read' WHERE id = :id");
    $stmt->execute([':id' => $_POST['id']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
