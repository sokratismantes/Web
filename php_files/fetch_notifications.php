<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json; charset=UTF-8');
ob_clean(); // Καθαρισμός του output buffer

$email = $_SESSION['email'];

// Σύνδεση στη βάση
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Εύρεση του professor_id
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'professor'");
    $stmt->execute([$email]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$professor) {
        echo json_encode([]); // Ο χρήστης δεν είναι καθηγητής
        exit();
    }

    $professor_id = $professor['user_id'];

    // Ανάκτηση ειδοποιήσεων από professors_notifications
    $stmt = $pdo->prepare("
        SELECT 
            pn.notification_id, 
            pn.student_id, 
            pn.thesis_id, 
            pn.status, 
            pn.sent_at, 
            CONCAT(s.name, ' ', s.surname) AS sender_name
        FROM professors_notifications pn
        JOIN students s ON s.student_id = pn.student_id
        WHERE pn.professor_id = ? AND pn.status = 'Pending'
        ORDER BY pn.sent_at DESC
    ");
    $stmt->execute([$professor_id]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notifications);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage()); // Καταγραφή σφάλματος
    echo json_encode(['error' => 'Σφάλμα στη βάση δεδομένων.']);
}
?>
