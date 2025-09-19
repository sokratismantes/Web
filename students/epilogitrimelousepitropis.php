<?php 
session_start();

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst"; 

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_invitations']) && isset($_POST['professors'])) {
    if (count($_POST['professors']) > 2) {
        $message = "Σφάλμα: Δεν μπορείτε να επιλέξετε πάνω από 2 καθηγητές.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_type = 'student'");
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            $student_id = $user['user_id'];
        } else {
            die("Σφάλμα: Δεν βρέθηκε ID φοιτητή.");
        }

        $stmt = $conn->prepare("SELECT thesis_id FROM theses WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $thesis = $result->fetch_assoc();
        $stmt->close();

        if ($thesis) {
            $thesis_id = $thesis['thesis_id'];
        } else {
            die("Σφάλμα: Δεν βρέθηκε διπλωματική εργασία για τον φοιτητή.");
        }

        $conn->begin_transaction(); 

        try {
            $comment = $_POST['general_comment'] ?? "";

            foreach ($_POST['professors'] as $professor_id) {
                $stmt = $conn->prepare("CALL SendInvitationToProfessor(?, ?, ?, ?)");
                if ($stmt === false) {
                    throw new Exception("Σφάλμα στην προετοιμασία του statement: " . $conn->error);
                }

                $stmt->bind_param("iiis", $student_id, $thesis_id, $professor_id, $comment);

                if (!$stmt->execute()) {
                    throw new Exception("Σφάλμα στην εκτέλεση της procedure: " . $stmt->error);
                }
                $stmt->close();
            }

            $conn->commit();
            $message = "Οι προσκλήσεις στάλθηκαν επιτυχώς!";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Σφάλμα: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επιλογή Τριμελούς Επιτροπής</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Icons + Fonts + Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
:root{
  --brand:#0b4ba6;
  --brand-2:#003366;
  --ink:#1c2a3a;
  --muted:#5c6b80;
  --silver:#e9eef6;
  --card:#ffffff;
  --shadow:0 14px 30px rgba(15,27,45,.12);
  --radius:14px;
}

/* Base */
* { box-sizing: border-box; transition: background-color .25s, color .25s, border-color .25s, box-shadow .25s; }
html, body { height: 100%; }
body {
  font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
  margin:0; padding:0; color:#333;
  background: linear-gradient(to right, #e2e2e2, #c9d6ff);
}
body::before { content:""; position:fixed; inset:0; background-color: hsla(211, 32.3%, 51.4%, 0.35); z-index:-1; }

/* Header */
.site-header {
  display:flex; justify-content:space-between; align-items:center;
  padding:20px 40px; background-color: rgba(0, 51, 102, 0.92);
  color:white; box-shadow: 0 8px 8px -4px rgba(0,0,0,.2);
  margin-bottom: 10px; height:120px; position:relative; z-index:10;
  border-bottom-left-radius:14px; border-bottom-right-radius:14px;
  font-family:'Segoe UI',sans-serif;
}
.site-header .left{ display:flex; align-items:center; gap:10px; }
.site-header .logo{ width:95px; height:80px; }
.system-name{ font-size:20px; font-weight:600; }
.site-header .right{ display:flex; align-items:center; gap:20px; }
.site-header .right nav a{ color:#fff; text-decoration:none; margin-right:15px; }
.site-header .user-info{ font-weight:500; }

/* Loader */
#loader{ position:fixed; inset:0; background:#fff; z-index:9999; display:flex; align-items:center; justify-content:center; transition:opacity .5s; }
#loader .loader-inner{ text-align:center; animation: popin .6s ease-out; }
@keyframes popin { from{transform:scale(.92);opacity:0} to{transform:scale(1);opacity:1} }

/* Title Row */
.page-header {
  max-width: 1100px; margin: 68px auto 6px; padding: 0 16px; 
  display:flex; align-items:center; gap:12px; justify-content:center;
}
.page-header img{ width: 48px; height:auto; }
.section-title{
  color:var(--brand-2); font-weight:700; font-size:1.6rem; letter-spacing:.2px;
}

/* Message container */
#message-container{ position: fixed; top: 110px; left:50%; transform: translateX(-50%); z-index: 10000; }
.success-message, .error-message-php{
  display:inline-block; padding: 14px 22px; font-size: 16px; border-radius: 12px; 
  box-shadow:0 8px 20px rgba(0,0,0,.15); font-weight:600; animation: fadeInOut 4.8s ease-in-out forwards;
}
.success-message{ background: linear-gradient(90deg,#c1f0db,#a9eec2); color:#0a4d32; }
.error-message-php{ background: linear-gradient(90deg,#ffc2c2,#ffb0b0); color:#6e0000; }
@keyframes fadeInOut { 0%{opacity:0;transform:translateY(-12px)} 10%{opacity:1;transform:translateY(0)} 90%{opacity:1} 100%{opacity:0;transform:translateY(-8px)} }

/* Error message */
.alert.error-message{ display:none; max-width: 680px; margin: 16px auto 0; }

/* Search */
.search-wrap{
  max-width: 900px; margin: 16px auto 26px; padding: 0 16px;
}
.input-group.search-bar{
  border:1px solid var(--silver); border-radius:999px; overflow:hidden; background:#fff; box-shadow: 0 6px 16px rgba(11,75,166,.10);
}
.input-group-text{ background:#fff; border:none; }
#search.form-control{
  border:none; box-shadow:none; padding: 12px 4px; height: 48px;
}

/* Counter επιλογών */
.counter-row{
  max-width: 1100px; margin: -4px auto 8px; padding: 0 16px;
  display:flex; justify-content: space-between; align-items: center; gap:12px;
}
.sel-counter{
  background:#fff; border: 1px solid var(--silver); border-radius: 999px;
  padding: 8px 14px; box-shadow: 0 6px 16px rgba(15,27,45,.08);
  font-weight:600; color: var(--brand-2);
}
.char-counter{ color: var(--muted); font-size: .9rem; }

/* Department Section */
.dept-section{
  max-width: 1150px; margin: 26px auto; padding: 16px; 
  background: rgba(255,255,255,.66);
  border: 1px solid #dfe7f3; border-radius: 16px;
  box-shadow: 0 10px 26px rgba(19,33,68,.10);
}
.dept-head{
  display:flex; flex-direction:column; align-items:center; gap:10px; margin-bottom: 10px;
}
.dept-head .dept-icon-clean{ width: 200px; height: 80px; object-fit: contain; }
.dept-sub{ font-weight:600; color:var(--brand-2); font-size: 15px; }

/* Grid */
.cards-wrap{ display:flex; flex-wrap: wrap; justify-content:center; gap: 14px; }

/* Professor Card */
.professor-card{
  background: var(--card); border:1px solid #e6ecf7; border-radius: 14px;
  padding: 16px; width: 340px; max-width: 100%;
  box-shadow: 0 10px 22px rgba(15,27,45,.08);
  transition: transform .15s ease, box-shadow .15s ease;
}
.professor-card:hover{ transform: translateY(-3px); box-shadow: 0 14px 28px rgba(15,27,45,.12); }
.avatar-name{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
.avatar{
  flex-shrink:0; width:58px; height:58px; border-radius:50%;
  background: var(--brand-2); color:#fff; display:flex; align-items:center; justify-content:center;
  font-weight:700; font-size: 22px; border:3px solid #fff; box-shadow: 0 0 0 rgba(0, 123, 255, 0.5);
  animation: pulse-border 2s infinite;
}
@keyframes pulse-border { 0%{box-shadow:0 0 0 0 rgba(0,123,255,.5)} 70%{box-shadow:0 0 0 10px rgba(0,123,255,0)} 100%{box-shadow:0 0 0 0 rgba(0,123,255,0)} }
.prof-name{ font-weight:700; color: var(--ink); }
.prof-meta{ color: var(--muted); font-size:.92rem; }

/* Toggle Button */
.toggle-btn{
  margin-top: 8px; padding: 8px 16px; font-size: 15px; font-weight: 600;
  border: none; border-radius: 999px; cursor: pointer; color: #fff;
  background: linear-gradient(135deg, var(--brand-2), var(--brand-2));
  box-shadow: 0 6px 16px rgba(0,0,0,.12);
}
.toggle-btn:hover{ filter: brightness(1.05); }
.toggle-btn.selected{
  background: linear-gradient(135deg, #198754, #0f5132);
  transform: translateY(0);
  box-shadow: 0 8px 18px rgba(0,0,0,.16);
}

/* Comment */
.comment-container{
  max-width: 900px; margin: 18px auto 8px; padding: 0 16px;
}
.comment-container label{ font-weight:600; color: var(--brand-2); }
#general_comment{ resize: vertical; }

/* Actions */
.actions-row{
  display:flex; justify-content:center; gap:12px; margin: 18px auto 40px; flex-wrap: wrap;
}
.btn-soft-blue{
  display:inline-block; padding: 12px 22px; border-radius: 12px; border: 1px solid #c7d7f7;
  background: linear-gradient(180deg, #e9f0ff, #d7e3ff); color: #163a74; font-weight:700; 
  text-decoration:none; box-shadow: 0 10px 20px rgba(11,75,166,.12), inset 0 1px 0 #fff;
}
.btn-soft-blue:hover{ transform: translateY(-1px); box-shadow: 0 14px 24px rgba(11,75,166,.18); }
.btn-soft-blue:active{ transform: translateY(0); }

/* Footer */
footer{ width:100%; background-color: rgba(0, 51, 102, 0.92); color:white; text-align:center; padding:30px; margin-top: 50px; }

/* Professor cards – refreshed */
.professor-card{
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 340px;
  max-width: 100%;
  padding: 16px 16px 14px;
  border-radius: 16px;
  border: 1px solid transparent;
  background:
    linear-gradient(#ffffff, #ffffff) padding-box,
    linear-gradient(135deg, #dce8ff, #eef3ff) border-box;
  box-shadow: 0 10px 24px rgba(15,27,45,.10);
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.professor-card:hover{
  transform: translateY(-4px);
  box-shadow: 0 14px 28px rgba(15,27,45,.14);
}
.professor-card.selected-card{
  border-color: transparent;
  background:
    linear-gradient(#ffffff,#ffffff) padding-box,
    linear-gradient(135deg,#8fb4ff,#c2d4ff) border-box;
  box-shadow: 0 16px 36px rgba(16,64,139,.18);
}

.avatar-name{ gap: 14px; }
.avatar{
  width: 60px; height: 60px; border-radius: 50%;
  background: radial-gradient(circle at 30% 30%, #1b4ea1, #0b3e86);
  color: #fff; display:flex; align-items:center; justify-content:center;
  font-size: 22px; font-weight: 800; letter-spacing: .5px;
  border: 3px solid #fff;
  box-shadow: inset 0 0 0 2px #fff, 0 8px 16px rgba(11,62,134,.25);
}

.prof-name{
  font-weight: 800; color: #0f2244;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 230px;
}
.prof-meta{
  display: inline-block;
  margin-top: 2px; padding: 4px 10px;
  font-size: .84rem; color: #2b4a7f;
  background: #eff4ff; border: 1px solid #dbe7ff;
  border-radius: 999px;  
}

/* Button – silver blue */
.toggle-btn{
  margin-top: auto;           
  width: 100%;
  padding: 10px 16px;
  font-weight: 800; letter-spacing: .2px;
  border: 1px solid #b4c7f7;
  border-radius: 12px;
  background: linear-gradient(180deg, #dfe8ff, #bcd1ff);
  color: #0f2c66;
  box-shadow: 0 8px 18px rgba(21,72,160,.18), inset 0 1px 0 #fff;
  transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
}
.toggle-btn:hover{
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(21,72,160,.22);
  filter: brightness(1.03);
}
.toggle-btn:focus-visible{ outline: 3px solid #9cc2ff; outline-offset: 2px; }
.toggle-btn.selected{
  background: linear-gradient(180deg, #1aa362, #0f734a);
  color: #fff; border-color: #0d6b43;
}

@media (max-width: 520px){
  .professor-card{ width: 100%; }
  .prof-name{ max-width: 60vw; }
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

<!-- Loader -->
<div id="loader">
  <div class="loader-inner">
    <div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status">
      <span class="visually-hidden">Φόρτωση...</span>
    </div>
    <p class="mt-3 text-muted">Φόρτωση σελίδας...</p>
  </div>
</div>

<!-- Τίτλος -->
<div class="page-header">
  <img src="commitee.png" alt="Committee Icon">
  <h2 class="section-title">Επιλογή Μελών Επιτροπής</h2>
</div>

<?php if (isset($message)): ?>
  <div id="message-container">
    <div class="<?php echo (strpos($message, 'Σφάλμα') === 0) ? 'error-message-php' : 'success-message'; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  </div>
<?php endif; ?>

<div class="alert alert-danger text-center error-message" id="error-message">
  Δεν μπορείτε να επιλέξετε πάνω από 2 καθηγητές.
</div>

<form method="POST" id="professors-form">
  <!-- Αναζήτηση -->
  <div class="search-wrap">
    <div class="input-group search-bar">
      <span class="input-group-text border-0"><i class="bi bi-search"></i></span>
      <input type="text" id="search" class="form-control" placeholder="Αναζήτηση καθηγητή με όνομα...">
    </div>
  </div>

  <!-- Counter + char counter -->
  <div class="counter-row">
    <div class="sel-counter">Επιλεγμένοι: <span id="selCount">0</span>/2</div>
    <div class="char-counter" id="commentCounter" aria-live="polite"></div>
  </div>

  <!-- Λίστα Καθηγητών -->
  <div id="professor-list">
    <div class="text-center text-muted mt-2">Φορτώνει δεδομένα...</div>
  </div>

  <!-- Σχόλιο -->
  <div class="comment-container">
    <label for="general_comment" class="form-label">Προαιρετικό Σχόλιο προς τους καθηγητές:</label>
    <textarea id="general_comment" name="general_comment" rows="3" class="form-control" maxlength="300"
      placeholder="Γράψε ένα σύντομο μήνυμα ή σχόλιο που θα σταλεί μαζί με την πρόσκληση..."></textarea>
  </div>

  <!-- Ενέργειες -->
  <div class="actions-row">
    <button type="submit" name="submit_invitations" class="btn-soft-blue">Αποστολή Προσκλήσεων</button>
  </div>
</form>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
// Loader
window.addEventListener("load", () => {
  const loader = document.getElementById("loader");
  loader.style.opacity = "0";
  setTimeout(() => loader.style.display = "none", 500);
});

// Helpers
function updateSelCount(){
  const count = document.querySelectorAll('input[name="professors[]"]').length;
  document.getElementById('selCount').textContent = count;
}

function showErrorMax(){
  const error = document.getElementById("error-message");
  error.style.display = "block";
  setTimeout(() => error.style.display = "none", 2600);
}

document.addEventListener("DOMContentLoaded", () => {
  const list = document.getElementById("professor-list");
  const form = document.getElementById("professors-form");
  const searchInput = document.getElementById("search");
  const comment = document.getElementById("general_comment");
  const commentCounter = document.getElementById("commentCounter");

  // Μετρητής χαρακτήρων για σχόλιο
  const updateCommentCounter = () => {
    commentCounter.textContent = `Χαρακτήρες: ${comment.value.length}/300`;
  };
  comment.addEventListener('input', updateCommentCounter);
  updateCommentCounter();

  // Submit validation 
  form.addEventListener("submit", function (e) {
    const selected = document.querySelectorAll('input[name="professors[]"]');
    if (selected.length > 2) {
      e.preventDefault();
      showErrorMax();
    }
  });

  // Φόρτωση καθηγητών
  fetch("fetch_theses(epilogitrimelousepitropis).php")
    .then(res => res.json())
    .then(data => {
      list.innerHTML = "";

      // Ταξινόμηση ανά department
      data.sort((a, b) => {
        if (!a.department) return 1;
        if (!b.department) return -1;
        return a.department.localeCompare(b.department);
      });

      let currentDept = null, section, wrapper;

      data.forEach(p => {
        const dept = (p.department || "Άγνωστο Τμήμα").trim();

        // Νέα ενότητα τμήματος
        if (dept !== currentDept) {
          currentDept = dept;

          section = document.createElement("section");
          section.className = "dept-section";

          const head = document.createElement("div");
          head.className = "dept-head";

          // Εικόνα ανά τμήμα
          let imageSrc = "";
          switch (dept.toLowerCase()) {
            case "computer engineering & informatics": imageSrc = "ceidlogo.png"; break;
            case "economics": imageSrc = "economics.png"; break;
            case "electrical & computer engineering": imageSrc = "electrical.png"; break;
            case "environmental engineering": imageSrc = "environmental.png"; break;
            default: imageSrc = "default.png";
          }

          const img = document.createElement("img");
          img.src = imageSrc; img.alt = dept; img.className = "dept-icon-clean";

          const sub = document.createElement("div");
          sub.className = "dept-sub";
          sub.textContent = "Συνεργαζόμενα Μέλη ΔΕΠ";

          head.appendChild(img);
          head.appendChild(sub);

          wrapper = document.createElement("div");
          wrapper.className = "cards-wrap";

          section.appendChild(head);
          section.appendChild(wrapper);
          list.appendChild(section);
        }

        // Κάρτα καθηγητή
        const card = document.createElement("div");
        card.className = "professor-card";

        const initials = (p.name || "?").charAt(0).toUpperCase();
        const fullName = `${p.name || ""} ${p.surname || ""}`.trim();

        card.innerHTML = `
          <div class="avatar-name">
            <div class="avatar">${initials}</div>
            <div>
              <div class="prof-name">${fullName}</div>
              <div class="prof-meta">${dept}</div>
            </div>
          </div>
          <button type="button" class="toggle-btn" data-id="${p.professor_id}">Επιλογή</button>
        `;

        wrapper.appendChild(card);
      });

      // Επιλογή με όριο 2
      document.querySelectorAll(".toggle-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-id");
          const selectedInputs = document.querySelectorAll('input[name="professors[]"]');
          const alreadySelected = document.querySelector(`input[name="professors[]"].prof-${id}`);

          if (!alreadySelected) {
            if (selectedInputs.length >= 2) {
              showErrorMax();
              return; 
            }
            const hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = "professors[]";
            hidden.value = id;
            hidden.classList.add("prof-hidden", `prof-${id}`);
            btn.after(hidden);
            btn.classList.add("selected");
            btn.textContent = "Επιλέχθηκε";
          } else {
            alreadySelected.remove();
            btn.classList.remove("selected");
            btn.textContent = "Επιλογή";
          }
          updateSelCount();
        });
      });

      // Αρχική μέτρηση
      updateSelCount();
    });

  // Αναζήτηση
  searchInput.addEventListener("keyup", function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll(".professor-card").forEach(card => {
      card.style.display = card.textContent.toLowerCase().includes(val) ? "block" : "none";
    });
  });
});
</script>
</body>
</html>

<?php $conn->close(); ?>
