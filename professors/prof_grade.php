<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}


$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";
$pdo  = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);


$stmt = $pdo->prepare("
    SELECT p.professor_id
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$professor_id = (int)$stmt->fetchColumn();
if ($professor_id <= 0) {
    http_response_code(403);
    die("Δεν βρέθηκε ο λογαριασμός καθηγητή για τον χρήστη.");
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Ενεργή & Υπό Εξέταση
    if ($_GET['action'] === 'list_active') {
        $q = "SELECT t.thesis_id, t.title, t.status,
                    s.student_id, s.name AS student_name, s.surname AS student_surname, s.student_number
            FROM theses t
            LEFT JOIN students s ON s.student_id = t.student_id
            WHERE t.supervisor_id = :pid
                AND t.status IN ('Ενεργή','Υπό Εξέταση')
            ORDER BY COALESCE(t.start_date, '0000-00-00') DESC, t.thesis_id DESC";
        $stmt = $pdo->prepare($q);
        $stmt->execute([':pid' => $professor_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Αλλαγή κατάστασης σε Υπό Εξέταση
    if ($_GET['action'] === 'to_under_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $thesis_id = (int)($_POST['thesis_id'] ?? 0);
        if ($thesis_id <= 0) { echo json_encode(['status'=>'error','message'=>'Άκυρο ID.']); exit; }

        $stmt = $pdo->prepare("
            UPDATE theses
            SET status = 'Υπό Εξέταση'
            WHERE thesis_id = :tid AND supervisor_id = :pid AND status = 'Ενεργή'
            LIMIT 1
        ");
        $stmt->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);

        if ($stmt->rowCount() === 1) {
            echo json_encode(['status'=>'success']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Δεν επιτράπηκε η αλλαγή (ελέγξτε ότι είστε επιβλέπων & status=Ενεργή).']);
        }
        exit;
    }

    // Αποθήκευση/ενημέρωση βαθμού
    if ($_GET['action'] === 'save_grade' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $thesis_id = (int)($_POST['thesis_id'] ?? 0);
            $grade_raw = trim($_POST['grade'] ?? '');
            $exam_date = trim($_POST['exam_date'] ?? '');

            if ($thesis_id <= 0) {
                echo json_encode(['status'=>'error','message'=>'Άκυρο ID.']); exit;
            }
            if ($grade_raw === '' || !is_numeric($grade_raw)) {
                echo json_encode(['status'=>'error','message'=>'Δώστε αριθμητικό βαθμό.']); exit;
            }
            $grade = (float)$grade_raw;
            $exam_date_sql = ($exam_date !== '') ? $exam_date : null;

            $chk = $pdo->prepare("
                SELECT COUNT(*) 
                FROM theses t
                LEFT JOIN committees c ON c.thesis_id = t.thesis_id
                WHERE t.thesis_id = :tid
                AND (:pid = t.supervisor_id OR :pid IN (c.member1_id, c.member2_id))
            ");
            $chk->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['status'=>'error','message'=>'Δεν έχετε δικαίωμα βαθμολόγησης.']); exit;
            }

            $sql = "
                INSERT INTO exam_results (thesis_id, professor_id, grade, exam_date)
                VALUES (:tid, :pid, :g, :ed)
                ON DUPLICATE KEY UPDATE
                    grade = VALUES(grade),
                    exam_date = VALUES(exam_date)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tid'=>$thesis_id,
                ':pid'=>$professor_id,
                ':g'=>$grade,
                ':ed'=>$exam_date_sql
            ]);

            echo json_encode(['status'=>'success']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB Error: '.$e->getMessage()]);
        }
        exit;
    }

    // Βάθμοι άλλων μελών 
    if ($_GET['action'] === 'list_other_grades') {
        $thesis_id = (int)($_GET['thesis_id'] ?? 0);
        if ($thesis_id <= 0) { echo json_encode([]); exit; }

        try {
            
            $stmt = $pdo->prepare("
                SELECT t.supervisor_id, c.member1_id, c.member2_id
                FROM theses t
                LEFT JOIN committees c ON c.thesis_id = t.thesis_id
                WHERE t.thesis_id = :tid
                LIMIT 1
            ");
            $stmt->execute([':tid' => $thesis_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) { echo json_encode([]); exit; }

            
            $ids = [];
            foreach (['supervisor_id','member1_id','member2_id'] as $k) {
                $v = isset($row[$k]) ? (int)$row[$k] : 0;
                if ($v > 0) $ids[$v] = true;
            }
            
            unset($ids[$professor_id]);

            
            $others = array_slice(array_keys($ids), 0, 2);
            if (count($others) === 0) { echo json_encode([]); exit; }

            
            $placeholders = implode(',', array_fill(0, count($others), '?'));

            // ονόματα & βαθμός
            $sql = "
                SELECT 
                    p.professor_id,
                    CONCAT(p.name, ' ', p.surname) AS professor_name,
                    er.grade,
                    er.exam_date
                FROM professors p
                LEFT JOIN exam_results er
                ON er.professor_id = p.professor_id
                AND er.thesis_id = ?
                WHERE p.professor_id IN ($placeholders)
                ORDER BY p.professor_id
            ";
            $params = array_merge([$thesis_id], $others);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($rows ?: []);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB Error: '.$e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'list_member_theses') {
        $q = "
            SELECT t.thesis_id, t.title, t.status,
                s.student_id, s.name AS student_name, s.surname AS student_surname, s.student_number
            FROM committees c
            JOIN theses t ON t.thesis_id = c.thesis_id
            LEFT JOIN students s ON s.student_id = t.student_id
            WHERE (c.member1_id = :pid OR c.member2_id = :pid)
            AND t.status IN ('Ενεργή','Υπό Εξέταση')
            ORDER BY COALESCE(t.start_date, '0000-00-00') DESC, t.thesis_id DESC
        ";
        $stmt = $pdo->prepare($q);
        $stmt->execute([':pid' => $professor_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Έλεγχος αν είναι έτοιμη η ανακοίνωση 
    if ($_GET['action'] === 'announce_ready') {
        $thesis_id = (int)($_GET['thesis_id'] ?? 0);
        if ($thesis_id <= 0) { echo json_encode(['ready'=>false]); exit; }

        $chk = $pdo->prepare("SELECT 1 FROM theses WHERE thesis_id = :tid AND supervisor_id = :pid LIMIT 1");
        $chk->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
        if (!$chk->fetchColumn()) { echo json_encode(['ready'=>false]); exit; }

        $q = $pdo->prepare("
            SELECT exam_date, exam_time, exam_mode, room, link, announcements
            FROM examinations
            WHERE thesis_id = :tid
            LIMIT 1
        ");
        $q->execute([':tid'=>$thesis_id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        $ready = false;
        $existing = null;
        if ($row) {
            $hasDate  = !empty($row['exam_date']);
            $hasTime  = !empty($row['exam_time']);
            $hasMode  = !empty($row['exam_mode']);
            $hasPlace = !empty($row['room']) || !empty($row['link']);
            $ready = ($hasDate && $hasTime && $hasMode && $hasPlace);
            $existing = $row['announcements'] ?? null;
        }

        echo json_encode(['ready'=>$ready, 'announcements'=>$existing]);
        exit;
    }

    // template ανακοίνωσης
    if ($_GET['action'] === 'announcement_template') {
        $thesis_id = (int)($_GET['thesis_id'] ?? 0);
        if ($thesis_id <= 0) { echo json_encode(['template'=>'']); exit; }

        // μόνο επιβλέπων
        $chk = $pdo->prepare("SELECT 1 FROM theses WHERE thesis_id = :tid AND supervisor_id = :pid LIMIT 1");
        $chk->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
        if (!$chk->fetchColumn()) { echo json_encode(['template'=>'']); exit; }

        $st = $pdo->prepare("
            SELECT t.title, s.name AS sname, s.surname AS ssurname,
                   e.exam_date, e.exam_time, e.exam_mode, e.room, e.link
            FROM theses t
            LEFT JOIN students s ON s.student_id = t.student_id
            LEFT JOIN examinations e ON e.thesis_id = t.thesis_id
            WHERE t.thesis_id = :tid
            LIMIT 1
        ");
        $st->execute([':tid'=>$thesis_id]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $title = $r['title'] ?? 'Διπλωματική Εργασία';
        $name  = trim(($r['sname'] ?? '').' '.($r['ssurname'] ?? ''));
        $date  = $r['exam_date'] ?? '—';
        $time  = $r['exam_time'] ? substr($r['exam_time'],0,5) : '—';
        $mode  = $r['exam_mode'] ?? '—';
        $place = ($mode && strtolower($mode)==='online') ? ($r['link'] ?? '—') : ($r['room'] ?? '—');

        $template = "ΑΝΑΚΟΙΝΩΣΗ ΠΑΡΟΥΣΙΑΣΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ\n\n".
                    "Τίτλος: $title\n".
                    "Φοιτητής/τρια: $name\n".
                    "Ημερομηνία: $date\n".
                    "Ώρα: $time\n".
                    "Τρόπος: $mode\n".
                    "Χώρος/Σύνδεσμος: $place\n\n".
                    "Σας προσκαλούμε στην παρουσίαση της διπλωματικής εργασίας.";
        echo json_encode(['template'=>$template]); exit;
    }

    // Αποθήκευση/ενημέρωση κειμένου ανακοίνωσης 
    if ($_GET['action'] === 'save_announcement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $thesis_id = (int)($_POST['thesis_id'] ?? 0);
        $text      = trim($_POST['announcements'] ?? '');

        if ($thesis_id <= 0) { echo json_encode(['status'=>'error','message'=>'Άκυρο ID.']); exit; }

        $chk = $pdo->prepare("SELECT 1 FROM theses WHERE thesis_id = :tid AND supervisor_id = :pid LIMIT 1");
        $chk->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['status'=>'error','message'=>'Δεν έχετε δικαίωμα για την ανακοίνωση αυτής της διπλωματικής.']);
            exit;
        }

        
        $ins = $pdo->prepare("INSERT IGNORE INTO examinations (thesis_id) VALUES (:tid)");
        $ins->execute([':tid'=>$thesis_id]);

        // Αποθήκευση κειμένου
        $upd = $pdo->prepare("
            UPDATE examinations
            SET announcements = :txt
            WHERE thesis_id = :tid
            LIMIT 1
        ");
        $upd->execute([':txt'=>$text, ':tid'=>$thesis_id]);

        echo json_encode(['status'=>'success']);
        exit;
    }

    // Λίστα σημειώσεων για μια διπλωματική
    if ($_GET['action'] === 'list_notes') {
        $thesis_id = (int)($_GET['thesis_id'] ?? 0);
        if ($thesis_id <= 0) { echo json_encode([]); exit; }

        $stmt = $pdo->prepare("
            SELECT n.note_id, n.note_text, n.created_at,
                   CONCAT(p.name,' ',p.surname) AS professor_name
            FROM professor_notes n
            JOIN professors p ON p.professor_id = n.professor_id
            WHERE n.thesis_id = :tid
            ORDER BY n.created_at DESC
        ");
        $stmt->execute([':tid'=>$thesis_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        exit;
    }

    // Προσθήκη νέας σημείωσης 
    if ($_GET['action'] === 'add_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $thesis_id = (int)($_POST['thesis_id'] ?? 0);
        $note_text = trim($_POST['note_text'] ?? '');

        if ($thesis_id <= 0 || $note_text === '' || mb_strlen($note_text) > 300) {
            echo json_encode(['status'=>'error','message'=>'Μη έγκυρη σημείωση (έως 300 χαρακτήρες).']); exit;
        }

        $chk = $pdo->prepare("
            SELECT 1
            FROM theses t
            LEFT JOIN committees c ON c.thesis_id = t.thesis_id
            WHERE t.thesis_id = :tid
              AND (:pid = t.supervisor_id OR :pid IN (c.member1_id, c.member2_id))
            LIMIT 1
        ");
        $chk->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['status'=>'error','message'=>'Δεν έχετε δικαίωμα να προσθέσετε σημείωση.']); exit;
        }

        $ins = $pdo->prepare("
            INSERT INTO professor_notes (thesis_id, professor_id, note_text)
            VALUES (:tid, :pid, :txt)
        ");
        $ins->execute([':tid'=>$thesis_id, ':pid'=>$professor_id, ':txt'=>$note_text]);

        echo json_encode(['status'=>'success']); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Ενεργές Διπλωματικές που Επιβλέπω</title>
<style>
html, body { height: 100%; margin: 0; padding: 0; display: flex; flex-direction: column;}
body { font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif; background: linear-gradient(to right, #e2e2e2, #c9d6ff); color: #333; font-size: 0.96rem; min-height: 100vh;}
body::before { content: ""; position: fixed; inset: 0; background-color: hsla(211, 32.3%, 51.4%, 0.35); z-index: -1;}
.header { display: flex; align-items: center; justify-content: space-between; background-color: rgba(245, 245, 245, 0.9); padding: 10px 20px; border-bottom: 1px solid #ddd; box-shadow: 0 8px 8px -4px rgba(0,0,0,0.15);}
.header a { text-decoration: none; display: flex; align-items: center; color: inherit;}
.header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; cursor: pointer; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.1);}
.header span { font-size: 1.2rem; color: #0056b3; }
.container{max-width:1100px;margin:30px auto;padding:24px;background:#fff;border-radius:14px;box-shadow:0 8px 20px rgba(0,0,0,.08)}
h1{margin:0 0 12px;color:#0b4ba6}
.desc{color:#555;margin-bottom:20px}
.table-wrap{overflow:auto;border:1px solid #e8eef6;border-radius:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 10px;border-bottom:1px solid #eef3f9;text-align:left}
th{background:#0b4ba6;color:#fff;position:sticky;top:0}
tr:hover{background:#f3f7ff}
.small{font-size:.9rem;color:#555}
.btn{border:0;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:600}
.btn-primary{background:#1e90ff;color:#fff}
.btn-primary:disabled{opacity:.6;cursor:default}
.btn-green{background:#2ecc71;color:#fff}
.btn-warning{background:#f39c12;color:#fff}
.grade-box{display:flex;gap:8px;align-items:center}
.grade-input{width:80px;padding:6px;border:1px solid #ccd6e0;border-radius:8px}
.badge{display:inline-block;background:#eef3f9;border:1px solid #d8e3ee;color:#334;padding:2px 8px;border-radius:999px;font-size:.8rem}
.grades-pill{background:#f6f9ff;border-color:#e0e9f6}
.note{margin-top:6px;color:#667}
/* Modal */
.og-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;
  background:rgba(0,0,0,.45);z-index:9999}
.og-box{width:min(720px,92vw);background:#fff;border-radius:14px;padding:18px 18px 14px;
  box-shadow:0 18px 50px rgba(0,0,0,.18);border:1px solid #e7eef7}
.og-title{margin:0 0 10px;color:#0b4ba6}
.og-table{width:100%;border-collapse:collapse}
.og-table th,.og-table td{padding:10px;border-bottom:1px solid #eef3f9;text-align:left}
.og-table th{background:#f7faff;font-weight:700;color:#234}
.og-empty{padding:14px;color:#667}
.og-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
.btn-ghost{background:#f3f6fb;border:1px solid #e3ebf6;color:#234}
.btn-ghost:hover{background:#e9f0fb}
.under-locked{border:2px solid #e74c3c !important;background:#fcebea !important;color:#c0392b !important;cursor:default !important;}
/* Notes list item */
.note-item{border:1px solid #eef3f9;background:#fbfdff;padding:8px;border-radius:10px;margin:6px 0}
.note-head{display:flex;justify-content:space-between;font-size:.85rem;color:#556}
.char-hint{font-size:.8rem;color:#667}
.site-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background-color: rgba(0, 51, 102, 0.92); color: white; box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); font-family: 'Segoe UI', sans-serif; margin-bottom: 80px; height: 80px; position: relative; z-index: 10; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;}
.site-header .left { display: flex; align-items: center; gap: 10px;}
.site-header .logo { width:95px; height: 80px;}
.system-name { font-size: 20px; font-weight: 600;}
.site-header .right { display: flex; align-items: center; gap: 20px;}
.site-header .right nav a { color: white; text-decoration: none; margin-right: 15px;}
.site-header .user-info { font-weight: 500;}
footer { flex-shrink: 0; width: 100%; background-color: rgba(0, 51, 102, 0.92); color: white; text-align: center; padding: 30px; margin-top: 20px; height:80px;}
</style>
</head>
<body>
<header class="site-header">
        <div class="left">
            <img src="ceid_logo.png" alt="Logo" class="logo">
            <span class="system-name">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</span>
        </div>
        <div class="right">
            <nav>
                <a href="professor_home.php">Αρχική</a>
                <a href="listaDiplomatikon.php">Οι Διπλωματικές Μου</a>
                <a href="proskliseis.php">Προσκλήσεις</a>
                <a href="statistika.php">Στατιστικά</a>
            </nav>
            <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
        </div>
    </header>
<div class="container">
  <h1>Διπλωματικές Που Επιβλέπω</h1>
  <p class="desc">Εδώ βλέπεις όλες τις <strong>Ενεργές</strong> διπλωματικές όπου είσαι επιβλέπων. Μπορείς:
    <span class="badge">να τις θέσεις σε “Υπό Εξέταση”</span> ·
    <span class="badge">να καταχωρήσεις/ενημερώσεις τον βαθμό σου</span>.
  </p>

  <!-- Βαθμοί άλλων μελών -->
  <div id="otherGradesModal" class="og-modal" aria-hidden="true">
    <div class="og-box" role="dialog" aria-modal="true" aria-labelledby="og-title">
        <h3 id="og-title" class="og-title">Βαθμοί Υπόλοιπων Μελών</h3>
        <div style="overflow:auto; max-height:60vh;">
          <table class="og-table">
            <thead>
              <tr>
                <th>Μέλος</th>
                <th>Βαθμός</th>
                <th>Ημ/νία Εξέτασης</th>
              </tr>
            </thead>
            <tbody id="og-tbody">
              <tr><td colspan="3" class="og-empty">Φορτώνει...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="og-actions">
          <button type="button" class="btn btn-ghost" onclick="closeOtherGradesModal()">Κλείσιμο</button>
        </div>
    </div>
  </div>

  <div class="table-wrap">
    <table id="supervisor-theses-table">
      <thead>
        <tr>
          <th style="min-width:240px">Τίτλος</th>
          <th>Φοιτητής</th>
          <th>Κατάσταση</th>
          <th style="min-width:260px">Ενέργειες</th>
          <th style="min-width:260px">Βαθμός μου</th>
          <th style="min-width:260px">Λοιπά</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="6" class="small">Φορτώνει...</td></tr>
      </tbody>
    </table>
  </div>
  <p class="note">Σημ.: Η αλλαγή κατάστασης σε <em>Υπό Εξέταση</em> επιτρέπεται μόνο στον επιβλέποντα και μόνο από <em>Ενεργή</em>.</p>
</div>

<div class="container">
  <h1>Διπλωματικές Στις Οποίες Είμαι Μέλος</h1>
  <p class="desc">Εδώ βλέπεις όλες τις <strong>Ενεργές</strong> διπλωματικές όπου είσαι μέλος τριμελούς επιτροπής. Μπορείς:
    <span class="badge">να καταχωρήσεις/ενημερώσεις τον βαθμό σου</span>.
  </p>

  <div class="table-wrap">
    <table id="member-theses-table">
      <thead>
        <tr>
          <th style="min-width:240px">Τίτλος</th>
          <th>Φοιτητής</th>
          <th>Κατάσταση</th>
          <th style="min-width:260px">Βαθμός μου</th>
          <th style="min-width:260px">Λοιπά</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="5" class="small">Φορτώνει...</td></tr>
      </tbody>
    </table>
  </div>
  <p class="note">Σημ.: Η αλλαγή κατάστασης σε <em>Υπό Εξέταση</em> επιτρέπεται μόνο στον επιβλέποντα και μόνο από <em>Ενεργή</em>.</p>
</div>

<!-- Κείμενο Ανακοίνωσης Παρουσίασης -->
<div id="announceModal" class="og-modal" aria-hidden="true">
  <div class="og-box" role="dialog" aria-modal="true" aria-labelledby="announce-title">
    <h3 id="announce-title" class="og-title">Κείμενο Ανακοίνωσης Παρουσίασης</h3>
    <div class="small" id="announce-hint" style="margin-bottom:8px;">
      Συμπλήρωσε/τροποποίησε το κείμενο και πάτησε Υποβολή.
    </div>
    <textarea id="announce-text" rows="8" style="width:100%;padding:10px;border:1px solid #e1e7f0;border-radius:10px;"></textarea>
    <div class="og-actions">
      <button type="button" class="btn btn-ghost" onclick="closeAnnounceModal()">Κλείσιμο</button>
      <button type="button" class="btn btn-green" id="announce-submit">Υποβολή</button>
    </div>
  </div>
</div>

<!-- Σημειώσεις Διπλωματικής -->
<div id="notesModal" class="og-modal" aria-hidden="true">
  <div class="og-box" role="dialog" aria-modal="true" aria-labelledby="notes-title">
    <h3 id="notes-title" class="og-title">Σημειώσεις Διπλωματικής</h3>

    <div id="notes-list" style="max-height:40vh;overflow:auto;margin-bottom:8px">Φορτώνει...</div>

    <textarea id="new-note" maxlength="300" placeholder="Γράψε σημείωση (έως 300 χαρακτήρες)..." 
      style="width:100%;height:70px;border:1px solid #e1e7f0;border-radius:10px;padding:8px;resize:vertical"></textarea>
    <div class="char-hint"><span id="note-count">0</span>/300</div>

    <div class="og-actions">
      <button type="button" class="btn btn-green" id="add-note-btn">Προσθήκη</button>
      <button type="button" class="btn btn-ghost" onclick="closeNotesModal()">Κλείσιμο</button>
    </div>
  </div>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
// Φόρτωση λίστας επιβλέποντα
function loadActiveTheses(){
  fetch('prof_grade.php?action=list_active&_=' + Date.now(), {cache:'no-store'})
    .then(r=>r.json())
    .then(rows=>{
      const tbody = document.querySelector('#supervisor-theses-table tbody');
      tbody.innerHTML = '';
      if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="small">Δεν βρέθηκαν ενεργές διπλωματικές.</td></tr>';
        return;
      }
      rows.forEach(row=>{
        const tr = document.createElement('tr');
        const studentFull = (row.student_name || row.student_surname)
          ? `${row.student_name||''} ${row.student_surname||''} ${row.student_number?`(ΑΜ: ${row.student_number})`:''}`.trim()
          : '—';
        const isUnderReview = (row.status || '').trim() === 'Υπό Εξέταση';
        const announceBtnId = `announce-${Number(row.thesis_id)}`;

        tr.innerHTML = `
        <td>${escapeHtml(row.title || '—')}</td>
        <td>${escapeHtml(studentFull)}</td>
        <td><span class="badge grades-pill">${escapeHtml(row.status || '—')}</span></td>
        <td>
            <button class="btn btn-primary ${isUnderReview ? 'under-locked' : ''}"
                    ${isUnderReview ? 'disabled' : ''}
                    data-under="${Number(row.thesis_id)}">Σε Υπό Εξέταση</button>
            <button class="btn btn-warning" id="${announceBtnId}" data-announce="${Number(row.thesis_id)}" disabled>Ανακοίνωση</button>
        </td>
        <td>
            <div class="grade-box">
              <input type="number" min="0" max="10" step="0.1" class="grade-input" placeholder="π.χ. 8.5" />
              <input type="date" class="date-input" />
              <button class="btn btn-green" data-save="${Number(row.thesis_id)}">Αποθήκευση</button>
            </div>
        </td>
        <td>
            <button class="btn" data-listg="${Number(row.thesis_id)}">Βαθμοί Μελών</button>
            <button class="btn" data-notes="${Number(row.thesis_id)}">Σημειώσεις</button>
        </td>
        `;

        tbody.appendChild(tr);

        
        fetch('prof_grade.php?action=announce_ready&thesis_id=' + Number(row.thesis_id) + '&_=' + Date.now(), {cache:'no-store'})
          .then(r=>r.json())
          .then(resp=>{
            const btn = document.getElementById(announceBtnId);
            if (!btn) return;
            if (resp && resp.ready) {
              btn.disabled = false;
              if (resp.announcements) {
                btn.dataset.existingAnn = resp.announcements;
              }
            } else {
              btn.disabled = true;
            }
          })
          .catch(()=>{  });
      });
    })
    .catch(()=>{
      const tbody = document.querySelector('#supervisor-theses-table tbody');
      tbody.innerHTML = '<tr><td colspan="6" class="small">Σφάλμα φόρτωσης.</td></tr>';
    });
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

document.addEventListener('click', function(ev){
  // αλλαγή σε Υπό Εξέταση 
  const btnUnder = ev.target.closest('[data-under]');
  if (btnUnder) {
    const thesisId = btnUnder.getAttribute('data-under');
    btnUnder.disabled = true;
    fetch('prof_grade.php?action=to_under_review', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'thesis_id=' + encodeURIComponent(thesisId)
    })
    .then(r=>r.json())
    .then(resp=>{
      if (resp.status === 'success') {
        alert('Η κατάσταση έγινε "Υπό Εξέταση".');
        loadActiveTheses();
      } else {
        alert(resp.message || 'Αποτυχία αλλαγής κατάστασης.');
        btnUnder.disabled = false;
      }
    })
    .catch(()=>{
      alert('Σφάλμα δικτύου.');
      btnUnder.disabled = false;
    });
  }

  // αποθήκευση βαθμού
  const btnSave = ev.target.closest('[data-save]');
  if (btnSave) {
    const thesisId = btnSave.getAttribute('data-save');
    const inputGrade = btnSave.parentNode.querySelector('.grade-input');
    const inputDate  = btnSave.parentNode.querySelector('.date-input');
    const grade = inputGrade ? inputGrade.value.trim() : '';
    const examDate = inputDate ? inputDate.value.trim() : '';

    if (grade === '' || isNaN(Number(grade))) {
        alert('Δώστε έγκυρο αριθμητικό βαθμό (0–10).');
        return;
    }
    btnSave.disabled = true;
    fetch('prof_grade.php?action=save_grade', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'thesis_id=' + encodeURIComponent(thesisId) +
            '&grade=' + encodeURIComponent(grade) +
            '&exam_date=' + encodeURIComponent(examDate)
    })
    .then(r=>r.json())
    .then(resp=>{
        if (resp.status === 'success') {
          alert('Ο βαθμός/ημερομηνία αποθηκεύτηκαν.');
        } else {
          alert(resp.message || 'Αποτυχία αποθήκευσης.');
        }
        btnSave.disabled = false;
    })
    .catch(()=>{
        alert('Σφάλμα δικτύου.');
        btnSave.disabled = false;
    });
  }

  // προβολή βαθμών άλλων μελών 
  const btnListG = ev.target.closest('[data-listg]');
  if (btnListG) {
    const thesisId = btnListG.getAttribute('data-listg');
    const tbody = document.getElementById('og-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="og-empty">Φορτώνει...</td></tr>';

    fetch('prof_grade.php?action=list_other_grades&thesis_id=' + encodeURIComponent(thesisId) + '&_=' + Date.now(), {cache:'no-store'})
      .then(r=>r.json())
      .then(rows=>{
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!rows || !rows.length) {
          tbody.innerHTML = '<tr><td colspan="3" class="og-empty">Δεν υπάρχουν ακόμη βαθμοί από τα άλλα μέλη.</td></tr>';
        } else {
          rows.forEach(r=>{
            const tr = document.createElement('tr');
            const name = r.professor_name || '—';
            const grade = (r.grade != null ? r.grade : '—');
            const exDate = r.exam_date || '—';
            tr.innerHTML = `
              <td>${escapeHtml(name)}</td>
              <td>${escapeHtml(grade)}</td>
              <td>${escapeHtml(exDate)}</td>
            `;
            tbody.appendChild(tr);
          });
        }
        openOtherGradesModal();
      })
      .catch(()=>{
        if (tbody) tbody.innerHTML = '<tr><td colspan="3" class="og-empty">Σφάλμα ανάκτησης βαθμών.</td></tr>';
        openOtherGradesModal();
      });
  }

  // modal ανακοίνωσης 
  const btnAnn = ev.target.closest('[data-announce]');
  if (btnAnn) {
    const thesisId = Number(btnAnn.getAttribute('data-announce'));
    const existing = btnAnn.dataset.existingAnn || '';
    openAnnounceModal(thesisId, existing);
  }

  // modal σημειώσεων 
  const btnNotes = ev.target.closest('[data-notes]');
  if (btnNotes) {
    const tid = Number(btnNotes.getAttribute('data-notes'));
    openNotesModal(tid);
  }
});

// Βαθμοί
function openOtherGradesModal(){
  const m = document.getElementById('otherGradesModal');
  if (!m) return;
  m.style.display = 'flex';
  m.setAttribute('aria-hidden','false');
}
function closeOtherGradesModal(){
  const m = document.getElementById('otherGradesModal');
  if (!m) return;
  m.style.display = 'none';
  m.setAttribute('aria-hidden','true');
}

// Ανακοίνωση 
function openAnnounceModal(thesisId, existingText){
  const m = document.getElementById('announceModal');
  const ta = document.getElementById('announce-text');
  const submitBtn = document.getElementById('announce-submit');

  if (!existingText || !existingText.trim()){
    fetch('prof_grade.php?action=announcement_template&thesis_id=' + thesisId + '&_=' + Date.now(), {cache:'no-store'})
      .then(r=>r.json())
      .then(d=>{ ta.value = (d && d.template) ? d.template : ''; })
      .catch(()=>{ ta.value = ''; });
  } else {
    ta.value = existingText;
  }

  submitBtn.onclick = function(){
    const txt = ta.value.trim();
    if (!txt) { alert('Συμπλήρωσε το κείμενο της ανακοίνωσης.'); return; }

    fetch('prof_grade.php?action=save_announcement', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'thesis_id=' + encodeURIComponent(thesisId) +
            '&announcements=' + encodeURIComponent(txt)
    })
    .then(r=>r.json())
    .then(resp=>{
      if (resp.status === 'success'){
        alert('Η ανακοίνωση αποθηκεύτηκε.');
        closeAnnounceModal();
      } else {
        alert(resp.message || 'Αποτυχία αποθήκευσης.');
      }
    })
    .catch(()=> alert('Σφάλμα δικτύου.'));
  };

  m.style.display = 'flex';
  m.setAttribute('aria-hidden','false');
}
function closeAnnounceModal(){
  const m = document.getElementById('announceModal');
  if (!m) return;
  m.style.display = 'none';
  m.setAttribute('aria-hidden','true');
}

// Modal Σημειώσεων
let notesThesisId = null;

function openNotesModal(thesisId){
  notesThesisId = thesisId;
  const m = document.getElementById('notesModal');
  const list = document.getElementById('notes-list');
  const ta = document.getElementById('new-note');
  const btn = document.getElementById('add-note-btn');

  list.innerHTML = 'Φορτώνει...';
  ta.value = '';
  document.getElementById('note-count').textContent = '0';

  // υπάρχουσες σημειώσεις
  fetch('prof_grade.php?action=list_notes&thesis_id='+encodeURIComponent(thesisId)+'&_=' + Date.now(), {cache:'no-store'})
    .then(r=>r.json())
    .then(rows=>{
      if (!rows || !rows.length){
        list.innerHTML = '<div class="og-empty">Δεν υπάρχουν σημειώσεις.</div>';
        return;
      }
      list.innerHTML = rows.map(r => `
        <div class="note-item">
          <div class="note-head"><strong>${escapeHtml(r.professor_name||'—')}</strong><span>${escapeHtml(r.created_at||'')}</span></div>
          <div>${escapeHtml(r.note_text||'')}</div>
        </div>
      `).join('');
    })
    .catch(()=>{ list.innerHTML = '<div class="og-empty" style="color:#b33">Σφάλμα φόρτωσης σημειώσεων.</div>'; });

  // submit νέας σημείωσης
  btn.onclick = function(){
    const txt = ta.value.trim();
    if (!txt){ alert('Γράψε μια σημείωση.'); return; }
    if (txt.length > 300){ alert('Μέχρι 300 χαρακτήρες.'); return; }

    btn.disabled = true;
    fetch('prof_grade.php?action=add_note', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'thesis_id='+encodeURIComponent(notesThesisId)+'&note_text='+encodeURIComponent(txt)
    })
    .then(r=>r.json())
    .then(resp=>{
      if (resp.status === 'success'){
        ta.value = '';
        document.getElementById('note-count').textContent = '0';
        
        return fetch('prof_grade.php?action=list_notes&thesis_id='+encodeURIComponent(notesThesisId)+'&_=' + Date.now());
      } else {
        throw new Error(resp.message || 'Αποτυχία');
      }
    })
    .then(r=>r.json())
    .then(rows=>{
      const list = document.getElementById('notes-list');
      list.innerHTML = (rows && rows.length) ? rows.map(r => `
        <div class="note-item">
          <div class="note-head"><strong>${escapeHtml(r.professor_name||'—')}</strong><span>${escapeHtml(r.created_at||'')}</span></div>
          <div>${escapeHtml(r.note_text||'')}</div>
        </div>
      `).join('') : '<div class="og-empty">Δεν υπάρχουν σημειώσεις.</div>';
    })
    .catch(e=>{
      alert(e.message || 'Σφάλμα.');
    })
    .finally(()=>{ btn.disabled = false; });
  };

  // counter
  ta.oninput = function(){
    document.getElementById('note-count').textContent = String(ta.value.length);
  };

  m.style.display = 'flex';
  m.setAttribute('aria-hidden','false');
}
function closeNotesModal(){
  const m = document.getElementById('notesModal');
  if (!m) return;
  m.style.display = 'none';
  m.setAttribute('aria-hidden','true');
}

// Φόρτωση λίστας μελών
function loadMemberTheses(){
  fetch('prof_grade.php?action=list_member_theses&_=' + Date.now(), {cache:'no-store'})
    .then(r=>r.json())
    .then(rows=>{
      const tbody = document.querySelector('#member-theses-table tbody');
      tbody.innerHTML = '';
      if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="small">Δεν βρέθηκαν διπλωματικές ως μέλος.</td></tr>';
        return;
      }
      rows.forEach(row=>{
        const tr = document.createElement('tr');
        const studentFull = (row.student_name || row.student_surname)
          ? `${row.student_name||''} ${row.student_surname||''} ${row.student_number?`(ΑΜ: ${row.student_number})`:''}`.trim()
          : '—';
        tr.innerHTML = `
          <td>${escapeHtml(row.title || '—')}</td>
          <td>${escapeHtml(studentFull)}</td>
          <td><span class="badge grades-pill">${escapeHtml(row.status || '—')}</span></td>
          <td>
            <div class="grade-box">
              <input type="number" min="0" max="10" step="0.1" class="grade-input" placeholder="π.χ. 8.5" />
              <input type="date" class="date-input" />
              <button class="btn btn-green" data-save="${Number(row.thesis_id)}">Αποθήκευση</button>
            </div>
          </td>
          <td>
            <button class="btn" data-listg="${Number(row.thesis_id)}">Βαθμοί Μελών</button>
            <button class="btn" data-notes="${Number(row.thesis_id)}">Σημειώσεις</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    })
    .catch(()=>{
      const tbody = document.querySelector('#member-theses-table tbody');
      tbody.innerHTML = '<tr><td colspan="5" class="small">Σφάλμα φόρτωσης.</td></tr>';
    });
}

// Αρχική φόρτωση
document.addEventListener('DOMContentLoaded', function () {
  loadActiveTheses();
  loadMemberTheses();
});
</script>

</body>
</html>
