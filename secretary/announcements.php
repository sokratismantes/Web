<?php

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'vasst';
const TABLE_NAME = 'Announcements'; 

/* ΒΟΗΘΗΤΙΚΑ ΓΙΑ API */
function respond_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT
    );
    exit();
}
function respond_xml(array $list, string $from, string $to, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/xml; charset=utf-8');
    $xml = new SimpleXMLElement('<announcements/>');
    $xml->addChild('from', $from);
    $xml->addChild('to', $to);
    $listNode = $xml->addChild('announcement_list');
    foreach ($list as $a) {
        $item = $listNode->addChild('announcement');
        $item->addChild('date', $a['date']);
        $item->addChild('time', $a['time']);
        $item->addChild('title', htmlspecialchars($a['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        $item->addChild('announcement_text', htmlspecialchars($a['announcement_text'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
    }
    echo $xml->asXML();
    exit();
}
/** ddmmyyyy -> 'YYYY-MM-DD' ή null */
function ddmmyyyy_to_mysql(?string $s): ?string {
    if (!is_string($s) || !preg_match('/^\d{8}$/', $s)) return null;
    $d = (int)substr($s, 0, 2);
    $m = (int)substr($s, 2, 2);
    $y = (int)substr($s, 4, 4);
    if (!checkdate($m, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}
/** 'YYYY-MM-DD' -> ddmmyyyy */
function mysql_date_to_ddmmyyyy(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('dmY', $ts) : '';
}
/** 'HH:MM[:SS]' -> hhmm */
function mysql_time_to_hhmm(string $time): string {
    if (preg_match('/^(\d{2}):(\d{2})/', $time, $m)) return $m[1].$m[2];
    $ts = strtotime($time);
    return $ts ? date('Hi', $ts) : '';
}

/* ROUTING */
$has_from_to = isset($_GET['from']) && isset($_GET['to']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$has_from_to) {
    
    $self_path = $_SERVER['PHP_SELF']; 
    ?>
<!doctype html>
<html lang="el">
<head>
  <meta charset="utf-8">
  <title>Ανακοινώσεις Παρουσιάσεων — Εξαγωγή</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:24px}
    .row{display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem;flex-wrap:wrap}
    label{font-weight:600}
    input[type="date"]{padding:.4rem .5rem}
    .group{position:relative;display:inline-block}
    .btn{background:#0b4ba6;color:#fff;border:0;padding:.6rem .9rem;border-radius:.5rem;cursor:pointer;font-weight:700}
    .btn:hover{background:#084086}
    .menu{position:absolute;top:100%;left:0;min-width:200px;background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;
      box-shadow:0 10px 20px rgba(0,0,0,.08);padding:.25rem;z-index:10}
    .hidden{display:none}
    .item{width:100%;text-align:left;background:transparent;border:0;padding:.6rem .7rem;border-radius:.4rem;cursor:pointer;font-weight:600}
    .item:hover{background:#f3f4f6}
  </style>
</head>
<body>

  <h1>Ανακοινώσεις Παρουσιάσεων — Εξαγωγή</h1>

  <div class="row">
    <label for="fromDate">Από:</label>
    <input type="date" id="fromDate" required>
    <label for="toDate">Έως:</label>
    <input type="date" id="toDate" required>
  </div>

  <div class="group">
    <button id="exportBtn" class="btn">Ανακοινώσεις Παρουσιάσεων ▾</button>
    <div id="menu" class="menu hidden" role="menu" aria-labelledby="exportBtn">
      <button class="item" data-format="json">Λήψη JSON</button>
      <button class="item" data-format="xml">Λήψη XML</button>
    </div>
  </div>

  <script>
    (function(){
      // endpoint
      const ENDPOINT_PATH = <?php echo json_encode($self_path, JSON_UNESCAPED_SLASHES); ?>;

      function toDdMmYyyy(iso){ const [y,m,d] = iso.split('-'); return d+m+y; }

      function setDefaults(){
        const fromEl = document.getElementById('fromDate');
        const toEl   = document.getElementById('toDate');
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm   = String(now.getMonth()+1).padStart(2,'0');
        const dd   = String(now.getDate()).padStart(2,'0');
        const first = `${yyyy}-${mm}-01`;
        const today = `${yyyy}-${mm}-${dd}`;
        if(!fromEl.value) fromEl.value = first;
        if(!toEl.value)   toEl.value   = today;
      }

      function exportFile(fmt){
        const fromISO = document.getElementById('fromDate').value;
        const toISO   = document.getElementById('toDate').value;
        if(!fromISO || !toISO){ alert('Συμπληρώστε ημερομηνίες Από/Έως.'); return; }

        const url = new URL(ENDPOINT_PATH, window.location.origin);
        url.searchParams.set('from', toDdMmYyyy(fromISO));
        url.searchParams.set('to',   toDdMmYyyy(toISO));
        url.searchParams.set('format', fmt);
        url.searchParams.set('download', '1'); 

        window.open(url.toString(), '_blank');
      }

      setDefaults();
      const btn  = document.getElementById('exportBtn');
      const menu = document.getElementById('menu');

      btn.addEventListener('click', () => { menu.classList.toggle('hidden'); });
      menu.addEventListener('click', (e) => {
        const item = e.target.closest('.item'); if(!item) return;
        const fmt = item.getAttribute('data-format');
        menu.classList.add('hidden'); exportFile(fmt);
      });
      document.addEventListener('click', (e) => {
        if (!menu.contains(e.target) && e.target !== btn) menu.classList.add('hidden');
      });
    })();
  </script>
</body>
</html>
<?php
    exit(); 
}

/*  παραγωγή JSON/XML */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(['error' => 'Μη έγκυρη μέθοδος. Χρησιμοποιήστε GET.'], 405);
}

$from_raw = $_GET['from'] ?? null;   // ddmmyyyy
$to_raw   = $_GET['to']   ?? null;   // ddmmyyyy
$format   = strtolower((string)($_GET['format'] ?? 'json')); // json|xml
$download = isset($_GET['download']) && $_GET['download'] === '1';

// Επικύρωση
if (!$from_raw || !$to_raw) {
    respond_json(['error' => 'Απαιτούνται οι παράμετροι from και to σε μορφή ddmmyyyy.'], 400);
}
$from_mysql = ddmmyyyy_to_mysql($from_raw);
$to_mysql   = ddmmyyyy_to_mysql($to_raw);
if (!$from_mysql || !$to_mysql) {
    respond_json(['error' => 'Μη έγκυρη ημερομηνία. Χρησιμοποιήστε ddmmyyyy και έγκυρες τιμές.'], 400);
}
if ($from_mysql > $to_mysql) {
    respond_json(['error' => 'Η from δεν μπορεί να είναι μεταγενέστερη της to.'], 400);
}
if (!in_array($format, ['json','xml'], true)) {
    respond_json(['error' => 'Μη υποστηριζόμενη μορφή. Επιλέξτε json ή xml.'], 400);
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    respond_json(['error' => 'Αποτυχία σύνδεσης στη βάση.'], 500);
}
$conn->set_charset('utf8mb4');

$sql = "SELECT `date`, `time`, `title`, `announcement_text`
        FROM `".TABLE_NAME."`
        WHERE `date` BETWEEN ? AND ?
        ORDER BY `date` ASC, `time` ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $conn->close();
    respond_json(['error' => 'Αποτυχία προετοιμασίας ερωτήματος.'], 500);
}
$stmt->bind_param('ss', $from_mysql, $to_mysql);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    respond_json(['error' => 'Αποτυχία εκτέλεσης ερωτήματος.'], 500);
}
$res = $stmt->get_result();

$announcement_list = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $announcement_list[] = [
            'date'              => mysql_date_to_ddmmyyyy((string)$row['date']),
            'time'              => mysql_time_to_hhmm((string)$row['time']),
            'title'             => (string)$row['title'],
            'announcement_text' => (string)$row['announcement_text'],
        ];
    }
    $res->free();
}
$stmt->close();
$conn->close();

// Προαιρετικό download
if ($download) {
    $fname = sprintf('announcements_%s_%s.%s', $from_raw, $to_raw, $format);
    header('Content-Disposition: attachment; filename="'.$fname.'"');
}

// Επιστροφή
$payload = [
    'announcements' => [
        'from' => $from_raw,
        'to'   => $to_raw,
        'announcement_list' => $announcement_list
    ]
];
if ($format === 'json') {
    respond_json($payload, 200);
} else {
    respond_xml($announcement_list, $from_raw, $to_raw, 200);
}
