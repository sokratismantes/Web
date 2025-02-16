<?php
// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ανάκτηση καθηγητών
    $stmt = $pdo->query("SELECT professor_id, CONCAT(name, ' ', surname) AS full_name FROM professors");
    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Επιστροφή δεδομένων σε JSON μορφή
    echo json_encode($professors);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Σφάλμα σύνδεσης με τη βάση δεδομένων: ' . $e->getMessage()]);
}
?>
