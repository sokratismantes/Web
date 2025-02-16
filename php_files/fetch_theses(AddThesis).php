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

// Ενέργεια για φόρτωση καθηγητών
if (isset($_GET['fetch_professors'])) {
    $sql = "SELECT professor_id, CONCAT(name, ' ', surname) AS fullname FROM professors";
    $result = $conn->query($sql);

    $professors = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $professors[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($professors);
    exit();
}

// Εάν δεν υπάρχει σωστό endpoint, επιστρέφεται σφάλμα
http_response_code(400);
echo json_encode(["error" => "Μη έγκυρη ενέργεια"]);
$conn->close();
?>
