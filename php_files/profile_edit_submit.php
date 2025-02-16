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

// Ανάκτηση τύπου χρήστη
$sql = "SELECT user_type FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_type = $row['user_type'];

    // Ενημέρωση δεδομένων
    if ($user_type === 'student') {
        $sql = "UPDATE students SET street = ?, number = ?, city = ?, postcode = ?, mobile_telephone = ?, landline_telephone = ? WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssi",
            $_POST['street'],
            $_POST['number'],
            $_POST['city'],
            $_POST['postcode'],
            $_POST['mobile_telephone'],
            $_POST['landline_telephone'],
            $_SESSION['user_id'] // Φορτώνεται από session
        );
    } elseif ($user_type === 'professor') {
        $sql = "UPDATE professors SET mobile = ?, landline = ?, department = ?, university = ? WHERE professor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssi",
            $_POST['mobile'],
            $_POST['landline'],
            $_POST['department'],
            $_POST['university'],
            $_SESSION['user_id']
        );
    } elseif ($user_type === 'secretary') {
        $sql = "UPDATE grammateia SET phone = ? WHERE grammateia_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "si",
            $_POST['phone'],
            $_SESSION['user_id']
        );
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to update data"]);
    }
} else {
    echo json_encode(["error" => "User type not found"]);
}

$conn->close();
?>
