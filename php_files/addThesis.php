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

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $status = $_POST['status'];
    $start_date = $_POST['start_date'];
    $end_date = empty($_POST['end_date']) ? null : $_POST['end_date'];
    $supervisor_id = intval($_POST['supervisor_id']); // Επιβλέπων καθηγητής

    try {
        // Χρήση της Stored Procedure για εισαγωγή δεδομένων
        $sql = "CALL AddThesis(:title, :description, :status, :start_date, :end_date, :supervisor_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->bindParam(':supervisor_id', $supervisor_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Το θέμα προστέθηκε επιτυχώς!";
            header("Location: listaDiplomatikon.php");
            exit();
        } else {
            $message = "Σφάλμα κατά την εκτέλεση της διαδικασίας: " . implode(", ", $stmt->errorInfo());
        }
    } catch (PDOException $e) {
        $message = "Σφάλμα κατά την εισαγωγή: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Νέου Θέματος</title>
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
            max-width: 600px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
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

        .message {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Προσθήκη Νέου Θέματος</h1>

        <!-- Εμφάνιση μηνύματος -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'Σφάλμα') !== false ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Φόρμα Προσθήκης -->
        <form method="POST" action="addThesis.php">
            <label for="title">Τίτλος Θέματος:</label>
            <input type="text" id="title" name="title" placeholder="Εισαγάγετε τον τίτλο" required>

            <label for="description">Περιγραφή:</label>
            <textarea id="description" name="description" placeholder="Εισαγάγετε περιγραφή" rows="5" required></textarea>

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

            <label for="supervisor_id">Επιβλέπων Καθηγητής:</label>
            <select id="supervisor_id" name="supervisor_id" required>
                <option value="">Φόρτωση...</option>
            </select>

            <button type="submit">Προσθήκη Θέματος</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Φόρτωση καθηγητών με AJAX από το fetch_theses.php
        fetch('fetch_theses(AddThesis).php?fetch_professors=1')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('supervisor_id');
                select.innerHTML = ''; // Καθαρισμός της λίστας

                if (data.error) {
                    select.innerHTML = `<option value="">${data.error}</option>`;
                    return;
                }

                // Προσθήκη επιλογών
                data.forEach(professor => {
                    const option = document.createElement('option');
                    option.value = professor.professor_id;
                    option.textContent = professor.fullname;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Σφάλμα κατά τη φόρτωση:', error);
                const select = document.getElementById('supervisor_id');
                select.innerHTML = `<option value="">Σφάλμα κατά τη φόρτωση δεδομένων</option>`;
            });
    });
    </script>
</body>
</html>

