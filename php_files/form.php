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

    // Ανάκτηση καθηγητών
    $stmt = $pdo->query("SELECT professor_id, CONCAT(name, ' ', surname) AS full_name FROM professors");
    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $e->getMessage());
}

// **Διαχείριση του POST Request και Κλήση της Stored Procedure**
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ανάκτηση του student_id βάσει του email
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'student'");
        $stmt->execute([$_SESSION['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Σφάλμα: Δεν βρέθηκε ID φοιτητή.");
        }

        $student_id = $user['user_id'];

        // Ανάκτηση του thesis_id του φοιτητή
        $stmt = $pdo->prepare("SELECT thesis_id FROM theses WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $thesis = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thesis) {
            throw new Exception("Σφάλμα: Δεν βρέθηκε διπλωματική εργασία για τον φοιτητή.");
        }

        $thesis_id = $thesis['thesis_id'];
        $professor_id = $_POST['professor'];
        $topic = $_POST['subject'];
        $message_text = $_POST['message'];

        // Κλήση της Stored Procedure
        $stmt = $pdo->prepare("CALL SendFormInvitationToProfessor(?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $thesis_id, $professor_id, $topic, $message_text]);

        $message = "Η πρόσκληση στάλθηκε επιτυχώς!";
    } catch (Exception $e) {
        $message = "Σφάλμα: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αποστολή Πρόσκλησης</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f9f9f9;
            color: #333;
        }

        .container {
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            color: #212529;
            font-size: 24px;
            margin-bottom: 20px;
        }

        label {
            font-weight: 500;
            display: block;
            margin: 15px 0 5px;
            font-size: 14px;
            color: #495057;
            text-align: left;
        }

        select, textarea, input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
            color: #495057;
            background-color: #fff;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.075);
        }

        textarea {
            resize: none;
        }

        /* Στυλ για τα κουμπιά ώστε να έχουν το ίδιο μέγεθος */
        .button-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .submit-button {
            display: inline-block;
            width: 100%;
            max-width: 300px; 
            padding: 8px;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: none;
            margin-top: 10px;
        }

        .back-button {
            display: inline-block;
            width: 100%;
            max-width: 300px; 
            padding: 2px;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: none;
            margin-top: 8px;
        }


        /* Πράσινο κουμπί αποστολής προσκλήσεων */
        .submit-button {
            background-color: #28a745;
            color: white;
        }

        .submit-button:hover {
            background-color: #218838;
        }

        /* Μπλε κουμπί επιστροφής */
        .back-button {
            background-color: #007bff;
            color: white;
            text-decoration: none;
            line-height: 40px; /* Σωστή στοίχιση του κειμένου */
        }

        .back-button:hover {
            background-color: #0056b3;
        }

        .notification {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #28a745;
            background-color: #d4edda;
            color: #155724;
            border-radius: 5px;
            text-align: center;
            display: none;
        }

        .notification.error {
            border-color: #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Αποστολή Πρόσκλησης</h1>

    <?php if (!empty($message)): ?>
        <div class="notification" style="display: block; <?php echo strpos($message, 'Σφάλμα') !== false ? 'background-color: #f8d7da; color: #721c24;' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label for="professor">Επιλογή Καθηγητή:</label>
        <select name="professor" id="professor" required>
            <option value="" disabled selected>Επιλέξτε...</option>
            <?php foreach ($professors as $professor): ?>
                <option value="<?php echo $professor['professor_id']; ?>">
                    <?php echo htmlspecialchars($professor['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="subject">Θέμα Πρόσκλησης:</label>
        <input type="text" name="subject" id="subject" required>

        <label for="message">Μήνυμα:</label>
        <textarea name="message" id="message" rows="4" required></textarea>

        <div class="button-container">
            <!-- Πράσινο κουμπί αποστολής -->
            <button type="submit" class="submit-button">Αποστολή Πρόσκλησης</button>

            <!-- Μπλε κουμπί επιστροφής -->
            <a href="student_home.php" class="back-button">Επιστροφή στην Αρχική Οθόνη</a>
        </div>
    </form>
</div>
</body>
</html>
