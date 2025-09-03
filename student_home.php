<?php
session_start(); // Έναρξη session

$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Έλεγχος αν ο χρήστης έχει συνδεθεί
    if (!isset($_SESSION['email'])) {
        header("Location: login.php");
        exit();
    }

    $email = $_SESSION['email']; // Ανάκτηση email από το session

    // Ανάκτηση ονόματος και επωνύμου χρήστη
    $stmt = $pdo->prepare("SELECT s.name, s.surname, s.student_number FROM students s JOIN users u ON s.student_id = u.user_id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Σφάλμα: Ο χρήστης με email $email δεν βρέθηκε.";
        exit();
    }

    $userFullName = $user['name'] . ' ' . $user['surname'];
    $studentNumber = $user['student_number'];
    $stmt = $pdo->prepare("SELECT student_id FROM students s JOIN users u ON s.student_id = u.user_id WHERE u.email = ?");
$stmt->execute([$email]);
$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
$student_id = $studentRow['student_id'];

// Έλεγχος αν υπάρχει περατωμένη διπλωματική για τον φοιτητή
$stmt = $pdo->prepare("SELECT COUNT(*) FROM theses WHERE student_id = :student_id AND status = 'Περατωμένη'");
$stmt->execute(['student_id' => $student_id]);
$hasFinalThesis = $stmt->fetchColumn() > 0;

    // Ανάκτηση δεδομένων διπλωματικών χωρίς φίλτρο αναζήτησης
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

    // Έλεγχος για διπλωματική "Ενεργή" που υπάρχει στην examinations και όχι στα αποτελέσματα
$examStmt = $pdo->prepare("
    SELECT e.exam_date, e.exam_time, t.thesis_id
    FROM theses t
    JOIN examinations e ON t.thesis_id = e.thesis_id
    LEFT JOIN exam_results r ON t.thesis_id = r.thesis_id
    WHERE t.student_id = :student_id 
    AND t.status = 'Υπο Εξέταση' 
    AND r.thesis_id IS NULL
    ORDER BY e.exam_id DESC
    LIMIT 1;
");
$examStmt->execute(['student_id' => $student_id]);
$pendingExam = $examStmt->fetch(PDO::FETCH_ASSOC);

$examInfoBox = "";
if ($pendingExam) {
    // Ενημέρωση κατάστασης διπλωματικής σε "Υπο Εξέταση"
    $updateStmt = $pdo->prepare("UPDATE theses SET status = 'Υπο Εξέταση' WHERE thesis_id = :thesis_id");
    $updateStmt->execute(['thesis_id' => $pendingExam['thesis_id']]);

    // Δημιουργία HTML για το box εμφάνισης
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
    <style>

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

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(245, 245, 245, 0.9);
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
        }

        .header a {
            text-decoration: none;
            display: flex;
            align-items: center;
            color: inherit;
        }

        .header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }

        .header span {
            font-size: 1.2rem;
            color: #0056b3;
        }

        .logout-button {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        .logout-button:hover {
            background-color: #555;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .action-button {
            display: block;
            width: fit-content;
            margin: 10px auto;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            text-align: center;
        }

        .action-button:hover {
            background-color: #218838;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
        }

        table th {
            background-color: #4da8da;
            color: white;
            text-align: center;
            padding: 10px;
        }

        table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        table tr:nth-child(even) {
            background-color: #e9f5ff;
        }

        table tr:hover {
            background-color: #f1f9ff;
            cursor: pointer;
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

        .back-to-list-container {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-list-button {
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

.back-to-list-button:hover {
    background-color: #007bff;
    color: white;
}

        .action-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.action-button {
    padding: 10px 20px;
    border: 2px solid transparent;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    background-color: transparent;
}

.action-button.green {
    color: #28a745;
    border-color: #28a745;
}

.action-button.green:hover {
    background-color: #28a745;
    color: white;
}

.action-button.blue {
    color: #007bff;
    border-color: #007bff;
}

.action-button.blue:hover {
    background-color: #007bff;
    color: white;
}

        .back-to-list-button:hover {
            background-color: #0056b3;
        }

        .thesis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.thesis-card {
    background-color:rgba(255, 255, 255, 0.87);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.thesis-card:hover {
    transform: scale(1.02);
    box-shadow: 0 8px 14px rgba(0,0,0,0.2);
}

.thesis-image img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.thesis-content {
    padding: 15px;
    text-align: left;
}

.thesis-content h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #003366;
}

.thesis-card {
    animation: fadein 0.5s ease-in;
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

        .user-welcome {
        display: flex;
        align-items: center;
        margin: 20px 40px;
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
        color:  #003366;
        margin-top: 4px;
    }

    .fade-box {
    position: absolute;
    top: 140px;
    right: 30px;
    background-color: #fff;
    border: 2px solid #007bff;
    border-radius: 10px;
    padding: 15px 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    font-weight: bold;
    z-index: 5;
    opacity: 0;
    animation: fadeIn 0.8s ease-in forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Ερώτηση κατά την αποσύνδεση
            function confirmLogout() {
                if (confirm("Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;")) {
                    window.location.href = "logout.php";
                }
            }

            // Προσθήκη συμβάντος στο κουμπί αποσύνδεσης
            const logoutButton = document.querySelector(".logout-button");
            logoutButton.addEventListener("click", confirmLogout);

            // Ερώτηση κατά την προσπάθεια επιστροφής με το κουμπί πίσω του browser
            window.history.pushState(null, "", window.location.href);
            window.onpopstate = function () {
                if (confirm("Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;")) {
                    window.location.href = "logout.php";
                } else {
                    window.history.pushState(null, "", window.location.href);
                }
            };

            // Φόρτωση δεδομένων διπλωματικών
            function fetchTheses(search = '') {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", student_home.php?search=${encodeURIComponent(search)}, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            // Δημιουργία ενός προσωρινού DOM parser
            const parser = new DOMParser();
            const doc = parser.parseFromString(xhr.responseText, "text/html");
            const newTableBody = doc.querySelector("#ajax-theses-table tbody");

            // Αντικατάσταση του υπάρχοντος πίνακα
            const tableBody = document.querySelector("#ajax-theses-table tbody");
            tableBody.innerHTML = newTableBody ? newTableBody.innerHTML : '<tr><td colspan="6">Δεν βρέθηκαν αποτελέσματα</td></tr>';
        }
    };
    xhr.send();
}


            // Αναζήτηση μέσω της φόρμας
            const searchForm = document.querySelector("#search-form");
searchForm.addEventListener("submit", function (e) {
    e.preventDefault(); // Αποτροπή ανανέωσης σελίδας
    const searchValue = document.querySelector("input[name='search']").value.trim();
    fetchTheses(searchValue); // Κλήση της fetchTheses με την τιμή αναζήτησης
});


            // Αρχική φόρτωση
            fetchTheses();
        });

        let chatbotVisible = false;

        function toggleChatbot() {
            chatbotVisible = !chatbotVisible;
            document.getElementById("chatbot-body").style.display = chatbotVisible ? "flex" : "none";
        }

        function handleQuestion(question) {
            addChatMessage("Χρήστης", question);
            getChatbotResponse(question);
        }

        function addChatMessage(sender, message) {
            const messageContainer = document.createElement("div");
            messageContainer.textContent = ${sender}: ${message};
            messageContainer.style.margin = "5px 0";
            document.getElementById("chatbot-messages").appendChild(messageContainer);
        }

        function getChatbotResponse(question) {
            const responses = {
                "Πώς μπορώ να αναζητήσω διπλωματική εργασία;": "Για να αναζητήσετε διπλωματική εργασία, πληκτρολογήστε τον τίτλο ή λέξεις-κλειδιά στο πεδίο αναζήτησης και πατήστε 'Αναζήτηση'.",
                "Πώς μπορώ να δω τις λεπτομέρειες μιας διπλωματικής;": "Κάντε κλικ σε οποιαδήποτε γραμμή στον πίνακα για να δείτε τις λεπτομέρειες της αντίστοιχης διπλωματικής.",
                "Πού μπορώ να διαχειριστώ τις διπλωματικές μου;": "Μπορείτε να διαχειριστείτε τις διπλωματικές σας πατώντας το κουμπί 'Διαχείριση Διπλωματικής Εργασίας'.",
                "Πώς να καλέσω έναν καθηγητή;": "Μπορείτε να στείλετε πρόσκληση σε καθηγητή πατώντας το κουμπί 'Πρόσκληση Καθηγητή'.",
                "Ποια είναι η χρήση του πίνακα στη σελίδα;": "Ο πίνακας εμφανίζει όλες τις διπλωματικές εργασίες, με πληροφορίες όπως τίτλος, κατάσταση, ημερομηνίες και μέλη επιτροπής.",
            };

            const botResponse = responses[question] || "Λυπάμαι, δεν έχω απάντηση για την ερώτηση αυτή.";
            addChatMessage("Chatbot", botResponse);
        }
    </script>
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
    <?php if (!empty($examInfoBox)) echo $examInfoBox; ?>

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
    <h1 class="main-title" style="display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: bold; margin-top: 30px;">
    <img src="thesis.png" alt="Εικόνα Διπλωματικής" style="height: 40px;">
    Η ΔΙΠΛΩΜΑΤΙΚΗ ΜΟΥ ΕΡΓΑΣΙΑ</h1>

    
<div class="thesis-grid">
    <?php foreach ($theses as $thesis): ?>
        <div class="thesis-card" onclick="window.location.href='view_student.php?thesis_id=<?php echo $thesis['thesis_id']; ?>';">
            <div class="thesis-image">
                <img src="weather_forecast.png" alt="Thesis Cover">
            </div>
            <div class="thesis-content">
                <h3><?php echo htmlspecialchars($thesis['title']); ?></h3>
                <p><strong>Κατάσταση:</strong> <?php echo htmlspecialchars($thesis['status']); ?></p>
                <p><strong>Έναρξη:</strong> <?php echo htmlspecialchars($thesis['start_date']); ?></p>
                <p><strong>Λήξη:</strong> <?php echo $thesis['end_date'] ? htmlspecialchars($thesis['end_date']) : 'Δεν έχει οριστεί'; ?></p>
                <p><strong>Επιτροπή:</strong> <?php echo htmlspecialchars($thesis['committee_members']); ?></p>
            </div>
           <?php
  $status = trim($thesis['status'] ?? '');
  $tid    = (int)$thesis['thesis_id'];
?>
<div class="action-buttons" style="margin-top: 15px;">
  <?php if ($status === 'Υπο Αναθεση' || $status === 'Υπό Ανάθεση'): ?>
    <a onclick="event.stopPropagation();" 
       href="epilogitrimelousepitropis.php?thesis_id=<?= $tid ?>" 
       class="action-button blue">Επιλογή Τριμελούς Επιτροπής</a>

  <?php elseif ($status === 'Υπο Εξέταση' || $status === 'Υπό Εξέταση'): ?>
    <a onclick="event.stopPropagation();" 
       href="student_action.php?thesis_id=<?= $tid ?>" 
       class="action-button blue">Διαχείριση Διπλωματικής</a>
    <a onclick="event.stopPropagation();" 
       href="thesis_exam_report.php?thesis_id=<?= $tid ?>" 
       class="action-button blue">Προβολή Πρακτικού Εξέτασης</a>

  <?php elseif ($status === 'Περατωμένη'): ?>
    <a onclick="event.stopPropagation();" 
       href="thesis_exam_report.php?thesis_id=<?= $tid ?>" 
       class="action-button blue">Προβολή Πρακτικού Εξέτασης</a>
  <?php endif; ?>
</div>

</div>
        </div>
    <?php endforeach; ?>
</div>

<div style="margin: 30px auto; width: 90%; max-width: 1200px; font-weight: bold;">
    <h2 class="text-center mb-4" style="color:rgba(0, 51, 102, 0.92);">Σχετικές Διπλωματικές</h2>
    <div class="thesis-grid">
        <?php foreach ($recommendedTheses as $thesis): ?>
            <div class="thesis-card" onclick="window.location.href='view_student.php?thesis_id=<?php echo $thesis['thesis_id']; ?>';">
                <div class="thesis-image">
                    <img src="weather_forecast.png" alt="Thesis Image">
                </div>
                <div class="thesis-content">
                    <h3><?php echo htmlspecialchars($thesis['title']); ?></h3>
                    <p><strong>Κατάσταση:</strong> <?php echo htmlspecialchars($thesis['status']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($theses)): ?>
    <div class="back-to-list-container">
        <a href="student_home.php" class="back-to-list-button">Επιστροφή στην Αρχική Λίστα</a>
    </div>
<?php endif; ?>


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
/* Chatbot toggle button */
#chatbot-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background-color: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 2rem;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    z-index: 1000;
}

/* Chatbot body */
#chatbot-body {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 300px;
    height: 400px;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    z-index: 1000;
}

/* Chatbot header */
#chatbot-header {
    background-color: #007bff;
    color: white;
    padding: 10px;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Chatbot messages */
#chatbot-messages {
    flex: 1;
    padding: 10px;
    overflow-y: auto;
    font-size: 14px;
    border-bottom: 1px solid #ddd;
}

/* Chatbot questions */
#chatbot-questions {
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

#chatbot-questions button {
    padding: 8px 10px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
}

#chatbot-questions button:hover {
    background-color: #0056b3;
}
</style>


<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>
<script>

function toggleChatbot() {
    chatbotVisible = !chatbotVisible;
    document.getElementById("chatbot-body").style.display = chatbotVisible ? "flex" : "none";
}

function handleQuestion(question) {
    addChatMessage("Χρήστης", question);
    getChatbotResponse(question);
}

function addChatMessage(sender, message) {
    const messageContainer = document.createElement("div");
    messageContainer.textContent = ${sender}: ${message};
    messageContainer.style.margin = "5px 0";
    document.getElementById("chatbot-messages").appendChild(messageContainer);
}

function getChatbotResponse(question) {
    const responses = {
        "Πώς μπορώ να αναζητήσω διπλωματική εργασία;": "Για να αναζητήσετε διπλωματική εργασία, πληκτρολογήστε τον τίτλο ή λέξεις-κλειδιά στο πεδίο αναζήτησης και πατήστε 'Αναζήτηση'.",
        "Πώς μπορώ να δω τις λεπτομέρειες μιας διπλωματικής;": "Κάντε κλικ σε οποιαδήποτε γραμμή στον πίνακα για να δείτε τις λεπτομέρειες της αντίστοιχης διπλωματικής.",
        "Πού μπορώ να διαχειριστώ τις διπλωματικές μου;": "Μπορείτε να διαχειριστείτε τις διπλωματικές σας πατώντας το κουμπί 'Διαχείριση Διπλωματικής Εργασίας'.",
        "Πώς να καλέσω έναν καθηγητή;": "Μπορείτε να στείλετε πρόσκληση σε καθηγητή πατώντας το κουμπί 'Πρόσκληση Καθηγητή'.",
        "Ποια είναι η χρήση του πίνακα στη σελίδα;": "Ο πίνακας εμφανίζει όλες τις διπλωματικές εργασίες, με πληροφορίες όπως τίτλος, κατάσταση, ημερομηνίες και μέλη επιτροπής.",
    };

    const botResponse = responses[question] || "Λυπάμαι, δεν έχω απάντηση για την ερώτηση αυτή.";
    addChatMessage("Chatbot", botResponse);
}

</script>

</body>
</html>