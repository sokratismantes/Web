<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

/* === Σύνδεση PDO & ανάκτηση professor_id === */
$notifications = [];
try {
    $dsn = "mysql:host=localhost;dbname=vasst;charset=utf8mb4";
    $dbusername = "root";
    $dbpassword = "";
    $pdo = new PDO($dsn, $dbusername, $dbpassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    /* FIX#1: ΠΑΝΤΑ αντλούμε το professor_id από το email του τρέχοντος login */
    $stmt = $pdo->prepare("
        SELECT p.professor_id
        FROM professors p
        JOIN users u ON u.user_id = p.professor_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['email']]);
    $professorId = (int) $stmt->fetchColumn();
    if ($professorId <= 0) {
        $notifications = [];
    } else {
        // (προαιρετικά ενημερώνουμε το session για χρήση από άλλα scripts)
        $_SESSION['professor_id'] = $professorId;
    }

    /* === AJAX μεταβολές status ειδοποιήσεων (ΔΕΝ αγγίζει άλλα endpoints) === */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pn_action'])) {
        header('Content-Type: application/json');
        $notifId = (int)($_POST['notification_id'] ?? 0);

        if ($professorId <= 0 || $notifId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Μη έγκυρα στοιχεία.']);
            exit;
        }

        if ($_POST['pn_action'] === 'accept') {
            try {
                // 1) Μαρκάρουμε την ειδοποίηση ως Accepted
                $stmt = $pdo->prepare("
                    UPDATE professors_notifications
                    SET status = 'Accepted', responded_at = NOW()
                    WHERE notification_id = :nid AND professor_id = :pid
                ");
                $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);

                $stmt = $pdo->prepare("
                    UPDATE professors_notifications
                    SET responded_at = NOW()
                    WHERE notification_id = :nid AND professor_id = :pid
                    AND responded_at IS NULL
                ");
                $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);

                // 2) Βρίσκουμε το thesis_id της ειδοποίησης
                $stmt = $pdo->prepare("
                    SELECT thesis_id
                    FROM professors_notifications
                    WHERE notification_id = :nid AND professor_id = :pid
                    LIMIT 1
                ");
                $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);
                $thesisId = (int)$stmt->fetchColumn();

                if ($thesisId > 0) {
                    // Χρησιμοποιούμε transaction για ασφάλεια
                    $pdo->beginTransaction();

                    // 2a) Παίρνουμε τον supervisor_id της διπλωματικής
                    $stmt = $pdo->prepare("SELECT supervisor_id FROM theses WHERE thesis_id = :tid LIMIT 1");
                    $stmt->execute([':tid' => $thesisId]);
                    $supervisorId = (int)$stmt->fetchColumn();

                    // 2b) Αναζητούμε αν υπάρχει ήδη εγγραφή στην committees
                    $stmt = $pdo->prepare("
                        SELECT member1_id, member2_id
                        FROM committees
                        WHERE thesis_id = :tid
                        LIMIT 1
                    ");
                    $stmt->execute([':tid' => $thesisId]);
                    $committee = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$committee) {
                        // Δεν υπάρχει row -> δημιουργούμε νέο
                        // ΣΗΜΑΝΤΙΚΟ: συμπληρώνουμε ΚΑΙ supervisor_id
                        $stmt = $pdo->prepare("
                            INSERT INTO committees (thesis_id, supervisor_id, member1_id)
                            VALUES (:tid, :sup, :pid)
                        ");
                        $stmt->execute([
                            ':tid' => $thesisId,
                            ':sup' => $supervisorId ?: null, // αν για κάποιο λόγο είναι 0/null
                            ':pid' => $professorId
                        ]);
                    } else {
                        // Υπάρχει row -> συμπληρώνουμε κατά προτεραιότητα
                        $m1 = (int)($committee['member1_id'] ?? 0);
                        $m2 = (int)($committee['member2_id'] ?? 0);

                        // Αν ήδη υπάρχει ο καθηγητής σε κάποιο slot, δεν κάνουμε τίποτα
                        if ($m1 !== $professorId && $m2 !== $professorId) {
                            if ($m1 === 0 || $committee['member1_id'] === null) {
                                $stmt = $pdo->prepare("
                                    UPDATE committees
                                    SET member1_id = :pid
                                    WHERE thesis_id = :tid
                                    LIMIT 1
                                ");
                                $stmt->execute([':pid' => $professorId, ':tid' => $thesisId]);
                            } elseif ($m2 === 0 || $committee['member2_id'] === null) {
                                $stmt = $pdo->prepare("
                                    UPDATE committees
                                    SET member2_id = :pid
                                    WHERE thesis_id = :tid
                                    LIMIT 1
                                ");
                                $stmt->execute([':pid' => $professorId, ':tid' => $thesisId]);
                            }
                            // Αν και τα δύο είναι γεμάτα με άλλους, δεν αλλάζουμε τίποτα.
                        }
                    }

                    $pdo->commit();
                }

                echo json_encode(['status' => 'success', 'new_status' => 'Accepted']);
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                echo json_encode(['status' => 'error', 'message' => 'Σφάλμα ενημέρωσης επιτροπής: '.$ex->getMessage()]);
            }
            exit;
        }

        if ($_POST['pn_action'] === 'decline') {
            $stmt = $pdo->prepare("
                UPDATE professors_notifications
                SET status = 'Rejected'
                WHERE notification_id = :nid AND professor_id = :pid
            ");
            $stmt->execute([':nid' => $notifId, ':pid' => $professorId]);
            echo json_encode(['status' => 'success', 'new_status' => 'Rejected']);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Άγνωστη ενέργεια.']);
        exit;
    }

    /* === Ανάκτηση ειδοποιήσεων για εμφάνιση (εκτός από Rejected) === */
    if ($professorId > 0) {
        /* FIX#2: Σωστό sort των πιο πρόσφατων
           - Αν είναι ήδη DATETIME: STR_TO_DATE('%Y-%m-%d %H:%i:%s') δουλεύει.
           - Αν είναι string τύπου 'DD/MM/YYYY HH:MM:SS': δοκιμάζουμε και αυτό το format.
           - Break ties με notification_id DESC.
        */
        $stmt = $pdo->prepare("
            SELECT 
                pn.notification_id,
                s.name       AS student_name,
                s.surname    AS student_surname,
                s.student_number,
                th.title     AS thesis_title,
                pn.sent_at,
                pn.status
            FROM professors_notifications pn
            LEFT JOIN students  s  ON s.student_id  = pn.student_id
            LEFT JOIN theses    th ON th.thesis_id  = pn.thesis_id
            WHERE pn.professor_id = :pid
              AND (pn.status IS NULL OR pn.status <> 'Rejected')
            ORDER BY
              COALESCE(
                STR_TO_DATE(pn.sent_at, '%Y-%m-%d %H:%i:%s'),
                STR_TO_DATE(pn.sent_at, '%d/%m/%Y %H:%i:%s'),
                FROM_UNIXTIME(0)
              ) DESC,
              pn.notification_id DESC
        ");
        $stmt->execute([':pid' => $professorId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $notifications = []; // σιωπηλή αποτυχία ώστε να μην επηρεαστεί η υπόλοιπη σελίδα
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσκλήσεις</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            margin: 50px auto;
            padding: 20px;
            max-width: 1200px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 2rem;
            color: #0056b3;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        h2.section-title {
            margin: 35px 0 10px 0;
            font-size: 1.4rem;
            color: #004085;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: center;
            border: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:hover {
            background-color: #e1f0ff;
        }

        table td {
            font-size: 0.95rem;
        }

        .status-pending  { color: #e68a00; font-weight: bold; }
        .status-accepted { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }

        .empty-message {
            font-size: 1.2rem;
            color: #888;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        .button:hover { background-color: #0056b3; }

        .table-wrapper {
            overflow-x: auto;
            margin: 0 auto;
            max-width: 95%;
        }

        /* Κουμπιά ενεργειών ειδοποιήσεων */
        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-accept, .btn-decline {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            transition: opacity .2s ease;
        }
        .btn-accept { background-color: #28a745; }
        .btn-accept:hover { opacity: .85; }
        .btn-decline { background-color: #dc3545; }
        .btn-decline:hover { opacity: .85; }
        .btn-disabled {
            opacity: .6;
            cursor: default;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Όλες οι Προσκλήσεις</h1>
        <!-- Νέα ενότητα: Ειδοποιήσεις προς Καθηγητή -->
        <h2 class="section-title">Ειδοποιήσεις Προς Καθηγητή</h2>
        <div class="table-wrapper">
            <table id="pn-table">
                <thead>
                    <tr>
                        <th>Φοιτητής</th>
                        <th>Αριθμός Μητρώου</th>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>Ημ/νία & Ώρα Αποστολής</th>
                        <th>Κατάσταση</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                        <tr>
                            <td colspan="6" class="empty-message">Δεν υπάρχουν ειδοποιήσεις.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): 
                            $full = trim(($n['student_name'] ?? '') . ' ' . ($n['student_surname'] ?? ''));
                            $full = $full !== '' ? $full : '—';
                            $status = $n['status'] ?? 'Pending';
                            $statusClass = strtolower($status) === 'accepted' ? 'status-accepted' :
                                           (strtolower($status) === 'pending' ? 'status-pending' : 'status-rejected');
                        ?>
                            <tr data-notif="<?= (int)$n['notification_id'] ?>">
                                <td><?= htmlspecialchars($full) ?></td>
                                <td><?= htmlspecialchars($n['student_number'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($n['thesis_title'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($n['sent_at'] ?? '—') ?></td>
                                <td class="<?= $statusClass ?>" data-status>
                                    <?= htmlspecialchars($status) ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if (strtolower($status) === 'pending'): ?>
                                            <button type="button" class="btn-accept" data-accept>Αποδοχή</button>
                                            <button type="button" class="btn-decline" data-decline>Απόρριψη</button>
                                        <?php else: ?>
                                            <button type="button" class="btn-accept btn-disabled" disabled>Αποδοχή</button>
                                            <button type="button" class="btn-decline btn-disabled" disabled>Απόρριψη</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="professor_home.php" class="button">Επιστροφή στον Πίνακα Ελέγχου</a>
    </div>

    <!-- Υπάρχον script για προσκλήσεις (αφήνεται ως έχει) -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        fetch('fetch_theses(proskliseis).php')
            .then(response => response.json())
            .then(data => {
                const tableBody = document.querySelector('#invitations-table tbody');
                if (!tableBody) return; // guard σε περίπτωση που δεν υπάρχει ο πίνακας αυτός στη σελίδα
                tableBody.innerHTML = '';

                if (data.length > 0) {
                    data.forEach(invitation => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${invitation.invitation_id}</td>
                            <td>${invitation.title || 'Χωρίς τίτλο'}</td>
                            <td>${invitation.invited_professor_id}</td>
                            <td class="status-${invitation.status.toLowerCase()}">${invitation.status}</td>
                            <td>${invitation.sent_at}</td>
                            <td>${invitation.responded_at || 'Δεν υπάρχει'}</td>
                            <td>${invitation.comments || 'Χωρίς σχόλια'}</td>
                        `;
                        row.style.cursor = 'pointer';
                        row.addEventListener('click', () => {
                            window.location.href = `provoliproskliseon.php?invitation_id=${invitation.invitation_id}`;
                        });
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="empty-message">Δεν υπάρχουν διαθέσιμες προσκλήσεις.</td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Σφάλμα κατά την ανάκτηση δεδομένων:', error);
            });
    });
    </script>

    <!-- JS για Αποδοχή/Απόρριψη ειδοποιήσεων professors_notifications (ως έχει) -->
    <script>
    (function(){
        const pnTable = document.getElementById('pn-table');
        if (!pnTable) return;

        pnTable.addEventListener('click', function(ev){
            const acceptBtn = ev.target.closest('[data-accept]');
            const declineBtn = ev.target.closest('[data-decline]');
            if (!acceptBtn && !declineBtn) return;

            const tr = ev.target.closest('tr[data-notif]');
            if (!tr) return;

            const notifId = tr.getAttribute('data-notif');
            const action = acceptBtn ? 'accept' : 'decline';

            // Προστασία από διπλά κλικ
            if (acceptBtn) acceptBtn.disabled = true;
            if (declineBtn) declineBtn.disabled = true;

            fetch(location.href, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `pn_action=${encodeURIComponent(action)}&notification_id=${encodeURIComponent(notifId)}`
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.status !== 'success') {
                    alert(resp.message || 'Σφάλμα ενημέρωσης.');
                    if (acceptBtn) acceptBtn.disabled = false;
                    if (declineBtn) declineBtn.disabled = false;
                    return;
                }

                if (resp.new_status === 'Accepted') {
                    // Ενημέρωση status κελιού, απενεργοποίηση κουμπιών
                    const statusTd = tr.querySelector('[data-status]');
                    if (statusTd) {
                        statusTd.textContent = 'Accepted';
                        statusTd.classList.remove('status-pending', 'status-rejected');
                        statusTd.classList.add('status-accepted');
                    }
                    const a = tr.querySelector('[data-accept]');
                    const d = tr.querySelector('[data-decline]');
                    if (a) { a.classList.add('btn-disabled'); a.disabled = true; }
                    if (d) { d.classList.add('btn-disabled'); d.disabled = true; }
                } else if (resp.new_status === 'Rejected') {
                    // Αφαίρεση γραμμής από τον πίνακα
                    tr.parentNode.removeChild(tr);

                    // Αν άδειασε ο πίνακας, δείξε μήνυμα
                    const tbody = pnTable.querySelector('tbody');
                    if (tbody && tbody.children.length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = `<td colspan="6" class="empty-message">Δεν υπάρχουν ειδοποιήσεις.</td>`;
                        tbody.appendChild(emptyRow);
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Σφάλμα δικτύου.');
                if (acceptBtn) acceptBtn.disabled = false;
                if (declineBtn) declineBtn.disabled = false;
            });
        });
    })();
    </script>
</body>
</html>
