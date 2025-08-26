<?php
// Στοιχεία σύνδεσης με βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

// Σύνδεση με τη βάση
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Έλεγχος σύνδεσης
if ($conn->connect_error) {
    die("Η σύνδεση απέτυχε: " . $conn->connect_error);
}

// Λήψη παραμέτρων GET
$from = isset($_GET['from']) ? $_GET['from'] : null;
$to = isset($_GET['to']) ? $_GET['to'] : null;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'json';

// Μετατροπή ημερομηνιών σε μορφή YYYY-MM-DD για MySQL
function convertDate($dateStr) {
    $day = substr($dateStr, 0, 2);
    $month = substr($dateStr, 2, 2);
    $year = substr($dateStr, 4, 4);
    return "$year-$month-$day";
}

$where = "";
if ($from && $to) {
    $from_date = convertDate($from);
    $to_date = convertDate($to);
    $where = "WHERE date BETWEEN '$from_date' AND '$to_date'";
}

// Ανάκτηση δεδομένων από τη βάση
$sql = "SELECT date, time, title, announcement_text FROM Announcements $where ORDER BY date, time";
$result = $conn->query($sql);

// Δημιουργία λίστας ανακοινώσεων
$announcements = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = [
            "date" => date("dmY", strtotime($row['date'])),
            "time" => date("Hi", strtotime($row['time'])),
            "title" => $row['title'],
            "announcement_text" => $row['announcement_text']
        ];
    }
}

// Δημιουργία δομής για έξοδο
$output = [
    "announcements" => [
        "from" => $from,
        "to" => $to,
        "announcement_list" => $announcements
    ]
];

// Επιστροφή JSON ή XML
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} elseif ($format === 'xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $xml = new SimpleXMLElement('<announcements/>');
    $xml->addChild('from', $from);
    $xml->addChild('to', $to);
    $list = $xml->addChild('announcement_list');
    foreach ($announcements as $a) {
        $item = $list->addChild('announcement');
        $item->addChild('date', $a['date']);
        $item->addChild('time', $a['time']);
        $item->addChild('title', htmlspecialchars($a['title']));
        $item->addChild('announcement_text', htmlspecialchars($a['announcement_text']));
    }
    echo $xml->asXML();
} else {
    echo "Μη υποστηριζόμενη μορφή. Επιλέξτε json ή xml.";
}

$conn->close();
?>
