<?php
header('Content-Type: application/json');

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";


$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Η σύνδεση με τη βάση δεδομένων απέτυχε."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';


    // Προστασία από SQL Injection
    $email = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);


    // Έλεγχος για τον χρήστη στη βάση
    $sql = "SELECT * FROM Users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);


    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        session_start();
        $_SESSION['email'] = $row['email'];
        $_SESSION['user_type'] = $row['user_type'];


        // Ανάλογα με τον τύπο του χρήστη
        if (strpos($row['email'], 'pr') === 0) {
            $redirect = "professor_home.php";
        } elseif (strpos($row['email'], 'gr') === 0) {
            $redirect = "grammateia_home.php";
        } elseif (strpos($row['email'], 'st') === 0) {
            $redirect = "student_home.php";
        } else {
            echo json_encode(["status" => "error", "message" => "Άγνωστος τύπος χρήστη."]);
            exit();
        }


        echo json_encode(["status" => "success", "redirect" => $redirect]);
    } else {
        echo json_encode(["status" => "error", "message" => "Λάθος email ή κωδικός πρόσβασης."]);
    }
}
$conn->close();
?>



