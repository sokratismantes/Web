<?php
// Σύνδεση με τη βάση δεδομένων
$dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων: " . $e->getMessage());
}

// Μεταβλητές για μηνύματα
$message = "";
$messageType = "";

// **Έλεγχος αν έγινε υποβολή φόρμας**
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $thesisId = $_POST['thesis_id'];

    // **Ανάρτηση Προχείρου (με αρχείο & σύνδεσμο)**
    if ($action == 'submit_draft') {
        $draftLink = $_POST['draft_link'] ?? null;
        $uploadSuccess = false;

        // **Αν υπάρχει αρχείο προς ανέβασμα**
        if (!empty($_FILES['draft_file']['name'])) {
            $fileName = basename($_FILES['draft_file']['name']);
            $fileType = $_FILES['draft_file']['type'];
            $fileTmpPath = $_FILES['draft_file']['tmp_name'];
            $uploadDir = "uploads/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . time() . "_" . $fileName;
            if (move_uploaded_file($fileTmpPath, $filePath)) {
                $uploadSuccess = true;

                try {
                    // **Αποθήκευση στο attachments**
                    $stmt = $pdo->prepare("INSERT INTO attachments (thesis_id, file_name, file_url, file_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$thesisId, $fileName, $filePath, $fileType]);

                    // **Ενημέρωση κατάστασης διπλωματικής**
                    $stmt = $pdo->prepare("UPDATE theses SET status = 'Υπό Εξέταση' WHERE thesis_id = ?");
                    $stmt->execute([$thesisId]);

                    $message = "✅ Το πρόχειρο αναρτήθηκε επιτυχώς!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "❌ Σφάλμα κατά την αποθήκευση του αρχείου: " . $e->getMessage();
                    $messageType = "error";
                }
            } else {
                $message = "❌ Σφάλμα κατά τη μεταφόρτωση του αρχείου.";
                $messageType = "error";
            }
        }

        // **Αν υπάρχει σύνδεσμος, αποθήκευσέ τον**
        if (!empty($draftLink)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO attachments (thesis_id, file_name, file_url, file_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$thesisId, "Σύνδεσμος Υλικού", $draftLink, "link"]);

                $message = "✅ Ο σύνδεσμος προχείρου προστέθηκε επιτυχώς!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "❌ Σφάλμα κατά την αποθήκευση του συνδέσμου: " . $e->getMessage();
                $messageType = "error";
            }
        }

        // **Αποστολή ειδοποίησης μέσω JavaScript**
        echo "<script>
            window.onload = function() {
                showNotification('$message', '$messageType');
            };
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ανάρτηση Προχείρου</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            padding: 20px;
        }

        .form-container h2 {
            text-align: center;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        /* Πράσινο κουμπί υποβολής */
        .btn-submit {
            display: block;
            width: 100%;
            background-color: #28a745;
            color: #fff;
            font-size: 16px;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        /* Μπλε κουμπί επιστροφής */
        .btn-back {
            display: block;
            width: 95%;
            background-color: #007bff;
            color: #fff;
            font-size: 16px;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
            text-align: center;
            text-decoration: none;
        }

        .btn-back:hover {
            background-color: #0056b3;
        }

        /* Ειδοποίηση Pop-Up */
        #notification {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            z-index: 1000;
        }

        .success {
            background-color: #28a745;
        }

        .error {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Ειδοποίηση Pop-Up -->
    <div id="notification"></div>

    <div class="form-container">
        <h2>Ανάρτηση Προχείρου</h2>
        <form method="POST" action="student_action.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="action">Επιλέξτε Ενέργεια:</label>
                <select id="action" name="action" required>
                    <option value="submit_draft" selected>Ανάρτηση Προχείρου</option>
                </select>
            </div>

            <div class="form-group">
                <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
                <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
            </div>

            <div class="form-group">
                <label for="draft_file">Ανέβασμα Προχείρου:</label>
                <input type="file" id="draft_file" name="draft_file">
            </div>

            <div class="form-group">
                <label for="draft_link">Σύνδεσμος Υλικού:</label>
                <input type="url" id="draft_link" name="draft_link" placeholder="π.χ. https://example.com">
            </div>

            <button type="submit" class="btn-submit">Υποβολή</button>
        </form>

        <!-- Κουμπί επιστροφής στην αρχική σελίδα -->
        <a href="student_home.php" class="btn-back">Επιστροφή στην Αρχική Σελίδα</a>
    </div>

    <script>
        function showNotification(message, type) {
            var notification = document.getElementById('notification');
            notification.innerHTML = message;
            notification.className = type;
            notification.style.display = 'block';
            setTimeout(() => { notification.style.display = 'none'; }, 4000);
        }
    </script>
</body>
</html>


