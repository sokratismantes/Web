<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Έλεγχος αν υπάρχει το thesis_id στη διεύθυνση URL
if (!isset($_GET['thesis_id'])) {
    die("Λάθος πρόσβαση: Δεν δόθηκε ID διπλωματικής");
}

$thesis_id = intval($_GET['thesis_id']);

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Ανάκτηση του τίτλου της διπλωματικής εργασίας
$query = "SELECT title FROM theses WHERE thesis_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $thesis_id);
$stmt->execute();
$result = $stmt->get_result();
$thesis_title = ($row = $result->fetch_assoc()) ? $row['title'] : "Άγνωστη Διπλωματική";
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Στατιστικά: <?php echo htmlspecialchars($thesis_title); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        canvas {
            margin: 20px auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($thesis_title); ?></h1>
        <canvas id="completionTimeChart"></canvas>
        <canvas id="gradeChart"></canvas>
        <canvas id="thesisCountChart"></canvas>
    </div>

    <script>
 function loadStatistics() {
    fetch(`fetch_theses_showstatistics.php?thesis_id=<?php echo $thesis_id; ?>`)
        .then(response => response.json())
        .then(stats => {
            console.log('Λήψη στατιστικών:', stats);

            if (!stats || stats.error) {
                console.error('Σφάλμα στα δεδομένα:', stats.error);
                return;
            }

            let completionTime = stats.average_completion_time || 0;
            let averageGrade = stats.average_grade || 0;
            let totalTheses = stats.total_theses || 0;

            new Chart(document.getElementById('completionTimeChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Μέσος Χρόνος Περάτωσης (Ημέρες)'],
                    datasets: [{
                        label: 'Χρόνος Περάτωσης',
                        data: [completionTime],
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                }
            });

            new Chart(document.getElementById('gradeChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Μέσος Βαθμός'],
                    datasets: [{
                        label: 'Βαθμός',
                        data: [averageGrade],
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                }
            });

            new Chart(document.getElementById('thesisCountChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Συνολικός Αριθμός Διπλωματικών'],
                    datasets: [{
                        label: 'Αριθμός Διπλωματικών',
                        data: [totalTheses],
                        backgroundColor: 'rgba(255, 159, 64, 0.2)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                }
            });
        })
        .catch(error => console.error('Σφάλμα φόρτωσης στατιστικών:', error));
}

document.addEventListener('DOMContentLoaded', loadStatistics);

    </script>
</body>
</html>
