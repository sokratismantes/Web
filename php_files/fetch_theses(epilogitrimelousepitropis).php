<?php
session_start();

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ανάκτηση λίστας καθηγητών
    $stmt = $pdo->prepare("SELECT professor_id, name, surname FROM professors");
    $stmt->execute();
    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Επιστροφή δεδομένων σε JSON
    header('Content-Type: application/json');
    echo json_encode($professors);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα κατά την ανάκτηση δεδομένων']);
}
?>
