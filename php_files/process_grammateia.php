<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Έλεγχος αν δόθηκε το ID του θέματος
if (!isset($_GET['thesis_id'])) {
    die("Δεν δόθηκε ID θέματος.");
}

$thesis_id = intval($_GET['thesis_id']);

// Ανάκτηση πληροφοριών για το θέμα
$sql = "SELECT thesis_id, title, description, status, start_date, end_date, grade, repository_link FROM Theses WHERE thesis_id = $thesis_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Το θέμα δεν βρέθηκε.");
}

$thesis = $result->fetch_assoc();

// Ανάκτηση του βαθμού εάν η κατάσταση είναι "Υπό Εξέταση"
$grade = $thesis['status'] == 'Υπό Εξέταση' ? $thesis['grade'] : '';

// Τριμελής Επιτροπή
$stmt_committee = $conn->prepare("
    SELECT 'Supervisor' AS role, p1.professor_id, p1.name, p1.surname
    FROM committees c
    JOIN professors p1 ON p1.professor_id = c.supervisor_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 1' AS role, p2.professor_id, p2.name, p2.surname
    FROM committees c
    JOIN professors p2 ON p2.professor_id = c.member1_id
    WHERE c.thesis_id = ?
    UNION
    SELECT 'Member 2' AS role, p3.professor_id, p3.name, p3.surname
    FROM committees c
    JOIN professors p3 ON p3.professor_id = c.member2_id
    WHERE c.thesis_id = ?
");
$stmt_committee->bind_param("iii", $thesis_id, $thesis_id, $thesis_id);
$stmt_committee->execute();
$result_committee = $stmt_committee->get_result();

$committee_members = [];
while ($row = $result_committee->fetch_assoc()) {
    $committee_members[] = $row;
}

// Υπολογισμός χρόνου από την έναρξη
$start_date = new DateTime($thesis['start_date']);
$current_date = new DateTime();
$interval = $start_date->diff($current_date);
$elapsed_time = $interval->format('%y έτη, %m μήνες, %d ημέρες');

$conn->close();
?>


<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Θέματος</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            margin: 50px auto;
            padding: 20px;
            max-width: 800px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #0056b3;
        }

        label {
            font-weight: bold;
        }

        input, textarea, button {
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
        }

        button {
            background-color: #0056b3;
            color: white;
            cursor: pointer;
            border: none;
            margin-top: 10px;
        }

        button:hover {
            background-color: #003f7f;
        }

        .delete-button {
            background-color: red;
        }

        .delete-button:hover {
            background-color: darkred;
        }

        .readonly-field {
            margin-bottom: 15px;
        }

        .status {
            font-weight: bold;
        }

        .status.active { color: green; }
        .status.pending { color: orange; }
        .status.cancelled { color: red; }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            text-decoration: none;
            color: #0056b3;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Επεξεργασία Θέματος</h1>

    <form id="thesisForm">
        <label>Τίτλος Θέματος:</label>
        <div class="readonly-field"><?php echo htmlspecialchars($thesis['title']); ?></div>

        <label>Περιγραφή:</label>
        <div class="readonly-field"><?php echo nl2br(htmlspecialchars($thesis['description'])); ?></div>

        <label>Κατάσταση:</label>
        <?php
            $status_class = '';
            switch ($thesis['status']) {
                case 'Ενεργή': $status_class = 'active'; break;
                case 'Υπό Εξέταση': $status_class = 'pending'; break;
                case 'Ακυρωμένη': $status_class = 'cancelled'; break;
            }
        ?>
        <p class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($thesis['status']); ?></p>

        <label>Ημερομηνία Έναρξης:</label>
        <div class="readonly-field"><?php echo htmlspecialchars($thesis['start_date']); ?></div>

        <?php if ($thesis['status'] !== 'Υπό Εξέταση'): ?>
            <p>Χρόνος από την ανάθεση: <?php echo $elapsed_time; ?></p>
        <?php endif; ?>

        <h3>Τριμελής Επιτροπή</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
            <tr>
                <th>Ρόλος</th>
                <th>ID Καθηγητή</th>
                <th>Όνομα</th>
                <th>Επώνυμο</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($committee_members as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['role']); ?></td>
                    <td><?php echo htmlspecialchars($member['professor_id']); ?></td>
                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                    <td><?php echo htmlspecialchars($member['surname']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($thesis['status'] === 'Ενεργή'): ?>
            <h3>Ακύρωση Ανάθεσης Θέματος</h3>

            <label for="cancel_gs_number">Αριθμός ΓΣ:</label>
            <input type="number" id="cancel_gs_number" name="cancel_gs_number" required>

            <label for="cancel_gs_year">Έτος Απόφασης ΓΣ:</label>
            <input type="number" id="cancel_gs_year" name="cancel_gs_year" required>

            <label for="cancelation_reason">Λόγος Ακύρωσης:</label>
            <textarea id="cancelation_reason" name="cancelation_reason" rows="3" required></textarea>

            <button type="button" name="cancel" class="delete-button">Ακύρωση Ανάθεσης</button>
        <?php endif; ?>

        <?php if ($thesis['status'] === 'Υπό Εξέταση'): ?>
            <label>Καταχωρημένος Βαθμός:</label>
            <input type="text" name="grade" value="<?php echo htmlspecialchars($grade); ?>" readonly>

            <label>Σύνδεσμος προς Νημερτή:</label>
            <input type="url" name="link" value="<?php echo htmlspecialchars($thesis['repository_link']); ?>" readonly>

            <button type="button" name="complete">Ολοκλήρωση ΔΕ</button>
        <?php endif; ?>
    </form>

    <div class="back-link">
        <a href="grammateia_home.php">← Πίσω στη Λίστα Διπλωματικών</a>
    </div>
</div>

<!-- ✅ AJAX Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const thesisId = <?php echo $thesis_id; ?>;

    const cancelBtn = document.querySelector('button[name="cancel"]');
    const completeBtn = document.querySelector('button[name="complete"]');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            const reason = document.getElementById('cancelation_reason').value.trim();
            const gsNumber = document.getElementById('cancel_gs_number').value;
            const gsYear = document.getElementById('cancel_gs_year').value;

            if (reason === '') {
                alert('Παρακαλώ εισάγετε τον λόγο ακύρωσης.');
                return;
            }

            fetch('fetch_cancel_thesis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    thesis_id: thesisId,
                    cancelation_reason: reason,
                    cancel_gs_number: gsNumber,
                    cancel_gs_year: gsYear
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Η ακύρωση πραγματοποιήθηκε επιτυχώς.');
                    window.location.href = 'grammateia_home.php';
                } else {
                    alert(data.message || 'Σφάλμα κατά την ακύρωση.');
                }
            })
            .catch(() => alert('Σφάλμα σύνδεσης.'));
        });
    }

    if (completeBtn) {
        completeBtn.addEventListener('click', () => {
            fetch('fetch_complete_thesis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ thesis_id: thesisId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Η ολοκλήρωση πραγματοποιήθηκε επιτυχώς.');
                    window.location.href = 'grammateia_home.php';
                } else {
                    alert(data.message || 'Σφάλμα κατά την ολοκλήρωση.');
                }
            })
            .catch(() => alert('Σφάλμα σύνδεσης.'));
        });
    }
});
</script>

</body>
</html>
