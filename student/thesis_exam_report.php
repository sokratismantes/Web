<?php
$dsn = "mysql:host=localhost;dbname=vasst";
$dbuser = "root";
$dbpass = "";

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- AJAX: αποθήκευση συνδέσμου αποθετηρίου (Νημερτής)
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_GET['action'])
        && $_GET['action'] === 'save_repo') {

        header('Content-Type: application/json; charset=utf-8');

        $thesisId = filter_input(INPUT_POST, 'thesis_id', FILTER_VALIDATE_INT);
        $repoLink = trim($_POST['repository_link'] ?? '');

        if (!$thesisId || $thesisId <= 0) {
            echo json_encode(['status'=>'error','message'=>'❌ Δώσε έγκυρο thesis_id.']);
            exit;
        }
        if (!filter_var($repoLink, FILTER_VALIDATE_URL)) {
            echo json_encode(['status'=>'error','message'=>'❌ Δώσε έγκυρο URL.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE theses SET repository_link = :link WHERE thesis_id = :tid");
            $stmt->execute([':link'=>$repoLink, ':tid'=>$thesisId]);

            if ($stmt->rowCount() === 1) {
                echo json_encode(['status'=>'success','message'=>'✅ Ο σύνδεσμος αποθηκεύτηκε.']);
            } else {
                // είτε δεν βρέθηκε thesis_id, είτε η τιμή ήταν ήδη ίδια
                echo json_encode(['status'=>'error','message'=>'⚠️ Δεν έγινε αλλαγή (λάθος thesis_id ή ίδια τιμή).']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status'=>'error','message'=>'❌ Σφάλμα βάσης: '.$e->getMessage()]);
        }
        exit; // ΣΤΑΜΑΤΑ εδώ για το AJAX
    }

    // Από εδώ και κάτω είναι η κανονική ροή σελίδας
    $thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

    // ➜ ΠΛΗΡΗ ΟΝΟΜΑΤΑ (CONCAT name + surname) ΓΙΑ ΦΟΙΤΗΤΗ & 3 ΚΑΘΗΓΗΤΕΣ
    $stmt = $pdo->prepare("
        SELECT 
            t.title, 
            t.description, 
            t.final_grade, 
            t.repository_link, 
            CONCAT(s.name, ' ', s.surname) AS student_name,
            CONCAT(p1.name, ' ', p1.surname) AS sup_name, c.supervisor_id,
            CONCAT(p2.name, ' ', p2.surname) AS mem1_name, c.member1_id,
            CONCAT(p3.name, ' ', p3.surname) AS mem2_name, c.member2_id
        FROM theses t
        JOIN students s   ON s.student_id = t.student_id
        JOIN committees c ON c.thesis_id = t.thesis_id
        JOIN professors p1 ON p1.professor_id = c.supervisor_id
        JOIN professors p2 ON p2.professor_id = c.member1_id
        JOIN professors p3 ON p3.professor_id = c.member2_id
        WHERE t.thesis_id = :thesis_id
    ");
    $stmt->execute(['thesis_id' => $thesis_id]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thesis) die("Διπλωματική δεν βρέθηκε.");

    // === ΥΠΟΛΟΓΙΣΜΟΣ Μ.Ο. 3 ΒΑΘΜΩΝ & UPDATE ΣΤΟ theses.final_grade ===
    $grades_stmt = $pdo->prepare("SELECT professor_id, grade, exam_date FROM exam_results WHERE thesis_id = :thesis_id");
    $grades_stmt->execute(['thesis_id' => $thesis_id]);
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$grades) {
        die("Δεν έχουν υποβληθεί βαθμοί ακόμα.");
    }

    // map: professor_id => grade, exam_date
    $grade_map = [];
    foreach ($grades as $g) {
        $grade_map[$g['professor_id']] = [
            'grade' => is_numeric($g['grade']) ? (float)$g['grade'] : null,
            'exam_date' => $g['exam_date']
        ];
    }

    // πιάσε ΜΟΝΟ τους 3 βαθμούς της επιτροπής
    $committeeIds = [
        (int)$thesis['supervisor_id'],
        (int)$thesis['member1_id'],
        (int)$thesis['member2_id'],
    ];

    $committeeGrades = [];
    foreach ($committeeIds as $pid) {
        if (!isset($grade_map[$pid]['grade']) || !is_numeric($grade_map[$pid]['grade'])) {
            die("Δεν έχουν υποβληθεί και οι τρεις βαθμοί της επιτροπής ακόμα.");
        }
        $committeeGrades[] = (float)$grade_map[$pid]['grade'];
    }

    // υπολόγισε μ.ο. και στρογγύλεψε σε 2 δεκαδικά
    $avg = round(array_sum($committeeGrades) / 3, 2);

    // Αν το αποθηκευμένο final_grade είναι διαφορετικό/κενό, ενημέρωσέ το
    if (!isset($thesis['final_grade']) || (float)$thesis['final_grade'] !== (float)$avg) {
        $upd = $pdo->prepare("UPDATE theses SET final_grade = :avg WHERE thesis_id = :tid");
        $upd->execute([':avg' => $avg, ':tid' => $thesis_id]);
        $thesis['final_grade'] = $avg; // για άμεση εμφάνιση
    }

    // Ημερομηνία εξέτασης (πάρε από έναν βαθμό)
    $exam_date = $grades[0]['exam_date'];

    function getBadgeClass($grade) {
        if ($grade >= 8) return 'green';
        if ($grade >= 6) return 'orange';
        return 'red';
    }

} catch (PDOException $e) {
    die("Σφάλμα DB: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="el">
<head>
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
    <span class="user-info"> <a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
  </div>
</header>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <meta charset="UTF-8">
    <title>Πρακτικό Εξέτασης</title>
    <style>
        .site-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 40px;
    background-color: rgba(0, 51, 102, 0.92);
    color: white;
    box-shadow: 0 8px 8px -4px rgba(0, 0, 0, 0.2); 
    font-family: 'Segoe UI', sans-serif;
    margin-bottom: 60px;
    height: 150px;
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
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        body {
            font-family: Roboto;
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            color: #333;
            font-size: 0.96rem;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 0; 
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: hsla(211, 32.30%, 51.40%, 0.35);
            z-index: -1;
        }
        #loader {
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            background-color: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease;
        }
        #loader .loader-inner {
            animation: popin 0.6s ease-out;
            text-align: center;
        }
        @keyframes popin {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .panel {
            flex: 1 0 auto;
            max-width: 920px;
            margin: 70px auto 20px auto; 
            padding: 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            animation: fadein 0.5s ease-in;
        }

        @keyframes fadein {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2 {
            color: #003366;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .row { margin-bottom: 15px; }
        .label { font-weight: bold; }
        a { color: #007bff; }

        .grades-boxes {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .grade-card {
            background-color: #f8f9fb;
            border: 1px solid #d0d4da;
            border-radius: 10px;
            padding: 15px 20px;
            min-width: 150px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            transition: transform 0.2s ease;
        }

        .grade-card:hover { transform: scale(1.03); }

        .grade-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 6px;
        }

        .grade-score { font-size: 1.1rem; }

        .info-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .info-row .label {
            min-width: 150px;
            font-weight: bold;
        }

        hr {
            border: none;
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }

        .info-card {
            background: #f9fafb;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            min-width: 250px;
            flex: 1;
        }
        .info-card .label {
            font-weight: bold;
            margin-bottom: 6px;
        }
        .info-card a {
            color: #007bff;
            word-break: break-all;
        }

        .download-btn {
            background-color: transparent;
            color: #007bff;
            border: 2px solid #007bff;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .download-btn:hover,
        .download-btn:focus {
            background-color: #007bff;
            color: white;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
            transform: translateY(-1px);
        }

        .tooltip-name {
            position: relative;
            cursor: help;
        }

        .tooltip-name::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            white-space: nowrap;
            font-size: 0.75rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .tooltip-name:hover::after { opacity: 1; }

        .grade-score.badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.95rem;
            color: white;
        }
        .badge.green { background-color: #28a745; }
        .badge.orange { background-color: #ffc107; color: black; }
        .badge.red { background-color: #dc3545; }

        .comment-block {
        margin: 30px auto 30px auto;
        padding: 25px 30px;
        max-width: 1000px;
        background: rgba(0, 51, 102, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 16px;
        backdrop-filter: blur(10px);
        box-shadow: 0 8px 24px rgba(0, 51, 102, 0.92);
        animation: fadein 0.8s ease-in;
        font-size: 1.05rem;
        color: #fefefe;
        position: relative;
    }

.green-grade {
    color: #28a745;
    font-weight: bold;
}

.timeline-box-wrapper {
    position: absolute;
    top: 40px;
    right: 50px;
    z-index: 100;
}

.timeline-box {
    position: fixed;
    top: 170px;
    right: 30px;
    background-color: #ffffff;
    border-left: 4px solid #007bff;
    padding: 14px 18px;
    border-radius: 10px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    width: 220px;
    font-size: 0.88rem;
    color: #333;
    z-index: 1000;

    /* Fade-in setup */
    opacity: 0;
    transition: opacity 0.8s ease;
}

.timeline-box h5 {
    margin-top: 0;
    font-size: 1rem;
    margin-bottom: 10px;
    color: #003366;
}

.timeline-box ul {
    list-style-type: none;
    padding-left: 0;
    margin: 0;
}

.timeline-box li {
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .timeline-box-wrapper {
        display: none;
    }
}

.form-container.repo-card {
  max-width: 640px;
  margin: 24px auto;
  padding: 22px 20px;
  background: #ffffff;
  border: 1px solid #e6e9ef;
  border-radius: 12px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

.form-container.repo-card h2 {
  margin: 0 0 14px 0;
  font-size: 20px;        /* μικρό τίτλο */
  line-height: 1.25;
  color: #0b2e59;
  letter-spacing: 0.2px;
}

.form-container.repo-card .form-group {
  margin-bottom: 14px;
}

.form-container.repo-card label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #334155;
  font-size: 14px;
}

#repository_link {
  width: 100%;
  height: 44px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  font-size: 14px;
  color: #1f2937;
  background: #fff;
  transition: border-color .2s ease, box-shadow .2s ease;
}

#repository_link::placeholder {
  color: #9aa7b5;
}

#repository_link:focus {
  outline: none;
  border-color: #0d6efd;
  box-shadow: 0 0 0 3px rgba(13,110,253,.15);
}

/* Κουμπί */
.btn-soft-blue {
  background-color: transparent;
  color: #0d6efd;
  border: 2px solid #0d6efd;
  padding: 10px 18px;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s ease;
}

.btn-soft-blue:hover,
.btn-soft-blue:focus {
  background-color: #0d6efd;
  color: #fff;
  transform: translateY(-1px);
}

/* Κεντράρισμα περιοχής κουμπιού που ήδη έχεις ως .button-center */
.form-container.repo-card .button-center {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
}

/* Μήνυμα αποτελέσματος */
#repo_msg {
  font-size: 14px;
  min-height: 18px; /* για να μην «πηδάει» το UI πριν εμφανιστεί μήνυμα */
}

footer {
    flex-shrink: 0;
    width: 100%;
    background-color: rgba(0, 51, 102, 0.92);
    background-color:;
    color: white;
    text-align: center;
    padding: 30px;
    margin-top: 20px;
}
    </style>
</head>
<body>

<div class="timeline-box">
    <h5>Χρονικό Διπλωματικής</h5>
    <ul>
        <li><strong>Ανάθεση:</strong> 2025-04-02</li>
        <li><strong>Εξέταση:</strong> <?= htmlspecialchars($exam_date) ?></li>
        <li><strong>Ολοκλήρωση:</strong> ✔️</li>
    </ul>
</div>
<div id="loader">
    <div class="loader-inner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Φόρτωση...</span>
        </div>
        <p class="mt-3 text-muted">Φόρτωση σελίδας...</p>
    </div>
</div>

<div class="panel">
    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 30px;">
        <img src="degree.png" alt="Icon" style="width: 42px;">
        <h2 style="margin: 0;">Πρακτικό Εξέτασης Διπλωματικής Εργασίας</h2>
    </div>

    <div class="info-row"><span class="label">Φοιτητής:</span> <span><?= htmlspecialchars($thesis['student_name']) ?></span></div>
    <hr>
    <div class="info-row"><span class="label">Τίτλος:</span> <span><?= htmlspecialchars($thesis['title']) ?></span></div>
    <hr>
    <div class="info-row"><span class="label">Περιγραφή:</span> <span><?= htmlspecialchars($thesis['description']) ?></span></div>
    <hr>
    <div class="info-row"><span class="label">Ημερομηνία Εξέτασης:</span> <span><?= htmlspecialchars($exam_date) ?></span></div>
    <hr>

    <h3 style="margin-top: 30px; text-align: center;">Βαθμολογία Επιτροπής</h3>
    <div class="grades-boxes">
        <?php
        $professors = [
            ['name' => $thesis['sup_name'],  'id' => $thesis['supervisor_id']],
            ['name' => $thesis['mem1_name'], 'id' => $thesis['member1_id']],
            ['name' => $thesis['mem2_name'], 'id' => $thesis['member2_id']]
        ];
        foreach ($professors as $prof) {
            $grade = $grade_map[$prof['id']]['grade'] ?? null;
            $badgeClass = $grade ? getBadgeClass($grade) : '';
            $displayGrade = $grade ? "<span class='badge $badgeClass'>{$grade}/10</span>" : "–";
            echo "<div class='grade-card'>
                    <div class='grade-name tooltip-name' data-tooltip='Καθηγητής της επιτροπής'>".htmlspecialchars($prof['name'])."</div>
                    <div class='grade-score'>$displayGrade</div>
                  </div>";
        }
        ?>
    </div>

    <div style="display: flex; gap: 20px; margin-top: 25px; flex-wrap: wrap;">
        <div class="info-card">
            <div class="label">Τελικός Βαθμός:</div>
            <div id="finalScore" data-score="<?= htmlspecialchars($thesis['final_grade']) ?>"></div>
        </div>

        <?php if ($thesis['repository_link']): ?>
        <div class="info-card">
            <div class="label">Αποθετήριο (Νημερτής):</div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="<?= htmlspecialchars($thesis['repository_link']) ?>" target="_blank">
                    <?= htmlspecialchars($thesis['repository_link']) ?>
                </a>
                <div id="qrcode"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 30px;">
    <button id="downloadPdfBtn" class="download-btn">Λήψη PDF</button>
</div>
</div>

<!-- Μικρό UI για Νημερτής -->
<div class="form-container repo-card">
  <h2>Τελικό Κείμενο (Αποθετήριο Νημερτής)</h2>

    
  <label for="repo_thesis_id" class="repo-label">Αναγνωριστικό Διπλωματικής</label>
  <input type="number" id="repo_thesis_id" class="repo-input" placeholder="π.χ. 125" min="1" required>

  <div class="form-group">
    <label for="repository_link">Σύνδεσμος αποθετηρίου:</label>
    <input type="url" id="repository_link" placeholder="https://nemertes.lis.upatras.gr/...">
  </div>

  <div class="form-group button-center">
    <button id="save_repo_btn" type="button" class="btn-submit btn-soft-blue">Αποθήκευση</button>
  </div>

  <div id="repo_msg" style="text-align:center; margin-top:8px; font-weight:600;"></div>
</div>

<div id="commentIntro" style="
    text-align: center;
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 50px;
    margin-bottom: 14px;
    opacity: 0;
    color: #003366;
    transition: opacity 1s ease;
">
    Δες παρακάτω τα σχόλια και τις παρατηρήσεις της επιτροπής:
</div>

<details class="comment-block">
  <summary> Παρατηρήσεις Επιτροπής</summary>
  <p>Η διπλωματική εργασία παρουσίασε σημαντική ερευνητική αξία...</p>
</details>


<?php if ($thesis['repository_link']): ?>
<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "<?= htmlspecialchars($thesis['repository_link']) ?>",
        width: 64,
        height: 64
    });
</script>
<?php endif; ?>

<script>
    window.addEventListener("load", () => {
        const loader = document.getElementById("loader");
        loader.style.opacity = "0";
        setTimeout(() => loader.style.display = "none", 500);
    });

    document.getElementById("downloadPdfBtn").addEventListener("click", function () {
        const element = document.querySelector(".panel");
        const opt = {
            margin: 0.5,
            filename: 'praktiko_ekshetasis.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().from(element).set(opt).save();
    });

    // Count-up τελικού βαθμού με έλεγχο τιμής
    const el = document.getElementById('finalScore');
    if (el) {
        const target = Number(el.dataset.score);
        if (!Number.isFinite(target)) {
            el.textContent = '—';
        } else {
            let current = 0;
            const step = 0.1;
            const interval = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(interval);
                    el.classList.add("green-grade");
                }
                el.textContent = current.toFixed(2) + "/10";
            }, 30);
        }
    }

    // Εμφάνιση της πρότασης πάνω από το comment block
    const intro = document.getElementById("commentIntro");
    if (intro) {
        setTimeout(() => {
            intro.style.opacity = 1;
        }, 600);
    }

    // Fade-in του Χρονικού
    const timelineBox = document.querySelector(".timeline-box");
    if (timelineBox) {
        setTimeout(() => {
            timelineBox.style.opacity = "1";
        }, 600);
    }
</script>

<script>
// POST στο ίδιο αρχείο με δράση ?action=save_repo
const ENDPOINT = window.location.pathname + '?action=save_repo';

document.getElementById('save_repo_btn')?.addEventListener('click', saveRepositoryLink);
// Enter για υποβολή
['repo_thesis_id','repository_link'].forEach(id=>{
  document.getElementById(id)?.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter'){ e.preventDefault(); saveRepositoryLink(); }
  });
});

async function saveRepositoryLink(){
  const msgEl   = document.getElementById('repo_msg');
  const idEl    = document.getElementById('repo_thesis_id');
  const linkEl  = document.getElementById('repository_link');

  const thesisId = Number(idEl.value);
  const link     = (linkEl.value || '').trim();

  if(!Number.isFinite(thesisId) || thesisId <= 0){
    msgEl.textContent = 'Δώσε έγκυρο αναγνωριστικό διπλωματικής (θετικός αριθμός).';
    msgEl.className = 'repo-msg err';
    idEl.focus();
    return;
  }
  if(!/^https?:\/\//i.test(link)){
    msgEl.textContent = 'Δώσε έγκυρο URL (π.χ. που ξεκινά με http ή https).';
    msgEl.className = 'repo-msg err';
    linkEl.focus();
    return;
  }

  msgEl.textContent = 'Αποθήκευση...';
  msgEl.className = 'repo-msg info';
  const btn = document.getElementById('save_repo_btn');
  btn.disabled = true;

  try{
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: new URLSearchParams({ thesis_id: String(thesisId), repository_link: link })
    });

    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const txt = await res.text();
      throw new Error('Μη αναμενόμενη απάντηση από τον server: ' + txt.slice(0,200));
    }

    const data = await res.json();
    if (data.status === 'success') {
      msgEl.textContent = data.message || '✅ Ο σύνδεσμος αποθηκεύτηκε.';
      msgEl.className = 'repo-msg ok';
    } else {
      throw new Error(data.message || 'Αποτυχία αποθήκευσης.');
    }
  }catch(err){
    msgEl.textContent = '❌ ' + (err.message || 'Σφάλμα αποθήκευσης.');
    msgEl.className = 'repo-msg err';
  }finally{
    btn.disabled = false;
  }
}
</script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

</body>
</html>

