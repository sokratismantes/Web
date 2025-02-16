<?php
session_start();

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
    echo json_encode(["error" => "Η σύνδεση με τη βάση δεδομένων απέτυχε"]);
    exit();
}

$conn->set_charset("utf8");

// Ανάκτηση δεδομένων από τη βάση
$sql = "SELECT ci.invitation_id, ci.thesis_id, ci.invited_professor_id, ci.status, ci.sent_at, ci.responded_at, ci.comments, t.title
        FROM committeeinvitations ci
        LEFT JOIN theses t ON ci.thesis_id = t.thesis_id";

$result = $conn->query($sql);

$invitations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $invitations[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($invitations);
