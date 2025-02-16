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


// Ενέργειες ανάλογα με την κατάσταση
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $thesisId = $_POST['thesis_id'];


    if ($action == 'assign_committee') { // Υπό Ανάθεση
        $committeeMembers = $_POST['committee_members']; // Πίνακας με IDs καθηγητών
        try {
            foreach ($committeeMembers as $memberId) {
                $stmt = $pdo->prepare("INSERT INTO committeeinvitations (thesis_id, invited_professor_id, status) VALUES (?, ?, 'Pending')");
                $stmt->execute([$thesisId, $memberId]);
            }
            echo "Οι προσκλήσεις στάλθηκαν στους καθηγητές.";
        } catch (PDOException $e) {
            echo "Σφάλμα κατά την ανάθεση: " . $e->getMessage();
        }
    } elseif ($action == 'submit_draft') { // Υπό Εξέταση
        $draftLink = $_POST['draft_link'];
        try {
            $stmt = $pdo->prepare("UPDATE theses SET repository_link = ?, status = 'Υπό Εξέταση' WHERE thesis_id = ?");
            $stmt->execute([$draftLink, $thesisId]);
            echo "Το πρόχειρο αναρτήθηκε επιτυχώς!";
        } catch (PDOException $e) {
            echo "Σφάλμα κατά την ανάρτηση: " . $e->getMessage();
        }
    } elseif ($action == 'set_presentation') { // Καταχώρηση παρουσίασης
        $presentationDate = $_POST['presentation_date'];
        $presentationTime = $_POST['presentation_time'];
        $presentationLocation = $_POST['presentation_location'];
        try {
            $stmt = $pdo->prepare("INSERT INTO presentations (thesis_id, date, time, location) VALUES (?, ?, ?, ?)");
            $stmt->execute([$thesisId, $presentationDate, $presentationTime, $presentationLocation]);
            echo "Η παρουσίαση καταχωρήθηκε επιτυχώς!";
        } catch (PDOException $e) {
            echo "Σφάλμα κατά την καταχώρηση της παρουσίασης: " . $e->getMessage();
        }
    } elseif ($action == 'finalize_thesis') { // Περατωμένη
        $finalGrade = $_POST['final_grade'];
        try {
            $stmt = $pdo->prepare("UPDATE theses SET status = 'Περατωμένη', final_grade = ? WHERE thesis_id = ?");
            $stmt->execute([$finalGrade, $thesisId]);
            echo "Η διπλωματική εργασία ολοκληρώθηκε!";
        } catch (PDOException $e) {
            echo "Σφάλμα κατά την ολοκλήρωση: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Φόρμα Φοιτητή</title>
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


        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }


        .form-group textarea {
            resize: vertical;
        }


        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }


        .btn {
            display: block;
            width: 100%;
            background-color: #007bff;
            color: #fff;
            font-size: 16px;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }


        .btn:hover {
            background-color: #0056b3;
        }


        .btn:disabled {
            background-color: #ddd;
            cursor: not-allowed;
        }


        .error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Φόρμα Διαχείρισης</h2>
        <form method="POST" action="student_action.php">
            <!-- Επιλογή Ενέργειας -->
            <div class="form-group">
                <label for="action">Επιλέξτε Ενέργεια:</label>
                <select id="action" name="action" required>
                    <option value="" disabled selected>-- Επιλέξτε --</option>
                    <option value="assign_committee">Ανάθεση Επιτροπής</option>
                    <option value="submit_draft">Ανάρτηση Προχείρου</option>
                    <option value="set_presentation">Καταχώρηση Παρουσίασης</option>
                    <option value="finalize_thesis">Ολοκλήρωση Διπλωματικής</option>
                </select>
            </div>


            <!-- Αναγνωριστικό Διπλωματικής -->
            <div class="form-group">
                <label for="thesis_id">Αναγνωριστικό Διπλωματικής:</label>
                <input type="number" id="thesis_id" name="thesis_id" required placeholder="π.χ. 1">
            </div>


            <!-- Σύνδεσμος Προχείρου -->
            <div class="form-group" id="draft-link-group" style="display: none;">
                <label for="draft_link">Σύνδεσμος Προχείρου:</label>
                <input type="url" id="draft_link" name="draft_link" placeholder="π.χ. https://example.com">
            </div>


            <!-- Ημερομηνία Παρουσίασης -->
            <div class="form-group" id="presentation-details-group" style="display: none;">
                <label for="presentation_date">Ημερομηνία Παρουσίασης:</label>
                <input type="date" id="presentation_date" name="presentation_date">
                <label for="presentation_time">Ώρα Παρουσίασης:</label>
                <input type="time" id="presentation_time" name="presentation_time">
                <label for="presentation_location">Τοποθεσία Παρουσίασης:</label>
                <input type="text" id="presentation_location" name="presentation_location" placeholder="π.χ. Αίθουσα 101">
            </div>


            <!-- Τελικός Βαθμός -->
            <div class="form-group" id="final-grade-group" style="display: none;">
                <label for="final_grade">Τελικός Βαθμός:</label>
                <input type="number" id="final_grade" name="final_grade" step="0.01" min="0" max="10" placeholder="π.χ. 8.5">
            </div>


            <button type="submit" class="btn">Υποβολή</button>
        </form>
    </div>


    <script>
        // Εμφάνιση των κατάλληλων πεδίων με βάση την επιλογή
        document.getElementById('action').addEventListener('change', function () {
            const draftLinkGroup = document.getElementById('draft-link-group');
            const presentationDetailsGroup = document.getElementById('presentation-details-group');
            const finalGradeGroup = document.getElementById('final-grade-group');


            draftLinkGroup.style.display = 'none';
            presentationDetailsGroup.style.display = 'none';
            finalGradeGroup.style.display = 'none';


            if (this.value === 'submit_draft') {
                draftLinkGroup.style.display = 'block';
            } else if (this.value === 'set_presentation') {
                presentationDetailsGroup.style.display = 'block';
            } else if (this.value === 'finalize_thesis') {
                finalGradeGroup.style.display = 'block';
            }
        });
    </script>
</body>


</html>

