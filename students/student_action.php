<?php
session_start(); 


$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $e->getMessage());
}

$message = "";
$messageType = "";

function redirect_with_notice(string $message, string $type = 'info', string $target = 'student_action.php') {
    $qs = http_build_query([
        'notice' => $message,
        'type'   => $type,
    ], '', '&', PHP_QUERY_RFC3986);

    
    header("Location: {$target}?{$qs}", true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $thesisId = (int)($_POST['thesis_id'] ?? 0);

    if ($action === 'submit_draft') {
        try {
            if ($thesisId <= 0) {
                throw new RuntimeException("Λείπει/λάθος thesis_id.");
            }

            
            $student_id = 0;
            if (!empty($_SESSION['email'])) {
                $st = $pdo->prepare("
                    SELECT s.student_id
                    FROM students s
                    JOIN users u ON u.user_id = s.student_id
                    WHERE u.email = ?
                    LIMIT 1
                ");
                $st->execute([$_SESSION['email']]);
                $student_id = (int)$st->fetchColumn();
            }
            if ($student_id <= 0) {
                $st = $pdo->prepare("SELECT student_id FROM theses WHERE thesis_id = ? LIMIT 1");
                $st->execute([$thesisId]);
                $student_id = (int)$st->fetchColumn();
                if ($student_id <= 0) {
                    throw new RuntimeException("Δεν βρέθηκε student_id (session/thesis).");
                }
            }

            $didSomething = false;

            // Ανέβασμα PDF 
            if (!empty($_FILES['draft_file']['name'])) {
                $tmpPath  = $_FILES['draft_file']['tmp_name'];
                $origName = $_FILES['draft_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new RuntimeException("Επιτρέπεται μόνο αρχείο PDF.");
                }

                $uploadDirFs = __DIR__ . '/uploads/drafts/';
                if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true)) {
                    throw new RuntimeException("Αδυναμία δημιουργίας φακέλου uploads/drafts.");
                }

                $finalName = sprintf("draft_t%ds%d_%s.pdf", $thesisId, $student_id, date('Ymd_His'));
                $destFs    = $uploadDirFs . $finalName;

                if (!move_uploaded_file($tmpPath, $destFs)) {
                    throw new RuntimeException("Αποτυχία αποθήκευσης αρχείου.");
                }

                // Εισαγωγή στον πίνακα attachments
                $ins = $pdo->prepare("
                    INSERT INTO attachments (thesis_id, student_id, filename, uploaded_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $ins->execute([$thesisId, $student_id, $finalName]);

                $didSomething = true;
            }

            // Σύνδεσμος πρόχειρου 
            $draftLink = trim($_POST['draft_link'] ?? '');
            if ($draftLink !== '') {
                if (!filter_var($draftLink, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException("Μη έγκυρος σύνδεσμος.");
                }

                // Διάβασμα από υπάρχοντα links
                $stmt = $pdo->prepare("SELECT links FROM theses WHERE thesis_id = ?");
                $stmt->execute([$thesisId]);
                $linksRaw = (string)$stmt->fetchColumn();
                $linksArr = json_decode($linksRaw ?: '[]', true);
                if (!is_array($linksArr)) { $linksArr = []; }

                // Αφαίρεση διπλότυπων
                if (!in_array($draftLink, $linksArr, true)) {
                    $linksArr[] = $draftLink;
                }

                // Αποθήκευση πίσω ως JSON
                $stmt = $pdo->prepare("UPDATE theses SET links = ? WHERE thesis_id = ?");
                $stmt->execute([ json_encode($linksArr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $thesisId ]);

                $didSomething = true;
            }

            if (!$didSomething) {
                throw new RuntimeException("Δεν επιλέχθηκε ούτε PDF ούτε σύνδεσμος.");
            }

            // Προαιρετικά status
            $pdo->prepare("
                UPDATE theses 
                SET status = 'Υπο Εξέταση'
                WHERE thesis_id = ? AND (status IS NULL OR status <> 'Περατωμένη')
            ")->execute([$thesisId]);

            // PRG redirect με μήνυμα επιτυχίας
            redirect_with_notice("✅ Το πρόχειρο καταχωρήθηκε (αρχείο/σύνδεσμος).", "success");

        } catch (Throwable $e) {
            error_log("[submit_draft] " . $e->getMessage());
            redirect_with_notice("❌ " . $e->getMessage(), "error");
        }
    }

    if ($action == 'submit_exam') {
        $examDate = $_POST['exam_date'] ?? null;
        $examTime = $_POST['exam_time'] ?? null;
        $examMode = $_POST['exam_type'] ?? null;
        $examRoom = $_POST['exam_room'] ?? null;
        $examLink = $_POST['exam_link'] ?? null;

        if ($examMode === 'διά ζώσης') {
            $examLink = null;
        } elseif ($examMode === 'διαδικτυακά') {
            $examRoom = null;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO examinations (thesis_id, exam_date, exam_time, exam_mode, room, link) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$thesisId, $examDate, $examTime, $examMode, $examRoom, $examLink]);

            // PRG redirect με μήνυμα επιτυχίας
            redirect_with_notice("✅ Η εξέταση καταχωρήθηκε επιτυχώς!", "success");

        } catch (PDOException $e) {
            redirect_with_notice("❌ Σφάλμα κατά την αποθήκευση εξέτασης: " . $e->getMessage(), "error");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ανάρτηση Προχείρου</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --brand:#0b4ba6;
  --brand-2:#003366;
  --ink:#1b2a3a;
  --muted:#617087;
  --card:#ffffff;
  --silver:#e9eef6;
  --ring:#bcd3ff;
  --radius:14px;
  --elev:0 14px 30px rgba(15,27,45,.10);
}

/* Base */
*{box-sizing:border-box; transition: background-color .25s, color .25s, border-color .25s, box-shadow .25s;}
html,body{height:100%}
body{
  font-family:'Inter', system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
  margin:0; padding:0; min-height:100vh; display:flex; flex-direction:column;
  background: linear-gradient(90deg, #edf1f7, #c9d6ff);
  color:#333;
}
body::before{content:""; position:fixed; inset:0; background-color: hsla(211,32%,51%,.28); z-index:-1}

/* Header */
.site-header{
  width:100%; display:flex; justify-content:space-between; align-items:center;
  padding:20px 40px; height:120px; margin-bottom:60px;
  background-color: rgba(0, 51, 102, 0.92); color:#fff; box-shadow: 0 8px 8px -4px rgba(0,0,0,.2);
  border-bottom-left-radius:14px; border-bottom-right-radius:14px;
  font-family:'Segoe UI',sans-serif;
}
.site-header .left{display:flex; align-items:center; gap:10px}
.site-header .logo{width:95px; height:80px; object-fit:contain}
.system-name{font-size:20px; font-weight:600; white-space:nowrap}
.site-header .right{display:flex; align-items:center; gap:20px}
.site-header .right nav a{color:#fff; text-decoration:none; margin-left:15px}
.site-header .user-info a{font-weight:500}

/* Top title + steps */
.container{ max-width:1200px; margin:0 auto; padding:0 20px; }
.thesis-title{ color: var(--brand-2); }
.subtext{ color: var(--muted); margin-top:6px; }

.steps{
  display:flex; gap:12px; align-items:center; margin:18px 0 8px;
  flex-wrap:wrap;
}
.step{
  display:flex; align-items:center; gap:10px; background:#fff; border:1px solid var(--silver);
  border-radius:999px; padding:8px 12px; box-shadow: 0 10px 18px rgba(15,27,45,.08);
  font-weight:600; color:var(--brand-2);
}
.step .badge{
  width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
  background: linear-gradient(180deg,#eaf2ff,#dbe8ff);
  border:1px solid #c7d7f7; box-shadow: inset 0 1px 0 #fff;
  font-size:.9rem;
}

/* Forms layout */
.form-wrapper{
  display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap:26px; align-items:stretch; justify-content:center; margin:18px 24px 10px;
}
.form-container{
  background:rgba(255,255,255,.98); border:1px solid #e6ecf7; border-radius:var(--radius);
  box-shadow: var(--elev); padding:22px 20px;
  animation: fadeIn .55s ease-out both;
  position: relative;
}
.form-container h2{
  margin:0 0 14px; color:var(--brand-2); font-size:1.15rem; display:flex; align-items:center; gap:8px;
}
.form-container h2 .emoji{ font-size:1.2rem }

@keyframes fadeIn{from{opacity:0; transform:translateY(12px)} to{opacity:1; transform:translateY(0)}}

/* Fields */
.form-group{ margin-bottom:14px; }
.form-group label{ display:flex; align-items:center; gap:8px; font-weight:700; color: var(--ink); margin-bottom:6px; }
.help{ font-size:.86rem; color:var(--muted); margin-top:4px; }

.form-group input,
.form-group select{
  width:100%; padding:12px 12px; font-size:14px;
  border:1px solid #dbe3f2; border-radius:10px; background:#fff;
  outline:none;
}
.form-group input:focus,
.form-group select:focus{
  border-color:#7aa6ff; box-shadow: 0 0 0 4px var(--ring);
}

/* File input */
input[type="file"]{
  padding:10px; border:1px dashed #c8d6f5; background: #fbfdff; border-radius: 10px;
}
input[type="file"]::file-selector-button{
  border:none; border-radius:8px; padding:8px 12px; margin-right:10px; cursor:pointer;
  background: linear-gradient(180deg, #e9f0ff, #d7e3ff); color:#1a3a74; font-weight:700;
  box-shadow: inset 0 1px 0 #fff, 0 6px 12px rgba(11,75,166,.12);
}
input[type="file"]::file-selector-button:hover{ filter:brightness(1.03) }

/* Buttons */
.btn-submit, .btn-back{
  display:inline-block; width:auto; cursor:pointer; user-select:none;
  background: linear-gradient(180deg,#e9f0ff,#d7e3ff); color:#163a74;
  border:1px solid #c7d7f7; border-radius:12px; padding:12px 22px;
  text-decoration:none; font-weight:700; box-shadow: 0 10px 20px rgba(11,75,166,.12), inset 0 1px 0 #fff;
}
.btn-submit:hover, .btn-back:hover{ transform:translateY(-1px); box-shadow: 0 14px 24px rgba(11,75,166,.18) }
.btn-submit:active, .btn-back:active{ transform:translateY(0) }

.form-group.button-center{ display:flex; justify-content:center; margin-top:18px }

/* Conditional sections */
#physical_details, #online_details{
  border-left:4px solid #d7e3ff; background:#f8fbff; padding:10px; border-radius:10px;
}

/* Back row */
.form-wrapper.btn-back-wrapper{ display:flex; justify-content:center; margin: 8px 24px 70px }

/* Footer */
footer{
  margin-top:auto; width:100%; background-color: rgba(0, 51, 102, 0.92);
  color:#fff; text-align:center; padding:30px;
}

/* Small responsive tweak */
@media (max-width:560px){
  .steps{ justify-content:center }
}

#exam_time {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 5px;
  font-size: 14px;
}

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
      <a href="student_home.php">Αρχική</a>
      <a href="view_student.php">Η Διπλωματική Μου</a>
    </nav>
    <span class="user-info"><a href="loginn.php" style="color:#ccc">Έξοδος</a></span>
  </div>
</header>

<div class="container">
  <h1 class="mb-0 fs-4 fw-semibold thesis-title">📄 Σύστημα Καταχώρησης Εξέτασης</h1>
  <p class="subtext">Συμπλήρωσε τα στοιχεία για το πρόχειρο και την εξέταση της διπλωματικής.</p>

  <div class="steps" aria-label="Βήματα διαδικασίας">
    <div class="step"><span class="badge">1</span> Ανάρτηση Προχείρου</div>
    <div class="step"><span class="badge">2</span> Πληροφορίες Εξέτασης</div>
  </div>
</div>

<div class="form-wrapper">
  <!-- Ανάρτηση Προχείρου -->
  <div class="form-container">
    <h2><span class="emoji">📝</span>Ανάρτηση Προχείρου</h2>
    <form id="form1" method="POST" action="student_action.php" enctype="multipart/form-data">
      <div class="form-group">
        <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
        <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
        <div class="help">Το ID της διπλωματικής σου.</div>
      </div>

      <div class="form-group">
        <label for="draft_file">Ανέβασμα Προχείρου (PDF):</label>
        <input type="file" id="draft_file" name="draft_file" accept="application/pdf,.pdf" required>
        <div class="help">Επιτρέπεται μόνο PDF.</div>
      </div>

      <div class="form-group">
        <label for="draft_link">Σύνδεσμος Υλικού (προαιρετικά):</label>
        <input type="url" id="draft_link" name="draft_link" placeholder="π.χ. https://example.com">
        <div class="help">Google Drive, GitHub, κ.λπ. (αν υπάρχει).</div>
      </div>

      <div class="form-group button-center">
        <button type="submit" class="btn-submit" name="action" value="submit_draft">Υποβολή</button>
      </div>
    </form>
  </div>

  <!-- Πληροφορίες Εξέτασης -->
  <div class="form-container">
    <h2><span class="emoji">🎓</span>Πληροφορίες Εξέτασης</h2>
    <form method="POST" action="student_action.php">
      <input type="hidden" id="hidden_thesis_id" name="thesis_id">

      <div class="form-group">
        <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
        <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
        <div class="help">Το ίδιο ID με το παραπάνω.</div>
      </div>

      <div class="form-group">
        <label for="exam_date">Ημερομηνία Εξέτασης:</label>
        <input type="date" id="exam_date" name="exam_date" required>
      </div>

      <div class="form-group">
        <label for="exam_time">Ώρα Εξέτασης (ανά 30′):</label>
        <!-- Βασικός περιορισμός 30' + λίστα επιλογών -->
        <input type="time" id="exam_time" name="exam_time" required step="1800" list="halfHourSlots">
        <div class="help">Επιλέξτε ώρα σε βήμα μισής ώρας (π.χ. 09:00, 09:30, 10:00 ...).</div>
      </div>

      <div class="form-group">
        <label for="exam_type">Τρόπος Εξέτασης:</label>
        <select id="exam_type" name="exam_type" onchange="toggleExamDetails()" required>
          <option value="">-- Επιλογή --</option>
          <option value="διά ζώσης">Δια ζώσης</option>
          <option value="διαδικτυακά">Διαδικτυακά</option>
        </select>
      </div>

      <div class="form-group" id="physical_details" style="display:none;">
        <label for="exam_room">Αίθουσα Εξέτασης:</label>
        <input type="text" id="exam_room" name="exam_room" placeholder="π.χ. Αμφιθέατρο 2">
      </div>

      <div class="form-group" id="online_details" style="display:none;">
        <label for="exam_link">Σύνδεσμος Παρουσίασης:</label>
        <input type="url" id="exam_link" name="exam_link" placeholder="π.χ. https://zoom.us/...">
      </div>

      <div class="form-group button-center">
        <button type="submit" class="btn-submit" name="action" value="submit_exam">Υποβολή</button>
      </div>
    </form>
  </div>
</div>


<datalist id="halfHourSlots"></datalist>

<div class="form-wrapper btn-back-wrapper">
  <a href="student_home.php" class="btn-back">Επιστροφή στην Αρχική Σελίδα</a>
</div>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
function showNotification(message, type) {
  var notification = document.getElementById('notification');
  if(!notification){ 
    alert(message);
    return; 
  }
  notification.textContent = message; 
  notification.className = type;
  notification.style.display = 'block';
  setTimeout(() => { notification.style.display = 'none'; }, 4000);
}

function toggleExamDetails() {
  var type = document.getElementById('exam_type').value;
  document.getElementById('physical_details').style.display = (type === 'διά ζώσης') ? 'block' : 'none';
  document.getElementById('online_details').style.display  = (type === 'διαδικτυακά') ? 'block' : 'none';
}


document.getElementById('thesis_id').addEventListener('input', function () {
  var hid = document.getElementById('hidden_thesis_id');
  if(hid){ hid.value = this.value; }
});

// === HALF-HOUR SLOTS ===

(function fillHalfHourDatalist(){
  var dl = document.getElementById('halfHourSlots');
  if(!dl) return;
  for(var h=0; h<24; h++){
    for(var m=0; m<60; m+=30){
      var hh = String(h).padStart(2,'0');
      var mm = String(m).padStart(2,'0');
      var opt = document.createElement('option');
      opt.value = hh + ':' + mm;
      dl.appendChild(opt);
    }
  }
})();


(function enforceHalfHour(){
  var t = document.getElementById('exam_time');
  if(!t) return;
  t.addEventListener('change', function(){
    if(!this.value) return;
    var parts = this.value.split(':');
    if(parts.length < 2) return;
    var h = parseInt(parts[0],10) || 0;
    var m = parseInt(parts[1],10) || 0;

    // Στρογγυλοποίηση στο κοντινότερο μισάωρο
    var snappedM = (m < 15) ? 0 : (m < 45 ? 30 : 0);
    if (m >= 45) { h = (h + 1) % 24; }

    var out = String(h).padStart(2,'0') + ':' + (snappedM === 0 ? '00' : '30');
    if (out !== this.value) { this.value = out; }
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  const timeInput = document.getElementById('exam_time');
  if (!timeInput) return;


  const select = document.createElement('select');
  select.id = 'exam_time';
  select.name = 'exam_time';
  select.required = true;
  select.className = timeInput.className || '';

  
  const prev = (timeInput.value || '').slice(0,5);

  // Γέμισμα με slots 
  function toMinutes(h, m) { return h*60 + m; }
  for (let t = toMinutes(9,0); t <= toMinutes(15,0); t += 30) {
    const h = String(Math.floor(t/60)).padStart(2,'0');
    const m = String(t % 60).padStart(2,'0');
    const v = `${h}:${m}`;
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v; 
    if (v === prev) opt.selected = true;
    select.appendChild(opt);
  }

  
  timeInput.replaceWith(select);
});


(function () {
  const params = new URLSearchParams(window.location.search);
  const notice = params.get('notice');
  const type   = params.get('type') || 'info';
  if (notice) {
    showNotification(notice, type);
    // Καθαρισμός των παραμέτρων από το URL χωρίς reload
    params.delete('notice'); params.delete('type');
    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);
  }
})();
</script>

</body>
</html>
