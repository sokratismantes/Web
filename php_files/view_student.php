<?php
// Σύνδεση στη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ανάκτηση του thesis_id από το URL
    $thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

    // Ανάκτηση λεπτομερειών της διπλωματικής
    $stmt = $pdo->prepare("
        SELECT t.title, t.description, t.status, t.start_date, t.end_date, t.repository_link,
               GROUP_CONCAT(DISTINCT CONCAT(p.name, ' ', p.surname) SEPARATOR ', ') AS committee_members
        FROM theses t
        LEFT JOIN committees c ON t.thesis_id = c.thesis_id
        LEFT JOIN professors p ON p.professor_id IN (c.supervisor_id, c.member1_id, c.member2_id)
        WHERE t.thesis_id = :thesis_id
        GROUP BY t.thesis_id
    ");
    $stmt->execute(['thesis_id' => $thesis_id]);
    $thesis = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ανάκτηση συνημμένων αρχείων
    $attachments_stmt = $pdo->prepare("
        SELECT file_name, file_url, file_type
        FROM attachments
        WHERE thesis_id = :thesis_id
    ");
    $attachments_stmt->execute(['thesis_id' => $thesis_id]);
    $attachments = $attachments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Υπολογισμός χρόνου από την ανάθεση
    $time_elapsed = "Δεν έχει οριστεί";
    if ($thesis && $thesis['start_date']) {
        $start_date = new DateTime($thesis['start_date']);
        $current_date = new DateTime();
        $interval = $start_date->diff($current_date);
        $time_elapsed = $interval->format('%y χρόνια, %m μήνες, %d ημέρες');
    }
} catch (PDOException $e) {
    echo "Σφάλμα: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προβολή Θέματος</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .form-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 500px;
        }

        .form-container h1 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            height: 80px;
        }

        .form-group input:read-only {
            background-color: #f9f9f9;
        }

        .button-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="form-container">
    <h1>Προβολή Θέματος</h1>

    <?php if ($thesis): ?>
        <div class="form-group">
            <label>Τίτλος Θέματος:</label>
            <input type="text" value="<?php echo htmlspecialchars($thesis['title']); ?>" readonly>
        </div>

        <div class="form-group">
            <label>Περιγραφή:</label>
            <textarea readonly><?php echo htmlspecialchars($thesis['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Κατάσταση:</label>
            <input type="text" value="<?php echo htmlspecialchars($thesis['status']); ?>" readonly>
        </div>

        <div class="form-group">
    <label>Ημερομηνία Έναρξης:</label>
    <input type="date" value="<?php echo htmlspecialchars($thesis['start_date']); ?>" readonly>
</div>

<div class="form-group">
    <label>Ημερομηνία Λήξης:</label>
    <input type="date" value="<?php echo htmlspecialchars($thesis['end_date'] ?? ''); ?>" readonly>
</div>

<?php if ($thesis['status'] === 'Υπό Εξέταση'): ?>
    <form action="update_examination.php" method="POST">
        <input type="hidden" name="thesis_id" value="<?php echo htmlspecialchars($thesis_id); ?>">
        
        <div class="form-group">
            <label for="date">Ημερομηνία Εξέτασης:</label>
            <input type="date" name="date" required>
        </div>
        
        <div class="form-group">
            <label for="time">Ώρα Εξέτασης:</label>
            <input type="time" name="time" required>
        </div>
        
        <div class="form-group">
            <label for="location">Τοποθεσία Εξέτασης:</label>
            <input type="text" name="location" placeholder="π.χ. Αίθουσα 101" required>
        </div>
        
        <button type="submit" class="button">Αποθήκευση Στοιχείων Εξέτασης</button>
    </form>
<?php endif; ?>


        <div class="form-group">
            <label>Χρόνος από Ανάθεση:</label>
            <input type="text" value="<?php echo $time_elapsed; ?>" readonly>
        </div>

        <div class="form-group">
            <label>Μέλη Τριμελούς Επιτροπής:</label>
            <input type="text" value="<?php echo htmlspecialchars($thesis['committee_members']); ?>" readonly>
        </div>

        <div class="form-group">
    <label>Προσθήκη Συνημμένου:</label>
    <form action="upload_attachment.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="attachment" required>
        <input type="hidden" name="thesis_id" value="<?php echo htmlspecialchars($thesis_id); ?>">
        <button type="submit" class="button">Ανέβασμα</button>
    </form>
</div>

<div class="form-group">
    <label>Συνημμένα Αρχεία:</label>
    <?php if (!empty($attachments)): ?>
        <ul>
            <?php foreach ($attachments as $attachment): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($attachment['file_url']); ?>" target="_blank">
                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                    </a> (<?php echo htmlspecialchars($attachment['file_type']); ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Δεν υπάρχουν συνημμένα αρχεία.</p>
    <?php endif; ?>
</div>

<?php
// Ανάκτηση πρακτικού εξέτασης
$result_stmt = $pdo->prepare("SELECT report FROM exam_results WHERE thesis_id = :thesis_id");
$result_stmt->execute(['thesis_id' => $thesis_id]);
$exam_result = $result_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php if ($exam_result): ?>
    <div class="form-group">
        <label>Πρακτικό Εξέτασης:</label>
        <div style="border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f9f9f9;">
            <?php echo nl2br(htmlspecialchars($exam_result['report'])); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($thesis['status'] === 'Υπό Εξέταση'): ?>
    <form action="update_repository.php" method="POST">
        <input type="hidden" name="thesis_id" value="<?php echo htmlspecialchars($thesis_id); ?>">
        
        <div class="form-group">
            <label for="repository_link">Σύνδεσμος Αποθετηρίου:</label>
            <input type="url" name="repository_link" placeholder="π.χ. https://repository.example.com" required>
        </div>
        
        <button type="submit" class="button">Αποθήκευση Συνδέσμου</button>
    </form>
<?php endif; ?>


    <?php else: ?>
        <p>Δεν βρέθηκαν λεπτομέρειες για αυτή τη διπλωματική εργασία.</p>
    <?php endif; ?>

    <div class="button-container">
        <a href="student_home.php" class="button">Πίσω στη Λίστα</a>
    </div>
</div>

<script>
    // Λήψη του παραμέτρου "message" από το URL
    function getMessageFromURL() {
        const params = new URLSearchParams(window.location.search);
        return params.get("message");
    }

    // Εμφάνιση μηνύματος αν υπάρχει
    window.onload = function() {
        const message = getMessageFromURL();
        if (message) {
            let alertMessage = "";
            switch (message) {
                case "success":
                    alertMessage = "✅ Το αρχείο ανέβηκε επιτυχώς!";
                    break;
                case "error":
                    alertMessage = "❌ Σφάλμα κατά την αποθήκευση του αρχείου.";
                    break;
                case "no_file":
                    alertMessage = "❌ Παρακαλώ επιλέξτε ένα αρχείο.";
                    break;
                case "db_error":
                    alertMessage = "❌ Σφάλμα σύνδεσης με τη βάση δεδομένων.";
                    break;
            }
            if (alertMessage) {
                alert(alertMessage);
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/&?message=[^&]*/, ""));
            }
        }
    };
</script>

</body>
</html>

