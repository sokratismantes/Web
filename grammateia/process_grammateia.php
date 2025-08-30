<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Θέματος</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; color: #333; }
        .container { margin: 50px auto; padding: 20px; max-width: 800px; background-color: #fff;
                     border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #0056b3; }
        label { font-weight: bold; }
        input, textarea, button { padding: 10px; font-size: 1rem; border: 1px solid #ccc;
                                  border-radius: 4px; width: 100%; }
        button { background-color: #0056b3; color: white; cursor: pointer; border: none; margin-top: 10px; }
        button:hover { background-color: #003f7f; }
        .delete-button { background-color: red; }
        .delete-button:hover { background-color: darkred; }
        .readonly-field { margin-bottom: 15px; }
        .status { font-weight: bold; }
        .status.active { color: green; }
        .status.pending { color: orange; }
        .status.cancelled { color: red; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { text-decoration: none; color: #0056b3; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h1>Επεξεργασία Θέματος</h1>

    <form id="thesisForm">
        <label>Τίτλος Θέματος:</label>
        <div id="title" class="readonly-field"></div>

        <label>Περιγραφή:</label>
        <div id="description" class="readonly-field"></div>

        <label>Κατάσταση:</label>
        <p id="status" class="status"></p>

        <label>Ημερομηνία Έναρξης:</label>
        <div id="start_date" class="readonly-field"></div>

        <p id="elapsed_time"></p>

        <h3>Τριμελής Επιτροπή</h3>
        <table id="committee" border="1" cellpadding="8" cellspacing="0">
            <thead>
            <tr><th>Ρόλος</th><th>ID Καθηγητή</th><th>Όνομα</th><th>Επώνυμο</th></tr>
            </thead>
            <tbody></tbody>
        </table>

        <div id="actions"></div>
    </form>

    <div class="back-link">
        <a href="grammateia_home.php">← Πίσω στη Λίστα Διπλωματικών</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const thesisId = urlParams.get('thesis_id');

    if (!thesisId) {
        alert('Δεν δόθηκε ID θέματος.');
        return;
    }

    //  AJAX για φόρτωση δεδομένων
    fetch('fetch_thesis_details.php?thesis_id=' + thesisId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }

            const thesis = data.thesis;
            const committee = data.committee;

            // Συμπλήρωση DOM
            document.getElementById('title').textContent = thesis.title;
            document.getElementById('description').textContent = thesis.description;
            document.getElementById('status').textContent = thesis.status;
            document.getElementById('start_date').textContent = thesis.start_date;

            if (thesis.elapsed_time) {
                document.getElementById('elapsed_time').textContent =
                    "Χρόνος από την ανάθεση: " + thesis.elapsed_time;
            }

            // Επιτροπή
            let tbody = document.querySelector('#committee tbody');
            committee.forEach(member => {
                let tr = document.createElement('tr');
                tr.innerHTML = `<td>${member.role}</td>
                                <td>${member.professor_id}</td>
                                <td>${member.name}</td>
                                <td>${member.surname}</td>`;
                tbody.appendChild(tr);
            });

            // Δυναμική εμφάνιση actions
            const actionsDiv = document.getElementById('actions');
            if (thesis.status === 'Ενεργή') {
                actionsDiv.innerHTML = `
                    <h3>Ακύρωση Ανάθεσης Θέματος</h3>
                    <label>Αριθμός ΓΣ:</label>
                    <input type="number" id="cancel_gs_number" required>
                    <label>Έτος Απόφασης ΓΣ:</label>
                    <input type="number" id="cancel_gs_year" required>
                    <label>Λόγος Ακύρωσης:</label>
                    <textarea id="cancelation_reason" rows="3" required></textarea>
                    <button type="button" id="cancelBtn" class="delete-button">Ακύρωση Ανάθεσης</button>
                `;

                document.getElementById('cancelBtn').addEventListener('click', () => {
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
                    });
                });

            } else if (thesis.status === 'Υπό Εξέταση') {
                actionsDiv.innerHTML = `
                    <label>Καταχωρημένος Βαθμός:</label>
                    <input type="text" value="${thesis.grade || ''}" readonly>
                    <label>Σύνδεσμος προς Νημερτή:</label>
                    <input type="url" value="${thesis.repository_link || ''}" readonly>
                    <button type="button" id="completeBtn">Ολοκλήρωση ΔΕ</button>
                `;

                document.getElementById('completeBtn').addEventListener('click', () => {
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
                    });
                });
            }
        })
        .catch(() => alert('Σφάλμα φόρτωσης δεδομένων.'));
});
</script>

</body>
</html>
