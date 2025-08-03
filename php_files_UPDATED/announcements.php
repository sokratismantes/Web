<?php
// Ορισμός επικεφαλίδων για CORS και χωρίς ταυτοποίηση
header("Access-Control-Allow-Origin: *");

// Λειτουργία: http://localhost/web_project/announcements.php?from=01082025&to=05082025&format=json
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';

function respondWithError($message) {
    http_response_code(400);
    echo json_encode(["error" => $message]);
    exit;
}

// Έλεγχος εγκυρότητας ημερομηνιών (μορφή ddmmyyyy)
if (!$from || !$to || !preg_match('/^\d{8}$/', $from) || !preg_match('/^\d{8}$/', $to)) {
    respondWithError("Παρακαλώ ορίστε έγκυρες παραμέτρους 'from' και 'to' σε μορφή ddmmyyyy.");
}

// Μετατροπή σε format ημερομηνίας για SQL
$from_sql = DateTime::createFromFormat('dmY', $from)->format('Y-m-d');
$to_sql = DateTime::createFromFormat('dmY', $to)->format('Y-m-d');

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    respondWithError("Σφάλμα σύνδεσης με τη βάση: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Ανάκτηση ανακοινώσεων βάσει εύρους ημερομηνιών
$stmt = $conn->prepare("
    SELECT date, time, title, announcement_text 
    FROM announcements 
    WHERE date BETWEEN ? AND ?
    ORDER BY date, time
");
$stmt->bind_param("ss", $from_sql, $to_sql);
$stmt->execute();
$result = $stmt->get_result();

$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = [
        "date" => DateTime::createFromFormat('Y-m-d', $row['date'])->format('dmY'),
        "time" => DateTime::createFromFormat('H:i:s', $row['time'])->format('Hi'),
        "title" => $row['title'],
        "announcement_text" => $row['announcement_text']
    ];
}

$output = [
    "announcements" => [
        "from" => $from,
        "to" => $to,
        "announcement_list" => $announcements
    ]
];

// Επιστροφή αποτελέσματος με βάση format
if ($format === 'json') {
    header("Content-Type: application/json");
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} elseif ($format === 'xml') {
    header("Content-Type: application/xml; charset=UTF-8");
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<announcements from=\"$from\" to=\"$to\">\n";
    foreach ($announcements as $a) {
        echo "  <announcement>\n";
        echo "    <date>{$a['date']}</date>\n";
        echo "    <time>{$a['time']}</time>\n";
        echo "    <title>" . htmlspecialchars($a['title']) . "</title>\n";
        echo "    <announcement_text>" . htmlspecialchars($a['announcement_text']) . "</announcement_text>\n";
        echo "  </announcement>\n";
    }
    echo "</announcements>";
} else {
    respondWithError("Άγνωστη μορφή εξόδου. Επιτρεπτές τιμές: 'json' ή 'xml'.");
}
