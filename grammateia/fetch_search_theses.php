<?php
$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Σφάλμα σύνδεσης στη βάση δεδομένων']));
}

$search = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : "";

$sql = "SELECT thesis_id, title, status, start_date, supervisor_id
        FROM Theses
        WHERE (title LIKE '%$search%' OR status LIKE '%$search%')
        AND status IN ('Ενεργή', 'Υπό Εξέταση', 'Υπό Ανάθεση')
        ORDER BY thesis_id ASC";

$result = $conn->query($sql);

$theses = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $theses[] = $row;
    }
}

echo json_encode($theses);
$conn->close();
?>
