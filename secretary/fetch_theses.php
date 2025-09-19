<?php
session_start();
if (!isset($_SESSION['email'])) { exit; }

$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) { die("DB Error"); }

$sql = "SELECT thesis_id, title, status, start_date, supervisor_id FROM Theses ORDER BY thesis_id ASC";
$result = $conn->query($sql);

$theses = [];
while ($row = $result->fetch_assoc()) {
    $theses[] = $row;
}
echo json_encode($theses, JSON_UNESCAPED_UNICODE);
$conn->close();
