<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$email = $_SESSION['email'];

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Fetch professor's name
$sql = "SELECT name FROM professors INNER JOIN users ON professors.professor_id = users.user_id WHERE users.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$name = ($result->num_rows > 0) ? $result->fetch_assoc()['name'] : "Χρήστης";

// Define cards for dashboard
$cards = [
    [
        "title" => "Λίστα Διπλωματικών",
        "link" => "listaDiplomatikon.php",
        "image" => "unnamed.png"
    ],
    [
        "title" => "Προσκλήσεις",
        "link" => "proskliseis.php",
        "image" => "ss-salesman-businessman-salesperson.png"
    ],
    [
        "title" => "Στατιστικά",
        "link" => "statistika.php",
        "image" => "statistika-stoixima-1024x479.png"
    ]
];

echo json_encode(["name" => $name, "cards" => $cards]);
?>
