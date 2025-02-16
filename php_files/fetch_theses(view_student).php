<?php
session_start();

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

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

// Ανάκτηση του ID του φοιτητή που προβάλλεται
if (!isset($_GET['student_id'])) {
    echo json_encode(["error" => "Student ID not provided"]);
    exit();
}

$student_id = intval($_GET['student_id']);

// Ανάκτηση δεδομένων φοιτητή
$sql_student = "SELECT name, surname FROM students WHERE student_id = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows === 0) {
    echo json_encode(["error" => "Student not found"]);
    exit();
}

$student = $result_student->fetch_assoc();

// Ανάκτηση διπλωματικών εργασιών φοιτητή
$sql_theses = "SELECT title, description, status FROM theses WHERE student_id = ?";
$stmt_theses = $conn->prepare($sql_theses);
$stmt_theses->bind_param("i", $student_id);
$stmt_theses->execute();
$result_theses = $stmt_theses->get_result();

$theses = [];
while ($row = $result_theses->fetch_assoc()) {
    $theses[] = $row;
}

// Επιστροφή δεδομένων ως JSON
echo json_encode([
    "student" => $student,
    "theses" => $theses
]);
?>