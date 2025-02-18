<?php
session_start();
if (!isset($_SESSION['email'])) {
    echo json_encode([]);
    exit();
}

// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ανάκτηση του ID του καθηγητή από το email του
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_type = 'professor'");
    $stmt->execute(['email' => $_SESSION['email']]);
    $professor_id = $stmt->fetchColumn();

    if (!$professor_id) {
        echo json_encode([]);
        exit();
    }

    // Λήψη ειδοποιήσεων από τον πίνακα `form` για τον συγκεκριμένο καθηγητή
    $stmt = $pdo->prepare("
        SELECT f.id, f.student_id, f.theses_id, f.topic, f.message, f.created_at, 
               s.name AS student_name, s.surname AS student_surname,
               t.title AS thesis_title
        FROM form f
        JOIN students s ON f.student_id = s.student_id
        JOIN theses t ON f.theses_id = t.thesis_id
        WHERE f.professor_id = :professor_id
        ORDER BY f.created_at DESC
    ");
    
    $stmt->execute(['professor_id' => $professor_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Επιστροφή των δεδομένων ως JSON
    echo json_encode($notifications);
} catch (PDOException $e) {
    echo json_encode(["error" => "Σφάλμα: " . $e->getMessage()]);
}
?>
