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
    $stmt = $pdo->prepare("SELECT s.name, s.surname FROM students s JOIN users u ON s.student_id = u.user_id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Σφάλμα: Ο χρήστης με email $email δεν βρέθηκε.";
        exit();
    }

    $userFullName = $user['name'] . ' ' . $user['surname'];

    // Αναζήτηση
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Ανάκτηση δεδομένων διπλωματικών με φίλτρο αναζήτησης
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
        WHERE t.title LIKE :search
        GROUP BY t.thesis_id
    ");
    $stmt->execute(['search' => "%$search%"]);
    $theses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
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
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
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

        .container {
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 1200px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .search-bar {
            margin-bottom: 20px;
            text-align: center;
        }

        .search-bar input {
            padding: 10px;
            width: 50%;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        .search-bar button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .search-bar button:hover {
            background-color: #0056b3;
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
            background-color: #333;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 20px;
        }


        .back-to-list-container {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-list-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            font-size: 16px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .back-to-list-button:hover {
            background-color: #0056b3;
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
    xhr.open("GET", `student_home.php?search=${encodeURIComponent(search)}`, true);
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
            messageContainer.textContent = `${sender}: ${message}`;
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
<div class="header">
    <a href="profile_edit.php">
        <img src="User_image.png" alt="User Icon">
        <span>Καλώς ήρθες, <strong><?php echo htmlspecialchars($userFullName); ?></strong></span>
    </a>
    <button class="logout-button">Αποσύνδεση</button>
</div>

<div class="container">
    <h1>Λίστα Διπλωματικών Εργασιών</h1>

    <div class="search-bar">
        <form id="search-form" method="GET" action="">
            <input type="text" name="search" placeholder="Αναζήτηση διπλωματικών..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Αναζήτηση</button>
        </form>
    </div>

    <a href="student_action.php" class="action-button">Διαχείριση Διπλωματικής Εργασίας</a>
    <a href="epilogitrimelousepitropis.php" class="action-button" style="background-color:rgb(0, 123, 255);">Επιλογή Τριμελούς Επιτροπής</a>
    <a href="form.php" class="action-button" style="background-color: #007bff;">Πρόσκληση Καθηγητή</a>

    <table id="ajax-theses-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Τίτλος</th>
                <th>Κατάσταση</th>
                <th>Ημερομηνία Έναρξης</th>
                <th>Ημερομηνία Λήξης</th>
                <th>Μέλη Τριμελούς Επιτροπής</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($theses as $thesis): ?>
            <tr onclick="window.location.href='view_student.php?thesis_id=<?php echo $thesis['thesis_id']; ?>';">
                <td><?php echo htmlspecialchars($thesis['thesis_id']); ?></td>
                <td><?php echo htmlspecialchars($thesis['title']); ?></td>
                <td><?php echo htmlspecialchars($thesis['status']); ?></td>
                <td><?php echo htmlspecialchars($thesis['start_date']); ?></td>
                <td><?php echo $thesis['end_date'] ? htmlspecialchars($thesis['end_date']) : 'Δεν έχει οριστεί'; ?></td>
                <td><?php echo htmlspecialchars($thesis['committee_members']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
    messageContainer.textContent = `${sender}: ${message}`;
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
