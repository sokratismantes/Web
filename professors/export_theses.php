<?php

declare(strict_types=1);
session_start();

$dsn  = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo "Not authorized.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.professor_id
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$profRow = $stmt->fetch();

$professor_id = (int)($profRow['professor_id'] ?? 0);
if ($professor_id <= 0) {
    http_response_code(403);
    echo "Professor not found.";
    exit;
}

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : '';
if (!in_array($format, ['csv', 'json'], true)) {
    http_response_code(400);
    echo "Missing or invalid 'format' (csv|json).";
    exit;
}

// φίλτρα
$title      = isset($_GET['title']) ? trim($_GET['title']) : null;        
$status     = isset($_GET['status']) ? trim($_GET['status']) : null;      
$startFrom  = isset($_GET['start_from']) ? trim($_GET['start_from']) : null; 
$startTo    = isset($_GET['start_to']) ? trim($_GET['start_to']) : null;     
$endFrom    = isset($_GET['end_from']) ? trim($_GET['end_from']) : null;     
$endTo      = isset($_GET['end_to']) ? trim($_GET['end_to']) : null;        

$exportColumns = [
    'title',
    'description',
    'status',
    'start_date',
    'end_date',
    'final_grade',
    'student_id',
];

$baseSelect = "
    SELECT 
        t.title,
        t.description,
        t.status,
        t.start_date,
        t.end_date,
        t.final_grade,
        t.student_id
    FROM theses t
    WHERE t.supervisor_id = :pid1

    UNION ALL

    SELECT
        t.title,
        t.description,
        t.status,
        t.start_date,
        t.end_date,
        t.final_grade,
        t.student_id
    FROM committees c
    JOIN theses t ON t.thesis_id = c.thesis_id
    WHERE (c.member1_id = :pid2 OR c.member2_id = :pid2)
";

$sql = "SELECT DISTINCT
            b.title,
            b.description,
            b.status,
            b.start_date,
            b.end_date,
            b.final_grade,
            b.student_id
        FROM (
            {$baseSelect}
        ) AS b
        WHERE 1=1";

$params = [
    ':pid1' => $professor_id,
    ':pid2' => $professor_id,
];

/* Φίλτρα στο ενιαίο σύνολο */
if ($title !== null && $title !== '') {
    $sql .= " AND b.title LIKE :title";
    $params[':title'] = '%' . $title . '%';
}

if ($status !== null && $status !== '') {
    $sql .= " AND b.status = :status";
    $params[':status'] = $status;
}

if ($startFrom) {
    $sql .= " AND b.start_date >= :start_from";
    $params[':start_from'] = $startFrom;
}
if ($startTo) {
    $sql .= " AND b.start_date <= :start_to";
    $params[':start_to'] = $startTo;
}
if ($endFrom) {
    $sql .= " AND b.end_date >= :end_from";
    $params[':end_from'] = $endFrom;
}
if ($endTo) {
    $sql .= " AND b.end_date <= :end_to";
    $params[':end_to'] = $endTo;
}

$sql .= " ORDER BY b.start_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filenameBase = "theses_export_" . date('Ymd_His');

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filenameBase}.json\"");

    $payload = [
        'exported_at' => date('c'),
        'count'       => count($rows),
        'columns'     => $exportColumns,
        'data'        => array_map(function ($r) {
            
            foreach (['start_date','end_date'] as $d) {
                if (array_key_exists($d, $r)) {
                    $r[$d] = ($r[$d] === null || $r[$d] === '') ? null : (string)$r[$d];
                }
            }
            return $r;
        }, $rows),
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// CSV
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filenameBase}.csv\"");

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
// Επικεφαλίδες
fputcsv($out, $exportColumns);

// Γραμμές
foreach ($rows as $row) {
    $line = [];
    foreach ($exportColumns as $col) {
        $val = $row[$col] ?? '';
        if (in_array($col, ['start_date','end_date'], true)) {
            $val = ($val === null || $val === '') ? '' : (string)$val;
        }
        $line[] = $val;
    }
    fputcsv($out, $line);
}
fclose($out);
exit;
