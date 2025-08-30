<?php
// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";


$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Η σύνδεση απέτυχε: " . $conn->connect_error);
}


// Ανάκτηση του query από το AJAX request
$search = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : "";


// Ερώτημα αναζήτησης στη βάση δεδομένων
$sql = "SELECT thesis_id, title, status, start_date, supervisor_id
        FROM Theses
        WHERE (title LIKE '%$search%' OR status LIKE '%$search%')
        AND status IN ('Ενεργή', 'Υπό Εξέταση')
        ORDER BY thesis_id ASC";


$result = $conn->query($sql);


// Δημιουργία HTML για τα αποτελέσματα
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr onclick=\"redirectToProcess({$row['thesis_id']})\">
                <td>" . htmlspecialchars($row['thesis_id']) . "</td>
                <td>" . htmlspecialchars($row['title']) . "</td>
                <td>" . htmlspecialchars($row['status']) . "</td>
                <td>" . htmlspecialchars($row['start_date']) . "</td>
                <td>" . htmlspecialchars($row['supervisor_id']) . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center;'>Δεν βρέθηκαν αποτελέσματα.</td></tr>";
}


$conn->close();
?>



