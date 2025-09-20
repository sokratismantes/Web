<?php
session_start();

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

if (!isset($_SESSION['email'])) {
    die("Σφάλμα: Δεν έχετε συνδεθεί.");
}

$email = $_SESSION['email'];

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $user_stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_type = 'student'");
    $user_stmt->execute(['email' => $email]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Δεν βρέθηκε φοιτητής με αυτό το email.");
    }

    $student_id = $user['user_id'];

    
    $thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

    
    $allowed_override_ids = [1,2,3,4,5,6,7,8,9,10,11,12,13];

    if (in_array($thesis_id, $allowed_override_ids)) {
        $stmt = $pdo->prepare("
            SELECT t.thesis_id, t.title, t.description, t.status, t.start_date, t.end_date, t.repository_link, t.links,
                   GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
            FROM theses t
            LEFT JOIN committees c ON t.thesis_id = c.thesis_id
            LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
            WHERE t.thesis_id = :thesis_id
            GROUP BY t.thesis_id
        ");
        $stmt->execute(['thesis_id' => $thesis_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.thesis_id, t.title, t.description, t.status, t.start_date, t.end_date, t.repository_link, t.links,
                   GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
            FROM theses t
            LEFT JOIN committees c ON t.thesis_id = c.thesis_id
            LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
            WHERE t.student_id = :student_id AND t.thesis_id = :thesis_id
            GROUP BY t.thesis_id
        ");
        $stmt->execute([
            'student_id' => $student_id,
            'thesis_id' => $thesis_id
        ]);
    }

    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);
    $thesis_id = $thesis['thesis_id'] ?? 0;

    // --- UPLOAD ΑΡΧΕΙΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_file'])) {
        if (!empty($_FILES['new_file']['name'])) {
            $file = $_FILES['new_file'];
            $originalName = basename($file['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeName = $student_id . "_" . $thesis_id . "_" . time() . "." . $ext;

            $targetDir  = __DIR__ . "/uploads/";
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
            $targetPath = $targetDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO attachments (thesis_id, student_id, filename)
                    VALUES (:thesis_id, :student_id, :filename)
                ");
                $stmtInsert->execute([
                    'thesis_id' => $thesis_id,
                    'student_id' => $student_id,
                    'filename' => $safeName
                ]);
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                echo "<p style='color:red;'>⛔ Αποτυχία στο ανέβασμα.</p>";
                exit();
            }
        } else {
            echo "<p style='color:red;'>⛔ Δεν επιλέχθηκε αρχείο για ανέβασμα.</p>";
            exit();
        }
    }

    // --- ΔΙΑΓΡΑΦΗ ΑΡΧΕΙΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_file'])) {
        $filename = basename($_POST['delete_file']);

        $stmtDelete = $pdo->prepare("
            DELETE FROM attachments 
            WHERE filename = :filename AND thesis_id = :thesis_id AND student_id = :student_id
        ");
        $stmtDelete->execute([
            'filename'   => $filename,
            'thesis_id'  => $thesis_id,
            'student_id' => $student_id
        ]);

        $paths = [
            __DIR__ . "/uploads/" . $filename,
            __DIR__ . "/uploads/drafts/" . $filename
        ];
        foreach ($paths as $p) {
            if (is_file($p)) { @unlink($p); }
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?thesis_id=" . $thesis_id);
        exit();
    }

    // --- ΔΙΑΓΡΑΦΗ ΣΥΝΔΕΣΜΟΥ ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_link'])) {
        $linkToDelete = $_POST['delete_link'];
        $thesis_id = intval($_POST['thesis_id'] ?? 0);

        $stmtLinks = $pdo->prepare("SELECT links FROM theses WHERE thesis_id = :thesis_id");
        $stmtLinks->execute(['thesis_id' => $thesis_id]);
        $row = $stmtLinks->fetch(PDO::FETCH_ASSOC);
        $currentLinks = json_decode($row['links'] ?? '[]', true);

        $updatedLinks = array_filter($currentLinks, function ($link) use ($linkToDelete) {
            return $link !== $linkToDelete;
        });

        $stmtUpdate = $pdo->prepare("UPDATE theses SET links = :links WHERE thesis_id = :thesis_id");
        $stmtUpdate->execute([
            'links' => json_encode(array_values($updatedLinks), JSON_UNESCAPED_UNICODE),
            'thesis_id' => $thesis_id
        ]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?thesis_id=" . $thesis_id);
        exit();
    }

    // --- ΕΠΕΞΕΡΓΑΣΙΑ ΛΟΙΠΩΝ ---
    if (!$thesis) {
        $links = [];
        $time_elapsed = "Δεν έχει οριστεί";
    } else {
        $links = json_decode($thesis['links'] ?? '[]', true);
        $time_elapsed = "Δεν έχει οριστεί";

        if ($thesis['start_date']) {
            $start_date = new DateTime($thesis['start_date']);
            $current_date = new DateTime();
            $interval = $start_date->diff($current_date);
            $time_elapsed = $interval->format('%y χρόνια, %m μήνες, %d ημέρες');
        }
    }

} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;800&display=swap" rel="stylesheet">
  <title>Προβολή Διπλωματικής</title>

  <style>
    *{ transition: background-color .3s, color .3s, border-color .25s, box-shadow .25s; box-sizing:border-box; }
    html, body{ height:100%; margin:0; padding:0; }
    body{
      font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(to right, #e2e2e2, #c9d6ff);
      color:#333; font-size:.96rem; min-height:100vh; display:flex; flex-direction:column;
    }
    body::before{ content:""; position:fixed; inset:0; background-color: hsla(211,32.3%,51.4%,.35); z-index:-1; }

    .site-header{
      display:flex; justify-content:space-between; align-items:center;
      padding:20px 40px; background-color: rgba(0,51,102,.92);
      color:#fff; box-shadow:0 8px 8px -4px rgba(0,0,0,.2);
      margin:0 0 20px; height:120px; position:relative; z-index:10;
      border-bottom-left-radius:14px; border-bottom-right-radius:14px;
      font-family:'Segoe UI',sans-serif;
    }
    .site-header .left{ display:flex; align-items:center; gap:10px; }
    .site-header .logo{ width:95px; height:80px; object-fit:contain; }
    .system-name{ font-size:20px; font-weight:600; }
    .site-header .right{ display:flex; align-items:center; gap:20px; }
    .site-header .right nav a{ color:#fff; text-decoration:none; margin-right:15px; }
    .site-header .user-info{ font-weight:500; }

    .content{ width:100%; max-width:1280px; margin: 0 auto 30px; padding: 0 22px; animation: fadein .5s ease; }
    @keyframes fadein{ from{opacity:0; transform:translateY(20px)} to{opacity:1; transform:translateY(0)} }

    .page-title{
      color: rgba(0,51,102,.92);
      font-weight:800; font-size: 26px;
      display:flex; align-items:center; gap:10px;
      margin: 10px 0 18px; letter-spacing:.2px;
    }
    .page-title img{ height: 40px; }

    .wrapper{
      display:grid;
      grid-template-columns: minmax(0,1fr) 300px;
      gap: 26px; align-items: start;
    }
    @media (max-width: 1024px){ .wrapper{ grid-template-columns: 1fr; } }

    /* Container εμφάνισης στοιχείων */
    .readonly-form{
      background:#fff; border: 1px solid #e6ecf7; border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,.12);
      padding: 26px;
      font-size: 0.92rem;
    }
    .info-grid{
      display:grid;
      grid-template-columns: repeat(2, minmax(0,1fr));
      gap: 18px 22px;
    }
    @media (max-width: 800px){ .info-grid{ grid-template-columns: 1fr; } }
    .form-group{ display:flex; flex-direction:column; gap:6px; position:relative; background:linear-gradient(180deg,#fff,#fbfdff); border:1px solid #dbe2ee; border-radius:12px; padding:12px 14px; transition:border-color .18s, box-shadow .18s, transform .12s; }
    .form-group:hover{ border-color:#c7d7f7; box-shadow:0 6px 16px rgba(18,61,101,.08); transform:translateY(-1px); }
    .form-group--full{ grid-column: 1 / -1; }
    .readonly-form label{ font-weight:800; font-size:.75rem; color:#5b6b84; text-transform:uppercase; letter-spacing:.04em; line-height:1.1; }
    .readonly-form input[readonly]{
      background:transparent; border:0; border-radius: 10px; padding:0; margin-top:4px;
      font-weight:800; color:#0f2244; outline:none; white-space:normal; word-break:break-word;
      font-size:clamp(.90rem, 1.6vw, 1.00rem); line-height:1.5; pointer-events:none;
    }
    .readonly-form .form-group--full input[readonly]{ font-size:clamp(.95rem, 1.9vw, 1.06rem); }

    /* Αριστερή μπάρα */
    .readonly-form .form-group::before{
      content:""; position:absolute; left:0; top:8px; bottom:8px; width:4px; border-radius:4px;
      background:linear-gradient(180deg,#8fb5ff,#5b8def); opacity:.55;
    }

    /* badges κατάστασης */
    .pill{display:inline-flex;align-items:center;gap:8px;padding:5px 8px;border-radius:999px;font-weight:800;font-size:.82rem;border:1px solid transparent; line-height:1}
    .pill::before{content:""; width:8px; height:8px; border-radius:50%; background: currentColor; opacity:.9}
    .pill-success{background:#e8f7ef;color:#0f5132;border-color:#a6dfc0}
    .pill-info{background:#e8f0ff;color:#0b2e59;border-color:#b9ccf3}
    .pill-warn{background:#fff6e6;color:#7a5200;border-color:#ffdd99}
    .pill-neutral{background:#f2f4f7;color:#374151;border-color:#e5e7eb}
    .readonly-form [data-field="status"]{
      position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;
    }

    /* Chips επιτροπής */
    .chips{display:flex;flex-wrap:wrap;gap:6px}
    .chip{display:inline-flex;align-items:center;gap:6px;background:#eef2ff;color:#0b2e59;border:1px solid #c7d7f7;padding:4px 8px;border-radius:999px;font-weight:700;font-size:.82rem;max-width:100%;white-space:nowrap;text-overflow:ellipsis;overflow:hidden}
    .chip::before{content:""; width:6px; height:6px; border-radius:50%; background:#5b8def}
    .readonly-form [data-field="committee"]{
      position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;
    }

    /* Expandable Περιγραφή */
    .desc-collapsible{
      position: relative; cursor: pointer; background:#f9f9f9; border:1px solid #dfe7f7; border-radius:10px;
      padding:12px 14px; color:#0f2244; font-weight:700; line-height:1.6; font-size:.95em;
      display:-webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3;
      overflow:hidden; max-height:4.8em; transition: border-color .18s, box-shadow .18s;
    }
    .desc-collapsible:hover{ border-color:#c7d7f7; box-shadow:0 6px 16px rgba(18,61,101,.08); }
    .desc-collapsible.is-clamped::after{
      content:""; position:absolute; left:0; right:0; bottom:0; height:40px;
      background: linear-gradient(180deg, rgba(249,249,249,0), #f9f9f9 70%); pointer-events:none;
    }
    .desc-collapsible .desc-more-hint{
      position:absolute; right:10px; bottom:8px; font-size:.78rem; font-weight:800; color:#0b2e59;
      background: rgba(255,255,255,.9); padding:2px 8px; border:1px solid #cfe0ff; border-radius:10px; display:none;
    }
    .desc-collapsible.is-clamped .desc-more-hint{ display:inline-block; }
    .desc-collapsible.expanded{ max-height:none; -webkit-line-clamp: initial; overflow: visible; }
    .desc-collapsible.expanded::after, .desc-collapsible.expanded .desc-more-hint{ display:none; }

    /* Expandable Τίτλος */
    .title-collapsible{
      position: relative; cursor: pointer; background:#f9fbff; border:1px solid #dfe7f7; border-radius:10px;
      padding:10px 12px; color:#0b2e59; font-weight:800; line-height:1.5; font-size:clamp(.95rem, 1.9vw, 1.06rem);
      display:-webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2;
      overflow:hidden; max-height:3.2em; transition: border-color .18s, box-shadow .18s;
    }
    .title-collapsible:hover{ border-color:#c7d7f7; box-shadow:0 6px 16px rgba(18,61,101,.08); }
    .title-collapsible.is-clamped::after{
      content:""; position:absolute; left:0; right:0; bottom:0; height:28px;
      background: linear-gradient(180deg, rgba(249,251,255,0), #f9fbff 70%); pointer-events:none;
    }
    .title-collapsible .title-more-hint{
      position:absolute; right:10px; bottom:6px; font-size:.75rem; font-weight:800; color:#0b2e59;
      background: rgba(255,255,255,.9); padding:2px 8px; border:1px solid #cfe0ff; border-radius:10px; display:none;
    }
    .title-collapsible.is-clamped .title-more-hint{ display:inline-block; }
    .title-collapsible.expanded{ max-height:none; -webkit-line-clamp: initial; overflow: visible; }
    .title-collapsible.expanded::after, .title-collapsible.expanded .title-more-hint{ display:none; }
    .readonly-form [data-field="title"]{
      position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;
    }

    /* Silver–Blue Buttons για αποθετήριο */
    .btn-silver-blue{
      background:linear-gradient(180deg,#e8eef6 0%,#cfdbee 55%,#b2c9ea 100%);
      border:1px solid #8ea9cc;color:#0b2e59;font-weight:800;padding:8px 12px;border-radius:12px;
      text-decoration:none;display:inline-flex;align-items:center;gap:8px;
      box-shadow:inset 0 1px 0 rgba(255,255,255,.65), 0 6px 16px rgba(18,61,101,.15);
      font-size:.9rem;
    }
    .btn-silver-blue:hover{filter:saturate(1.05)}
    a:focus-visible, button:focus-visible, .btn-silver-blue:focus-visible{outline:3px solid rgba(13,110,253,.33); outline-offset:2px}

    /* Δεξιά στήλη */
    .right-column{ display:flex; flex-direction:column; gap:14px; }
    .preview-card{ background:#fff; border: 1px solid #e6ecf7; border-radius: 14px; box-shadow: 0 10px 18px rgba(15,27,45,.08); overflow:hidden; }
    .preview-media{ width:100%; height: 220px; object-fit: cover; display:block; }
    .preview-footer{ padding: 8px 10px; border-top: 1px solid #e6ecf7; text-align:center; font-weight:700; color:#163a74; background: linear-gradient(180deg, #e9f0ff, #d7e3ff); font-size:.92rem; }
    @media (max-width: 1024px){ .preview-media{ height: 200px; } }

    /* PDF & λοιπά */
    .desc-pdf-section{ margin-top: 26px; background:#fff; border:1px solid #e6e9ef; border-radius: 12px; padding: 16px 18px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
    .desc-pdf-section h3{ margin:0 0 12px 0; color:#003366; font-size:20px; font-weight:700; }
    .pdf-viewer-container{ height: 520px; border:1px solid #e6e9ef; border-radius: 10px; overflow:hidden; background:#f9fafb; }
    .pdf-viewer-container iframe{ width:100%; height:100%; border:none; }
    .pdf-actions{ display:flex; gap:14px; margin-top:10px; }
    .pdf-actions a{ font-weight:700; color:#003366; text-decoration:none; }
    .pdf-actions a:hover{ text-decoration: underline; }

    .section-title{ color:#003366; text-align:center; margin: 42px 0 14px; font-size:20px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px; }
    .file-wrapper{ margin-top: 26px; }
    .file-container{ background:#fff; border:1px solid #e6ecf7; border-radius: 14px; padding: 14px 12px 12px; box-shadow: 0 10px 22px rgba(15,27,45,.08); position:relative; margin-bottom: 22px; }
    .delete-btn{ position:absolute; top:10px; right:10px; background: linear-gradient(180deg, #e9f0ff, #d7e3ff); color:#163a74; border:1px solid #c7d7f7; padding:6px 12px; border-radius: 999px; font-weight:800; cursor:pointer; box-shadow: 0 10px 20px rgba(11,75,166,.12), inset 0 1px 0 #fff; }
    .delete-btn:hover{ transform: translateY(-1px); }
    .links-list{ list-style:none; padding-left:0; max-width:960px; margin:0 auto; }
    .links-item{ background:#fff; border:1px solid #e6ecf7; border-radius: 12px; padding:10px 12px; margin-bottom:10px; display:flex; align-items:center; gap:10px; justify-content: space-between; box-shadow: 0 10px 22px rgba(15,27,45,.08); }
    .links-item a{ font-weight:800; color:#003366; text-decoration:none; word-break: break-all; }
    .links-item a:hover{ text-decoration: underline; }

    .back-button-wrapper{ display:flex; justify-content:center; margin: 26px 0 8px; }
    .btn-back{ display:inline-block; width:auto; background: linear-gradient(180deg, #e9f0ff, #d7e3ff); color:#163a74; font-weight:800; font-size:15px; padding:10px 18px; border:1px solid #c7d7f7; border-radius:12px; text-decoration:none; box-shadow: 0 10px 20px rgba(11,75,166,.12), inset 0 1px 0 #fff; }
    .btn-back:hover{ transform: translateY(-1px); }

    footer{ width:100%; background-color: rgba(0, 51, 102, 0.92); color:white; text-align:center; padding:30px; margin-top:auto; }

    @media print{
      body{background:#fff}
      header.site-header, .right-column, footer{display:none !important}
      .readonly-form{box-shadow:none;border-color:#ccc}
    }
    @media (prefers-reduced-motion: reduce){
      *{animation:none !important; transition:none !important}
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
      <a href="profile_edit.php">Το Προφιλ Μου</a>
    </nav>
    <span class="user-info"><a href="loginn.php" style="color:#ccc">Έξοδος</a></span>
  </div>
</header>

<div class="content">

  <h2 class="page-title">
    <img src="data_thesis.png" alt="Εικόνα Διπλωματικής">
    Προβολή Διπλωματικής Εργασίας
  </h2>

  <div class="wrapper">
    <?php if ($thesis): ?>
      <form class="readonly-form">
        <div class="info-grid">

          <!-- Τίτλος -->
          <div class="form-group form-group--full">
            <label>Τίτλος:</label>
            <input type="text" readonly data-field="title" value="<?php echo htmlspecialchars($thesis['title']); ?>">
            <div id="thesisTitle"
                 class="title-collapsible"
                 tabindex="0"
                 role="button"
                 aria-expanded="false"
                 aria-label="Εναλλαγή πλήρους προβολής τίτλου">
              <?php echo htmlspecialchars($thesis['title']); ?>
              <span class="title-more-hint">Περισσότερα</span>
            </div>
          </div>

          <!-- Περιγραφή -->
          <div class="form-group form-group--full">
            <label>Περιγραφή:</label>
            <div id="thesisDesc"
                 class="desc-collapsible"
                 tabindex="0"
                 role="button"
                 aria-expanded="false"
                 aria-label="Εναλλαγή πλήρους προβολής περιγραφής">
              <?php echo nl2br(htmlspecialchars($thesis['description'] ?? '—')); ?>
              <span class="desc-more-hint">Περισσότερα</span>
            </div>
          </div>

          <!-- Κατάσταση -->
          <div class="form-group">
            <label>Κατάσταση:</label>
            <input type="text" readonly data-field="status"
                   value="<?php echo htmlspecialchars($thesis['status']); ?>">
            <span class="pill" data-role="status-pill" aria-live="polite"></span>
          </div>

          <!-- Επιτροπή-->
          <div class="form-group">
            <label>Μέλη Τριμελούς:</label>
            <input type="text" readonly data-field="committee"
                   value="<?php echo htmlspecialchars($thesis['committee_members']); ?>">
            <div class="chips" data-role="committee"></div>
          </div>

          <div class="form-group">
            <label>Ημερομηνία Έναρξης:</label>
            <input type="text" readonly value="<?php echo htmlspecialchars($thesis['start_date']); ?>">
          </div>

          <div class="form-group">
            <label>Διάρκεια Ανάθεσης:</label>
            <input type="text" readonly value="<?php echo $time_elapsed; ?>">
          </div>

          <?php if (!empty($thesis['repository_link'])): ?>
          <div class="form-group form-group--full">
            <label>Αποθετήριο (Νημερτής):</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
              <a id="repoLink" class="btn-silver-blue"
                 href="<?php echo htmlspecialchars($thesis['repository_link']); ?>"
                 target="_blank" rel="noopener">Άνοιγμα</a>
              <button type="button" class="btn-silver-blue" id="copyRepoBtn">Αντιγραφή συνδέσμου</button>
              <span id="copyRepoMsg" style="font-weight:700;color:#0b2e59"></span>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </form>

      <div class="right-column">
        <div class="preview-card">
          <img class="preview-media" src="weather_forecast.png" alt="Thesis Visual">
        </div>
      </div>
    <?php else: ?>
      <p>Δεν βρέθηκαν στοιχεία για τη διπλωματική.</p>
    <?php endif; ?>
  </div>

  <?php
    // Paths & web base
    $uploadsDirFs       = __DIR__ . '/uploads/';
    $uploadsDraftsDirFs = __DIR__ . '/uploads/drafts/';
    $webBase            = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // PDF περιγραφής διδάσκοντα
    $descDirFs   = __DIR__ . '/uploads/theses_pdfs/';
    $descUrlBase = $webBase . '/uploads/theses_pdfs';
    $descPdfUrl  = null;

    if ($thesis_id > 0 && is_dir($descDirFs)) {
        $patterns = [
            $descDirFs . "thesis_{$thesis_id}.pdf",
            $descDirFs . "thesis-{$thesis_id}.pdf",
            $descDirFs . "{$thesis_id}.pdf",
            $descDirFs . "{$thesis_id}_*.pdf",
            $descDirFs . "*_{$thesis_id}.pdf",
            $descDirFs . "*{$thesis_id}*.pdf",
        ];
        foreach ($patterns as $pat) {
            foreach (glob($pat) as $found) {
                if (is_file($found)) {
                    $descPdfUrl = $descUrlBase . '/' . rawurlencode(basename($found));
                    break 2;
                }
            }
        }
    }
  ?>

  <?php if ($descPdfUrl): ?>
    <div class="desc-pdf-section">
      <h3>Αρχείο Περιγραφής (Από τον/την Διδάσκοντα)</h3>
      <div class="pdf-viewer-container">
        <iframe src="<?php echo htmlspecialchars($descPdfUrl); ?>#view=FitH" title="Περιγραφή Διπλωματικής"></iframe>
      </div>
      <div class="pdf-actions">
        <a href="<?php echo htmlspecialchars($descPdfUrl); ?>" target="_blank" rel="noopener">Άνοιγμα σε νέο παράθυρο</a>
        <a href="<?php echo htmlspecialchars($descPdfUrl); ?>" download>Λήψη PDF</a>
      </div>
    </div>
  <?php else: ?>
    <div class="desc-pdf-section" style="background:#f9fafb;">
      <h3>Αρχείο Περιγραφής (Από τον/την Διδάσκοντα)</h3>
      <div style="color:#556; font-style:italic;">Δεν βρέθηκε αρχείο περιγραφής στον φάκελο <strong>uploads/theses_pdfs</strong> για αυτή τη διπλωματική.</div>
    </div>
  <?php endif; ?>

  <?php
    $stmtFiles = $pdo->prepare("
      SELECT filename 
      FROM attachments 
      WHERE thesis_id = :thesis_id AND student_id = :student_id
    ");
    $stmtFiles->execute([
      'thesis_id'  => $thesis_id,
      'student_id' => $student_id
    ]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);
  ?>

  <?php if ($files): ?>
    <h3 class="section-title">
      <img src="files.png" alt="Αρχεία" style="height: 24px;">
      Πρόχειρο Αρχείο Κειμένου
    </h3>

    <div class="file-wrapper">
      <?php foreach ($files as $filename):
        $filename = (string)$filename;
        $fileUrl  = null;
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (is_file($uploadsDirFs . $filename)) {
            $fileUrl = $webBase . '/uploads/' . rawurlencode($filename);
        } elseif (is_file($uploadsDraftsDirFs . $filename)) {
            $fileUrl = $webBase . '/uploads/drafts/' . rawurlencode($filename);
        }
      ?>
        <div class="file-container">
          <form method="POST" action="" onsubmit="return confirm('Σίγουρα θέλεις να διαγράψεις το αρχείο;');">
            <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($filename); ?>">
            <button type="submit" class="delete-btn">Διαγραφή</button>
          </form>

          <p style="font-weight:800; margin: 2px 0 10px;"><?php echo htmlspecialchars($filename); ?></p>

          <?php if ($fileUrl): ?>
            <?php if ($ext === 'pdf'): ?>
              <embed src="<?php echo htmlspecialchars($fileUrl); ?>" type="application/pdf" width="100%" height="520px" style="border:none; border-radius:10px;" />
            <?php else: ?>
              <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" download style="font-weight:800; color:#003366; text-decoration:none;">⬇️ Λήψη αρχείου</a>
            <?php endif; ?>
          <?php else: ?>
            <div style="color:#b00">Το αρχείο δεν βρέθηκε στον δίσκο.</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="text-align:center; color:#555; margin-top:20px;">Δεν υπάρχουν ακόμα ανεβασμένα αρχεία.</p>
  <?php endif; ?>

  <?php if (!empty($links)): ?>
    <h3 class="section-title">
      <img src="links.png" alt="Σύνδεσμοι" style="height: 22px;">
      Σύνδεσμοι Υλικού
    </h3>
    <ul class="links-list">
      <?php foreach ($links as $link): ?>
        <li class="links-item">
          <a href="<?= htmlspecialchars($link) ?>" target="_blank">🔗 <?= htmlspecialchars($link) ?></a>
          <form method="POST" action="" onsubmit="return confirm('Να διαγραφεί ο σύνδεσμος;');" style="display:inline;">
            <input type="hidden" name="delete_link" value="<?= htmlspecialchars($link) ?>">
            <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
            <button type="submit" class="delete-btn" style="position:static; padding:4px 10px; border-radius:10px;">Διαγραφή</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="back-button-wrapper">
    <a href="student_home.php" class="btn-back">Επιστροφή στην Αρχική Σελίδα</a>
  </div>

</div>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>


<script>
document.addEventListener('DOMContentLoaded', function(){

  /* Badge κατάστασης  */
  const statusInput = document.querySelector('.readonly-form [data-field="status"]');
  const statusPill  = document.querySelector('.readonly-form [data-role="status-pill"]');

  const classifyStatus = (s) => {
    const v = (s || '').toLowerCase();
    if (!v.trim()) return 'pill-neutral';
    if (v.includes('ολοκ') || v.includes('complete') || v.includes('comp') || v.includes('done')) return 'pill-success';
    if (v.includes('εκκρ') || v.includes('pending') || v.includes('αναμον')) return 'pill-warn';
    return 'pill-info';
  };

  if (statusInput && statusPill) {
    const txt = (statusInput.value || '').trim();
    statusPill.textContent = txt || '—';
    statusPill.classList.add(classifyStatus(txt));
  }

  /* Chips επιτροπής */
  const committeeInput = document.querySelector('.readonly-form [data-field="committee"]');
  const committeeBox   = document.querySelector('.readonly-form [data-role="committee"]');

  const esc = (s) => { const p = document.createElement('p'); p.textContent = s; return p.innerHTML; };

  if (committeeInput && committeeBox) {
    const items = (committeeInput.value || '')
      .split(',')
      .map(v => v.trim())
      .filter(Boolean);

    committeeBox.innerHTML = items.length
      ? items.map(m => `<span class="chip">${esc(m)}</span>`).join('')
      : '<span class="chip">—</span>';
  }

  const descBox = document.getElementById('thesisDesc');
  if (descBox) {
    const recompute = () => {
      const wasExpanded = descBox.classList.contains('expanded');
      descBox.classList.remove('expanded');
      descBox.setAttribute('aria-expanded','false');
      requestAnimationFrame(() => {
        const isOverflowing = descBox.scrollHeight > descBox.clientHeight + 1;
        descBox.classList.toggle('is-clamped', isOverflowing);
        if (wasExpanded) {
          descBox.classList.add('expanded');
          descBox.setAttribute('aria-expanded','true');
        }
      });
    };
    const toggle = () => {
      const expanded = descBox.classList.toggle('expanded');
      descBox.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };
    descBox.addEventListener('click', toggle);
    descBox.addEventListener('keydown', (e)=>{
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
    recompute();
    window.addEventListener('resize', recompute);
  }

  const titleBox = document.getElementById('thesisTitle');
  if (titleBox) {
    const recomputeTitle = () => {
      const wasExpanded = titleBox.classList.contains('expanded');
      titleBox.classList.remove('expanded');
      titleBox.setAttribute('aria-expanded','false');
      requestAnimationFrame(() => {
        const isOverflowing = titleBox.scrollHeight > titleBox.clientHeight + 1;
        titleBox.classList.toggle('is-clamped', isOverflowing);
        if (wasExpanded) {
          titleBox.classList.add('expanded');
          titleBox.setAttribute('aria-expanded','true');
        }
      });
    };
    const toggleTitle = () => {
      const expanded = titleBox.classList.toggle('expanded');
      titleBox.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };
    titleBox.addEventListener('click', toggleTitle);
    titleBox.addEventListener('keydown', (e)=>{
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleTitle(); }
    });
    recomputeTitle();
    window.addEventListener('resize', recomputeTitle);
  }

  /* Αντιγραφή αποθετηρίου */
  const copyBtn = document.getElementById('copyRepoBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', ()=>{
      const a = document.getElementById('repoLink');
      if (!a || !a.href) return;
      navigator.clipboard.writeText(a.href).then(()=>{
        const m = document.getElementById('copyRepoMsg');
        if (m) { m.textContent = 'Αντιγράφηκε ✓'; setTimeout(()=> m.textContent='', 1400); }
      });
    });
  }

});
</script>

</body>
</html>

