<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

// Εμφάνιση ειδοποίησης
$success_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λίστα Διπλωματικών</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            margin: 20px auto;
            padding: 20px;
            max-width: 1200px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }

        .search-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .search-bar input {
            padding: 10px;
            width: 50%;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }

        .search-bar button {
            padding: 10px 15px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .search-bar button:hover {
            background-color: #003f7f;
        }

        .add-button, .back-button {
            display: block;
            width: fit-content;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .add-button:hover {
            background-color: #218838;
        }

        .back-button {
            background-color: #0056b3;
        }

        .back-button:hover {
            background-color: #003f7f;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
        }

        table thead tr:first-child th:first-child {
            border-top-left-radius: 12px;
        }

        table thead tr:first-child th:last-child {
            border-top-right-radius: 12px;
        }

        table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }

        table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        table th, table td {
            padding: 15px;
            text-align: left;
            border: 1px solid #ddd;
        }

        table th {
            background-color: #4da8da;
            color: white;
        }

        table tr:hover {
            background-color: #f1f9ff;
        }

        table tr:nth-child(even) {
            background-color: #e9f5ff;
        }

        .message {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            color: green;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <h1>Λίστα Διπλωματικών</h1>

        <div class="search-bar">
            <input type="text" id="search" placeholder="Αναζήτηση διπλωματικών..." onkeyup="fetchTheses()">
            <button onclick="fetchTheses()">Αναζήτηση</button>
        </div>

        <a href="addThesis.php" class="add-button">Προσθήκη Νέου Θέματος</a>

        <div class="table-wrapper">
            <table id="theses-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Τίτλος</th>
                        <th>Κατάσταση</th>
                        <th>Ημ. Έναρξης</th>
                        <th>Ημ. Λήξης</th>
                        <th>Επιβλέπων</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" style="text-align: center;">Φορτώνει δεδομένα...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <a href="professor_home.php" class="back-button">Επιστροφή στον Πίνακα Ελέγχου</a>
    </div>

    <script>
        function fetchTheses() {
            const search = document.getElementById('search').value.trim();
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `fetch_theses(listaDiplomatikon).php?search=${encodeURIComponent(search)}`, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const theses = JSON.parse(xhr.responseText);
                        const tableBody = document.querySelector('#theses-table tbody');
                        tableBody.innerHTML = '';
                        if (theses.length > 0) {
                            theses.forEach(thesis => {
                                const row = `<tr onclick="redirectToProcess(${thesis.thesis_id})">
                                    <td>${thesis.thesis_id}</td>
                                    <td>${thesis.title}</td>
                                    <td>${thesis.status}</td>
                                    <td>${thesis.start_date}</td>
                                    <td>${thesis.end_date || ''}</td>
                                    <td>${thesis.supervisor_id}</td>
                                </tr>`;
                                tableBody.innerHTML += row;
                            });
                        } else {
                            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Δεν βρέθηκαν διπλωματικές.</td></tr>';
                        }
                    } catch (e) {
                        console.error("Σφάλμα κατά την επεξεργασία των δεδομένων JSON:", e);
                    }
                }
            };
            xhr.send();
        }

        function redirectToProcess(thesisId) {
            window.location.href = `process.php?thesis_id=${thesisId}`;
        }

        function fetchInvitations() {
            fetch('fetch_theses(proskliseis).php')
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.querySelector('#invitations-table tbody');
                    tableBody.innerHTML = '';

                    if (data.length > 0) {
                        data.forEach(invitation => {
                            const row = `
                                <tr>
                                    <td>${invitation.invitation_id}</td>
                                    <td>${invitation.title || 'Χωρίς τίτλο'}</td>
                                    <td>${invitation.invited_professor_id}</td>
                                    <td>${invitation.status}</td>
                                    <td>${invitation.sent_at}</td>
                                    <td>${invitation.responded_at || 'Δεν υπάρχει'}</td>
                                    <td>${invitation.comments || 'Χωρίς σχόλια'}</td>
                                </tr>`;
                            tableBody.innerHTML += row;
                        });
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="empty-message">Δεν υπάρχουν διαθέσιμες προσκλήσεις.</td>
                            </tr>`;
                    }
                })
                .catch(error => console.error('Σφάλμα:', error));
        }

        document.addEventListener('DOMContentLoaded', fetchTheses);
    </script>
</body>
</html>

