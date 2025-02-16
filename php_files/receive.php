<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Εύρεση του student_id του αποστολέα από το session email
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'student'");
    $stmt->execute([$_SESSION['email']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die("Σφάλμα: Δεν βρέθηκε ο φοιτητής.");
    }

    $sender_id = $student['user_id'];

    // Εισαγωγή ειδοποίησης
    $sql = "INSERT INTO professors_notifications (professor_id, subject, message, student_id, status) 
            VALUES (:professor_id, :subject, :message, :student_id, 'Unread')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':professor_id' => $_POST['professor'],
        ':subject' => $_POST['subject'],
        ':message' => $_POST['message'],
        ':sender_id' => $student_id
    ]);

    echo "Η πρόσκληση στάλθηκε επιτυχώς!";
    header("Location: dashboard.php"); // Ανακατεύθυνση στη σελίδα "dashboard.php"
    exit();
} catch (PDOException $e) {
    die("Σφάλμα: " . $e->getMessage());
}
?>
