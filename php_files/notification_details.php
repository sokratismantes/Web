<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Λήψη του ID της ειδοποίησης
$notificationId = $_GET['id'] ?? null;

if (!$notificationId) {
    die("Η ειδοποίηση δεν βρέθηκε.");
}

// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ανάκτηση λεπτομερειών της ειδοποίησης
    $stmt = $pdo->prepare("
        SELECT subject, message, created_at
        FROM professors_notifications
        WHERE id = ?
    ");
    $stmt->execute([$notificationId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        die("Η ειδοποίηση δεν βρέθηκε.");
    }
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λεπτομέρειες Ειδοποίησης</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .notification-details {
            line-height: 1.6;
            font-size: 16px;
            color: #555;
        }

        .notification-details p {
            margin: 10px 0;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Λεπτομέρειες Ειδοποίησης</h1>
        <div class="notification-details">
            <p><strong>Θέμα:</strong> <?php echo htmlspecialchars($notification['subject']); ?></p>
            <p><strong>Μήνυμα:</strong> <?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
            <p><strong>Ημερομηνία:</strong> <?php echo htmlspecialchars($notification['created_at']); ?></p>
        </div>
        <a href="professor_home.php" class="back-button">Επιστροφή</a>
    </div>
</body>
</html>
