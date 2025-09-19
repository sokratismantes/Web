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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Έλεγχου</title>

    <!-- Roboto font -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --radius-sm:10px;
            --radius-md:14px;
            --radius-lg:18px;

            --space-1:8px;
            --space-2:12px;
            --space-3:16px;
            --space-4:20px;
            --space-5:24px;
            --space-6:32px;

            --brand:#0b4ba6;
            --brand-2:#0056b3;
            --muted:#556070;

            /* Silver-Blue παλέτα */
            --sb-start:#eef3f8;
            --sb-end:#cbd9eb;
            --sb-hover-start:#e6eff7;
            --sb-hover-end:#bcd0ea;
            --sb-active-start:#d5e3f2;
            --sb-active-end:#a9bfdc;
            --sb-text:#0f2f55;
            --sb-border:#c6d6e8;
            --sb-border-strong:#b9c9db;
            --sb-focus:rgba(95,143,193,.35);

            --elev-1:0 8px 18px rgba(0,0,0,.08);
            --elev-2:0 16px 30px rgba(0,0,0,.14);
        }

        * { transition: background-color .3s, color .3s; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; display: flex; flex-direction: column; }

        body {
            font-family: Roboto, system-ui, -apple-system, Segoe UI, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #c9d6ff);
            color: #333; font-size: 0.96rem; min-height: 100vh;
        }
        body::before {
            content: ""; position: fixed; inset: 0;
            background-color: hsla(211, 32.3%, 51.4%, 0.35); z-index: -1;
        }

        /* Header */
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

        /* κύριο container */
        .container {
            animation: fadein .5s ease-in;
            margin: 26px auto; padding: 20px;
            width: min(1100px, 92%);
            background-color: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-md);
            box-shadow: var(--elev-1);
            text-align: center; flex-grow: 1;
        }
        @keyframes fadein { from { opacity: 0; transform: translateY(20px);} to{opacity:1; transform: translateY(0);} }

        h1 { margin: 4px 0 14px; color: #003366; font-size: 1.4rem; letter-spacing:.2px }

        /* Search */
        .search-bar {
            margin: 14px auto 18px; width: min(780px, 100%);
            background: linear-gradient(180deg, #ffffff, #f6f8fb);
            border:1px solid #e5eaf2;
            border-radius: 14px;
            padding: 10px;
            box-shadow: 0 6px 14px rgba(0,0,0,.06);
            display:flex; gap:10px; align-items:center;
        }
        .search-input-wrap{ position:relative; flex:1; }
        .search-input-wrap input {
            padding: 12px 42px 12px 40px; width: 100%;
            border:1px solid #d9e2ef; border-radius:10px; outline:none;
            background:#fff;
            font-size:.98rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
        }
        .search-input-wrap input:focus{ border-color:#b9c9db; box-shadow: 0 0 0 3px var(--sb-focus); }
        .search-icon{
            position:absolute; left:10px; top:50%; transform:translateY(-50%);
            opacity:.55; font-size:18px;
        }
        .clear-btn{
            position:absolute; right:8px; top:50%; transform:translateY(-50%);
            border:0; background:transparent; font-size:18px; cursor:pointer; opacity:.5;
        }
        .clear-btn:hover{ opacity:.9 }

        .btn {
            border-radius:12px; border: 1px solid var(--sb-border);
            border-bottom-color: var(--sb-border-strong);
            color: var(--sb-text);
            background: linear-gradient(180deg, var(--sb-start), var(--sb-end));
            box-shadow: 0 10px 18px rgba(15,47,85,.12), inset 0 1px 0 rgba(255,255,255,.8);
            padding: 11px 16px; font-weight:800; letter-spacing:.2px;
            cursor:pointer;
            transition: transform .2s ease, box-shadow .2s ease, background .2s ease, border-color .2s ease;
        }
        .btn:hover{ transform: translateY(-1px); background: linear-gradient(180deg, var(--sb-hover-start), var(--sb-hover-end)); }
        .btn:active{ transform: translateY(0); background: linear-gradient(180deg, var(--sb-active-start), var(--sb-active-end)); }
        .btn:focus-visible{ outline:3px solid var(--sb-focus); outline-offset:2px; }

        /* Upload cards row */
        .upload-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
            margin: 18px 0 8px;
        }
        .upload-container {
            padding: 16px 16px 14px;
            border:1px solid #e6ebf3;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff, #f7f9fc);
            text-align: left;
            box-shadow: 0 8px 18px rgba(0,0,0,.07);
        }
        .upload-container h3 {
            margin: 4px 0 12px;
            color: #0b3c6f;
            font-size: 1.05rem;
            display:flex; align-items:center; gap:8px;
        }
        .upload-container form { display:grid; gap:10px; }
        .file-row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .file-input{
            display:inline-block; padding:10px 12px; border:1px dashed #cfd9e6; border-radius:10px; background:#fff; min-width:220px;
        }
        .file-input input[type="file"]{ border:0; background:transparent; }
        .upload-container .btn { padding:10px 14px; }

        .msg { margin-top:8px; min-height:20px; font-weight:600; color:#0f2f55; }

        /* Table */
        .table-wrapper {
            margin-top: 20px; overflow:auto; border-radius: 12px; border:1px solid #e6ebf3; background:#fff;
            box-shadow: 0 10px 22px rgba(0,0,0,.08);
        }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        thead th {
            position: sticky; top:0; z-index:1;
            background: linear-gradient(180deg, #eaf3ff, #dbe9ff);
            color: #0a3970; text-align: left; padding: 12px 14px; font-weight:800; letter-spacing:.2px;
            border-bottom:1px solid #d4e3fb;
        }
        tbody td {
            border-bottom: 1px solid #eef2f8; padding: 12px 14px; text-align: left;
        }
        tbody tr:nth-child(even) { background-color: #f8fbff; }
        tbody tr:hover { background-color: #f1f7ff; cursor: pointer; }
        tbody tr { transition: background-color .18s ease; }

        /* Empty state */
        .empty-state{ padding:24px; text-align:center; color:#4b5a6b }
        .empty-state strong{ color:#0b3c6f }

        /* footer */
        footer {
            flex-shrink: 0; width: 100%;
            background-color: rgba(0, 51, 102, 0.92);
            color: #fff; text-align: center; padding: 30px; margin-top: 20px;
        }

        /* Loading overlay */
        #loadingOverlay{
            position: fixed; inset:0; background: rgba(255,255,255,.6); backdrop-filter: blur(2px);
            display:none; align-items:center; justify-content:center; z-index: 9999;
        }
        .spinner{
            width: 56px; height: 56px; border-radius: 50%;
            border: 4px solid #bcd0ea; border-top-color:#0b4ba6;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin{ to { transform: rotate(360deg);} }

        .results-meta{ margin: 6px 2px 0; color:#415a77; font-weight:600; text-align:left; }
        .search-actions{ display:flex; gap:10px; align-items:center; }
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
            <a href="profile_edit.php">Το Προφιλ Μου</a>
        </nav>
        <span class="user-info"><a href="loginn.php" style="color: #ccc">Έξοδος</a></span>
    </div>
</header>

<div class="container">
    <h1>Λίστα Διπλωματικών</h1>

    <!-- Search bar -->
    <div class="search-bar">
        <div class="search-input-wrap">
            <span class="search-icon">🔎</span>
            <input type="text" id="search" placeholder="Αναζήτηση διπλωματικών...">
            <button class="clear-btn" id="clearBtn" title="Καθαρισμός">✖</button>
        </div>
        <div class="search-actions">
            <button class="btn" onclick="searchTheses()">Αναζήτηση</button>
        </div>
    </div>
    <div class="results-meta" id="resultsMeta"></div>

    <!-- Εισαγωγή Δεδομένων -->
    <div class="upload-row">
        <!-- Εισαγωγή Φοιτητών -->
        <div class="upload-container">
            <h3>📥 Εισαγωγή Φοιτητών</h3>
            <form id="uploadStudentsForm" enctype="multipart/form-data">
                <input type="hidden" name="type" value="students">
                <div class="file-row">
                    <div class="file-input">
                        <input type="file" name="json_file" accept=".json" required>
                    </div>
                    <button type="button" class="btn" onclick="uploadData('uploadStudentsForm','studentsMessage')">Προσθήκη Φοιτητών</button>
                </div>
            </form>
            <div id="studentsMessage" class="msg"></div>
        </div>

        <!-- Εισαγωγή Διδασκόντων -->
        <div class="upload-container">
            <h3>📥 Εισαγωγή Διδασκόντων</h3>
            <form id="uploadProfessorsForm" enctype="multipart/form-data">
                <input type="hidden" name="type" value="professors">
                <div class="file-row">
                    <div class="file-input">
                        <input type="file" name="json_file" accept=".json" required>
                    </div>
                    <button type="button" class="btn" onclick="uploadData('uploadProfessorsForm','professorsMessage')">Προσθήκη Διδασκόντων</button>
                </div>
            </form>
            <div id="professorsMessage" class="msg"></div>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width:80px">ID</th>
                    <th>Τίτλος</th>
                    <th style="width:160px">Κατάσταση</th>
                    <th style="width:160px">Ημ. Έναρξης</th>
                    <th style="width:160px">Επιβλέπων</th>
                </tr>
            </thead>
            <tbody id="thesisTableBody">
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<div id="loadingOverlay"><div class="spinner" aria-label="Φόρτωση"></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    loadTheses();

    // Enter για αναζήτηση
    const input = document.getElementById('search');
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); searchTheses(); }
    });

    // Καθαρισμός αναζήτησης
    document.getElementById('clearBtn').addEventListener('click', () => {
        document.getElementById('search').value = '';
        loadTheses();
        input.focus();
    });
});

function showLoading(show){
    document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
}

function loadTheses() {
    showLoading(true);
    fetch('fetch_theses.php')
    .then(res => res.json())
    .then(data => {
        renderTable(data);
        setResultsMeta(data);
    })
    .catch(err => console.error("Σφάλμα:", err))
    .finally(() => showLoading(false));
}

function searchTheses() {
    const query = document.getElementById('search').value.trim();
    if (!query) {
        alert("Παρακαλώ εισάγετε όρο αναζήτησης.");
        return;
    }
    showLoading(true);
    fetch('fetch_search_theses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `search=${encodeURIComponent(query)}`
    })
    .then(res => res.json())
    .then(data => {
        renderTable(data);
        setResultsMeta(data, query);
    })
    .catch(err => console.error("Σφάλμα:", err))
    .finally(() => showLoading(false));
}

function setResultsMeta(theses, query=''){
    const meta = document.getElementById('resultsMeta');
    const count = Array.isArray(theses) ? theses.length : 0;
    meta.textContent = query
        ? `Αποτελέσματα: ${count} για "${query}"`
        : `Σύνολο Διπλωματικών: ${count}`;
}

function uploadData(formId, msgId) {
    const formData = new FormData(document.getElementById(formId));
    showLoading(true);
    fetch('import_json.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        document.getElementById(msgId).innerText = res.message || 'Ολοκληρώθηκε.';
    })
    .catch(err => {
        console.error('Σφάλμα:', err);
        document.getElementById(msgId).innerText = "Αποτυχία αποστολής.";
    })
    .finally(() => showLoading(false));
}

function renderTable(theses) {
    const tbody = document.getElementById('thesisTableBody');
    tbody.innerHTML = "";

    if (!theses || theses.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="5">
                <div class="empty-state">Δεν βρέθηκαν διπλωματικές. <strong>Δοκιμάστε άλλη αναζήτηση</strong> ή φορτώστε ξανά τα δεδομένα.</div>
            </td></tr>`;
        return;
    }

    // helper για badge κατάστασης
    const badge = (status) => {
        const s = (status || '').toLowerCase();
        let bg = '#eef3ff', color = '#0a3970', bd = '#cfe1ff';
        if (s.includes('περατω') || s.includes('ολοκ')) { bg = '#e7fff2'; color='#0f6a42'; bd='#ccefdc'; }
        if (s.includes('εξέτ') || s.includes('εξετ') || s.includes('υπο')) { bg = '#fff6e7'; color='#7a4b00'; bd='#ffe2b1'; }
        return `<span style="
            display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:.82rem;
            background:${bg};color:${color};border:1px solid ${bd};letter-spacing:.2px;">${status||''}</span>`;
    };

    theses.forEach(row => {
        tbody.innerHTML += `
            <tr onclick="window.location.href='process_grammateia.php?thesis_id=${row.thesis_id}'">
                <td>${row.thesis_id}</td>
                <td>${escapeHtml(row.title)}</td>
                <td>${badge(row.status)}</td>
                <td>${row.start_date || ''}</td>
                <td>${row.supervisor_id || ''}</td>
            </tr>`;
    });
}

// escape για τίτλους
function escapeHtml(str){
    if (!str) return '';
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
        return ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;",
            "'": "&#39;", "/": "&#x2F;", "`": "&#x60;", "=": "&#x3D;"
        })[s];
    });
}
</script>
</body>
</html>
