<?php
session_start(); 

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    if (!isset($_SESSION['email'])) {
        header("Location: login.php");
        exit();
    }

    $email = $_SESSION['email']; 

    
    $stmt = $pdo->prepare("SELECT s.name, s.surname, s.student_number FROM students s JOIN users u ON s.student_id = u.user_id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Σφάλμα: Ο χρήστης με email $email δεν βρέθηκε.";
        exit();
    }

    $userFullName   = $user['name'] . ' ' . $user['surname'];
    $studentNumber  = $user['student_number'];

    $stmt = $pdo->prepare("SELECT student_id FROM students s JOIN users u ON s.student_id = u.user_id WHERE u.email = ?");
    $stmt->execute([$email]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_id = $studentRow['student_id'];

    // Έλεγχος αν υπάρχει περατωμένη διπλωματική για τον φοιτητή
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM theses WHERE student_id = :student_id AND status = 'Περατωμένη'");
    $stmt->execute(['student_id' => $student_id]);
    $hasFinalThesis = $stmt->fetchColumn() > 0;

    // Ανάκτηση δεδομένων διπλωματικών
    $stmt = $pdo->prepare("
        SELECT 
            t.thesis_id, 
            t.title, 
            t.status, 
            t.start_date, 
            t.end_date, 
            GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
        FROM theses t
        LEFT JOIN committees c ON t.thesis_id = c.thesis_id
        LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
        WHERE t.student_id = :student_id
        GROUP BY t.thesis_id
    ");
    $stmt->execute(['student_id' => $student_id]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Προτεινόμενες διπλωματικές
    $stmt = $pdo->prepare("SELECT thesis_id, title, status FROM theses WHERE student_id != :student_id AND status = 'Περατωμένη' LIMIT 3");
    $stmt->execute(['student_id' => $student_id]);
    $recommendedTheses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Έλεγχος για εξέταση σε εκκρεμότητα
    $examStmt = $pdo->prepare("
        SELECT e.exam_date, e.exam_time, t.thesis_id
        FROM theses t
        JOIN examinations e ON t.thesis_id = e.thesis_id
        LEFT JOIN exam_results r ON t.thesis_id = r.thesis_id
        WHERE t.student_id = :student_id 
          AND t.status = 'Υπό Εξέταση' 
        ORDER BY e.exam_id DESC
        LIMIT 1;
    ");
    $examStmt->execute(['student_id' => $student_id]);
    $pendingExam = $examStmt->fetch(PDO::FETCH_ASSOC);

    $examInfoBox = "";
    if ($pendingExam) {
        $updateStmt = $pdo->prepare("UPDATE theses SET status = 'Υπο Εξέταση' WHERE thesis_id = :thesis_id");
        $updateStmt->execute(['thesis_id' => $pendingExam['thesis_id']]);

        $examDate = htmlspecialchars($pendingExam['exam_date']);
        $examTime = htmlspecialchars($pendingExam['exam_time']);

        $examInfoBox = "
            <div id='exam-box' class='fade-box'>
                📅 Ημερομηνία Εξέτασης: <span style='color: #003366;'>$examDate</span><br>
                ⏰ Ώρα Εξέτασης: <span style='color: #003366;'>$examTime</span>
            </div>
        ";
    }

} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonts + Bootstrap -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Bootstrap Icons για τα meta εικονίδια -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

  <title>Η Διπλωματική Μου Εργασία</title>

  <style>
    *{ transition: background-color .3s, color .3s; }
    .container{ animation: fadein .5s ease-in; }
    @keyframes fadein{ from{opacity:0; transform:translateY(20px)} to{opacity:1; transform:translateY(0)} }

    html, body{ height:100%; margin:0; padding:0; display:flex; flex-direction:column; }
    body{
      font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(to right, #e2e2e2, #c9d6ff);
      color:#333; font-size:.96rem; min-height:100vh;
    }
    body::before{ content:""; position:fixed; inset:0; background-color: hsla(211,32.3%,51.4%,.35); z-index:-1; }

    /* Header */
    .site-header{ display:flex; justify-content:space-between; align-items:center; padding:20px 40px;
      background-color: rgba(0,51,102,.92); color:#fff; box-shadow:0 8px 8px -4px rgba(0,0,0,.2);
      margin-bottom:20px; height:120px; position:relative; z-index:10; border-bottom-left-radius:14px; border-bottom-right-radius:14px;
      font-family:'Segoe UI',sans-serif;
    }
    .site-header .left{ display:flex; align-items:center; gap:10px; }
    .site-header .logo{ width:95px; height:80px; }
    .system-name{ font-size:20px; font-weight:600; }
    .site-header .right{ display:flex; align-items:center; gap:20px; }
    .site-header .right nav a{ color:#fff; text-decoration:none; margin-right:15px; }
    .site-header .user-info{ font-weight:500; }

    /* Κεντρικός τίτλος */
    .main-title{ font-weight:bold; font-size:30px; color: rgba(0,51,102,.92); }

    /* Welcome block */
    .user-welcome{ display:flex; align-items:center; margin:20px 40px; }
    .user-profile-link{ display:flex; align-items:center; gap:15px; text-decoration:none; color:inherit; }
    .user-profile-link img{ height:60px; width:60px; border-radius:50%; object-fit:cover; box-shadow:0 2px 6px rgba(0,0,0,.1); }
    .user-info{ display:flex; flex-direction:column; justify-content:center; }
    .user-fullname{ font-size:24px; font-weight:600; color:#003366; }
    .user-id{ font-size:20px; color:#003366; margin-top:4px; }

    /* Πλέγμα καρτών */
    .thesis-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(300px,1fr)); gap:20px; margin-top:30px; }

    .thesis-card{
      position:relative; cursor:pointer;
      background:#fff;
      border-radius:16px; overflow:hidden;
      border:1px solid transparent;
      background:
        linear-gradient(#ffffff,#ffffff) padding-box,
        linear-gradient(135deg, #dde7ff, #eef3ff) border-box;
      box-shadow:0 12px 26px rgba(15,27,45,.10);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .thesis-card:hover{
      transform: translateY(-4px);
      box-shadow:0 18px 36px rgba(15,27,45,.16);
      border-color:#cddafc;
    }
    .thesis-image{ position:relative; aspect-ratio: 21/3; overflow:hidden; }
    .thesis-image img{ width:100%; height:100%; object-fit:cover; display:block; }
    .thesis-image::after{
      content:""; position:absolute; inset:0;
      background:linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,.08) 100%);
    }

    /* Badge κατάστασης επάνω στην εικόνα */
    .status-chip{
      position:absolute; top:10px; left:10px;
      padding:6px 10px; border-radius:999px; font-size:.82rem; font-weight:700; color:#fff;
      box-shadow:0 6px 14px rgba(0,0,0,.14); backdrop-filter: blur(4px);
    }
    .status--active{ background: linear-gradient(180deg,#21a67a,#148a63) }
    .status--pending{ background: linear-gradient(180deg,#ffb020,#e69500) }
    .status--done{ background: linear-gradient(180deg,#4c6ef5,#2544c3) }
    .status--cancel{ background: linear-gradient(180deg,#e55353,#c53b3b) }

    .thesis-content{ padding:16px 16px 10px; text-align:left; }
    .thesis-title{
      font-size:19px; font-weight:800; color:#0f2244; line-height:1.35; margin:0 0 10px;
      display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
    }
    .meta{ list-style:none; padding:0; margin:0 0 8px; display:grid; grid-template-columns:1fr; row-gap:6px; }
    .meta li{ display:flex; align-items:center; gap:8px; color:#344563; font-size:.95rem; }
    .meta i{ font-size:1rem; opacity:.9; }

    .pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:4px 10px; border-radius:999px; font-weight:700; font-size:.82rem;
      border:1px solid transparent;
    }
    .pill--active{ background:#e6faf3; color:#0f734a; border-color:#bfead8; }
    .pill--pending{ background:#fff4e1; color:#a66300; border-color:#ffd59e; }
    .pill--done{ background:#e9edff; color:#2b44b8; border-color:#cfd7ff; }
    .pill--cancel{ background:#ffe9ea; color:#b13a3a; border-color:#ffc7cb; }

    .card-divider{ height:1px; background:linear-gradient(90deg,#edf2ff, #e8eefc, #edf2ff); margin:10px 16px 0; }

    .action-buttons{
      display:flex; justify-content:center; gap:10px; flex-wrap:wrap;
      margin: 12px 16px 16px;
    }
    .action-button{
      padding:10px 14px; border:1px solid #c7d7f7; border-radius:12px;
      background: linear-gradient(180deg,#e9f0ff,#d7e3ff);
      color:#163a74; font-weight:800; text-decoration:none; font-size:.95rem;
      transition: transform .15s ease, box-shadow .15s ease;
      box-shadow: 0 10px 20px rgba(11,75,166,.12), inset 0 1px 0 #fff;
    }
    .action-button:hover{ transform: translateY(-1px); box-shadow: 0 14px 24px rgba(11,75,166,.18) }
    .action-button.green{ color:#0f734a; border-color:#a7e6c9; background: linear-gradient(180deg,#dff7ec,#c9f0df) }

    .thesis-card--compact .thesis-title{ font-size:18px; }

    /* Exam info floating box */
    .fade-box{
      position:absolute; top:140px; right:30px; background:#fff; border:2px solid #007bff; border-radius:10px;
      padding:15px 20px; box-shadow:0 4px 10px rgba(0,0,0,.15); font-weight:bold; z-index:5; opacity:0;
      animation: fadeIn .8s ease-in forwards;
    }
    @keyframes fadeIn{ from{opacity:0; transform:translateY(-10px)} to{opacity:1; transform:translateY(0)} }

    /* Footer */
    footer{ flex-shrink:0; width:100%; background-color: rgba(0,51,102,.92); color:#fff; text-align:center; padding:30px; margin-top:20px; }
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
      <a href="profile_edit.php">Το Προφιλ Μου</a>
    </nav>
    <span class="user-info"><a href="loginn.php" style="color:#ccc">Έξοδος</a></span>
  </div>
</header>

<?php if (!empty($examInfoBox)) echo $examInfoBox; ?>

<!-- ΟΝΟΜΑ & ΑΜ -->
<div class="user-welcome fade-block">
  <a href="profile_edit.php" class="user-profile-link">
    <img src="User_icon.png" alt="User Icon">
    <div class="user-info">
      <div class="user-fullname"><?php echo htmlspecialchars($userFullName); ?></div>
      <div class="user-id">🎓 ΑΜ: <?php echo htmlspecialchars($studentNumber); ?></div>
    </div>
  </a>
</div>

<div class="container">
  <h1 class="main-title" style="display:flex;align-items:center;justify-content:center;gap:10px;font-weight:bold;margin-top:30px;">
    <img src="thesis.png" alt="Εικόνα Διπλωματικής" style="height:40px;">
    Η ΔΙΠΛΩΜΑΤΙΚΗ ΜΟΥ ΕΡΓΑΣΙΑ
  </h1>

  <div class="thesis-grid">
    <?php foreach ($theses as $thesis):
      $status = trim($thesis['status'] ?? '');
      $tid    = (int)$thesis['thesis_id'];
      $chip = 'status--pending';
      $pill = 'pill--pending';
      if ($status === 'Περατωμένη') { $chip='status--done'; $pill='pill--done'; }
      elseif ($status === 'Υπο Εξέταση' || $status === 'Υπό Εξέταση') { $chip='status--pending'; $pill='pill--pending'; }
      elseif ($status === 'Ακυρωμένη') { $chip='status--cancel'; $pill='pill--cancel'; }
      elseif ($status === 'Ενεργή' || $status === 'Ενεργη') { $chip='status--active'; $pill='pill--active'; }
    ?>
      <div class="thesis-card" onclick="window.location.href='view_student.php?thesis_id=<?= $tid ?>';">
        <div class="thesis-image">
          <img src="weather_forecast.png" alt="Thesis Cover">
          <span class="status-chip <?= $chip ?>"><?= htmlspecialchars($status) ?></span>
        </div>

        <div class="thesis-content">
          <h3 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h3>

          <ul class="meta">
            <li>
              <i class="bi bi-flag"></i>
              <span><strong>Κατάσταση:</strong>
                <span class="pill <?= $pill ?>"><?= htmlspecialchars($status) ?></span>
              </span>
            </li>
            <li>
              <i class="bi bi-calendar-event"></i>
              <span><strong>Έναρξη:</strong> <?= htmlspecialchars($thesis['start_date']) ?></span>
            </li>
            <li>
              <i class="bi bi-calendar-check"></i>
              <span><strong>Λήξη:</strong>
                <?= $thesis['end_date'] ? htmlspecialchars($thesis['end_date']) : 'Δεν έχει οριστεί' ?>
              </span>
            </li>
            <li>
              <i class="bi bi-people"></i>
              <span><strong>Επιτροπή:</strong> <?= htmlspecialchars($thesis['committee_members']) ?></span>
            </li>
          </ul>
        </div>

        <div class="card-divider"></div>

        <div class="action-buttons">
          <?php if ($status === 'Υπο Αναθεση' || $status === 'Υπό Ανάθεση'): ?>
            <a onclick="event.stopPropagation();" href="epilogitrimelousepitropis.php?thesis_id=<?= $tid ?>" class="action-button">Επιλογή Τριμελούς Επιτροπής</a>
          <?php elseif ($status === 'Υπο Εξέταση' || $status === 'Υπό Εξέταση'): ?>
            <a onclick="event.stopPropagation();" href="student_action.php?thesis_id=<?= $tid ?>" class="action-button">Διαχείριση Διπλωματικής</a>
            <a onclick="event.stopPropagation();" href="thesis_exam_report.php?thesis_id=<?= $tid ?>" class="action-button">Προβολή Πρακτικού Εξέτασης</a>
          <?php elseif ($status === 'Περατωμένη'): ?>
            <a onclick="event.stopPropagation();" href="thesis_exam_report.php?thesis_id=<?= $tid ?>" class="action-button green">Προβολή Πρακτικού Εξέτασης</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div style="margin:30px auto; width:90%; max-width:1200px; font-weight:bold;">
  <h2 class="text-center mb-4" style="color:rgba(0,51,102,.92);">Σχετικές Διπλωματικές</h2>
  <div class="thesis-grid">
    <?php foreach ($recommendedTheses as $thesis): ?>
      <div class="thesis-card thesis-card--compact" onclick="window.location.href='view_student.php?thesis_id=<?= (int)$thesis['thesis_id'] ?>';">
        <div class="thesis-image">
          <img src="weather_forecast.png" alt="Thesis Image">
          <span class="status-chip status--done"><?= htmlspecialchars($thesis['status']) ?></span>
        </div>
        <div class="thesis-content">
          <h3 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h3>
          <ul class="meta">
            <li><i class="bi bi-flag"></i><span><strong>Κατάσταση:</strong> <span class="pill pill--done"><?= htmlspecialchars($thesis['status']) ?></span></span></li>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($theses)): ?>
  <div class="back-to-list-container" style="text-align:center; margin-top:20px;">
  </div>
<?php endif; ?>

<!-- Chatbot -->
<div id="chatbot-toggle" onclick="toggleChatbot()">💬</div>
<div id="chatbot-body">
  <div id="chatbot-header">
    <span>Βοήθεια Chatbot</span>
    <button onclick="toggleChatbot()">✖</button>
  </div>
  <div id="chatbot-messages"></div>
  <div id="chatbot-questions">
    <button onclick="handleQuestion('Πώς μπορώ να αναζητήσω διπλωματική εργασία;')">Πώς μπορώ να αναζητήσω διπλωματική εργασία;</button>
    <button onclick="handleQuestion('Πώς μπορώ να δω τις λεπτομέρειες μιας διπλωματικής;')">Πώς μπορώ να δω τις λεπτομέρειες μιας διπλωματικής;</button>
    <button onclick="handleQuestion('Πού μπορώ να διαχειριστώ τις διπλωματικές μου;')">Πού μπορώ να διαχειριστώ τις διπλωματικές μου;</button>
    <button onclick="handleQuestion('Πώς να καλέσω έναν καθηγητή;')">Πώς να καλέσω έναν καθηγητή;</button>
    <button onclick="handleQuestion('Ποια είναι η χρήση του πίνακα στη σελίδα;')">Ποια είναι η χρήση του πίνακα στη σελίδα;</button>
  </div>
</div>

<style>
  /* Chatbot styling */
  #chatbot-toggle{
    position:fixed; bottom:20px; right:20px; width:60px; height:60px; background:#007bff; color:#fff;
    border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:2rem;
    cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,.2); z-index:1000;
  }
  #chatbot-body{
    position:fixed; bottom:90px; right:20px; width:300px; height:400px; background:#fff; border:1px solid #ddd;
    border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,.2); display:none; flex-direction:column; z-index:1000;
  }
  #chatbot-header{ background:#007bff; color:#fff; padding:10px; border-radius:10px 10px 0 0; display:flex; justify-content:space-between; align-items:center; }
  #chatbot-messages{ flex:1; padding:10px; overflow-y:auto; font-size:14px; border-bottom:1px solid #ddd; }
  #chatbot-questions{ padding:10px; display:flex; flex-wrap:wrap; gap:10px; justify-content:center; }
  #chatbot-questions button{ padding:8px 10px; background:#007bff; color:#fff; border:none; border-radius:5px; font-size:12px; cursor:pointer; }
  #chatbot-questions button:hover{ background:#0056b3; }
</style>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
 
  let chatbotVisible = false;

  function toggleChatbot(){
    chatbotVisible = !chatbotVisible;
    document.getElementById("chatbot-body").style.display = chatbotVisible ? "flex" : "none";
  }
  function handleQuestion(q){
    addChatMessage("Χρήστης", q);
    getChatbotResponse(q);
  }
  function addChatMessage(sender, message){
    const wrap = document.getElementById("chatbot-messages");
    const div = document.createElement("div");
    div.style.margin = "5px 0";
    div.textContent = sender + ": " + message;
    wrap.appendChild(div);
    wrap.scrollTop = wrap.scrollHeight;
  }
  function getChatbotResponse(question){
    const responses = {
      "Πώς μπορώ να αναζητήσω διπλωματική εργασία;": "Για να αναζητήσετε διπλωματική εργασία, πληκτρολογήστε τον τίτλο ή λέξεις-κλειδιά στο πεδίο αναζήτησης και πατήστε 'Αναζήτηση'.",
      "Πώς μπορώ να δω τις λεπτομέρειες μιας διπλωματικής;": "Κάντε κλικ σε οποιαδήποτε κάρτα για να δείτε τις λεπτομέρειες της αντίστοιχης διπλωματικής.",
      "Πού μπορώ να διαχειριστώ τις διπλωματικές μου;": "Μπορείτε να διαχειριστείτε τις διπλωματικές σας από τα σχετικά κουμπιά στη κάρτα.",
      "Πώς να καλέσω έναν καθηγητή;": "Μπορείτε να στείλετε πρόσκληση σε καθηγητή μέσα από την «Επιλογή Τριμελούς Επιτροπής».",
      "Ποια είναι η χρήση του πίνακα στη σελίδα;": "Οι κάρτες εμφανίζουν όλες τις διπλωματικές εργασίες, με πληροφορίες όπως τίτλος, κατάσταση, ημερομηνίες και μέλη επιτροπής."
    };
    const botResponse = responses[question] || "Λυπάμαι, δεν έχω απάντηση για την ερώτηση αυτή.";
    addChatMessage("Chatbot", botResponse);
  }
</script>

</body>
</html>

