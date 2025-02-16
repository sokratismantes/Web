<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(["error" => "Μη εξουσιοδοτημένη πρόσβαση"]);
    exit();
}

if (!isset($_GET['thesis_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Λείπει το thesis_id"]);
    exit();
}

$thesis_id = intval($_GET['thesis_id']);
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Σφάλμα σύνδεσης στη βάση δεδομένων"]);
    exit();
}
$conn->set_charset("utf8");

// Ανάκτηση στατιστικών για τη συγκεκριμένη διπλωματική
$query = "
    SELECT 
        AVG(DATEDIFF(end_date, start_date)) AS average_completion_time,
        AVG(final_grade) AS average_grade,
        COUNT(*) AS total_theses
    FROM theses
    WHERE supervisor_id = (SELECT supervisor_id FROM theses WHERE thesis_id = ?)
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $thesis_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$stmt->close();
$conn->close();

// Επιστροφή δεδομένων JSON
echo json_encode([
    "average_completion_time" => round($data["average_completion_time"], 2),
    "average_grade" => round($data["average_grade"], 2),
    "total_theses" => $data["total_theses"]
]);
?>
