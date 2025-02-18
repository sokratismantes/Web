<?php 
session_start();

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst"; 

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Έλεγχος σύνδεσης
if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Ελέγχουμε αν έγινε POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['professors'])) {

    // **Ανάκτηση του student_id βάσει email**
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'student'");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $student_id = $user['user_id'];
    } else {
        die("Σφάλμα: Δεν βρέθηκε ID φοιτητή.");
    }

    // **Ανάκτηση του thesis_id του φοιτητή**
    $stmt = $conn->prepare("SELECT thesis_id FROM theses WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $thesis = $result->fetch_assoc();
    $stmt->close();

    if ($thesis) {
        $thesis_id = $thesis['thesis_id'];
    } else {
        die("Σφάλμα: Δεν βρέθηκε διπλωματική εργασία για τον φοιτητή.");
    }

    // **Ξεκινάμε transaction για ασφάλεια**
    $conn->begin_transaction(); 

    try {
        foreach ($_POST['professors'] as $professor_id) {
            // **Κλήση της stored procedure**
            $stmt = $conn->prepare("CALL SendInvitationToProfessor(?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Σφάλμα στην προετοιμασία του statement: " . $conn->error);
            }

            $stmt->bind_param("iii", $student_id, $thesis_id, $professor_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Σφάλμα στην εκτέλεση της procedure: " . $stmt->error);
            }
            $stmt->close();

            // **Έλεγχος αν προστέθηκε η ειδοποίηση στον πίνακα professors_notifications**
            $check_stmt = $conn->prepare("SELECT * FROM professors_notifications WHERE professor_id = ? AND student_id = ? AND thesis_id = ? ORDER BY sent_at DESC LIMIT 1");
            $check_stmt->bind_param("iii", $professor_id, $student_id, $thesis_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            $check_stmt->close();
        }

        // **Commit εφόσον όλα πάνε καλά**
        $conn->commit();
        $message = "Οι προσκλήσεις στάλθηκαν επιτυχώς!";
    } catch (Exception $e) {
        // **Rollback αν υπάρξει σφάλμα**
        $conn->rollback();
        $message = "Σφάλμα: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επιλογή Τριμελούς Επιτροπής</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .container {
            margin: 50px auto;
            padding: 20px;
            max-width: 800px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .submit-button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-button:hover {
            background-color: #218838;
        }
        .message {
            font-size: 1.2rem;
            color: green;
            font-weight: bold;
        }
        .back-button {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color:rgb(19, 127, 221);
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Επιλογή Τριμελούς Επιτροπής</h1>

        <!-- Εμφάνιση μηνύματος -->
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Επιλογή</th>
                        <th>Όνομα</th>
                        <th>Επώνυμο</th>
                    </tr>
                </thead>
                <tbody id="professor-list">
                    <tr>
                        <td colspan="3" class="loading">Φορτώνει δεδομένα...</td>
                    </tr>
                </tbody>
            </table>
            <button type="submit" class="submit-button">Αποστολή Προσκλήσεων</button>
        </form>

        <!-- Τοποθετήσαμε το κουμπί επιστροφής κάτω από το πράσινο κουμπί -->
        <a href="student_home.php" class="back-button">Επιστροφή στην Αρχική Οθόνη</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const professorList = document.getElementById('professor-list');

            fetch('fetch_theses(epilogitrimelousepitropis).php')
                .then(response => response.json())
                .then(data => {
                    professorList.innerHTML = '';
                    data.forEach(professor => {
                        const row = document.createElement('tr');
                        row.innerHTML = 
                            `<td><input type="checkbox" name="professors[]" value="${professor.professor_id}"></td>
                            <td>${professor.name}</td>
                            <td>${professor.surname}</td>`;
                        professorList.appendChild(row);
                    });
                })
                .catch(error => {
                    professorList.innerHTML = `<tr><td colspan="3" class="loading">${error.message}</td></tr>`;
                });
        });
    </script>

</body>
</html>

<?php
$conn->close();
?>  
