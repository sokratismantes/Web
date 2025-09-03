<?php
// cancelThesis.php
session_start();

// === Έλεγχος πρόσβασης ===
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// === Σύνδεση PDO ===
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$dbuser = "root";
$dbpass = "";
$pdo = new PDO($dsn, $dbuser, $dbpass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// === Βρες professor_id από το email login ===
$stmt = $pdo->prepare("
    SELECT p.professor_id, p.name, p.surname, p.department
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$prof = $stmt->fetch();
$professor_id = (int)($prof['professor_id'] ?? 0);
if ($professor_id <= 0) {
    die("Μη έγκυρη συνεδρία χρήστη.");
}

/* =========================
   Ακύρωση με AJAX (POST)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    header('Content-Type: application/json; charset=utf-8');

    $thesis_id = (int)($_POST['thesis_id'] ?? 0);
    $ga_number = trim($_POST['ga_number'] ?? '');
    $ga_year   = (int)($_POST['ga_year'] ?? 0);

    if ($thesis_id <= 0 || $ga_number === '' || $ga_year <= 0) {
        echo json_encode(['status'=>'error','message'=>'Συμπληρώστε σωστά Αρ. Γ.Σ. και Έτος.']);
        exit;
    }

    // 1) Έλεγχος ιδιοκτησίας και κανόνα 2 ετών: start_date → σήμερα
    $stmt = $pdo->prepare("
        SELECT thesis_id, supervisor_id, student_id, start_date,
               TIMESTAMPDIFF(YEAR, start_date, CURDATE()) AS years_diff
        FROM theses
        WHERE thesis_id = :tid
          AND supervisor_id = :pid
          AND student_id IS NOT NULL
          AND start_date IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([':tid'=>$thesis_id, ':pid'=>$professor_id]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['status'=>'error','message'=>'Δεν βρέθηκε κατάλληλη διπλωματική για ακύρωση.']);
        exit;
    }
    if ((int)$row['years_diff'] < 2) {
        echo json_encode(['status'=>'error','message'=>'Δεν έχουν συμπληρωθεί 2 έτη από την έναρξη. Η ακύρωση δεν επιτρέπεται.']);
        exit;
    }

    // 2) Ενημέρωση: student_id → NULL, reason + πεδία Γ.Σ.
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE theses
            SET student_id = NULL,
                cancellation_reason    = 'από Διδάσκοντα',
                cancellation_ga_number = :ga_num,
                cancellation_ga_year   = :ga_year
            WHERE thesis_id = :tid
              AND supervisor_id = :pid
            LIMIT 1
        ");
        $stmt->execute([
            ':ga_num'  => $ga_number,
            ':ga_year' => $ga_year,
            ':tid'     => $thesis_id,
            ':pid'     => $professor_id
        ]);

        $pdo->commit();
        echo json_encode(['status'=>'success','message'=>'Η ανάθεση ακυρώθηκε επιτυχώς.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status'=>'error','message'=>'Σφάλμα ακύρωσης: '.$e->getMessage()]);
    }
    exit;
}

/* ==============================================================
   Λίστα επιλέξιμων για ακύρωση (start_date → σήμερα, ≥ 2 έτη)
   ============================================================== */
$stmt = $pdo->prepare("
    SELECT 
        t.thesis_id,
        t.title,
        t.start_date,
        CONCAT(s.name,' ',s.surname) AS student_fullname,
        s.student_number,
        TIMESTAMPDIFF(YEAR, t.start_date, CURDATE()) AS years_diff
    FROM theses t
    LEFT JOIN students s ON s.student_id = t.student_id
    WHERE t.supervisor_id = :pid
      AND t.student_id IS NOT NULL
      AND t.start_date IS NOT NULL
      AND TIMESTAMPDIFF(YEAR, t.start_date, CURDATE()) >= 2
    ORDER BY t.thesis_id DESC
");
$stmt->execute([':pid'=>$professor_id]);
$rows = $stmt->fetchAll();

/* Μικρά KPIs για την κεφαλίδα */
$total = count($rows);
$avgYears = 0;
if ($total > 0) {
    $sum = 0;
    foreach ($rows as $r) { $sum += (int)($r['years_diff'] ?? 0); }
    $avgYears = $sum / $total;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Κατάργηση Ανάθεσης Διπλωματικών</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --radius:14px; --space:16px; --brand:#0b4ba6; --muted:#6b7a90;
  --bg-grad: linear-gradient(135deg, #eef2ff, #e0ecff);
  --shadow-1: 0 8px 20px rgba(0,0,0,.08);
  --shadow-2: 0 10px 24px rgba(0,0,0,.18);
}

html, body{
  height: 100%;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
        body {
            font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            color: #333;
            font-size: 0.96rem;
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-color: hsla(211, 32.3%, 51.4%, 0.35);
            z-index: -1;
        }
        
.container{
  width:min(1120px,92%); margin:24px auto; padding:24px;
  background:#fff; border-radius:var(--radius); box-shadow:var(--shadow-1);
  opacity:0; transform:translateY(8px); animation:pop-in .45s ease forwards; margin-top: 90px; margin-bottom: 20px;
}
@keyframes pop-in{ to{opacity:1; transform:none} }

h1{
  margin:0 0 6px; color:#0b4ba6; font-size:1.45rem; text-align:center; letter-spacing:.2px;
}
.sub{ text-align:center; color:var(--muted); margin-bottom:18px; }

/* Toolbar */
.toolbar{
  display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; align-items:center;
  background:#f6f9ff; border:1px solid #e6eefc; padding:12px; border-radius:12px; margin:12px 0 18px;
}
.search{
  display:flex; align-items:center; gap:8px; flex:1 1 320px;
  background:#fff; border:1px solid #dfe7f7; border-radius:10px; padding:8px 10px;
}
.search input{
  border:none; outline:none; width:100%; font-size:14.5px;
}
.kpis{
  display:flex; gap:10px; flex-wrap:wrap;
}
.kpi{
  background:#fff; border:1px solid #e7eefb; border-radius:12px; padding:10px 12px; min-width:140px;
  text-align:center; box-shadow:0 3px 10px rgba(0,0,0,.03);
}
.kpi .lbl{ font-size:12.5px; color:#62748b; }
.kpi .val{ font-size:18px; font-weight:800; color:#0b4ba6; margin-top:4px; }

/* Table */
.table-wrap{ overflow-x:auto; }
table{
  width:100%; border-collapse:collapse; border-radius:12px; overflow:hidden;
  box-shadow:0 4px 10px rgba(0,0,0,.05);
}
th,td{ padding:12px 14px; border:1px solid #edf1f7; text-align:left; vertical-align:middle; }
th{ background:#0b4ba6; color:#fff; font-weight:700; }
tr:nth-child(even){ background:#fbfdff; }

.badge-year{
  display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700;
  background:#ecf3ff; color:#0b4ba6; border:1px solid #dbe7ff;
}

/* Actions & Buttons */
.action{ display:inline-flex; gap:8px; align-items:center; }
.btn{
  border:none; padding:10px 14px; border-radius:12px; cursor:pointer; font-weight:700; letter-spacing:.2px;
  transition:transform .08s ease, box-shadow .2s ease, filter .2s ease;
}
.btn:active{ transform:translateY(1px); }
.btn-danger{ background:#dc3545; color:#fff; box-shadow:0 6px 14px rgba(220,53,69,.22); }
.btn-danger:hover{ filter:brightness(.95); }
.btn-secondary{ background:#6c757d; color:#fff; }
.btn-outline{
  background:#fff; color:#0b4ba6; border:1px solid #d7e4ff;
}
.btn-outline:hover{ background:#f4f8ff; }

/* Modal */
.modal{
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999;
  align-items:center; justify-content:center; padding:16px;
}
.modal-card{
  width:min(540px,96%); background:#fff; border-radius:16px; padding:20px 20px 16px;
  box-shadow:var(--shadow-2); animation:fadein .28s ease;
}
.modal-card h3{ margin:0 0 10px; color:#0b4ba6; }
.modal-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.input{
  width:100%; padding:10px 12px; border:1px solid #cfd9ee; border-radius:10px; font-size:14.5px;
}
.input:focus{ outline:none; border-color:#0b4ba6; box-shadow:0 0 0 3px #0b4ba61a; }
.modal-actions{ display:flex; justify-content:flex-end; gap:10px; margin-top:12px; }

@keyframes fadein{ from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:none} }

/* Toast */
#toast{
  position:fixed; right:18px; bottom:18px; z-index:1000; pointer-events:none;
}
.toast-item{
  background:#0b4ba6; color:#fff; padding:12px 14px; border-radius:12px; margin-top:10px; min-width:260px; max-width:360px;
  box-shadow:0 10px 20px rgba(0,0,0,.18); animation:toast-in .25s ease forwards;
}
.toast-item.error{ background:#c62828; }
@keyframes toast-in{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:none} }

/* Row appear / leave */
tbody tr{ animation:row-in .25s ease; }
@keyframes row-in{ from{opacity:.0; transform:translateY(4px)} to{opacity:1; transform:none} }
.row-leave{ animation:row-out .22s ease forwards; }
@keyframes row-out{ to{opacity:0; transform:translateY(4px)} }

/* Empty */
.empty{
  padding:16px; text-align:center; color:#667; background:#f0f5ff; border:1px dashed #cfe0f5; border-radius:12px;
}

/* Small helpers */
.sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }

.site-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background-color: rgba(0, 51, 102, 0.92); color: white; box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); font-family: 'Segoe UI', sans-serif; margin-bottom: 24px; height: 80px; position: relative; z-index: 10; border-bottom-left-radius: 14px; border-bottom-right-radius: 14px;}
.site-header .left { display: flex; align-items: center; gap: 10px;}
.site-header .logo { width:95px; height: 80px;}
.system-name { font-size: 20px; font-weight: 600;}
.site-header .right { display: flex; align-items: center; gap: 20px;}
.site-header .right nav a { color: white; text-decoration: none; margin-right: 15px;}
.site-header .user-info { font-weight: 500;}
footer { flex-shrink: 0; width: 100%; background-color: rgba(0, 51, 102, 0.92); color: white; text-align: center; padding: 30px; margin-top: 20px; height:80px;}

button, .add-button, .back-button {
    padding: 10px 18px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}
.back-button {
    background: linear-gradient(135deg, #0056b3, #004494);
    color: white;
    display: block;
    margin: 30px auto 0;
    margin-bottom: 40px;
    width: 260px;
    text-align: center;
    text-decoration: none;
}
.back-button:hover { opacity: 0.85; }

/* Μικρότερα πεδία για Γ.Σ. */
.input--short { width: 180px; }   /* Αριθμός Γ.Σ. */
.input--xs    { width: 120px; }   /* Έτος Γ.Σ.     */

/* Κάνε τις στήλες του modal να ταιριάζουν στο περιεχόμενο */
#cancelModal .modal-grid{
  grid-template-columns: auto auto;
  align-items: end;
}

/* Σε μικρές οθόνες, γυρνά τα σε full-width για ευχρηστία */
@media (max-width: 600px){
  .input--short, .input--xs { width: 100%; }
  #cancelModal .modal-grid { grid-template-columns: 1fr; }
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
            <a href="professor_home.php">Αρχική</a>
            <a href="listaDiplomatikon.php">Οι Διπλωματικές Μου</a>
            <a href="proskliseis.php">Προσκλήσεις</a>
            <a href="statistika.php">Στατιστικά</a>
        </nav>
        <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
    </div>
</header>

<div class="container">
  <h1>Κατάργηση Ανάθεσης Διπλωματικών</h1>
  <div class="sub">Ακύρωσε τις Ενεργές Διπλωματικές που επιβλέπεις, για τις οποίες έχουν παρέλθει 2 Έτη από την οριστική ανάθεσή τους.</div>

  <div class="toolbar">
    <div class="search" role="search">
      <span aria-hidden="true">🔎</span>
      <input id="filterInput" type="text" placeholder="Γρήγορη αναζήτηση σε τίτλο/φοιτητή...">
    </div>
    <div class="kpis">
      <div class="kpi">
        <div class="lbl">Διαθέσιμες για ακύρωση</div>
        <div class="val"><?php echo (int)$total; ?></div>
      </div>
      <div class="kpi">
        <div class="lbl">Μ.Ο. Παλαιότητας (έτη)</div>
        <div class="val"><?php echo number_format($avgYears, 1, ',', '.'); ?></div>
      </div>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">Δεν υπάρχουν διαθέσιμες διπλωματικές για ακύρωση βάσει του κανόνα.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table id="thesesTable">
      <caption class="sr-only">Πίνακας διπλωματικών επιλέξιμων για ακύρωση</caption>
      <thead>
        <tr>
          <th>Τίτλος</th>
          <th>Φοιτητής</th>
          <th>Έναρξη</th>
          <th>Περασμένα Έτη</th>
          <th>Ενέργεια</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr data-tid="<?php echo (int)$r['thesis_id']; ?>">
            <td data-col="title"><?php echo htmlspecialchars($r['title'] ?? '—'); ?></td>
            <td data-col="student"><?php
              $sf = trim(($r['student_fullname'] ?? '').' '.($r['student_number'] ? "(ΑΜ: ".$r['student_number'].")" : ""));
              echo $sf !== '' ? htmlspecialchars($sf) : '—';
            ?></td>
            <td><?php echo htmlspecialchars($r['start_date'] ?? '—'); ?></td>
            <td><span class="badge-year"><?php echo (int)($r['years_diff'] ?? 0); ?> έτη</span></td>
            <td>
              <div class="action">
                <button class="btn btn-danger" onclick="openCancelModal(<?php echo (int)$r['thesis_id']; ?>)" title="Ακύρωση ανάθεσης">Ακύρωση</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Toasts -->
<div id="toast" aria-live="polite" aria-atomic="true"></div>

<!-- Modal -->
<div id="cancelModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="m-title">
  <div class="modal-card" role="document">
    <h3 id="m-title">Επιβεβαίωση Ακύρωσης</h3>
    <div id="m-body">
      <p style="margin-top:0;color:#3c4a5f;">Για να ολοκληρωθεί η ακύρωση, καταχωρήστε τα στοιχεία της Γενικής Συνέλευσης.</p>
      <div class="modal-grid">
        <div>
          <label for="ga_number">Αριθμός Γ.Σ.</label>
          <input type="text"   id="ga_number" class="input input--short" placeholder="π.χ. 123/Θ">
        </div>
        <div>
          <label for="ga_year">Έτος Γ.Σ.</label>
          <input type="number" id="ga_year" class="input input--xs" placeholder="π.χ. 2024" min="1900" max="2100">
        </div>
      </div>
      <input type="hidden" id="cancel_thesis_id" value="">
    </div>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeCancelModal()">Άκυρο</button>
      <button id="confirmBtn" class="btn btn-danger" onclick="submitCancel()">
        <span class="btn-text">Ακύρωση Ανάθεσης</span>
      </button>
    </div>
  </div>
</div>

<a href="professor_home.php" class="back-button">Επιστροφή στον Πίνακα Ελέγχου</a>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
/* ---- Toast helper ---- */
function showToast(msg, type='success'){
  const wrap = document.getElementById('toast');
  const div = document.createElement('div');
  div.className = 'toast-item' + (type==='error' ? ' error' : '');
  div.textContent = msg;
  wrap.appendChild(div);
  setTimeout(()=>{ div.style.opacity='0'; div.style.transform='translateY(6px)'; }, 2600);
  setTimeout(()=>{ if (div.parentNode) div.parentNode.removeChild(div); }, 3000);
}

/* ---- Modal logic (focus/esc/outside) ---- */
const modal = document.getElementById('cancelModal');
const confirmBtn = document.getElementById('confirmBtn');

function openCancelModal(thesisId){
  if (!thesisId) return;
  document.getElementById('cancel_thesis_id').value = thesisId;
  document.getElementById('ga_number').value = '';
  document.getElementById('ga_year').value = '';
  modal.style.display = 'flex';
  setTimeout(()=>{ document.getElementById('ga_number').focus(); }, 50);
}
function closeCancelModal(){
  modal.style.display = 'none';
}
modal.addEventListener('click', (e) => {
  if (e.target === modal) closeCancelModal();
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape' && modal.style.display === 'flex') closeCancelModal();
  if (e.key === 'Enter'  && modal.style.display === 'flex') submitCancel();
});

/* ---- Spinner state for submit button ---- */
function setBusy(el, busy){
  if (!el) return;
  const txt = el.querySelector('.btn-text');
  if (busy){
    el.disabled = true;
    el.dataset.oldText = txt.textContent;
    txt.textContent = 'Επεξεργασία...';
  } else {
    el.disabled = false;
    if (el.dataset.oldText) txt.textContent = el.dataset.oldText;
  }
}

/* ---- Submit cancel ---- */
function submitCancel(){
  const tid = document.getElementById('cancel_thesis_id').value;
  const num = document.getElementById('ga_number').value.trim();
  const yr  = document.getElementById('ga_year').value.trim();

  if (!tid || num === '' || yr === '') {
    showToast('Συμπληρώστε Αρ. Γ.Σ. και Έτος.', 'error');
    return;
  }

  const fd = new URLSearchParams();
  fd.append('action','cancel');
  fd.append('thesis_id', tid);
  fd.append('ga_number', num);
  fd.append('ga_year', yr);

  setBusy(confirmBtn, true);
  fetch(location.href, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: fd.toString()
  })
  .then(r=>r.json())
  .then(d=>{
    if (d.status === 'success') {
      showToast('Η ανάθεση ακυρώθηκε επιτυχώς.');
      const tr = document.querySelector(`tr[data-tid="${CSS.escape(String(tid))}"]`);
      if (tr){
        tr.classList.add('row-leave');
        tr.addEventListener('animationend', ()=>{
          if (tr.parentNode) tr.parentNode.removeChild(tr);
          // Αν άδειασε, δείξε μήνυμα
          const tbody = document.querySelector('#thesesTable tbody');
          if (tbody && tbody.children.length === 0){
            const empty = document.createElement('div');
            empty.className = 'empty';
            empty.style.marginTop = '8px';
            empty.textContent = 'Δεν υπάρχουν πλέον διαθέσιμες διπλωματικές για ακύρωση.';
            document.querySelector('.container').appendChild(empty);
          }
        }, {once:true});
      }
      closeCancelModal();
    } else {
      showToast(d.message || 'Σφάλμα ακύρωσης.', 'error');
    }
  })
  .catch(()=> showToast('Σφάλμα δικτύου.', 'error'))
  .finally(()=> setBusy(confirmBtn, false));
}

/* ---- Client-side Filter ---- */
const filterInput = document.getElementById('filterInput');
if (filterInput){
  filterInput.addEventListener('input', ()=>{
    const q = filterInput.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#thesesTable tbody tr');
    rows.forEach(tr=>{
      const title = (tr.querySelector('[data-col="title"]')?.textContent || '').toLowerCase();
      const student = (tr.querySelector('[data-col="student"]')?.textContent || '').toLowerCase();
      const show = title.includes(q) || student.includes(q);
      tr.style.display = show ? '' : 'none';
    });
  });
}
</script>
</body>
</html>
