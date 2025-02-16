<?php
session_start();

if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "User not authenticated"]);
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

// Ανάκτηση τύπου χρήστη και δεδομένων
$sql = "SELECT user_type FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_type = $row['user_type'];

    switch ($user_type) {
        case 'student':
            $sql = "SELECT * FROM students INNER JOIN users ON students.student_id = users.user_id WHERE users.email = ?";
            break;
        case 'professor':
            $sql = "SELECT * FROM professors INNER JOIN users ON professors.professor_id = users.user_id WHERE users.email = ?";
            break;
        case 'secretary':
            $sql = "SELECT * FROM grammateia INNER JOIN users ON grammateia.grammateia_id = users.user_id WHERE users.email = ?";
            break;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        echo json_encode($userData);
    } else {
        echo json_encode(["error" => "No data found"]);
    }
} else {
    echo json_encode(["error" => "User type not found"]);
}

$conn->close();
?>
