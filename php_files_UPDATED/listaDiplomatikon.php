<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

// Σύνδεση με βάση (PDO)
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$pdo = new PDO($dsn, $user, $pass, $options);

// === Επεξεργασία AJAX αιτημάτων ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'assign') {
        header('Content-Type: application/json');

        $thesis_id    = (int)($_POST['thesis_id'] ?? 0);
        $student_id   = (int)($_POST['student_id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $professor_id = (int)($_SESSION['professor_id'] ?? 0);

        if ($thesis_id <= 0 || $student_id <= 0) {
            echo json_encode(["status"=>"error","message"=>"Λείπουν υποχρεωτικά πεδία."]);
            exit;
        }
        if ($professor_id <= 0) {
            echo json_encode(["status"=>"error","message"=>"Δεν υπάρχει καθηγητής στη συνεδρία. Κάνε ξανά είσοδο."]);
            exit;
        }

        try {
            // Κατοχύρωση προσωρινά του θέματος στον φοιτητή (ΜΟΝΟ αν ανήκει στον επιβλέποντα και δεν έχει ήδη student)
            $sql = "
                UPDATE theses
                SET student_id = :sid
                WHERE thesis_id = :tid
                  AND supervisor_id = :pid
                  AND (student_id IS NULL OR student_id = 0)
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sid' => $student_id,
                ':tid' => $thesis_id,
                ':pid' => $professor_id
            ]);

            if ($stmt->rowCount() === 1) {
                echo json_encode(["status"=>"success","message"=>"Η διπλωματική κατοχυρώθηκε προσωρινά στον φοιτητή."]);
            } else {
                echo json_encode(["status"=>"error","message"=>"Αποτυχία ανάθεσης. Ελέγξτε ότι το θέμα σας είναι διαθέσιμο και δεν έχει ήδη φοιτητή."]);
            }
        } catch (PDOException $e) {
            echo json_encode(["status"=>"error","message"=>"Σφάλμα ανάθεσης: ".$e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'revoke') {
        $thesis_id = intval($_POST['thesis_id']);

        $stmt = $pdo->prepare("CALL RevokeThesis(?)");
        $stmt->execute([$thesis_id]);

        echo json_encode(["status" => "success", "message" => "Η ανάθεση αναιρέθηκε."]);
        exit;
    }
}

// === Αναζήτηση φοιτητών με AJAX ===
if (isset($_GET['action']) && $_GET['action'] === 'search_student') {
    header('Content-Type: application/json');
    $q = "%".($_GET['q'] ?? "")."%";
    $sql = "SELECT student_id, am, CONCAT(name,' ',surname) AS fullname 
            FROM students 
            WHERE am LIKE ? OR CONCAT(name,' ',surname) LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$q, $q]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
    exit;
}

// === Ανάκτηση ΜΟΝΟ ελεύθερων διπλωματικών του συνδεδεμένου επιβλέποντα ===
if (isset($_GET['action']) && $_GET['action'] === 'fetch_theses') {
    // NO-CACHE headers για να μην «κολλάνε» τα αποτελέσματα ανά login
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    $search = "%".($_GET['search'] ?? "")."%";
    $professor_id = (int)($_SESSION['professor_id'] ?? 0);
    if ($professor_id <= 0) { echo json_encode([]); exit; }

    $sql = "SELECT thesis_id, title, description, status, start_date, end_date, supervisor_id
            FROM theses
            WHERE supervisor_id = :pid
              AND (student_id IS NULL OR student_id = 0)
              AND (title LIKE :q OR status LIKE :q)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $professor_id, ':q' => $search]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($theses);
    exit;
}

// === Ανάκτηση λίστας φοιτητών ===
if (isset($_GET['action']) && $_GET['action'] === 'fetch_students') {
    header('Content-Type: application/json');
    $sql = "SELECT student_id, student_number, name, surname 
            FROM students";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
    exit;
}

// === Έλεγχος αν ο φοιτητής έχει ήδη ανάθεση ===
if (isset($_GET['action']) && $_GET['action'] === 'check_student_assignment') {
    header('Content-Type: application/json');
    $student_id = intval($_GET['student_id'] ?? 0);

    $sql = "SELECT thesis_id 
            FROM theses 
            WHERE student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        echo json_encode(["assigned" => true, "thesis_id" => $exists['thesis_id']]);
    } else {
        echo json_encode(["assigned" => false]);
    }
    exit;
}

// Εμφάνιση ειδοποίησης (HTML μέρος)
$success_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λίστα Διπλωματικών</title>
    <style>
        body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
    color: #333;
}

.container {
    margin: 30px auto;
    padding: 25px;
    max-width: 1200px;
    background-color: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

h1, h2 {
    text-align: center;
    color: #004085;
    margin-bottom: 20px;
    font-weight: 600;
}

.search-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 25px;
    gap: 10px;
    flex-wrap: wrap;
}

.search-bar input {
    padding: 12px;
    width: 45%;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 1rem;
    transition: 0.3s;
}
.search-bar input:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px #007bff66;
    outline: none;
}

button, .add-button, .back-button {
    padding: 10px 18px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.add-button {
    background: linear-gradient(135deg, #28a745, #218838);
    color: white;
    display: block;
    margin: 20px auto;
    text-align: center;
    text-decoration: none;
}
.add-button:hover { opacity: 0.85; }

.back-button {
    background: linear-gradient(135deg, #0056b3, #004494);
    color: white;
    display: block;
    margin: 30px auto 0;
    text-align: center;
    text-decoration: none;
}
.back-button:hover { opacity: 0.85; }

.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    margin-top: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

table th, table td {
    padding: 14px;
    text-align: left;
    border: 1px solid #e6e6e6;
}

table th {
    background-color: #007bff;
    color: white;
    font-weight: 600;
}

table tr:nth-child(even) {
    background-color: #f8f9fa;
}

table tr:hover {
    background-color: #eaf4ff;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    color: white;
    transition: 0.2s;
}
.assign-btn { background: #28a745; }
.assign-btn:hover { background: #218838; }
.revoke-btn { background: #dc3545; }
.revoke-btn:hover { background: #b52b38; }

.message {
    text-align: center;
    margin-bottom: 20px;
    font-weight: bold;
    color: green;
}

/* Modal Form */
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center; align-items: center;
}
.modal-content {
    background: white;
    padding: 25px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}
.modal-content h3 {
    margin-top: 0;
    color: #004085;
}
.modal-content input, .modal-content textarea {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

.cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.thesis-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.thesis-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.thesis-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #004085;
}
.thesis-status {
    font-size: 0.9rem;
    margin-bottom: 10px;
    color: #666;
}
.thesis-dates {
    font-size: 0.85rem;
    margin-bottom: 10px;
    color: #444;
}
.card-actions {
    margin-top: 10px;
}

.card-actions {
    margin-top: 15px;
    display: flex;
    justify-content: flex-end; /* τοποθέτηση δεξιά */
}

.assign-btn {
    background: #28a745;
    color: white;
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.3s;
}
.assign-btn:hover {
    background: #218838;
}

.modal-field {
    margin-bottom: 12px;
}
.modal-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #333;
}
.modal-field input, 
.modal-field textarea, 
.modal-field select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
}

    </style>
</head>
<body>
    
        <?php if (!empty($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <h1>Λίστα Διπλωματικών</h1>

        <div class="search-bar">
            <input type="text" id="search" placeholder="Αναζήτηση διπλωματικών..." onkeyup="fetchTheses()">
            <input type="text" id="studentSearch" placeholder="Αναζήτηση φοιτητή (ΑΜ ή Ονοματεπώνυμο)" onkeyup="fetchStudents()">
            <button onclick="fetchTheses()">Αναζήτηση</button>
        </div>

        <a href="addThesis.php" class="add-button">Προσθήκη Νέου Θέματος</a>

        <div class="table-wrapper">
            <h2>Διαθέσιμες Διπλωματικές</h2>
            <div class="cards-container" id="theses-cards">
                <div class="thesis-card">
                    <h3 class="thesis-title">Φορτώνει δεδομένα...</h3>
                </div>
            </div>

            <!-- Modal Φόρμα -->
        <div id="assignModal" class="modal">
        <div class="modal-content">
            <h3>Ανάθεση Θέματος</h3>
            
            <!-- Κρυφό ID διπλωματικής -->
            <input type="hidden" id="assignThesisId">

            <!-- Πεδίο Θέματος -->
            <div class="modal-field">
                <label>Θέμα:</label>
                <input type="text" id="assignThesisTitle" readonly>
            </div>

            <div class="modal-field">
                <label>Περιγραφή:</label>
                <textarea id="assignThesisDescription" rows="3" readonly></textarea>
            </div>

            <!-- Επιλογή Φοιτητή -->
            <div class="modal-field">
                <label>Φοιτητής:</label>
                <select id="assignStudentSelect">
                    <option value="">-- Επιλέξτε φοιτητή --</option>
                </select>
            </div>

            <!-- Στοιχεία Φοιτητή -->
            <div id="studentInfo" style="display:none; margin-top:10px; font-size:0.9rem; color:#444;">
                <p><strong>Όνομα:</strong> <span id="studentName"></span></p>
                <p><strong>Email:</strong> <span id="studentEmail"></span></p>
            </div>

            <div class="modal-actions">
            <button type="button" class="revoke-btn" onclick="closeModal()">✖ Ακύρωση</button>
            <button type="button" class="assign-btn" onclick="submitAssign()">✔ Ανάθεση</button>
            </div>
        </div>
        </div>

        <a href="professor_home.php" class="back-button">Επιστροφή στον Πίνακα Ελέγχου</a>

    <script>
        
// === Φόρτωση διπλωματικών ===
function fetchTheses() {
    const qEl = document.getElementById('search');
    const q = qEl ? qEl.value.trim() : '';
    const url = 'listaDiplomatikon.php?action=fetch_theses'
              + '&search=' + encodeURIComponent(q)
              + '&_=' + Date.now(); // cache-buster

    fetch(url, { cache: 'no-store' })
    .then(r=>r.json())
    .then(data=>{
        const c=document.getElementById('theses-cards');
        c.innerHTML='';
        if(!data || data.length===0){ c.innerHTML='Δεν βρέθηκαν.'; return;}
        data.forEach(t=>{
            const d=document.createElement('div');
            d.className='thesis-card';
            d.innerHTML=`
                <div class="thesis-title">${t.title || ''}</div>
                <div class="thesis-status">${t.status || ''}</div>
                <div class="thesis-dates">Έναρξη: ${t.start_date||'—'} | Λήξη: ${t.end_date||'—'}</div>
                <div class="card-actions">
                  <button type="button" class="assign-btn" onclick='openAssignModal(${JSON.stringify(t)})'>Ανάθεση</button>
                </div>
            `;
            c.appendChild(d);
        });
    });
}

// === Άνοιγμα modal ===
function openAssignModal(thesis){
    document.getElementById('assignThesisId').value = thesis.thesis_id;
    document.getElementById('assignThesisTitle').value = thesis.title || '';
    document.getElementById('assignThesisDescription').value = thesis.description || '';
    loadStudents();
    document.getElementById('assignModal').style.display='flex';
}
function closeModal(){ document.getElementById('assignModal').style.display='none'; }

// === Γέμισμα φοιτητών ===
function loadStudents(){
    fetch('listaDiplomatikon.php?action=fetch_students&_=' + Date.now(), { cache: 'no-store' })
    .then(r=>r.json())
    .then(students=>{
        const sel=document.getElementById('assignStudentSelect');
        sel.innerHTML='<option value="">-- Επιλέξτε φοιτητή --</option>';
        students.forEach(s=>{
            const o=document.createElement('option');
            o.value=s.student_id;
            o.textContent=`${s.student_number} - ${s.name} ${s.surname}`;
            sel.appendChild(o);
        });
    });
}

// === Έλεγχος αν έχει ήδη ανάθεση ===
document.getElementById('assignStudentSelect').addEventListener('change',function(){
    const studentId=this.value;
    if(!studentId) return;
    fetch(`listaDiplomatikon.php?action=check_student_assignment&student_id=${encodeURIComponent(studentId)}&_=${Date.now()}`, { cache: 'no-store' })
    .then(r=>r.json())
    .then(d=>{
        if(d.assigned){
            alert("Αδυναμία Ανάθεσης: Ο φοιτητής έχει ήδη διπλωματική.");
            this.value="";
        }
    });
});

// === Υποβολή ανάθεσης ===
function submitAssign(){
    const thesisId=document.getElementById('assignThesisId').value;
    const studentId=document.getElementById('assignStudentSelect').value;
    const title=document.getElementById('assignThesisTitle').value;
    const description=document.getElementById('assignThesisDescription').value;

    if(!studentId){ alert("Επιλέξτε φοιτητή."); return; }

    fetch('listaDiplomatikon.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:
          `action=assign`+
          `&thesis_id=${encodeURIComponent(thesisId)}`+
          `&student_id=${encodeURIComponent(studentId)}`+
          `&title=${encodeURIComponent(title)}`+
          `&description=${encodeURIComponent(description)}`
    })
    .then(r=>r.text())              // βοηθά στο debug αν ο server δεν στείλει JSON
    .then(txt=>{
        try {
            const d = JSON.parse(txt);
            alert(d.message || 'Ολοκληρώθηκε.');
            if (d.status === 'success') {
                closeModal();
                fetchTheses();
            }
        } catch(e){
            console.error('Μη έγκυρο JSON από server:', txt);
            alert('Σφάλμα εξυπηρετητή.');
        }
    })
    .catch(err=>{
        console.error(err);
        alert('Σφάλμα δικτύου.');
    });
}

document.addEventListener('DOMContentLoaded',fetchTheses);
</script>
</body>
</html>
