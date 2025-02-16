<?php
session_start();

header("Content-Type: application/json");

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$email = $_SESSION['email'];

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Ανάκτηση παραμέτρου αναζήτησης
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ανάκτηση δεδομένων διπλωματικών εργασιών
$sql_theses = "
    SELECT title, description, status 
    FROM theses 
    WHERE student_id = (SELECT user_id FROM users WHERE email = ?) 
      AND title LIKE ?";
$stmt = $conn->prepare($sql_theses);
$searchParam = "%$search%";
$stmt->bind_param("ss", $email, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

$theses = [];
while ($row = $result->fetch_assoc()) {
    $theses[] = $row;
}

// Επιστροφή δεδομένων ως JSON
echo json_encode($theses);
?>
