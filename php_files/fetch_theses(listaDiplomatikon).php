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
    die(json_encode(["error" => "Η σύνδεση με τη βάση δεδομένων απέτυχε"]));
}

$conn->set_charset("utf8");

// Λήψη παραμέτρου αναζήτησης
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Ανάκτηση θεμάτων
$sql = "SELECT thesis_id, title, status, start_date, end_date, supervisor_id 
        FROM Theses 
        WHERE title LIKE '%$search%' 
        OR status LIKE '%$search%' 
        ORDER BY thesis_id ASC";
$result = $conn->query($sql);

$theses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $theses[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($theses);
