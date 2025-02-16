<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Έλεγχος αν δόθηκε το ID του θέματος
if (!isset($_GET['thesis_id'])) {
    die("Δεν δόθηκε ID θέματος.");
}

$thesis_id = intval($_GET['thesis_id']);

// Ανάκτηση πληροφοριών για το θέμα
$sql = "SELECT thesis_id, title, description, status, start_date, end_date, grade, repository_link FROM Theses WHERE thesis_id = $thesis_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Το θέμα δεν βρέθηκε.");
}

$thesis = $result->fetch_assoc();

// Ανάκτηση του βαθμού εάν η κατάσταση είναι "Υπό Εξέταση"
$grade = $thesis['status'] == 'Υπό Εξέταση' ? $thesis['grade'] : '';

// Τριμελής Επιτροπή
$stmt_committee = $conn->prepare("
    SELECT 'Supervisor' AS role, p1.professor_id, p1.name, p1.surname
    FROM committees c
    JOIN professors p1 ON p1.professor_id = c.supervisor_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 1' AS role, p2.professor_id, p2.name, p2.surname
    FROM committees c
    JOIN professors p2 ON p2.professor_id = c.member1_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 2' AS role, p3.professor_id, p3.name, p3.surname
    FROM committees c
    JOIN professors p3 ON p3.professor_id = c.member2_id
    WHERE c.thesis_id = ?
");
$stmt_committee->bind_param("iii", $thesis_id, $thesis_id, $thesis_id);
$stmt_committee->execute();
$result_committee = $stmt_committee->get_result();

$committee_members = [];
while ($row = $result_committee->fetch_assoc()) {
    $committee_members[] = $row;
}

// Υπολογισμός χρόνου που έχει περάσει από την ημερομηνία έναρξης
$start_date = new DateTime($thesis['start_date']);
$current_date = new DateTime();
$interval = $start_date->diff($current_date);
$elapsed_time = $interval->format('%y έτη, %m μήνες, %d ημέρες');



if (isset($_POST['cancel'])) {
    // Έλεγχος και ανάκτηση δεδομένων από τη φόρμα
    $cancelation_reason = isset($_POST['cancelation_reason']) ? $conn->real_escape_string($_POST['cancelation_reason']) : null;
    $cancel_gs_number = isset($_POST['cancel_gs_number']) ? intval($_POST['cancel_gs_number']) : 0;
    $cancel_gs_year = isset($_POST['cancel_gs_year']) ? intval($_POST['cancel_gs_year']) : 0;

    // Έλεγχος αν το πεδίο cancelation_reason είναι κενό
    if (empty($cancelation_reason)) {
        echo "<script>alert('Παρακαλώ εισάγετε τον λόγο ακύρωσης.');</script>";
        exit();
    }

    // Καταγραφή της ακύρωσης στο log πριν από τη διαγραφή
    $log_sql = "INSERT INTO ThesisLogs (thesis_id, action, reason, gs_number, gs_year) 
                VALUES ($thesis_id, 'Ακύρωση', '$cancelation_reason', $cancel_gs_number, $cancel_gs_year)";
    
    if ($conn->query($log_sql) === TRUE) {
        // Διαγραφή της διπλωματικής από τον πίνακα Theses
        $delete_sql = "DELETE FROM Theses WHERE thesis_id = $thesis_id";
        
        if ($conn->query($delete_sql) === TRUE) {
            $message = "Η ανάθεση ακυρώθηκε επιτυχώς!";
            header("Location: grammateia_home.php");
            exit();
        } else {
            echo "<script>alert('Σφάλμα κατά τη διαγραφή: " . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('Σφάλμα κατά την καταγραφή της ακύρωσης: " . $conn->error . "');</script>";
    }
}



    if (isset($_POST['complete'])) {
        $status = "Περατωμένη";

        $complete_sql = "UPDATE Theses SET status = '$status' WHERE thesis_id = $thesis_id";

        if ($conn->query($complete_sql) === TRUE) {
            $message = "Η κατάσταση άλλαξε σε 'Περατωμένη' επιτυχώς!";
            header("Location: grammateia_home.php");
            exit();
        } else {
            $message = "Σφάλμα κατά την ενημέρωση: " . $conn->error;
        }
    }




$conn->close();
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
        .status {
    font-weight: bold;
        }

    .status.active {
    color: green;
       }

   .status.pending {
    color: orange;
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

        <!-- Εμφάνιση δεδομένων -->
        <form method="POST">
            <label for="title">Τίτλος Θέματος:</label>
            <div class="readonly-field" id="title"> <?php echo $thesis['title']; ?> </div>

            <label for="description">Περιγραφή:</label>
            <div class="readonly-field" id="description"> <?php echo $thesis['description']; ?> </div>

            <label>Τρέχουσα Κατάσταση:</label>
            <p class="status <?php echo $thesis['status'] == 'Ενεργή' ? 'active' : 'pending'; ?>">
                <?php echo $thesis['status']; ?>
            </p>

                
            </select>

            <label for="start_date">Ημερομηνία Έναρξης:</label>
            <div class="readonly-field" id="start_date"><?php echo $thesis['start_date']; ?></div>

        

            <!-- Έλεγχος αν η κατάσταση δεν είναι 'Υπό Εξέταση' -->
            <?php if ($thesis['status'] != 'Υπό Εξέταση'): ?>
            <p>Χρόνος από την ανάθεση: <?php echo $elapsed_time; ?></p>
            <?php endif; ?>
            
            <!-- Τριμελής Επιτροπή -->
            <h3>Τριμελής Επιτροπή</h3>
<table border="1" cellpadding="8" cellspacing="0">
    <thead>
        <tr>
            <th>Ρόλος</th>
            <th>ID Καθηγητή</th>
            <th>Όνομα</th>
            <th>Επώνυμο</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($committee_members as $member): ?>
            <tr>
                <td><?php echo htmlspecialchars($member['role']); ?></td>
                <td><?php echo htmlspecialchars($member['professor_id']); ?></td>
                <td><?php echo htmlspecialchars($member['name']); ?></td>
                <td><?php echo htmlspecialchars($member['surname']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


            <?php if ($thesis['status'] == 'Ενεργή'): ?>
                <h3>Ακύρωση Ανάθεσης Θέματος</h3>
                

                <label for="cancel_gs_number">Αριθμός Ακύρωσης ΓΣ:</label>
                <input type="number" id="cancel_gs_number" name="cancel_gs_number" required>

                <label for="cancel_gs_year">Έτος Απόφασης ΓΣ:</label>
                <input type="number" id="cancel_gs_year" name="cancel_gs_year" required>

                <label for="cancelation_reason">Λόγος Ακύρωσης:</label>
                <textarea id="cancelation_reason" name="cancelation_reason" rows="3" required></textarea>

                <button type="submit" name="cancel" class="delete-button">Ακύρωση Ανάθεσης</button>
                <button type="submit" name="update">Αποθήκευση Αλλαγών</button>
            <?php endif; ?>

            
                
            <?php if ($thesis['status'] == 'Υπό Εξέταση'): ?>
    <form method="POST" action="">
        <label for="grade">Καταχωρημένος Βαθμός:</label>
        <input type="text" id="grade" name="grade" value="<?php echo htmlspecialchars($grade); ?>" readonly required>
        

        <label for="link">Σύνδεσμος προς το Νημερτή:</label>
        <input type="url" id="link" name="link" value="<?php echo htmlspecialchars($thesis['repository_link']); ?>" readonly required>

        <button type="submit" name="complete">Ολοκλήρωση ΔΕ</button>
    </form>
            <?php endif; ?>

            
        </form>

        <div class="back-link">
            <a href="grammateia_home.php">Πίσω στη Λίστα Διπλωματικών</a>
        </div>
    </div>
</body>
</html>
