<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];


$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης στη βάση: " . $e->getMessage());
}


$stmt = $pdo->prepare("
    SELECT p.professor_id, p.name, p.surname, p.department
    FROM professors p
    JOIN users u ON u.user_id = p.professor_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['email']]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);

$professor_id = (int)($professor['professor_id'] ?? 0);
$professorFullName = trim(($professor['name'] ?? '') . ' ' . ($professor['surname'] ?? ''));
$professorDepartment = $professor['department'] ?? '';


// Ανακοινώσεις 
if (isset($_GET['action']) && $_GET['action'] === 'my_announcements') {
    header('Content-Type: application/json; charset=utf-8');
    if ($professor_id <= 0) { echo json_encode([]); exit; }

    $sql = "
        SELECT 
            t.thesis_id,
            t.title,
            e.announcements,
            e.exam_date,
            e.exam_time,
            e.exam_mode,
            e.room,
            e.link
        FROM theses t
        JOIN examinations e ON e.thesis_id = t.thesis_id
        WHERE t.supervisor_id = :pid
          AND e.announcements IS NOT NULL
          AND TRIM(e.announcements) <> ''
        ORDER BY COALESCE(e.exam_date, '1970-01-01') DESC, t.thesis_id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':pid' => $professor_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows ?: []);
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Έλεγχου</title>

    
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
        --radius-sm:10px;
        --radius-md:14px;
        --radius-lg:18px;
        --space-1:8px;
        --space-2:12px;
        --space-3:16px;
        --space-4:20px;
        --space-5:24px;
        --space-6:32px;
        --elev-1:0 4px 10px rgba(0,0,0,.12);
        --elev-2:0 8px 20px rgba(0,0,0,.18);
        --elev-soft:0 10px 24px rgba(10,30,60,.12);
        --brand:#0b4ba6;
        --brand-2:#0056b3;
        --muted:#556070;
        }
        
        * {
            transition: background-color 0.3s, color 0.3s;
            box-sizing: border-box;
        }
        .container { animation: fadein 0.5s ease-in; }
        @keyframes fadein {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        
        html, body {
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

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(245, 245, 245, 0.9);
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
            box-shadow: 0 8px 8px -4px rgba(0,0,0,0.15);
        }
        .header a {
            text-decoration: none;
            display: flex;
            align-items: center;
            color: inherit;
        }
        .header img {
            width: 40px; height: 40px; border-radius: 50%;
            margin-right: 10px; cursor: pointer; object-fit: cover;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .header span { font-size: 1.2rem; color: #0056b3; }

        /* Κουμπιά */
        .logout-button {
            background-color: #333; color: #fff;
            padding: 10px 20px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 1rem;
        }
        .logout-button:hover { background-color: #555; }

        .notifications-button,
        .form-notifications-button {
            background-color: #007bff; color: #fff;
            padding: 10px; border: none; border-radius: 50%;
            cursor: pointer; font-size: 1.2rem;
        }
        .notifications-button:hover,
        .form-notifications-button:hover { background-color: #0056b3; }

        .action-button {
            display: inline-block;
            padding: 10px 20px;
            border: 2px solid transparent;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            background-color: transparent;
        }
        .action-button.green { color: #28a745; border-color: #28a745; }
        .action-button.green:hover { background-color: #28a745; color: #fff; }
        .action-button.blue  { color: #007bff; border-color: #007bff; }
        .action-button.blue:hover  { background-color: #007bff; color: #fff; }

        .back-to-list-button {
            display: inline-block; padding: 10px 20px;
            background-color: transparent; color: #007bff;
            text-decoration: none; font-size: 16px;
            border: 2px solid #007bff; border-radius: 6px; font-weight: 500;
        }
        .back-to-list-button:hover { background-color: #007bff; color: #fff; }

        /* Κύρια containers */
        .container {
            margin: 30px auto; padding: 24px;
            width: min(1100px, 92%);
            background-color: rgba(255, 255, 255, 0.87);
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        h1, h2 { margin: 0 0 14px; color: #003366; text-align: center;}
        p { margin: 0 0 14px; color: #ffffff; }

        /* Dashboard */
        .grid {
            display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
        }
        .card {
            background-color: #000cb3ff;
            border-radius: 12px;
            color: #fff;
            padding: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            width: 220px; text-align: center;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .card:hover { transform: scale(1.03); box-shadow: 0 8px 18px rgba(0,0,0,0.25); }
        .card img {
            width: 100%; height: 110px; object-fit: cover; border-radius: 12px;
        }
        .card .button {
            display: inline-block; margin-top: 10px; padding: 8px 14px;
            background: #28a745; color: #fff; border-radius: 8px; text-decoration: none;
        }
        .card .button:hover { background: #218838; }

        /* Πίνακες */
        table {
            width: 100%; border-collapse: collapse; border-radius: 10px; overflow: hidden;
        }
        table th {
            background-color: #4da8da; color: #fff; text-align: center; padding: 10px;
        }
        table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        table tr:nth-child(even) { background-color: #e9f5ff; }
        table tr:hover { background-color: #f1f9ff; cursor: pointer; }

        /* Ανακοινώσεις */
        #my-announcements h2 { color: #0b4ba6; }
        #ann-list {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;
        }
        .ann-card {
            background: #fff; border: 1px solid #e8eef7; border-radius: 12px; padding: 16px;
            box-shadow: 0 6px 16px rgba(0,0,0,.04);
        }

        /* Modals */
        #overlay {
            display: none; position: fixed; inset: 0;
            background-color: rgba(0, 0, 0, 0.5); z-index: 999;
        }
        #notifications-popup, #form-notifications-popup {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: #fff; width: min(600px, 92%);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            padding: 20px; border-radius: 12px; z-index: 1000;
            animation: fadein 0.45s ease-in;
        }
        #notifications-popup h3 { margin: 0 0 10px; color: #0056b3; }
        #form-notifications-popup h3 { margin: 0 0 10px; color: #28a745; }
        #notifications-list, #form-notifications-list {
            list-style: none; padding: 0; margin: 0; max-height: 340px; overflow-y: auto;
        }
        #notifications-list li, #form-notifications-list li {
            padding: 10px; border-bottom: 1px solid #eee;
        }
        .close-btn {
            display: inline-block; margin-top: 12px; padding: 10px 20px;
            background-color: #dc3545; color: #fff; border: none; border-radius: 8px; cursor: pointer;
        }
        .close-btn:hover { background-color: #c82333; }

        /* Θέματα χρήστη/τίτλοι */
        .user-welcome { display: flex; align-items: center; margin: 20px 40px; }
        .user-profile-link { display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit; }
        .user-fullname { font-size: 24px; font-weight: 600; color: #003366; }
        .user-id { font-size: 20px; color: #003366; margin-top: 4px; }

        /* Κάρτες διπλωματικών */
        .thesis-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px; margin-top: 30px;
        }
        .thesis-card {
            background-color: rgba(255, 255, 255, 0.87);
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadein 0.5s ease-in;
        }
        .thesis-card:hover { transform: scale(1.02); box-shadow: 0 8px 14px rgba(0,0,0,0.2); }
        .thesis-image img { width: 100%; height: 180px; object-fit: cover; }
        .thesis-content { padding: 15px; text-align: left; }
        .thesis-content h3 { font-size: 20px; margin-bottom: 10px; color: #003366; }

        /* Footer */
        footer {
            flex-shrink: 0; width: 100%;
            background-color: rgba(0, 51, 102, 0.92);
            color: #fff; text-align: center; padding: 30px; margin-top: 20px;
            border-top-left-radius: 14px; border-top-right-radius: 14px;
        }

.intro-wrap{
  width: min(1180px, 92%);   
  margin: 24px auto 0;
  padding: 0 8px;
  margin-bottom: 100px;
}

.intro-row{
  display: flex;
  flex-wrap: wrap;
  gap: 40px;                     
  justify-content: center;       
}

.intro-card{
  width: 360px;
  margin: 0;
  padding: var(--space-5);
  background: rgba(255,255,255,0.95);
  border: 1px solid #e7eef7;
  border-radius: var(--radius-lg);
  box-shadow: var(--elev-soft);
  animation: fadein .45s ease-in;
  text-align: left;
}

.intro-title{
  margin: 0 0 var(--space-2);
  font-size: 1.25rem;
  color: var(--brand);
  letter-spacing: .2px;
  font-weight: 700;
}
.intro-welcome{
  margin: 0 0 var(--space-2);
  font-weight: 600;
  color: var(--brand-2);
}
.intro-text{
  margin: 0 0 var(--space-3);
  color: #334155;
  line-height: 1.55;
}
.intro-button{
  display: inline-block;
  padding: 10px 18px;
  border-radius: 999px;
  background: #28a745;
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  box-shadow: 0 6px 16px rgba(40,167,69,.28);
  transition: transform .15s ease;
}
.intro-button:hover{ transform: translateY(-1px); }

.section-title{
  font-weight:700;
  font-size:1.35rem;
  color: var(--brand);
  text-align:center;
  margin-top: 30px;
  margin-bottom: 20px;
}
.section-sub{
  text-align:center;
  color:#6b7480;
  margin-bottom: 20px;
}

.grid{ gap: var(--space-4); }
.card{
  width: 260px;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background:#0b5dbb;
  box-shadow: var(--elev-1);
}
.card img{
  height: 128px; border-radius: var(--radius-md);
}
.card .card-title{
  margin-top: var(--space-2);
  font-weight:600;
}
.card:hover{ transform: translateY(-2px); }

/* Διαχωριστική λωρίδα */
.section-divider{
  width:min(1100px,92%);
  margin: var(--space-6) auto;
  height:1px;
  background: linear-gradient(to right,transparent,rgba(0,0,0,.08),transparent);
  border-radius:999px;
}

/* Ανακοινώσεις */
#my-announcements.container{ text-align:left; }
#my-announcements h2{ text-align:center; }
.ann-card{
  border:1px solid #e7eef7;
  background:#f9fbff;
}
.empty-state{
  display:flex; align-items:center; justify-content:center;
  gap:12px; padding:18px;
  background:#fff; border:1px dashed #cfe0f5;
  border-radius: var(--radius-md);
  color: var(--muted);
  font-weight:500;
}

.header{
  border-bottom: none;
  background: rgba(245,245,245,.85);
  backdrop-filter: blur(6px);
  box-shadow: 0 8px 12px -6px rgba(0,0,0,.2);
}

.site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2);
            font-family: 'Segoe UI', sans-serif;
            margin-bottom: 20px;
            height: 120px;
            position: relative;
            z-index: 10;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .site-header .left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .site-header .logo {
            width:95px;
            height: 80px;
        }
        .system-name {
            font-size: 20px;
            font-weight: 600;
        }
        .site-header .right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .site-header .right nav a {
            color: white;
            text-decoration: none;
            margin-right: 15px;
        }
        .site-header .user-info {
            font-weight: 500;
        }

        .user-welcome {
    display: flex;
    align-items: center;
    margin: 20px 40px;
    justify-content: space-between;
}

.user-profile-link {
    display: flex;
    align-items: center;
    gap: 15px;
    text-decoration: none;
    color: inherit;
}

.user-profile-link img {
    height: 60px;
    width: 60px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.user-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.user-fullname {
    font-size: 24px;
    font-weight: 600;
    color: #003366;
}

.user-id {
    font-size: 20px;
    color: #003366;
    margin-top: 4px;
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

<div class="user-welcome fade-block">
  <a href="profile_edit.php" class="user-profile-link">
    <img src="User_icon.png" alt="User Icon">
    <div class="user-info">
      <div class="user-fullname">
        <?php echo htmlspecialchars($professorFullName); ?>
      </div>
      <div class="user-id">
        🏛️ <?php echo htmlspecialchars($professorDepartment); ?>
      </div>
    </div>
  </a>
</div>

<span id="welcomeMessage" style="display:none;"></span>

<div class="section-title"><h2>Πίνακας Έλεγχου Καθηγητή</h2></div>
<div class="section-sub">Εδώ μπορείτε να διαχειρίσετε διπλωματικές, να απαντήσετε σε προσκλήσεις και να δείτε στατιστικά.</div>

<div class="section-divider"></div>

<div class="intro-wrap">
 <div class="intro-row">
 <div class="intro-card">
  <div id="welcomeMessage" class="intro-welcome" aria-live="polite"></div>
  <h3 class="intro-title">Ανάθεση Και Διαχείριση Διπλωματικών</h3>
  <p class="intro-text">
   Καταχώρησε νέο θέμα διπλωματικής, κάνε ανάθεση ελεύθερου θέματος σε φοιτητή και δες τη λίστα όλων των διπλωματικών όπου
έχεις συμμετάσχει. 
  </p>
  <a href="listaDiplomatikon.php" class="intro-button">Μετάβαση</a>
</div>

 <div class="intro-card">
  <div id="welcomeMessage" class="intro-welcome" aria-live="polite"></div>
  <h3 class="intro-title">Βαθμολόγηση Ενεργών Διπλωματικών</h3>
  <p class="intro-text">
    Δες τις Ενεργές Διπλωματικές που επιβλέπεις, άλλαξε κατάσταση σε
    <em>Υπό Εξέταση</em> και καταχώρησε βαθμούς.
  </p>
  <a href="prof_grade.php" class="intro-button">Μετάβαση</a>
</div>

<div class="intro-card">
  <div id="welcomeMessage" class="intro-welcome" aria-live="polite"></div>
  <h3 class="intro-title">Κατάργηση Ανάθεσης Διπλωματικών</h3>
  <p class="intro-text">
    Ακύρωσε τις Ενεργές Διπλωματικές που επιβλέπεις, για τις οποίες έχουν παρέλθει <em>2 Έτη</em> από την οριστική ανάθεσή τους.
  </p>
  <a href="cancelThesis.php" class="intro-button">Μετάβαση</a>
</div>

<div class="intro-card">
  <div id="welcomeMessage" class="intro-welcome" aria-live="polite"></div>
  <h3 class="intro-title">Προσκλήσεις Συμμετοχής Σε Επιτροπή</h3>
  <p class="intro-text">
    Ακύρωσε τις Ενεργές Διπλωματικές που επιβλέπεις, για τις οποίες έχουν παρέλθει <em>2 Έτη</em> από την οριστική ανάθεσή τους.
  </p>
  <a href="proskliseis.php" class="intro-button">Μετάβαση</a>
</div>

<div class="intro-card">
  <div id="welcomeMessage" class="intro-welcome" aria-live="polite"></div>
  <h3 class="intro-title">Προβολή Στατιστικών Περατωμένων Διπλωματικών</h3>
  <p class="intro-text">
    Για τον Μέσο Χρόνο Περάτωσης διπλωματικών, τον Μέσο Βαθμό διπλωματικών και το Συνολικό Πλήθος Διπλωματικών.
  </p>
  <a href="statistika.php" class="intro-button">Μετάβαση</a>
</div>
</div>
</div>

<!-- Ανακοινώσεις Μου -->
<h2>Οι Ανακοινώσεις Μου</h2>
<div class="container" id="my-announcements">
  <p style="color:#555">Κείμενα ανακοινώσεων για παρουσιάσεις διπλωματικών που επιβλέπεις.</p>
  <div id="ann-list">
    <div class="ann-card"><div style="color:#667">Φορτώνει...</div></div>
  </div>
</div>

<div id="overlay"></div>

<script>
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
function fmtDate(d){ if(!d) return '—'; try{const x=new Date(d); if(isNaN(x)) return d; return x.toLocaleDateString();}catch(_){return d;} }

function loadMyAnnouncements(){
  fetch('professor_home.php?action=my_announcements&_=' + Date.now(), {cache:'no-store'})
    .then(r=>r.json())
    .then(rows=>{
      const wrap = document.getElementById('ann-list');
      if (!wrap) return;
      wrap.innerHTML = '';

      if (!rows || !rows.length){
        wrap.innerHTML = `
          <div class="ann-card">
            <div class="empty-state">Δεν υπάρχουν ακόμη ανακοινώσεις.</div>
          </div>`;
        return;
      }

      rows.forEach(row=>{
        const title = row.title || 'Διπλωματική';
        const ann   = row.announcements || '';
        const d     = fmtDate(row.exam_date);
        const tm    = row.exam_time ? row.exam_time.toString().slice(0,5) : '—';
        const mode  = row.exam_mode || '—';
        const place = (row.exam_mode && row.exam_mode.toLowerCase()==='online')
                      ? (row.link || '—')
                      : (row.room || '—');

        const card = document.createElement('div');
        card.className = 'ann-card';
        card.innerHTML = `
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
            <h3 style="margin:0 0 6px;color:#0b4ba6;font-size:1.05rem">${escapeHtml(title)}</h3>
            <span style="font-size:.8rem;background:#f3f6fb;border:1px solid #e3ebf6;border-radius:999px;padding:2px 8px;color:#234">${escapeHtml(mode)}</span>
          </div>
          <div style="font-size:.9rem;color:#445;margin:6px 0 8px">
            <strong>Ημ/νία:</strong> ${escapeHtml(d)} &nbsp;|&nbsp; <strong>Ώρα:</strong> ${escapeHtml(tm)} &nbsp;|&nbsp; <strong>Χώρος/Σύνδεσμος:</strong> ${escapeHtml(place)}
          </div>
          <div style="white-space:pre-wrap;color:#233;border:1px solid #f0f4fa;background:#fbfdff;padding:10px;border-radius:8px">${escapeHtml(ann)}</div>
          <div style="margin-top:8px;font-size:.8rem;color:#7a8594">thesis_id: ${Number(row.thesis_id)}</div>
        `;
        wrap.appendChild(card);
      });
    })
    .catch(()=>{
      const wrap = document.getElementById('ann-list');
      if (!wrap) return;
      wrap.innerHTML = `
        <div class="ann-card">
          <div style="color:#b33">Σφάλμα φόρτωσης ανακοινώσεων.</div>
        </div>`;
    });
}

document.addEventListener('DOMContentLoaded', loadMyAnnouncements);
</script>

<script>
function loadDashboard() {
    fetch('fetch_theses(professor_home).php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('welcomeMessage').textContent = `Καλώς ήλθατε, ${data.name}!`;
            const grid = document.getElementById('dashboardGrid');
            grid.innerHTML = '';
            data.cards.forEach(card => {
                const cardElement = document.createElement('div');
                cardElement.className = 'card';
                cardElement.innerHTML = `
                    <a href="${card.link}" style="color: white; text-decoration:none;">
                        <img src="${card.image}" alt="">
                        <div class="card-title">${card.title}</div>
                    </a>
                `;
                grid.appendChild(cardElement);
            });
        })
        .catch(error => console.error('Error loading dashboard:', error));
}

function logout() {
    if (confirm("Θέλετε να αποσυνδεθείτε;")) {
        window.location.href = 'logout.php';
    }
}

function showNotifications() {
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('notifications-popup').style.display = 'block';

    fetch('fetch_notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = '';

        if (data.length > 0) {
            let notificationsHTML = '';
            data.forEach(notification => {
                notificationsHTML += `
                    <li>
                        <strong>Πρόσκληση Επιτροπής</strong><br>
                        Φοιτητής ID: ${notification.student_id} - Διπλωματική ID: ${notification.thesis_id}<br>
                        Κατάσταση: ${notification.status} <br>
                        <small>Ημερομηνία: ${notification.sent_at}</small>
                    </li>`;
            });
            notificationsList.innerHTML = notificationsHTML;
        } else {
            notificationsList.innerHTML = '<li>Δεν υπάρχουν νέες ειδοποιήσεις.</li>';
        }
    })
    .catch(error => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = `<li>Σφάλμα: ${error.message}</li>`;
    });
}

function showFormNotifications() {
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('notifications-popup').style.display = 'block';

    fetch('fetch_form_notifications.php')
    .then(response => response.json())
    .then(data => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = '';

        if (data.length > 0) {
            let notificationsHTML = '';
            data.forEach(notification => {
                notificationsHTML += `
                    <li>
                        <strong>${notification.student_name} ${notification.student_surname}</strong> - 
                        <em>${notification.thesis_title}</em><br>
                        <strong>Θέμα:</strong> ${notification.topic} <br>
                        <strong>Μήνυμα:</strong> ${notification.message} <br>
                        <small>Ημερομηνία: ${notification.created_at}</small>
                    </li>`;
            });
            notificationsList.innerHTML = notificationsHTML;
        } else {
            notificationsList.innerHTML = '<li>Δεν υπάρχουν νέες ειδοποιήσεις.</li>';
        }
    })
    .catch(error => {
        const notificationsList = document.getElementById('notifications-list');
        notificationsList.innerHTML = `<li>Σφάλμα: ${error.message}</li>`;
    });
}

function closeFormNotifications() {
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('form-notifications-popup').style.display = 'none';
}

function markAsRead(notificationId) {
    fetch('mark_notification_as_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificationId }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Η ειδοποίηση μαρκάρεται ως διαβασμένη.');
            showNotifications();
        } else {
            alert('Αποτυχία ενημέρωσης.');
        }
    })
    .catch(error => console.error('Σφάλμα:', error));
}

function closeNotifications() {
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('notifications-popup').style.display = 'none';
}

window.addEventListener('popstate', function () {
    if (confirm("Θέλετε να αποσυνδεθείτε;")) {
        window.location.href = "logout.php";
    } else {
        history.pushState(null, document.title, location.href);
    }
});
window.addEventListener('load', function () {
    history.pushState(null, document.title, location.href);
});
document.addEventListener('DOMContentLoaded', loadDashboard);

function exportTheses(format){
  const params = new URLSearchParams();
  params.set('format', (format || '').toLowerCase() === 'json' ? 'json' : 'csv');

  // τίτλος 
  const title = document.getElementById('exp_title')?.value?.trim();
  if (title) params.set('title', title);

  // Υπόλοιπα προαιρετικά φίλτρα
  const status     = document.getElementById('exp_status')?.value?.trim();
  const studentId  = document.getElementById('exp_student')?.value?.trim();
  const startFrom  = document.getElementById('exp_start_from')?.value?.trim();
  const startTo    = document.getElementById('exp_start_to')?.value?.trim();
  const endFrom    = document.getElementById('exp_end_from')?.value?.trim();
  const endTo      = document.getElementById('exp_end_to')?.value?.trim();

  if (status)    params.set('status', status);
  if (studentId) params.set('student_id', studentId);
  if (startFrom) params.set('start_from', startFrom);
  if (startTo)   params.set('start_to', startTo);
  if (endFrom)   params.set('end_from', endFrom);
  if (endTo)     params.set('end_to', endTo);

  // Άνοιγμα σε νέο tab για κατέβασμα αρχείου
  const url = 'export_theses.php?' + params.toString();
  window.open(url, '_blank', 'noopener');
}
</script>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>
</body>
</html>
