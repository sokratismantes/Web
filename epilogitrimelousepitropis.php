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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <meta charset="UTF-8">
    <title>Επιλογή Τριμελούς Επιτροπής</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonts + Bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        * {
    transition: background-color 0.3s, color 0.3s;
}

.container {
    animation: fadein 0.5s ease-in;
}

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

.main-title {
    font-weight: bold;
    color: rgba(0, 51, 102, 0.92);
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

        .error-message {
            color: red;
            font-weight: bold;
            display: none;
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

        /* Custom buttons */
        .btn-soft-blue {
        display: inline-block;
        padding: 10px 20px;
        background-color: transparent;
        color: #007bff;
        text-decoration: none;
        font-size: 16px;
        border: 2px solid #007bff;
        border-radius: 5px;
        font-weight: 500;
        transition: all 0.3s ease;
        margin-top: 45px;
        }

        .btn-soft-blue:hover {
            background-color: #0b5ed7;
        }

        .btn-soft-grey {
        display: inline-block;
        padding: 10px 20px;
        background-color: transparent;
        color: #007bff;
        text-decoration: none;
        font-size: 16px;
        border: 2px solid #007bff;
        border-radius: 5px;
        font-weight: 500;
        transition: all 0.3s ease;
        }

        .btn-soft-grey:hover {
            background-color: #5a6268;
        }

        .buttons-wrapper {
            margin-top: 60px; 
        }

        .avatar-name {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            text-align: left;
            justify-content: flex-start; 
        }

        .avatar {
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color:rgba(0, 51, 102, 0.92);;
            color: white;
            text-align: center;
            line-height: 60px;
            font-weight: bold;
            font-size: 22px;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 rgba(0, 123, 255, 0.5);
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0% {
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.5);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
            }
        }

        .professor-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            width: 360px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: left;
            transition: 0.3s ease;
        }

        .professor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }

        .toggle-btn {
            align-self: center;
            margin-top: 10px;
            padding: 5px 13px;
            font-size: 15px;
            font-weight: 500;
            border: none;
            border-radius: 30px;
            background: linear-gradient(135deg,rgba(0, 51, 102, 0.92),rgba(0, 51, 102, 0.92));
            color: white;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
        }

        .toggle-btn:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg,rgba(0, 51, 102, 0.92),rgba(0, 51, 102, 0.92));
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        .toggle-btn.selected {
            background: linear-gradient(135deg, #198754, #0f5132);
            color: white;
            font-weight: 400;
            transform: scale(1.07);
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        }

        .search-bar {
            max-width: 520px;       
            margin-top: -10px;      
            margin-bottom: 65px;    
            margin-left: auto;
            margin-right: auto;
        }

        @keyframes slideDownFade {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
        }

        .fade-in-out {
            opacity: 0;
            animation: fadeInOut 4.8s ease-in-out forwards;
        }

        @keyframes fadeInOut {
            0% {
                opacity: 0;
                transform: translateY(-30px);
            }
            10% {
                opacity: 1;
                transform: translateY(0);
            }
            90% {
                opacity: 1;
                transform: translateY(0);
            }
            100% {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        #message-container {
            position: fixed;
            top: 110px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
        }

        .success-message,
        .error-message-php {
            display: inline-block;
            padding: 18px 30px;
            font-size: 17px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-weight: 600;
            animation: fadeInOut 4.8s ease-in-out forwards;
        }

        .success-message {
            background: linear-gradient(90deg, #c1f0db, #a9eec2);
            color: #0a4d32;
        }

        .error-message-php {
            background: linear-gradient(90deg, #ffc2c2, #ffb0b0);
            color: #6e0000;
        }

        .section-title {
            color:rgba(0, 51, 102, 0.92);
            font-weight: 550;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1);
        }

        .dept-icon-clean {
            width: 90px;
            height: auto;
            object-fit: contain;
            border: none;
            box-shadow: none;
            padding: 0;
            background: none;
        }

        footer {
            flex-shrink: 0;
            width: 100%;
            background-color: rgba(0, 51, 102, 0.92);
            background-color:;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 70px;
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
            <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
        </div>
    </header>

<!-- Loader -->
<div id="loader">
    <div class="loader-inner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Φόρτωση...</span>
        </div>
        <p class="mt-3 text-muted">Φόρτωση σελίδας...</p>
    </div>
</div>

<!-- Περιεχόμενο -->

    <div class="d-flex align-items-center justify-content-center gap-3 mb-4" style="margin-top: 50px;">
        <img src="commitee.png" alt="Committee Icon" style="width: 48px;" class="img-fluid">
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
    <div class="container mt-4">
    <div class="container mt-2">
  <div class="input-group search-bar">
    <span class="input-group-text bg-white border-end-0">
      <i class="bi bi-search"></i>
    </span>
    <input type="text" id="search" class="form-control border-start-0" placeholder="Αναζήτηση καθηγητή με όνομα...">
  </div>
</div>

</div>

<!-- Dynamic Card Layout αντί για πίνακα -->
<div class="container d-flex flex-wrap justify-content-center gap-3" id="professor-list">
    <div>Φορτώνει δεδομένα...</div>
</div>

<div class="container mt-4 mb-2" style="max-width: 600px;">
    <label for="general_comment" class="form-label" style="font-weight: 500; color: rgba(0, 51, 102, 0.92);">
        Προαιρετικό Σχόλιο προς τους καθηγητές:
    </label>
    <textarea id="general_comment" name="general_comment" rows="3" class="form-control"
              placeholder="Γράψε ένα σύντομο μήνυμα ή σχόλιο που θα σταλεί μαζί με την πρόσκληση..."></textarea>
</div>

<div class="d-flex justify-content-center gap-3 mt-4">
        <button type="submit" name="submit_invitations" class="btn-soft-blue">Αποστολή Προσκλήσεων</button>
        <a href="student_home.php" class="btn-soft-blue">Επιστροφή στην Αρχική Οθόνη</a>
</div>
</form>
</div>

<!-- Κουμπιά εκτός container και κάτω από το πλαίσιο -->


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

    // Load professors + validate
    document.addEventListener("DOMContentLoaded", () => {
        const list = document.getElementById("professor-list");
        const form = document.getElementById("professors-form");
        const error = document.getElementById("error-message");
        const searchInput = document.getElementById("search");

        form.addEventListener("submit", function (e) {
            const checked = document.querySelectorAll('input[name="professors[]"]');
            if (checked.length > 2) {
                e.preventDefault();
                error.style.display = "block";
            } else {
                error.style.display = "none";
            }
        });

        fetch("fetch_theses(epilogitrimelousepitropis).php")
            .then(res => res.json())
            .then(data => {
    list.innerHTML = '';

    //Ταξινόμηση καθηγητών βάσει department
    data.sort((a, b) => {
        if (!a.department) return 1;
        if (!b.department) return -1;
        return a.department.localeCompare(b.department);
    });

    let currentDepartment = null;
    let section = null;
    let wrapper = null;

    data.forEach(p => {
        const dept = p.department ? p.department.trim() : "Άγνωστο Τμήμα";

        if (dept !== currentDepartment) {
            // Κλείσιμο προηγούμενου section
            currentDepartment = dept;

            // Δημιουργία νέου section
            section = document.createElement("div");
            section.className = "mb-5 p-3 rounded-4 shadow-sm";
            section.style.background = "rgba(255, 255, 255, 0.6)";
            section.style.border = "1px solid #ccc";

            const title = document.createElement("h4");
            title.className = "mb-4 text-center fw-bold section-title d-flex flex-row-reverse align-items-center justify-content-center gap-2";

            // Ορισμός εικόνας με βάση το τμήμα
            let imageSrc = "";
            switch (dept.toLowerCase()) {
                case "computer engineering and informatics":
                    imageSrc = "ceidlogo.png"; break;
                case "economics":
                    imageSrc = "economics.png"; break;
                case "electrical engineering":
                    imageSrc = "electrical.png"; break;
                case "environmental science":
                    imageSrc = "environmental.png"; break;
                default:
                    imageSrc = "default.png";
            }

            // Τίτλος με εικόνα
            title.innerHTML = `
            <img src="${imageSrc}" alt="${dept}" class="dept-icon-clean" style="width: 200px; height: 80px;">
            `;

            const infoLabel = document.createElement("div");
            infoLabel.textContent = "Συνεργαζόμενα Μέλη ΔΕΠ";
            infoLabel.style.fontWeight = "600";
            infoLabel.style.marginBottom = "5px";
            infoLabel.style.color = "rgba(0, 51, 102, 0.92)";
            infoLabel.style.textAlign = "left";
            infoLabel.style.width = "100%";
            infoLabel.style.fontSize = "15px";

            section.appendChild(infoLabel);

            section.appendChild(title);

            wrapper = document.createElement("div");
            wrapper.className = "d-flex flex-wrap justify-content-center gap-3";
            section.appendChild(wrapper);

            list.appendChild(section);
        }

        // Δημιουργία κάρτας
        const card = document.createElement("div");
        card.className = "professor-card";
        card.innerHTML = `
            <div class="avatar-name">
                <div class="avatar">${p.name.charAt(0)}</div>
                <div><strong>${p.name} ${p.surname}</strong></div>
            </div>
            <button type="button" class="toggle-btn" data-id="${p.professor_id}">
                Επιλογή
            </button>
        `;
        wrapper.appendChild(card);
    });

    // Attach listeners για toggle
    document.querySelectorAll(".toggle-btn").forEach(button => {
        button.addEventListener("click", () => {
            const id = button.getAttribute("data-id");
            const selected = button.classList.toggle("selected");

            if (selected) {
                const hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = "professors[]";
                hiddenInput.value = id;
                hiddenInput.classList.add("prof-hidden", `prof-${id}`);
                button.after(hiddenInput);
                button.innerText = "Επιλέχθηκε";
            } else {
                document.querySelector(`.prof-${id}`)?.remove();
                button.innerText = "Επιλογή";
            }
        });
    });
});


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