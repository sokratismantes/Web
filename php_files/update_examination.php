<?php
// Σύνδεση με τη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Λήψη δεδομένων από τη φόρμα
    $thesis_id = intval($_POST['thesis_id']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $location = $_POST['location'];

    // Εισαγωγή δεδομένων στον πίνακα examinations
    $stmt = $pdo->prepare("INSERT INTO examinations (thesis_id, date, time, location) VALUES (?, ?, ?, ?)");
    $stmt->execute([$thesis_id, $date, $time, $location]);

    echo "<script>alert('Τα στοιχεία εξέτασης αποθηκεύτηκαν με επιτυχία!'); window.location.href='view_student.php?thesis_id=$thesis_id';</script>";
} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
}
?>
