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

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --brand:#0b4ba6;
            --brand-2:#0056b3;
            --muted:#556070;
        }
        * { transition: background-color 0.3s, color 0.3s; box-sizing: border-box; }
        .container { animation: fadein 0.5s ease-in; }
        @keyframes fadein { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
        body {
            font-family: Roboto, Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            color: #333;
            font-size: 0.96rem;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            margin-bottom: 20px;
            height: 120px;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .site-header .left { display: flex; align-items: center; gap: 10px; }
        .site-header .logo { width: 95px; height: 80px; }
        .system-name { font-size: 20px; font-weight: 600; }
        .site-header .right { display: flex; align-items: center; gap: 20px; }
        .site-header .right nav a { color: white; text-decoration: none; margin-right: 15px; }
        .site-header .user-info { font-weight: 500; }

        .status { font-weight: bold; }
        .status.cancelled { color: red; }
        .status.completed { color: green; }
        .status.active { color: green; }
        .status.pending { color: orange; }

        footer {
            width: 100%;
            background-color: rgba(0, 51, 102, 0.92);
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 20px;
        }

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
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { text-decoration: none; color: #0056b3; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="left">
            <img src="logo.png" alt="Logo" class="logo">
            <span class="system-name">Σύστημα Διπλωματικών</span>
        </div>
        <div class="right">
            <nav>
                <a href="grammateia_home.php">Αρχική</a>
                <a href="logout.php">Αποσύνδεση</a>
            </nav>
            <div class="user-info">
                <?php echo htmlspecialchars($_SESSION['email']); ?>
            </div>
        </div>
    </header>

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

    <footer>
        &copy; <?php echo date("Y"); ?> Πανεπιστήμιο - Σύστημα Διπλωματικών
    </footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const thesisId = urlParams.get('thesis_id');

    if (!thesisId) {
        alert('Δεν δόθηκε ID θέματος.');
        return;
    }

    fetch('fetch_thesis_details.php?thesis_id=' + thesisId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }

            const thesis = data.thesis;
            const committee = data.committee;

            document.getElementById('title').textContent = thesis.title;
            document.getElementById('description').textContent = thesis.description;
            document.getElementById('start_date').textContent = thesis.start_date;

            //  Ενημέρωση Status 
            const statusEl = document.getElementById('status');
            statusEl.textContent = thesis.status;
            statusEl.className = 'status';

            if (thesis.status === 'Περατωμένη') {
                statusEl.classList.add('completed');
                if (thesis.end_date) {
                    let completeInfo = document.createElement('p');
                    completeInfo.innerHTML = `<strong>Ημερομηνία Περάτωσης:</strong> ${thesis.end_date}`;
                    document.getElementById('thesisForm').appendChild(completeInfo);
                }
            } else if (thesis.status === 'Ακυρωμένη') {
                statusEl.classList.add('cancelled');
                let cancelInfo = document.createElement('p');
                cancelInfo.innerHTML = `
                    <strong>Απόφαση ΓΣ:</strong> Αρ. ${thesis.cancel_gs_number}, Έτος ${thesis.cancel_gs_year}<br>
                    <strong>Λόγος Ακύρωσης:</strong> ${thesis.cancellation_reason}
                `;
                document.getElementById('thesisForm').appendChild(cancelInfo);
            } else if (thesis.status === 'Ενεργή') {
                statusEl.classList.add('active');
            } else if (thesis.status === 'Υπό Εξέταση') {
                statusEl.classList.add('pending');
            }

            if (thesis.elapsed_time) {
                document.getElementById('elapsed_time').textContent =
                    "Χρόνος από την ανάθεση: " + thesis.elapsed_time;
            }

            let tbody = document.querySelector('#committee tbody');
            committee.forEach(member => {
                let tr = document.createElement('tr');
                tr.innerHTML = `<td>${member.role}</td>
                                <td>${member.professor_id}</td>
                                <td>${member.name}</td>
                                <td>${member.surname}</td>`;
                tbody.appendChild(tr);
            });

            const actionsDiv = document.getElementById('actions');

            if (thesis.status === 'Ενεργή') {
                actionsDiv.innerHTML = `
                    <h3>Ανάθεση Θέματος</h3>
                    <label>Αριθμός Πρωτοκόλλου ΓΣ:</label>
                    
                    <input type="number" id="assign_gs_number" value="${thesis.assign_gs_number || ''}" ${thesis.assign_gs_number ? 'readonly' : ''}>
                    <button type="button" id="assignBtn" ${thesis.assign_gs_number ? 'disabled' : ''}>
                   ${thesis.assign_gs_number ? 'Καταχωρήθηκε ' : 'Καταχώρηση ΑΠ ΓΣ'}
                   </button>


                    <hr>
                    
                    <h3>Ακύρωση Ανάθεσης Θέματος</h3>
                    <label>Αριθμός ΓΣ:</label>
                    <input type="number" id="cancel_gs_number" required>
                    <label>Έτος Απόφασης ΓΣ:</label>
                    <input type="number" id="cancel_gs_year" required>
                    <label>Λόγος Ακύρωσης:</label>
                    <textarea id="cancelation_reason" rows="3" required></textarea>
                    <button type="button" id="cancelBtn" class="delete-button">Ακύρωση Ανάθεσης</button>
                    
                `;
                
                // Καταχώρηση ΑΠ ΓΣ 
    document.getElementById('assignBtn').addEventListener('click', () => {
        const gsNumber = document.getElementById('assign_gs_number').value;

        if (!gsNumber) {
            alert('Συμπλήρωσε τον αριθμό πρωτοκόλλου ΓΣ.');
            return;
        }

        fetch('fetch_assign_gs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                thesis_id: thesisId,
                assign_gs_number: gsNumber
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
        alert('Ο ΑΠ ΓΣ καταχωρήθηκε επιτυχώς.');
        const input = document.getElementById('assign_gs_number');
        const btn = document.getElementById('assignBtn');

        // κάνουμε το input μόνο για προβολή
        input.readOnly = true;
        input.style.backgroundColor = "#f0f0f0"; // για να φαίνεται κλειδωμένο

        // απενεργοποιούμε ή κρύβουμε το κουμπί
        btn.disabled = true;
        btn.textContent = "Καταχωρήθηκε ";


                alert('Ο ΑΠ ΓΣ καταχωρήθηκε επιτυχώς.');
            } else {
                alert(data.message || 'Σφάλμα κατά την καταχώρηση.');
            }
        })
        .catch(() => alert('Σφάλμα κατά την καταχώρηση.'));
    });

                
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
                            statusEl.textContent = 'Ακυρωμένη';
                            statusEl.className = 'status cancelled';

                            let reasonEl = document.getElementById('cancellation_reason');
                            if (!reasonEl) {
                                reasonEl = document.createElement('p');
                                reasonEl.id = 'cancellation_reason';
                                document.getElementById('thesisForm').appendChild(reasonEl);
                            }
                            reasonEl.innerHTML = `
                                <strong>Απόφαση ΓΣ:</strong> Αρ. ${gsNumber}, Έτος ${gsYear}<br>
                                <strong>Λόγος Ακύρωσης:</strong> ${data.cancellation_reason}
                            `;

                            actionsDiv.innerHTML = '';
                        } else {
                            alert(data.message || 'Σφάλμα κατά την ακύρωση.');
                        }
                    })
                    .catch(() => alert('Σφάλμα κατά την ακύρωση.'));
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
                            statusEl.textContent = 'Περατωμένη';
                            statusEl.className = 'status completed';

                            let completeInfo = document.createElement('p');
                            completeInfo.innerHTML = `<strong>Ημερομηνία Περάτωσης:</strong> ${new Date().toISOString().split('T')[0]}`;
                            document.getElementById('thesisForm').appendChild(completeInfo);

                            actionsDiv.innerHTML = '';
                        } else {
                            alert(data.message || 'Σφάλμα κατά την ολοκλήρωση.');
                        }
                    })
                    .catch(() => alert('Σφάλμα κατά την ολοκλήρωση.'));
                });
            }
        })
        .catch(() => alert('Σφάλμα φόρτωσης δεδομένων.'));
});
</script>

</body>
</html>
