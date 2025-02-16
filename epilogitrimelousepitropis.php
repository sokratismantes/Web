<?php
// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst"; // Σωστή βάση δεδομένων

// Δημιουργία σύνδεσης με τη βάση
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Έλεγχος σύνδεσης
if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Διαχείριση υποβολής φόρμας και κλήση της procedure
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['professors'])) {
    $student_id = 101; // Αντικατέστησε με την τρέχουσα ID του φοιτητή (ίσως από SESSION)
    $thesis_id = 2; // Αντικατέστησε με την επιλεγμένη διπλωματική

    $conn->begin_transaction(); // Ξεκινάμε transaction για ασφάλεια

    try {
        foreach ($_POST['professors'] as $professor_id) {
            $stmt = $conn->prepare("CALL SendInvitationToProfessor(?, ?, ?)");
            $stmt->bind_param("iii", $student_id, $thesis_id, $professor_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        $message = "Οι προσκλήσεις στάλθηκαν επιτυχώς!";
    } catch (Exception $e) {
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
        .loading {
            font-size: 1.2rem;
            color: #555;
        }
        .message {
            font-size: 1.2rem;
            color: green;
            font-weight: bold;
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const professorList = document.getElementById('professor-list');

            // Φόρτωση δεδομένων μέσω AJAX
            fetch('fetch_theses(epilogitrimelousepitropis).php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Σφάλμα κατά την ανάκτηση δεδομένων');
                    }
                    return response.json();
                })
                .then(data => {
                    professorList.innerHTML = '';
                    data.forEach(professor => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><input type="checkbox" name="professors[]" value="${professor.professor_id}"></td>
                            <td>${professor.name}</td>
                            <td>${professor.surname}</td>
                        `;
                        professorList.appendChild(row);
                    });
                })
                .catch(error => {
                    professorList.innerHTML = `
                        <tr>
                            <td colspan="3" class="loading">${error.message}</td>
                        </tr>
                    `;
                });
        });
    </script>

<script>
    document.querySelector('form').addEventListener('submit', function (event) {
        event.preventDefault(); // Αποτροπή ανανέωσης σελίδας

        const formData = new FormData(this);

        fetch('', { // Αποστολή στο ίδιο αρχείο
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // Λήψη απάντησης
        .then(data => {
            // Εμφάνιση μηνύματος
            alert("Οι προσκλήσεις στάλθηκαν επιτυχώς!");

            // Επαναφορά φόρμας αν η αποστολή ήταν επιτυχής
            document.querySelector('form').reset();
        })
        .catch(error => {
            console.error('Σφάλμα:', error);
            alert('Παρουσιάστηκε σφάλμα κατά την αποστολή.');
        });
    });
</script>

</body>
</html>

<?php
// Κλείσιμο σύνδεσης με τη βάση
$conn->close();
?>

