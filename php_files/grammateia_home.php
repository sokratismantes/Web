<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Η σύνδεση απέτυχε: " . $conn->connect_error);
}

// Ανάκτηση ονόματος χρήστη
$name = "Χρήστης";
$sql = "SELECT full_name FROM grammateia INNER JOIN users ON grammateia.grammateia_id = users.user_id WHERE users.email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result_name = $stmt->get_result();
if ($result_name->num_rows > 0) {
    $row = $result_name->fetch_assoc();
    $name = $row['full_name'];
}



// Αναζήτηση
$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}


// Ερώτημα για διπλωματικές
$sql = "SELECT thesis_id, title, status, start_date, supervisor_id 
        FROM Theses
    
        ORDER BY thesis_id ASC";

$result = $conn->query($sql);

// Αποθήκευση δεδομένων σε πίνακα
$theses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $theses[] = $row;
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διπλωματικές Εργασίες</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #f5f5f5;
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
        }


        .header a {
            text-decoration: none;
            display: flex;
            align-items: center;
            color: inherit;
        }


        .header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            cursor: pointer;
        }


        .header span {
            font-size: 1.2rem;
            color: #0056b3;
        }

        .container {
            margin: 20px auto;
            padding: 20px;
            max-width: 1200px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }


        h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }


        .search-bar {
            text-align: center;
            margin-bottom: 20px;
        }


        .search-bar input {
            padding: 10px;
            width: 60%;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
        }


        .search-bar button {
            padding: 10px 15px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }


        .search-bar button:hover {
            background-color: #003f7f;
        }
        
        .upload-container { 
            text-align: center; 
            margin: 10px 0;
        }

        .upload-container button {
            padding: 10px 15px;
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer;
        }
        .upload-container button:hover { 
            background-color: #218838;  
        }

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px; /* Στρογγυλεμένες γωνίες */
            overflow: hidden;
            margin-bottom: 20px;
        }


        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }


        table th {
            background-color: #4da8da; /* Ανοιχτό μπλε */
            color: white;
        }


        table tr:hover {
            background-color: #f1f9ff; /* Ελαφρύ μπλε hover */
        }


        table tr:nth-child(even) {
            background-color: #e9f5ff; /* Απαλό μπλε για τις εναλλασσόμενες σειρές */
        }



        .message {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            color: green;
        }

        .logout-button {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }


        .logout-button:hover {
            background-color: #555;
        }
        
  
        #backToHome {
            margin-top: 10px;
            background-color: #0056b3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
}

        #backToHome:hover {
            background-color: #003f7f;


}
</style>

</head>
<body>

<!-- Header Section -->
<div class="header">
        <a href="profile_edit.php">
            <img src="User_image.png" alt="User Icon">
            <span>Welcome <?php echo htmlspecialchars($name); ?>,</span>
        </a>
        <!-- Κουμπί αποσύνδεσης -->
        <button class="logout-button" onclick="logout()">Αποσύνδεση</button>
    </div>


    <div class="container">
        <div class="grid">
    <div class="container">

        <!-- Εμφάνιση μηνύματος επιτυχίας -->
        <?php if (!empty($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <h1>Λίστα Διπλωματικών</h1>

       <!-- Μπάρα Αναζήτησης -->
       <div class="search-bar">
    <form onsubmit="searchTheses(); return false;">
        <input type="text" id="search" name="search" placeholder="Αναζήτηση διπλωματικών...">
        <button type="button" onclick="searchTheses()">Αναζήτηση</button>
    </form>
</div>

        <!-- Φόρμα για ανέβασμα αρχείου JSON -->
<div class="upload-container">
<form id="uploadForm" enctype="multipart/form-data">
    <label for="json_file">Εισαγωγή Δεδομένων</label>
    <input type="file" id="json_file" name="json_file" accept=".json" required>
    <button type="button" onclick="uploadFile()">Προσθήκη</button>
</form>
<div id="uploadMessage"></div>


        <!-- Πίνακας Διπλωματικών -->
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
                
                <tbody id="thesisTableBody">
    <?php if (!empty($theses)): ?>
        <?php foreach ($theses as $row): ?>
            <tr onclick="redirectToProcess(<?php echo $row['thesis_id']; ?>)">
                <td><?php echo $row['thesis_id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['start_date']); ?></td>
                <td><?php echo htmlspecialchars($row['supervisor_id']); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5" style="text-align: center;">Δεν βρέθηκαν διπλωματικές.</td>
        </tr>
    <?php endif; ?>
</tbody>


            </table>
        </div>
    </div>

<!-- Κουμπί Επιστροφής -->
<div>
    <button type="button" onclick="goToHomePage()" id="backToHome" style="display: none;">Επιστροφή στην Αρχική
  </button>
  </div>

<script>
        function redirectToProcess(thesisId) {
            window.location.href = `process_grammateia.php?thesis_id=${thesisId}`;
        }

        
    </script>

<script>
        document.addEventListener('DOMContentLoaded', function () {
            // Προειδοποίηση μόνο για το κουμπί "Αποσύνδεση"
            document.querySelector('.logout-button').addEventListener('click', function () {
                if (confirm("Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;")) {
                    window.location.href = "logout.php";
                }
            });


            // Προειδοποίηση για το πίσω κουμπί του browser
            window.addEventListener('popstate', function (e) {
                if (confirm("Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;")) {
                    window.location.href = "logout.php";
                } else {
                    history.pushState(null, null, location.href); // Μπλοκάρει το πίσω κουμπί
                }
            });
            history.pushState(null, null, location.href); // Αρχική κατάσταση ιστορικού
        });
        </script>
<script>
    function uploadFile() {
    const formData = new FormData(document.getElementById('uploadForm'));

    fetch('import_json.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.text(); // ή response.json() αν περιμένεις JSON
    })
    .then(data => {
        document.getElementById('uploadMessage').innerText = data;
    })
    .catch(error => {
        console.error('Σφάλμα κατά την αποστολή του αρχείου:', error);
    });
}

</script>
<script>
  function searchTheses() {
    const searchQuery = document.getElementById('search').value.trim();

    if (searchQuery === "") {
        alert("Παρακαλώ εισάγετε όρο αναζήτησης.");
        return;
    }

    fetch('fetch_search_theses.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `search=${encodeURIComponent(searchQuery)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.text();
    })
    .then(data => {
        document.getElementById('thesisTableBody').innerHTML = data;
        document.getElementById('backToHome').style.display = "inline-block";
    })
    .catch(error => {
        console.error('Σφάλμα κατά την κλήση AJAX:', error);
    });
}


function goToHomePage() {
    // Ανακατεύθυνση στην αρχική σελίδα
    window.location.href = "grammateia_home.php";
}


</script>


</body>
</html>


