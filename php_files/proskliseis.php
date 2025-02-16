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


        .status-pending {
            color: #e68a00;
            font-weight: bold;
        }


        .status-accepted {
            color: #28a745;
            font-weight: bold;
        }


        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }


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


        .button:hover {
            background-color: #0056b3;
        }


        .table-wrapper {
            overflow-x: auto;
            margin: 0 auto;
            max-width: 95%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Όλες οι Προσκλήσεις</h1>


        <div class="table-wrapper">
            <table id="invitations-table">
                <thead>
                    <tr>
                        <th>Αριθμός Πρόσκλησης</th>
                        <th>Τίτλος Διπλωματικής</th>
                        <th>ID Καθηγητή</th>
                        <th>Κατάσταση</th>
                        <th>Ημερομηνία Αποστολής</th>
                        <th>Ημερομηνία Απόκρισης</th>
                        <th>Σχόλια</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="empty-message">Φορτώνει δεδομένα...</td>
                    </tr>
                </tbody>
            </table>
        </div>


        <a href="professor_home.php" class="button">Επιστροφή στον Πίνακα Ελέγχου</a>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', function () {
        fetch('fetch_theses(proskliseis).php')
            .then(response => response.json())
            .then(data => {
                const tableBody = document.querySelector('#invitations-table tbody');
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
</body>
</html>







