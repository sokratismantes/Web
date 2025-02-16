<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
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
    <title>Λίστα Διπλωματικών</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .container {
            max-width: 1000px;
            margin-top: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px; /* Κάνει το box πιο rounded */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            color: #0056b3;
        }
        .search-bar {
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .search-bar input {
            padding: 8px;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: 8px; /* Rounded edges */
        }
        .search-bar button {
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 8px; /* Rounded edges */
            cursor: pointer;
        }
        .search-bar button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 12px; /* Rounded edges για ολόκληρο τον πίνακα */
            overflow: hidden; /* Κάνει τα rounded edges να δουλεύουν */
        }
        th, td {
            padding: 15px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 16px;
            border-radius: 8px; /* Rounded edges για κάθε κελί */
        }
        th {
            background-color: #007bff;
            color: white;
            border-radius: 12px 12px 0 0; /* Μόνο τα πάνω corners rounded */
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
            cursor: pointer;
            border-radius: 8px; /* Κάνει κάθε row πιο μαλακό στις γωνίες */
        }
        tr {
            transition: background-color 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Λίστα Διπλωματικών</h1>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Αναζήτηση διπλωματικών...">
            <button onclick="searchTheses()">Αναζήτηση</button>
        </div>
        <table id="thesisTable">
            <thead>
                <tr>
                    <th>Τίτλος Διπλωματικής</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Φόρτωση...</td></tr>
            </tbody>
        </table>
    </div>

    <script>
        function loadTheses() {
            fetch('fetch_theses_statistika.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Λήψη διπλωματικών:', data);

                    const thesisTable = document.getElementById('thesisTable').getElementsByTagName('tbody')[0];
                    thesisTable.innerHTML = '';

                    if (!data.theses || data.theses.length === 0) {
                        thesisTable.innerHTML = '<tr><td>Δεν υπάρχουν διαθέσιμες διπλωματικές</td></tr>';
                        return;
                    }

                    data.theses.forEach(thesis => {
                        const row = thesisTable.insertRow();
                        row.innerHTML = `<td>${thesis.title}</td>`;
                        row.onclick = function() {
                            window.location.href = `showstatistics.php?thesis_id=${thesis.thesis_id}`;
                        };
                    });
                })
                .catch(error => {
                    console.error('Σφάλμα φόρτωσης διπλωματικών:', error);
                    document.getElementById('thesisTable').getElementsByTagName('tbody')[0].innerHTML =
                        '<tr><td>Αποτυχία φόρτωσης δεδομένων</td></tr>';
                });
        }

        function searchTheses() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll("#thesisTable tbody tr");

            rows.forEach(row => {
                const title = row.cells[0]?.textContent.toLowerCase();
                if (title.includes(searchInput) || searchInput === "") {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        document.addEventListener('DOMContentLoaded', loadTheses);
    </script>
</body>
</html>

