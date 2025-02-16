<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

// Σύνδεση με τη βάση δεδομένων μέσω PDO
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης: " . $e->getMessage());
}

// Έλεγχος αν δόθηκε το ID θέματος
if (!isset($_GET['thesis_id'])) {
    die("Δεν δόθηκε ID θέματος.");
}

$thesis_id = intval($_GET['thesis_id']);

// Ενημέρωση πληροφοριών θέματος
$sql = "SELECT * FROM Theses WHERE thesis_id = :thesis_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':thesis_id', $thesis_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    die("Το θέμα δεν βρέθηκε.");
}

$thesis = $stmt->fetch(PDO::FETCH_ASSOC);

// Ενημέρωση πληροφοριών μέσω POST
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $status = $_POST['status'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        $update_sql = "CALL UpdateThesis(:thesis_id, :title, :description, :status, :start_date, :end_date)";
        $stmt = $pdo->prepare($update_sql);
        $stmt->bindParam(':thesis_id', $thesis_id, PDO::PARAM_INT);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        

        if ($stmt->execute()) {
            $message = "Οι αλλαγές αποθηκεύτηκαν επιτυχώς!";
        } else {
            $message = "Σφάλμα κατά την ενημέρωση: " . implode(", ", $stmt->errorInfo());
        }
    }

    if (isset($_POST['delete'])) {
        $delete_sql = "CALL DeleteThesis(:thesis_id)";
        $stmt = $pdo->prepare($delete_sql);
        $stmt->bindParam(':thesis_id', $thesis_id, PDO::PARAM_INT);
        
    
        try {
            $stmt->execute();
            // Επιστροφή στην προηγούμενη σελίδα μετά τη διαγραφή
            header("Location: listaDiplomatikon.php");
            exit();
        } catch (PDOException $e) {
            // Εμφάνιση σφάλματος αν η διαγραφή αποτύχει
            $message = "Σφάλμα κατά τη διαγραφή: " . $e->getMessage();
        }
    }
    
    
}
?>



<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Θέματος</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }


        .container {
            margin: 50px auto;
            padding: 20px;
            max-width: 800px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }


        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }


        .message {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
            color: green;
        }


        .error {
            color: red;
        }


        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }


        label {
            font-weight: bold;
        }


        input, textarea, select, button {
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }


        button {
            background-color: #0056b3;
            color: white;
            cursor: pointer;
            border: none;
        }


        button:hover {
            background-color: #003f7f;
        }


        .delete-button {
            background-color: red;
            margin-top: 10px;
        }


        .delete-button:hover {
            background-color: darkred;
        }


        .back-link {
            text-align: center;
            margin-top: 20px;
        }


        .back-link a {
            text-decoration: none;
            color: #0056b3;
        }


        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Επεξεργασία Θέματος</h1>


        <!-- Εμφάνιση μηνύματος -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'Σφάλμα') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>


        <!-- Φόρμα Ενημέρωσης -->
        <form method="POST" action="process.php?thesis_id=<?php echo $thesis_id; ?>">
            <label for="title">Τίτλος Θέματος:</label>
            <input type="text" id="title" name="title" required>


            <label for="description">Περιγραφή:</label>
            <textarea id="description" name="description" rows="5" required></textarea>


            <label for="status">Κατάσταση:</label>
            <select id="status" name="status" required>
                <option value="Υπό Ανάθεση">Υπό Ανάθεση</option>
                <option value="Ενεργή">Ενεργή</option>
                <option value="Υπό Εξέταση">Υπό Εξέταση</option>
                <option value="Περατωμένη">Περατωμένη</option>
            </select>


            <label for="start_date">Ημερομηνία Έναρξης:</label>
            <input type="date" id="start_date" name="start_date" required>


            <label for="end_date">Ημερομηνία Λήξης:</label>
            <input type="date" id="end_date" name="end_date">


            <button type="submit" name="update">Αποθήκευση Αλλαγών</button>
            <button type="submit" name="delete" class="delete-button">Διαγραφή Θέματος</button>
        </form>


        <div class="back-link">
    <a href="deleted_theses_log.php">Προβολή Ιστορικού Διαγραφών</a>
</div>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Φόρτωση δεδομένων μέσω AJAX
            fetch(`fetch_theses(process).php?thesis_id=<?php echo $thesis_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }


                    document.getElementById('title').value = data.title;
                    document.getElementById('description').value = data.description;
                    document.getElementById('status').value = data.status;
                    document.getElementById('start_date').value = data.start_date;
                    document.getElementById('end_date').value = data.end_date || '';
                })
                .catch(error => console.error('Σφάλμα στο AJAX:', error));
        });
    </script>
</body>
</html>

