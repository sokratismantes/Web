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
            --muted:#556070;
            --line:#e6eef7;
            --radius:14px;
        }
        *{box-sizing:border-box;transition:background-color .3s,color .3s}
        @keyframes fadein{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

        body{
            font-family:Roboto, Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            color:#333; font-size:0.96rem; margin:0; min-height:100vh; display:flex; flex-direction:column;
        }

        /* Header  */
        .site-header{
            display:flex; justify-content:space-between; align-items:center;
            padding:20px 40px; background-color:rgba(0, 51, 102, 0.92); color:#fff;
            margin-bottom:20px; height:120px; border-bottom-left-radius:14px; border-bottom-right-radius:14px;
        }
        .site-header .left{display:flex; align-items:center; gap:10px}
        .site-header .logo{width:95px; height:80px}
        .system-name{font-size:20px; font-weight:600}
        .site-header .right{display:flex; align-items:center; gap:20px}
        .site-header .right nav a{color:#fff; text-decoration:none; margin-right:15px}
        .site-header .user-info{font-weight:500}

        footer{
            width:100%; background-color:rgba(0, 51, 102, 0.92); color:#fff; text-align:center;
            padding:30px; margin-top:20px;
        }

        /* Layout */
        .page-container{ width:min(1200px,92%); margin:24px auto 8px; animation:fadein .5s ease-in}
        .page-grid{ display:grid; grid-template-columns: 1fr 320px; gap:24px; }
        @media (max-width: 1000px){ .page-grid{ grid-template-columns: 1fr; } }

        .card{
            background:#fff; border-radius:var(--radius);
            box-shadow:0 8px 24px rgba(0,0,0,.08); padding:24px;
        }

        h1{margin:0 0 16px; color:#003366; font-size:26px}
        label{font-weight:600; color:#23344a}
        .readonly-field{margin:6px 0 16px; padding:12px; background:#f9fbfe; border:1px solid var(--line); border-radius:10px}

        .status{font-weight:700}
        .status.cancelled{color:#d12f3f}
        .status.completed{color:#1f9d55}
        .status.active{color:#1f9d55}
        .status.pending{color:#d48806}

        /* Buttons – silver-blue */
        button,.btn{
            appearance:none; border:0; border-radius:10px; cursor:pointer;
            padding:10px 14px; font-weight:600; font-size:0.95rem;
            background:linear-gradient(180deg,#b8c9de,#7da1c8); color:#0b2540;
            box-shadow:0 2px 0 rgba(0,0,0,.08), inset 0 1px 0 rgba(255,255,255,.45);
        }
        button:hover,.btn:hover{filter:brightness(1.04)}
        button:disabled{opacity:.6; cursor:not-allowed}
        .delete-button{background:linear-gradient(180deg,#ffb3b3,#ff7a7a); color:#5c0b0b}

        table{width:100%; border-collapse:collapse; margin-top:10px; border-radius:10px; overflow:hidden}
        thead th{background:#e9f1fb; color:#27496d; padding:10px; text-align:left}
        tbody td{border-top:1px solid var(--line); padding:10px}

        #actions h3{margin-top:18px; color:#0b4ba6}
        #actions input,#actions textarea{width:100%; padding:10px; border:1px solid var(--line); border-radius:10px; margin:6px 0 12px}
        #actions hr{border:0; border-top:1px solid var(--line); margin:18px 0}

        .back-link{text-align:center; margin-top:16px}
        .back-link a{color:#0b4ba6; text-decoration:none; font-weight:600}
        .back-link a:hover{text-decoration:underline}

        /* Notes - sidebar */
        .notes{background:#fff; border-radius:var(--radius); box-shadow:0 8px 24px rgba(0,0,0,.08); padding:20px}
        .notes h2{margin:0 0 12px; color:#0b4ba6}
        .notes ul{margin:0 0 10px 18px; padding:0}
        .notes li{margin:8px 0; color:#33455b}
        .notes hr{border:0; border-top:1px solid var(--line); margin:12px 0}

        /* Mini helper strip */
        .mini-helper{
            margin:16px 0 0;
            background:linear-gradient(180deg,#f6f9ff,#eef3fb);
            border:1px solid var(--line);
            border-radius:12px;
            box-shadow:0 6px 18px rgba(0,0,0,.06);
            padding:14px 16px;
        }
        .mini-helper h3{margin:0 0 8px; color:#0b4ba6; font-size:18px}
        .mini-row{display:flex; gap:18px; flex-wrap:wrap}
        .mini-col{flex:1 1 260px}
        .link-list{list-style:none; margin:0; padding:0}
        .link-list li{margin:6px 0}
        .link-list a{color:#0b4ba6; text-decoration:none}
        .link-list a:hover{text-decoration:underline}
        .chips{display:flex; gap:8px; flex-wrap:wrap}
        .chip{padding:6px 10px; border-radius:999px; background:#edf3fb; border:1px solid #d5e1f3; color:#264b74; font-weight:600; font-size:.88rem}
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
            <a href="grammateia_home.php">Αρχική</a>
            <a href="logout.php">Αποσύνδεση</a>
        </nav>
        <div class="user-info"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
    </div>
</header>

<div class="page-container">
    <div class="page-grid">
        <!-- MAIN -->
        <main class="card">
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

                <h3 style="color:#0b4ba6; margin-top:18px;">Τριμελής Επιτροπή</h3>
                <table id="committee" border="0" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr><th>Ρόλος</th><th>ID Καθηγητή</th><th>Όνομα</th><th>Επώνυμο</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <div id="actions" style="margin-top:8px;"></div>
            </form>

            <div class="back-link">
                <a href="grammateia_home.php">← Πίσω στη Λίστα Διπλωματικών</a>
            </div>
        </main>

        <!-- SIDEBAR -->
        <aside class="notes" aria-label="Σημειώσεις">
            <h2>Σημειώσεις</h2>
            <ul>
                <li>Το <b>ΑΠ ΓΣ</b> καταχωρείται μία φορά και στη συνέχεια κλειδώνει.</li>
                <li>Η <b>Ακύρωση Ανάθεσης</b> απαιτεί Αριθμό/Έτος ΓΣ και τεκμηριωμένο λόγο.</li>
                <li>Σε <i>Υπό Εξέταση</i> ολοκληρώνετε τη διπλωματική όταν υπάρχουν τα απαραίτητα στοιχεία.</li>
            </ul>
            <hr>
            <div style="color:#44566c; font-weight:600;">Όλες οι αλλαγές καταγράφονται.</div>
        </aside>
    </div>

    <!-- ΜΙΝΙ PANEL -->
    <section class="mini-helper" aria-label="Χρήσιμα">
        <div class="mini-row">
            <div class="mini-col">
                <h3>Χρήσιμοι Σύνδεσμοι</h3>
                <ul class="link-list">
                    <li><a href="/assets/templates/praktiko.docx" target="_blank">Υπόδειγμα Πρακτικού (.docx)</a></li>
                    <li><a href="/assets/templates/aitisi_exetasis.pdf" target="_blank">Αίτηση Εξέτασης (.pdf)</a></li>
                    <li><a href="/assets/guides/nimerti_guide.pdf" target="_blank">Οδηγός Νημερτή (.pdf)</a></li>
                </ul>
            </div>
            <div class="mini-col">
                <h3>Επαφές Επιτροπής</h3>
                <div id="mh-contacts" class="chips"></div>
            </div>
        </div>
    </section>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const thesisId = urlParams.get('thesis_id');

    if (!thesisId) { alert('Δεν δόθηκε ID θέματος.'); return; }

    async function parseJsonSafe(res) {
        const raw = await res.text();
        try { return JSON.parse(raw); }
        catch (e) { console.error('RAW RESPONSE:\n', raw); throw new Error('Μη έγκυρη JSON απόκριση από τον server.'); }
    }

    // εμφάνιση επαφών στο mini panel
    function renderMiniContacts(committee){
        const box = document.getElementById('mh-contacts');
        if(!box) return;
        box.innerHTML = '';
        (committee||[]).forEach(m=>{
            const span = document.createElement('span');
            span.className = 'chip';
            span.textContent = `${m.role}: ${m.name} ${m.surname}`;
            box.appendChild(span);
        });
    }

    fetch('fetch_thesis_details.php?thesis_id=' + encodeURIComponent(thesisId))
        .then(parseJsonSafe)
        .then(data => {
            if (!data.success) { alert(data.message || 'Σφάλμα φόρτωσης.'); return; }

            const thesis = data.thesis;
            const committee = data.committee;

            document.getElementById('title').textContent = thesis.title || '';
            document.getElementById('description').textContent = thesis.description || '';
            document.getElementById('start_date').textContent = thesis.start_date || '';

            const statusEl = document.getElementById('status');
            statusEl.textContent = thesis.status || '';
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
                    <strong>Απόφαση ΓΣ:</strong> Αρ. ${thesis.cancel_gs_number ?? ''}, Έτος ${thesis.cancel_gs_year ?? ''}<br>
                    <strong>Λόγος Ακύρωσης:</strong> ${thesis.cancellation_reason ?? ''}
                `;
                cancelInfo.id = 'cancellation_info';
                document.getElementById('thesisForm').appendChild(cancelInfo);
            } else if (thesis.status === 'Ενεργή') {
                statusEl.classList.add('active');
            } else if (thesis.status === 'Υπό Εξέταση') {
                statusEl.classList.add('pending');
            }

            if (thesis.elapsed_time) {
                document.getElementById('elapsed_time').textContent = "Χρόνος από την ανάθεση: " + thesis.elapsed_time;
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

            // γέμισμα επαφών στο mini panel 
            renderMiniContacts(committee);

            // Actions 
            const actionsDiv = document.getElementById('actions');

            if (thesis.status === 'Ενεργή') {
                actionsDiv.innerHTML = `
                    <h3>Ανάθεση Θέματος</h3>
                    <label>Αριθμός Πρωτοκόλλου ΓΣ:</label>
                    <input type="number" id="assign_gs_number" value="${thesis.assign_gs_number || ''}" ${thesis.assign_gs_number ? 'readonly' : ''}>
                    <button type="button" id="assignBtn" ${thesis.assign_gs_number ? 'disabled' : ''}>
                        ${thesis.assign_gs_number ? 'Καταχωρήθηκε' : 'Καταχώρηση ΑΠ ΓΣ'}
                    </button>

                    <hr>

                    <h3>Ακύρωση Ανάθεσης Θέματος</h3>
                    <label>Αριθμός ΓΣ:</label>
                    <input type="number" id="cancel_gs_number" required>
                    <label>Έτος Απόφασης ΓΣ:</label>
                    <input type="number" id="cancel_gs_year" required>
                    <label>Λόγος Ακύρωσης:</label>
                    <textarea id="cancellation_reason" rows="3" required></textarea>
                    <button type="button" id="cancelBtn" class="delete-button">Ακύρωση Ανάθεσης</button>
                `;

                document.getElementById('assignBtn').addEventListener('click', async () => {
                    const input = document.getElementById('assign_gs_number');
                    const gsNumber = (input.value || '').trim();
                    if (!gsNumber) { alert('Συμπλήρωσε τον αριθμό πρωτοκόλλου ΓΣ.'); return; }

                    try {
                        const res = await fetch('fetch_assign_gs.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ thesis_id: thesisId, assign_gs_number: gsNumber })
                        });
                        const d = await parseJsonSafe(res);
                        if (d.success) {
                            alert('Ο ΑΠ ΓΣ καταχωρήθηκε επιτυχώς.');
                            input.readOnly = true;
                            input.style.backgroundColor = "#f0f0f0";
                            const btn = document.getElementById('assignBtn');
                            btn.disabled = true;
                            btn.textContent = "Καταχωρήθηκε";
                        } else {
                            alert(d.message || 'Σφάλμα κατά την καταχώρηση.');
                        }
                    } catch (e) { alert(e.message || 'Σφάλμα κατά την καταχώρηση.'); }
                });

                document.getElementById('cancelBtn').addEventListener('click', async () => {
                    const reasonEl = document.getElementById('cancellation_reason');
                    const gsNumEl = document.getElementById('cancel_gs_number');
                    const gsYearEl = document.getElementById('cancel_gs_year');

                    const reason = (reasonEl.value || '').trim();
                    const gsNumber = parseInt(gsNumEl.value, 10);
                    const gsYear = parseInt(gsYearEl.value, 10);

                    const errors = [];
                    if (!reason) errors.push('Ο λόγος ακύρωσης είναι υποχρεωτικός.');
                    if (!Number.isInteger(gsNumber) || gsNumber <= 0) errors.push('Ο αριθμός ΓΣ πρέπει να είναι θετικός ακέραιος.');
                    if (!Number.isInteger(gsYear) || gsYear < 1900 || gsYear > 2100) errors.push('Το έτος ΓΣ πρέπει να είναι στην μορφή YYYY (1900–2100).');
                    if (errors.length) { alert(errors.join('\n')); return; }

                    const btn = document.getElementById('cancelBtn');
                    btn.disabled = true;

                    try {
                        const res = await fetch('fetch_cancel_thesis.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                thesis_id: thesisId,
                                cancellation_reason: reason,
                                cancel_gs_number: String(gsNumber),
                                cancel_gs_year: String(gsYear)
                            })
                        });
                        const d = await parseJsonSafe(res);

                        if (d.success) {
                            alert('Η ακύρωση πραγματοποιήθηκε επιτυχώς.');
                            const statusEl = document.getElementById('status');
                            statusEl.textContent = 'Ακυρωμένη';
                            statusEl.className = 'status cancelled';

                            let info = document.getElementById('cancellation_info');
                            if (!info) {
                                info = document.createElement('p');
                                info.id = 'cancellation_info';
                                document.getElementById('thesisForm').appendChild(info);
                            }
                            info.innerHTML = `
                                <strong>Απόφαση ΓΣ:</strong> Αρ. ${gsNumber}, Έτος ${gsYear}<br>
                                <strong>Λόγος Ακύρωσης:</strong> ${d.cancellation_reason || reason}
                            `;
                            document.getElementById('actions').innerHTML = '';
                        } else {
                            alert(d.message || 'Σφάλμα κατά την ακύρωση.');
                            btn.disabled = false;
                        }
                    } catch (e) {
                        alert(e.message || 'Σφάλμα κατά την ακύρωση.');
                        btn.disabled = false;
                    }
                });

            } else if (thesis.status === 'Υπό Εξέταση') {
                const statusEl = document.getElementById('status');
                const actionsDiv = document.getElementById('actions');
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
                    .then(parseJsonSafe)
                    .then(d => {
                        if (d.success) {
                            alert('Η ολοκλήρωση πραγματοποιήθηκε επιτυχώς.');
                            statusEl.textContent = 'Περατωμένη';
                            statusEl.className = 'status completed';
                            let completeInfo = document.createElement('p');
                            completeInfo.innerHTML = `<strong>Ημερομηνία Περάτωσης:</strong> ${new Date().toISOString().split('T')[0]}`;
                            document.getElementById('thesisForm').appendChild(completeInfo);
                            actionsDiv.innerHTML = '';
                        } else { alert(d.message || 'Σφάλμα κατά την ολοκλήρωση.'); }
                    })
                    .catch(e => alert(e.message || 'Σφάλμα κατά την ολοκλήρωση.'));
                });
            }
        })
        .catch(e => alert(e.message || 'Σφάλμα φόρτωσης δεδομένων.'));
});
</script>

</body>
</html>
