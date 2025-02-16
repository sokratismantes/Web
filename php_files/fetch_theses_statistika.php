<?php
session_start();

// Ορισμός των headers
header('Content-Type: application/json');

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(["error" => "Μη εξουσιοδοτημένη πρόσβαση"]);
    exit();
}

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $conn->connect_error]);
    exit();
}

$conn->set_charset("utf8");

// Εκτέλεση του query για να πάρουμε τις διπλωματικές
$sql = "SELECT thesis_id, title FROM theses";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Σφάλμα εκτέλεσης query: " . $conn->error]);
    exit();
}

// Ανάκτηση δεδομένων
$theses = [];
while ($row = $result->fetch_assoc()) {
    $theses[] = $row;
}

$conn->close();

// Επιστροφή δεδομένων JSON
echo json_encode(["theses" => $theses]);
?>

