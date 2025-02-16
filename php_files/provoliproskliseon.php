<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Έλεγχος αν υπάρχει το invitation_id
    if (!isset($_GET['invitation_id'])) {
        die("Δεν δόθηκε ID πρόσκλησης.");
    }

    $invitation_id = intval($_GET['invitation_id']);

    // Ανάκτηση πληροφοριών πρόσκλησης
    $stmt = $pdo->prepare("
        SELECT ci.invitation_id, ci.status, ci.sent_at, ci.comments, t.title 
        FROM committeeinvitations ci
        LEFT JOIN theses t ON ci.thesis_id = t.thesis_id
        WHERE ci.invitation_id = ?
    ");
    $stmt->execute([$invitation_id]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invitation) {
        die("Η πρόσκληση δεν βρέθηκε.");
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
    <title>Προβολή Πρόσκλησης</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h1 {
            font-size: 2rem;
            color: #0056b3;
            margin-bottom: 20px;
        }

        p {
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        .accept {
            background-color: #28a745;
        }

        .accept:hover {
            background-color: #218838;
        }

        .reject {
            background-color: #dc3545;
        }

        .reject:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Πρόσκληση</h1>
        <p><strong>Τίτλος Διπλωματικής:</strong> <?php echo htmlspecialchars($invitation['title']); ?></p>
        <p><strong>Κατάσταση:</strong> <?php echo htmlspecialchars($invitation['status']); ?></p>
        <p><strong>Ημερομηνία Αποστολής:</strong> <?php echo htmlspecialchars($invitation['sent_at']); ?></p>
        <p><strong>Σχόλια:</strong> <?php echo htmlspecialchars($invitation['comments'] ?? 'Χωρίς σχόλια'); ?></p>

        <a href="#" class="button accept" onclick="respondToInvitation('Accepted')">Αποδοχή</a>
        <a href="#" class="button reject" onclick="respondToInvitation('Rejected')">Απόρριψη</a>
    </div>

    <script>
    function respondToInvitation(action) {
        const confirmationMessage = action === 'Accepted' 
            ? "Είστε σίγουροι ότι θέλετε να αποδεχτείτε αυτή την πρόσκληση;"
            : "Είστε σίγουροι ότι θέλετε να απορρίψετε αυτή την πρόσκληση;";

        if (!confirm(confirmationMessage)) {
            return; // Ο χρήστης ακύρωσε τη δράση
        }

        const invitationId = <?php echo $invitation_id; ?>;

        fetch('fetch_theses(provoliproskliseon).php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invitation_id: invitationId, action })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            window.location.href = 'proskliseis.php';
        })
        .catch(error => console.error('Σφάλμα:', error));
    }
</script>
</body>
</html>
