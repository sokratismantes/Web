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
            --elev-1:0 4px 10px rgba(0,0,0,.12);
            --elev-2:0 8px 20px rgba(0,0,0,.18);
            --brand:#0b4ba6;
            --brand-2:#0056b3;
            --muted:#556070;
        }
        * { transition: background-color 0.3s, color 0.3s; box-sizing: border-box; }
        .container { animation: fadein 0.5s ease-in; }
        @keyframes fadein { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

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

        .site-header {
            display: flex; justify-content: space-between; align-items: center;
            background-color: #003f7f; color: white; padding: 10px 20px;
        }
        .site-header .left { display: flex; align-items: center; }
        .site-header .logo { width: 50px; margin-right: 15px; }
        .site-header .system-name { font-size: 1.2rem; font-weight: bold; }
        .site-header nav a { margin: 0 10px; color: white; text-decoration: none; }
        .site-header nav a:hover { text-decoration: underline; }
        .site-header .user-info { margin-left: 15px; }

        .container {
            margin: 30px auto; padding: 24px;
            width: min(1100px, 92%);
            background-color: rgba(255, 255, 255, 0.87);
            border-radius: 14px; box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            text-align: center; flex-grow: 1;
        }
        h1 { margin: 0 0 14px; color: #003366; }

        .search-bar { margin-bottom: 20px; }
        .search-bar input { padding: 10px; width: 60%; border: 1px solid #ccc; border-radius: 4px; }
        .search-bar button {
            padding: 10px 15px; background-color: #0056b3;
            color: white; border: none; border-radius: 4px; cursor: pointer;
        }
        .search-bar button:hover { background-color: #003f7f; }

        /* --- Εισαγωγή Δεδομένων --- */
        .upload-row {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .upload-container {
            flex: 1;
            min-width: 300px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #f9f9f9;
            text-align: center;
        }
        .upload-container h3 {
            margin-bottom: 10px;
            color: #0056b3;
        }
        .upload-container button {
            margin-top: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        .upload-container button:hover {
            background-color: #218838;
        }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; border-radius: 10px; overflow: hidden; }
        table th {
            background-color: #4da8da; color: #fff; text-align: center; padding: 10px;
        }
        table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        table tr:nth-child(even) { background-color: #e9f5ff; }
        table tr:hover { background-color: #f1f9ff; cursor: pointer; }

        footer {
            background-color: #003f7f; color: white; text-align: center;
            padding: 15px; font-size: 0.9rem;
        }
        footer p { margin: 5px 0; }
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
        <span class="user-info"><a href="login.php" style="color: #ccc">Έξοδος</a></span>
    </div>
</header>

<div class="container">
    <h1>Λίστα Διπλωματικών</h1>

    <div class="search-bar">
        <input type="text" id="search" placeholder="Αναζήτηση διπλωματικών...">
        <button onclick="searchTheses()">Αναζήτηση</button>
    </div>

    <!-- Εισαγωγή Δεδομένων -->
    <div class="upload-row">
        <!-- Εισαγωγή Φοιτητών -->
        <div class="upload-container">
            <h3>Εισαγωγή Φοιτητών</h3>
            <form id="uploadStudentsForm" enctype="multipart/form-data">
                <input type="hidden" name="type" value="students">
                <input type="file" name="json_file" accept=".json" required>
                <button type="button" onclick="uploadData('uploadStudentsForm','studentsMessage')">
                    Προσθήκη Φοιτητών
                </button>
            </form>
            <div id="studentsMessage"></div>
        </div>

        <!-- Εισαγωγή Διδασκόντων -->
        <div class="upload-container">
            <h3>Εισαγωγή Διδασκόντων</h3>
            <form id="uploadProfessorsForm" enctype="multipart/form-data">
                <input type="hidden" name="type" value="professors">
                <input type="file" name="json_file" accept=".json" required>
                <button type="button" onclick="uploadData('uploadProfessorsForm','professorsMessage')">
                    Προσθήκη Διδασκόντων
                </button>
            </form>
            <div id="professorsMessage"></div>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Τίτλος</th>
                    <th>Κατάσταση</th>
                    <th>Ημ. Έναρξης</th>
                    <th>Επιβλέπων</th>
                </tr>
            </thead>
            <tbody id="thesisTableBody"></tbody>
        </table>
    </div>
</div>

<footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    loadTheses();
});

function loadTheses() {
    fetch('fetch_theses.php')
    .then(res => res.json())
    .then(data => {
        renderTable(data);
    })
    .catch(err => console.error("Σφάλμα:", err));
}

function searchTheses() {
    const query = document.getElementById('search').value.trim();
    if (!query) {
        alert("Παρακαλώ εισάγετε όρο αναζήτησης.");
        return;
    }
    fetch('fetch_search_theses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `search=${encodeURIComponent(query)}`
    })
    .then(res => res.json())
    .then(data => {
        renderTable(data);
    })
    .catch(err => console.error("Σφάλμα:", err));
}

function uploadData(formId, msgId) {
    const formData = new FormData(document.getElementById(formId));
    fetch('import_json.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(res => {
        document.getElementById(msgId).innerText = res.message;
    })
    .catch(err => {
        console.error('Σφάλμα:', err);
        document.getElementById(msgId).innerText = "Αποτυχία αποστολής.";
    });
}

function renderTable(theses) {
    const tbody = document.getElementById('thesisTableBody');
    tbody.innerHTML = "";
    if (!theses || theses.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5">Δεν βρέθηκαν διπλωματικές.</td></tr>`;
        return;
    }
    theses.forEach(row => {
        tbody.innerHTML += `
            <tr onclick="window.location.href='process_grammateia.php?thesis_id=${row.thesis_id}'">
                <td>${row.thesis_id}</td>
                <td>${row.title}</td>
                <td>${row.status}</td>
                <td>${row.start_date}</td>
                <td>${row.supervisor_id}</td>
            </tr>`;
    });
}
</script>
</body>
</html>
