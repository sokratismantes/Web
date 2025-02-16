<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Ο χρήστης δεν είναι συνδεδεμένος.']);
    exit();
}

// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Εύρεση του student_id του φοιτητή
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'student'");
    $stmt->execute([$_SESSION['email']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα: Δεν βρέθηκε ο φοιτητής.']);
        exit();
    }

    $student_id = $student['user_id'];

    // Εισαγωγή ειδοποιήσεων για κάθε καθηγητή
    if (isset($_POST['professors']) && is_array($_POST['professors'])) {
        foreach ($_POST['professors'] as $professor_id) {
            $stmt = $pdo->prepare("
                INSERT INTO professors_notifications (professor_id, subject, message, student_id, status, type) 
                VALUES (:professor_id, :subject, :message, :student_id, 'Unread', 'committee_invitation')
            ");
            $stmt->execute([
                ':professor_id' => $professor_id,
                ':subject' => 'Πρόσκληση Τριμελούς Επιτροπής',
                ':message' => 'Έχετε πρόσκληση να συμμετέχετε σε τριμελή επιτροπή.',
                ':student_id' => $student_id
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Οι προσκλήσεις στάλθηκαν επιτυχώς.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Δεν επιλέξατε καθηγητές.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Σφάλμα στη βάση δεδομένων: ' . $e->getMessage()]);
}
?>
