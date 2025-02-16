<?php
session_start();

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση']);
    exit();
}

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents('php://input'), true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['invitation_id'], $data['action'])) {
        $invitation_id = intval($data['invitation_id']);
        $action = $data['action'];

        $stmt = $pdo->prepare("
            UPDATE committeeinvitations 
            SET status = ?, responded_at = NOW() 
            WHERE invitation_id = ?
        ");
        $stmt->execute([$action, $invitation_id]);

        echo json_encode(['message' => 'Η ενέργεια πραγματοποιήθηκε επιτυχώς.']);
    } else {
        echo json_encode(['error' => 'Μη έγκυρο αίτημα.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα διακομιστή: ' . $e->getMessage()]);
}
