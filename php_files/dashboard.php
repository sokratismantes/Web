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

    // Ανάκτηση μοναδικών ειδοποιήσεων για τον καθηγητή
    $professor_id = $_SESSION['professor_id']; // Προσαρμόστε ανάλογα με το session
    $stmt = $pdo->prepare("
        SELECT DISTINCT message, created_at, status 
        FROM professors_notifications 
        WHERE professor_id = :professor_id AND status = 'Unread'
        ORDER BY created_at DESC
    ");
    $stmt->execute([':professor_id' => $professor_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ενημέρωση των ειδοποιήσεων σε "Read"
    $updateStmt = $pdo->prepare("
        UPDATE professors_notifications 
        SET status = 'Read' 
        WHERE professor_id = :professor_id AND status = 'Unread'
    ");
    $updateStmt->execute([':professor_id' => $professor_id]);

} catch (PDOException $e) {
    die("Σφάλμα: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ειδοποιήσεις Καθηγητή</title>
</head>
<body>
    <h1>Ειδοποιήσεις</h1>
    <?php if (empty($notifications)): ?>
        <p>Δεν υπάρχουν νέες ειδοποιήσεις.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($notifications as $notification): ?>
                <li>
                    <?php echo htmlspecialchars($notification['message']); ?> 
                    (<?php echo htmlspecialchars($notification['created_at']); ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
