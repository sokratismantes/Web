<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "Δεν έχετε συνδεθεί"]);
    exit();
}

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(["error" => "Η σύνδεση με τη βάση δεδομένων απέτυχε"]);
    exit();
}

// Έλεγχος αν δόθηκε το ID του θέματος
if (!isset($_GET['thesis_id'])) {
    echo json_encode(["error" => "Δεν δόθηκε ID θέματος"]);
    exit();
}

$thesis_id = intval($_GET['thesis_id']);

// Ανάκτηση πληροφοριών για το θέμα
$sql = "SELECT * FROM Theses WHERE thesis_id = $thesis_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo json_encode(["error" => "Το θέμα δεν βρέθηκε"]);
    exit();
}

$thesis = $result->fetch_assoc();

$conn->close();

// Επιστροφή δεδομένων σε JSON
header('Content-Type: application/json');
echo json_encode($thesis);
?>
