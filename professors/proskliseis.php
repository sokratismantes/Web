<?php
session_start();


if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}


$notifications = [];
try {
    $dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
    $dbusername = "root";
    $dbpassword = "";
    $pdo = new PDO($dsn, $dbusername, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->prepare("
        SELECT p.professor_id
        FROM professors p
        JOIN users u ON u.user_id = p.professor_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['email']]);
    $professorId = (int) $stmt->fetchColumn();
    if ($professorId > 0) {
        $_SESSION['professor_id'] = $professorId;
    }

    /* μεταβολές status ειδοποιήσεων */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pn_action'])) {
        header('Content-Type: application/json; charset=utf-8');
        $notifId = (int)($_POST['notification_id'] ?? 0);

        if ($professorId <= 0 || $notifId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Μη έγκυρα στοιχεία.']);
            exit;
        }

        if ($_POST['pn_action'] === 'accept') {
            try {
                $stmt = $pdo->prepare("
                    UPDATE professors_notifications
                    SET status = 'Accepted', responded_at = NOW()
                    WHERE notification_id = :nid AND professor_id = :pid
                ");
                $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);

                $stmt = $pdo->prepare("
                    SELECT thesis_id
                    FROM professors_notifications
                    WHERE notification_id = :nid AND professor_id = :pid
                    LIMIT 1
                ");
                $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);
                $thesisId = (int)$stmt->fetchColumn();

                if ($thesisId > 0) {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("SELECT supervisor_id FROM theses WHERE thesis_id = :tid LIMIT 1");
                    $stmt->execute([':tid' => $thesisId]);
                    $supervisorId = (int)$stmt->fetchColumn();

                    $stmt = $pdo->prepare("
                        SELECT member1_id, member2_id
                        FROM committees
                        WHERE thesis_id = :tid
                        LIMIT 1
                    ");
                    $stmt->execute([':tid' => $thesisId]);
                    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$committee) {
                        $stmt = $pdo->prepare("
                            INSERT INTO committees (thesis_id, supervisor_id, member1_id)
                            VALUES (:tid, :sup, :pid)
                        ");
                        $stmt->execute([
                            ':tid' => $thesisId,
                            ':sup' => $supervisorId ?: null,
                            ':pid' => $professorId
                        ]);
                    } else {
                        $m1 = (int)($committee['member1_id'] ?? 0);
                        $m2 = (int)($committee['member2_id'] ?? 0);

                        if ($m1 !== $professorId && $m2 !== $professorId) {
                            if ($m1 === 0 || $committee['member1_id'] === null) {
                                $stmt = $pdo->prepare("
                                    UPDATE committees
                                    SET member1_id = :pid
                                    WHERE thesis_id = :tid
                                    LIMIT 1
                                ");
                                $stmt->execute([':pid' => $professorId, ':tid' => $thesisId]);
                            } elseif ($m2 === 0 || $committee['member2_id'] === null) {
                                $stmt = $pdo->prepare("
                                    UPDATE committees
                                    SET member2_id = :pid
                                    WHERE thesis_id = :tid
                                    LIMIT 1
                                ");
                                $stmt->execute([':pid' => $professorId, ':tid' => $thesisId]);
                            }
                        }
                    }

                    $stmt = $pdo->prepare("
                        SELECT member1_id, member2_id
                        FROM committees
                        WHERE thesis_id = :tid
                        FOR UPDATE
                    ");
                    $stmt->execute([':tid' => $thesisId]);
                    $cnow = $stmt->fetch(PDO::FETCH_ASSOC);

                    $m1 = (int)($cnow['member1_id'] ?? 0);
                    $m2 = (int)($cnow['member2_id'] ?? 0);

                    if ($m1 > 0 && $m2 > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE theses
                            SET status = 'Ενεργη'
                            WHERE thesis_id = :tid
                              AND (status = 'Υπο Αναθεση' OR status = 'Υπό Ανάθεση')
                        ");
                        $stmt->execute([':tid' => $thesisId]);
                    }

                    $pdo->commit();
                }

                echo json_encode(['status' => 'success', 'new_status' => 'Accepted']);
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                echo json_encode(['status' => 'error', 'message' => 'Σφάλμα ενημέρωσης επιτροπής: '.$ex->getMessage()]);
            }
            exit;
        }

        if ($_POST['pn_action'] === 'decline') {
            $stmt = $pdo->prepare("
                UPDATE professors_notifications
                SET status = 'Rejected'
                WHERE notification_id = :nid AND professor_id = :pid
            ");
            $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);
            echo json_encode(['status' => 'success', 'new_status' => 'Rejected']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Άγνωστη ενέργεια.']);
        exit;
    }

    /* Ανάκτηση ειδοποιήσεων */
    if ($professorId > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                pn.notification_id,
                s.name       AS student_name,
                s.surname    AS student_surname,
                s.student_number,
                th.title     AS thesis_title,
                pn.sent_at,
                pn.status
            FROM professors_notifications pn
            LEFT JOIN students  s  ON s.student_id  = pn.student_id
            LEFT JOIN theses    th ON th.thesis_id  = pn.thesis_id
            WHERE pn.professor_id = :pid
              AND (pn.status IS NULL OR pn.status <> 'Rejected')
            ORDER BY
              COALESCE(
                STR_TO_DATE(pn.sent_at, '%Y-%m-%d %H:%i:%s'),
                STR_TO_DATE(pn.sent_at, '%d/%m/%Y %H:%i:%s'),
                FROM_UNIXTIME(0)
              ) DESC,
              pn.notification_id DESC
        ");
        $stmt->execute([':pid' => $professorId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Προσκλήσεις</title>
<style>
:root{
  --bg-grad: linear-gradient(135deg,#eef2f9 0%,#e6ecf7 60%,#dbe7ff 100%);
  --panel-bg: #ffffff;
  --panel-br: #e6ecf7;
  --primary: #0b2e59;
  --primary-600:#0d4a8c;
  --muted:#5b6b84;
  --blue-50:#eef2ff;
  --blue-200:#cfe0ff;
  --blue-300:#b9ccf3;
  --blue-700:#0b2e59;
  --ok:#1e874b; --warn:#a56800; --err:#b02a37;
}

/* Base */
@keyframes fadeSlideIn{from{opacity:0;transform:translateY(12px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes rowIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

html,body{margin:0;padding:0}
body{
  font-family:'Inter', system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
  margin:0; padding:0; min-height:100vh; display:flex; flex-direction:column;
  background: linear-gradient(90deg, #edf1f7, #c9d6ff);
  color:#333;
}
body::before{content:""; position:fixed; inset:0; background-color: hsla(211,32%,51%,.28); z-index:-1}

/* Header */
.site-header{
  display:flex;justify-content:space-between;align-items:center;
  padding:16px 32px;background-color:rgba(0,51,102,.92);color:#fff;
  box-shadow:0 8px 8px -4px rgba(0,0,0,.25);
  height:84px;border-bottom-left-radius:14px;border-bottom-right-radius:14px;
  font-family:'Segoe UI',sans-serif;
}
.site-header .left{display:flex;align-items:center;gap:12px}
.site-header .logo{width:80px;height:64px;object-fit:contain}
.system-name{font-size:18px;font-weight:700;letter-spacing:.2px}
.site-header .right{display:flex;align-items:center;gap:18px}
.site-header .right nav a{color:#fff;text-decoration:none;margin-right:12px;opacity:.95}
.site-header .right nav a:hover{opacity:1;text-decoration:underline}
.user-info a{color:#d6e2ff}

/* Container */
.container{
  margin: 40px auto;
  padding: 20px 22px 28px;
  max-width: 1200px;
  background: var(--panel-bg);
  border: 1px solid var(--panel-br);
  border-radius: 14px;
  box-shadow: 0 18px 40px rgba(15,27,45,.12);
  animation: fadeSlideIn .55s ease-out both;
}
h1{
  margin: 4px 0 8px;
  font-size: 1.6rem;
  color: var(--primary);
  text-transform: none;
  letter-spacing: .2px;
}
.section-title{
  margin: 22px 0 6px;
  font-size: 1.05rem;
  color:#143b72;
  font-weight: 700;
  letter-spacing:.3px;
  opacity:.85
}

/* Table wrapper */
.table-toolbar{
  display:flex;justify-content:space-between;align-items:center;
  gap:12px;margin:10px auto 8px;max-width:95%;
}
.legend{display:flex;gap:10px;flex-wrap:wrap;font-size:.85rem;color:#3b4a63}
.legend .dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
.legend .ok .dot{background:var(--ok)}
.legend .warn .dot{background:var(--warn)}
.legend .info .dot{background:#3760c9}

.table-wrapper{
  overflow: auto;
  margin: 0 auto;
  max-width: 95%;
  border:1px solid var(--panel-br);
  border-radius:12px;
  background:#fff;
}

/* Table */
table{width:100%;border-collapse:separate;border-spacing:0}
thead th{
  position: sticky; top: 0; z-index: 2;
  background: linear-gradient(180deg,#f6f8ff,#eaf0ff);
  color:#0b2e59; font-weight:800; font-size:.92rem;
  padding:12px 14px; text-align:left; border-bottom:1px solid var(--panel-br);
}
tbody td{
  padding:12px 14px; text-align:left; border-bottom:1px solid #edf1f7;
  font-size:.94rem; color:#18243a;
}
tbody tr:nth-child(odd){background:#fbfcff}
tbody tr:hover{background:#f2f6ff}

/* Rounded corners */
table thead th:first-child{border-top-left-radius:12px}
table thead th:last-child{border-top-right-radius:12px}
table tbody tr:last-child td:first-child{border-bottom-left-radius:12px}
table tbody tr:last-child td:last-child{border-bottom-right-radius:12px}

/* Status */
.status-pill{
  display:inline-flex;align-items:center;gap:8px;
  padding:6px 10px;border-radius:999px;border:1px solid var(--blue-300);
  font-weight:800; font-size:.85rem; color:var(--blue-700);
  background: linear-gradient(180deg,#f7f9ff,#e8f0ff);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
}
.status-pill::before{content:"";width:8px;height:8px;border-radius:50%}
.status-accepted .status-pill::before{background:var(--ok)}
.status-pending  .status-pill::before{background:var(--warn)}
.status-rejected .status-pill::before{background:var(--err)}

/* Actions */
.actions{display:flex;gap:8px;justify-content:flex-start;align-items:center}
.btn-accept,.btn-decline{
  display:inline-flex;align-items:center;gap:8px;cursor:pointer;
  padding:8px 12px;border-radius:10px;border:1px solid #8ea9cc;
  background: linear-gradient(180deg,#e8eef6 0%,#cfdbee 55%,#b2c9ea 100%);
  color:#0b2e59;font-weight:800;font-size:.9rem;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.65), 0 6px 14px rgba(18,61,101,.15);
  transition: transform .12s ease, filter .15s ease, box-shadow .2s ease;
}
.btn-accept:hover,.btn-decline:hover{transform: translateY(-1px); filter:saturate(1.05)}
.btn-accept::before,.btn-decline::before{content:"";width:8px;height:8px;border-radius:50%}
.btn-accept::before{background:var(--ok)}
.btn-decline::before{background:var(--err)}
.btn-disabled{opacity:.55;cursor:default;filter:grayscale(.15);transform:none!important}

/* Primary link button */
.button{
  display:inline-flex;align-items:center;gap:8px;justify-content:center;
  padding:10px 16px;margin-top:16px;border-radius:12px;border:1px solid #8ea9cc;
  background: linear-gradient(180deg,#e8eef6 0%,#cfdbee 55%,#b2c9ea 100%);
  color:#0b2e59;text-decoration:none;font-weight:800;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.65), 0 8px 20px rgba(18,61,101,.18);
}
.button:hover{filter:saturate(1.05);transform:translateY(-1px)}

/* Empty state */
.empty-message{font-size:1rem;color:#6b7483;padding:18px 0}

/* Row appear */
.row-appear{animation: rowIn .35s ease-out both}

/* Responsive cards */
@media (max-width: 860px){
  .table-wrapper{max-width:100%}
  thead{display:none}
  table, tbody, tr, td{display:block;width:100%}
  tbody tr{background:#fff;border:1px solid var(--panel-br);border-radius:12px;margin:10px 0;overflow:hidden}
  tbody td{
    display:flex;justify-content:space-between;align-items:center;gap:14px;
    padding:10px 14px;border-bottom:1px dashed #eef2f7;
  }
  tbody tr td:last-child{border-bottom:none}
  tbody td::before{
    content: attr(data-th);
    font-weight:800;color:#506080;flex: 0 0 50%;
  }
  .actions{justify-content:flex-end}
}
footer{flex-shrink:0;width:100%;background-color:rgba(0,51,102,.92);color:#fff;text-align:center;padding:22px;margin-top:20px;height:80px}
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
    <span class="user-info"><a href="loginn.php">Έξοδος</a></span>
  </div>
</header>

<div class="container">
  <h1>Όλες οι Προσκλήσεις</h1>
  <h2 class="section-title">Ειδοποιήσεις για συμμετοχή ως Μέλος Επιτροπής Εξέτασης</h2>

  <div class="table-toolbar">
    <div class="legend">
      <span class="ok"><span class="dot"></span>Accepted</span>
      <span class="warn"><span class="dot"></span>Pending</span>
      <span class="info"><span class="dot"></span>Άλλο</span>
    </div>
  </div>

  <div class="table-wrapper">
    <table id="pn-table">
      <thead>
        <tr>
          <th>Φοιτητής</th>
          <th>Αριθμός Μητρώου</th>
          <th>Τίτλος Διπλωματικής</th>
          <th>Ημ/νία & Ώρα Αποστολής</th>
          <th>Κατάσταση</th>
          <th>Ενέργειες</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($notifications)): ?>
          <tr>
            <td colspan="6" class="empty-message">Δεν υπάρχουν ειδοποιήσεις.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($notifications as $n): 
            $full = trim(($n['student_name'] ?? '') . ' ' . ($n['student_surname'] ?? ''));
            $full = $full !== '' ? $full : '—';
            $status = $n['status'] ?? 'Pending';
            $statusClass = strtolower($status) === 'accepted' ? 'status-accepted' :
                           (strtolower($status) === 'pending' ? 'status-pending' : 'status-rejected');
          ?>
          <tr data-notif="<?= (int)$n['notification_id'] ?>">
            <td><?= htmlspecialchars($full) ?></td>
            <td><?= htmlspecialchars($n['student_number'] ?? '—') ?></td>
            <td><?= htmlspecialchars($n['thesis_title'] ?? '—') ?></td>
            <td><?= htmlspecialchars($n['sent_at'] ?? '—') ?></td>
            <td class="<?= $statusClass ?>" data-status><?= htmlspecialchars($status) ?></td>
            <td>
              <div class="actions">
                <?php if (strtolower($status) === 'pending'): ?>
                  <button type="button" class="btn-accept" data-accept>Αποδοχή</button>
                  <button type="button" class="btn-decline" data-decline>Απόρριψη</button>
                <?php else: ?>
                  <button type="button" class="btn-accept btn-disabled" disabled>Αποδοχή</button>
                  <button type="button" class="btn-decline btn-disabled" disabled>Απόρριψη</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  fetch('fetch_theses(proskliseis).php')
    .then(response => response.json())
    .then(data => {
      const tableBody = document.querySelector('#invitations-table tbody');
      if (!tableBody) return;
      tableBody.innerHTML = '';

      if (data.length > 0) {
        data.forEach(invitation => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${invitation.invitation_id}</td>
            <td>${invitation.title || 'Χωρίς τίτλο'}</td>
            <td>${invitation.invited_professor_id}</td>
            <td class="status-${invitation.status.toLowerCase()}">${invitation.status}</td>
            <td>${invitation.sent_at}</td>
            <td>${invitation.responded_at || 'Δεν υπάρχει'}</td>
            <td>${invitation.comments || 'Χωρίς σχόλια'}</td>
          `;
          row.style.cursor = 'pointer';
          row.addEventListener('click', () => {
            window.location.href = `provoliproskliseon.php?invitation_id=${invitation.invitation_id}`;
          });
          tableBody.appendChild(row);
        });
      } else {
        tableBody.innerHTML = `
          <tr>
            <td colspan="7" class="empty-message">Δεν υπάρχουν διαθέσιμες προσκλήσεις.</td>
          </tr>
        `;
      }
    })
    .catch(error => {
      console.error('Σφάλμα κατά την ανάκτηση δεδομένων:', error);
    });
});
</script>

<!-- Αποδοχή/Απόρριψη ειδοποιήσεων -->
<script>
(function(){
  const pnTable = document.getElementById('pn-table');
  if (!pnTable) return;

  pnTable.addEventListener('click', function(ev){
    const acceptBtn = ev.target.closest('[data-accept]');
    const declineBtn = ev.target.closest('[data-decline]');
    if (!acceptBtn && !declineBtn) return;

    const tr = ev.target.closest('tr[data-notif]');
    if (!tr) return;

    const notifId = tr.getAttribute('data-notif');
    const action = acceptBtn ? 'accept' : 'decline';

    if (acceptBtn) acceptBtn.disabled = true;
    if (declineBtn) declineBtn.disabled = true;

    fetch(location.href, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `pn_action=${encodeURIComponent(action)}&notification_id=${encodeURIComponent(notifId)}`
    })
    .then(r => r.json())
    .then(resp => {
      if (resp.status !== 'success') {
        alert(resp.message || 'Σφάλμα ενημέρωσης.');
        if (acceptBtn) acceptBtn.disabled = false;
        if (declineBtn) declineBtn.disabled = false;
        return;
      }

      if (resp.new_status === 'Accepted') {
        const statusTd = tr.querySelector('[data-status]');
        if (statusTd) {
          statusTd.textContent = 'Accepted';
          statusTd.classList.remove('status-pending', 'status-rejected');
          statusTd.classList.add('status-accepted');
          wrapStatusCells();
        }
        const a = tr.querySelector('[data-accept]');
        const d = tr.querySelector('[data-decline]');
        if (a) { a.classList.add('btn-disabled'); a.disabled = true; }
        if (d) { d.classList.add('btn-disabled'); d.disabled = true; }
      } else if (resp.new_status === 'Rejected') {
        tr.parentNode.removeChild(tr);
        const tbody = pnTable.querySelector('tbody');
        if (tbody && tbody.children.length === 0) {
          const emptyRow = document.createElement('tr');
          emptyRow.innerHTML = `<td colspan="6" class="empty-message">Δεν υπάρχουν ειδοποιήσεις.</td>`;
          tbody.appendChild(emptyRow);
        }
      }
    })
    .catch(err => {
      console.error(err);
      alert('Σφάλμα δικτύου.');
      if (acceptBtn) acceptBtn.disabled = false;
      if (declineBtn) declineBtn.disabled = false;
    });
  });
})();
</script>

<script>
function wrapStatusCells(){
  const t = document.getElementById('pn-table');
  if (!t) return;
  t.querySelectorAll('td[data-status]').forEach(td=>{
    const txt = (td.textContent || '').trim();
    td.innerHTML = `<span class="status-pill">${txt || '—'}</span>`;
  });
  
  t.querySelectorAll('td[data-status]').forEach(td=>{
    const val = (td.textContent || td.innerText || '').toLowerCase();
    td.classList.remove('status-accepted','status-pending','status-rejected');
    if (val.includes('accept')) td.classList.add('status-accepted');
    else if (val.includes('pend') || val.includes('αναμον')) td.classList.add('status-pending');
    else if (val.includes('reject')) td.classList.add('status-rejected');
  });
}
function addDataLabels(){
  const table = document.getElementById('pn-table');
  if (!table) return;
  const headers = Array.from(table.querySelectorAll('thead th')).map(th=>th.textContent.trim());
  table.querySelectorAll('tbody tr').forEach(tr=>{
    tr.querySelectorAll('td').forEach((td, idx)=>{
      td.setAttribute('data-th', headers[idx] || '');
    });
  });
}
document.addEventListener('DOMContentLoaded', function(){
  
  const rows = document.querySelectorAll('#pn-table tbody tr');
  rows.forEach((tr, i) => {
    tr.style.animationDelay = (i * 50) + 'ms';
    tr.classList.add('row-appear');
  });

  wrapStatusCells();
  addDataLabels();
  
  window.addEventListener('resize', addDataLabels);
});
</script>
</body>
</html>
