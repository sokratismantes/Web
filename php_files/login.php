<?php

// Στοιχεία σύνδεσης με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst"; // Σωστή βάση δεδομένων

// Δημιουργία σύνδεσης με τη βάση
$conn = new mysqli($servername, $username_db, $password_db, $dbname);


// Έλεγχος σύνδεσης
if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Ανάκτηση δεδομένων για τις διπλωματικές
$theses = [];
$sql = "SELECT thesis_id, title, description FROM theses";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $theses[] = $row;
    }
}

// Διαχείριση υποβολής φόρμας
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['username']; // Χρησιμοποιούμε το email για το username
    $password = $_POST['password'];


// Προστασία από SQL Injection
    $email = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);


// Έλεγχος για τον χρήστη στη βάση
    $sql = "SELECT * FROM Users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);


    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        session_start();
        $_SESSION['email'] = $row['email']; // Αποθήκευση του email στη συνεδρία
        $_SESSION['user_type'] = $row['user_type']; // Αποθήκευση του τύπου χρήστη
    // Έλεγχος για το πρόθεμα του email
    if (strpos($row['email'], 'pr') === 0) {
        header("Location: professor_home.php"); // Ανακατεύθυνση στη σελίδα καθηγητή
    } elseif (strpos($row['email'], 'gr') === 0) {
        header("Location: grammateia_home.php"); // Ανακατεύθυνση στη σελίδα γραμματείας
    } elseif (strpos($row['email'], 'st') === 0) {
        header("Location: student_home.php"); // Ανακατεύθυνση στη σελίδα φοιτητή
    } else {
        echo "<script>alert('Άγνωστος τύπος χρήστη.');</script>";
    }
    exit();
} else {
    echo "<script>alert('Λάθος email ή κωδικός πρόσβασης.');</script>";
}
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύστημα Υποστήριξης Διπλωματικών Εργασιών</title>
    <style>
        /* Γενικό στυλ για το σώμα */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: url('ggeffyra.png') no-repeat center center fixed;
            background-size: cover;
        }




        /* Στυλ για το header */
        header {
            display: flex;
            flex-direction: column;
            align-items: left;
            justify-content: center;
            height: 100px;
            background-color: rgba(255, 255, 255, 0.1);
           
           
        }




        .header-content {
            display: flex;
            align-items: right;
            justify-content: space-between;
            width: 100%;
            max-width: 3000px;
           
        }




        .logo {
            display: flex;
            align-items: center;
        }




        .logo img {
            width: 200px;
            height: auto;
            margin-right: 15px;
        }




        .logo span {
            font-size: 1.1rem;
            font-weight: bold;
            color: #003366; /* Μπλε σκούρο */
        }




        .title {
            font-size: 1.4rem;
            margin: 200px ;
            color: #003366; /* Μπλε σκούρο */
            font-weight: bold;
            text-align: right;
        }




        /* Κεντρικό κουτί για το Login */
        .login-box {
            text-align: center;
            margin: 110px auto 0; /* Απόσταση από την κορυφή */
            padding: 20px;
            width: 280px;
            background-color: rgba(255, 255, 255, 0.2); /* Περισσότερη διαφάνεια */
            border-radius: 70px;
           
        }




        .login-box h2 {
            color: #39608f;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-radius: 20px;
           
        }




        .login-box img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
            border-radius: 40%;
            background-color: #ddd;
        }




        .login-box input {
            display: block;
            width: 80%;
            margin: 10px auto;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 20px;
            font-size: 1rem;
            background-color: rgba(255, 255, 255, 0.3);
       
        }




        .login-box input::placeholder {
            color: #003366; /* Μπλε σκούρο placeholder */
         
        }




        .login-box button {
            display: block;
            width: 80%;
            padding: 10px;
            background-color: #003366;
            color: white;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 20px;
            margin: 10px auto;
           
        }




        .login-box button:hover {
            background-color: #003f7f;
        }

    /* Responsive styles */
    @media (max-width: 768px) {
            .login-box {
                width: 90%;
                padding: 15px;
            }
        }
    
        #menuModal div {
    background: white;
    width: 90%;
    max-width: 600px;
    margin: 10% auto;
    padding: 20px;
    border-radius: 10px;
    position: relative;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    max-height: 70%; /* Περιορισμός ύψους */
    overflow-y: scroll; /* Scroll πάντα ορατό */
    overflow-x: hidden; /* Απόκρυψη οριζόντιου scroll */
}


    </style>
</head>




<body>
    <!-- Πάνω μέρος της σελίδας -->
    <header>
        <div class="header-content">
            <div class="logo">
                <img src="ceidlogo.png" alt="Λογότυπο">
               
         
        </div>
        <div class="title">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</div>
    </header>




    <!-- Κουτί σύνδεσης -->
    <main>
        <div class="login-box">
            <h2>Log In</h2>
            <img src="User_image.png" alt="User Icon">
            <form method="POST">
                <input type="text" name="username" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Σύνδεση</button>
            </form>
        </div>
    </main>


<!-- Κουμπί μενού πάνω δεξιά -->
<div style="position: fixed; top: 20px; right: 20px;">
    <button onclick="openMenu()" style="
        background-color: #003366;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;">
        Μενού
    </button>
</div>


<!-- Modal για τον πίνακα των διπλωματικών -->
<div id="menuModal" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;">
    <div style="
        background: white;
        width: 90%;
        max-width: 600px;
        margin: 10% auto;
        padding: 20px;
        border-radius: 10px;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);"
        max-height: 70%;
        overflow-y: scroll; 
        overflow-x: hidden;">

        <span onclick="closeMenu()" style="
            position: absolute;
            top: 10px; right: 15px;
            cursor: pointer;
            font-size: 1.5rem;">&times;</span>

        <h3>Πίνακας Διπλωματικών</h3>
        <table style="width: 100%; border-collapse: collapse; text-align: center;">
            <thead>
                <tr>
                    <th style="background: #003366; color: white; padding: 10px;">Τίτλος</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($theses as $thesis): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; cursor: pointer;"
                        onclick="showThesisDescription('<?php echo htmlspecialchars(addslashes($thesis['description'])); ?>')">
                        <?php echo htmlspecialchars($thesis['title']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal για την περιγραφή διπλωματικής -->
<div id="descriptionModal" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;">
    <div style="
        background: white;
        width: 90%;
        max-width: 500px;
        margin: 20% auto;
        padding: 20px;
        border-radius: 10px;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
        <span onclick="closeDescriptionModal()" style="
            position: absolute;
            top: 10px; right: 15px;
            cursor: pointer;
            font-size: 1.5rem;">&times;</span>
        <p id="thesisDescription" style="font-size: 1rem; color: #333;"></p>
    </div>
</div>

<!-- JavaScript για την εμφάνιση/απόκρυψη modal -->
<script>
function openMenu() {
    const menuModal = document.getElementById('menuModal');
    menuModal.style.display = 'block';
    setTimeout(() => menuModal.style.opacity = '1', 10);
}

function closeMenu() {
    const menuModal = document.getElementById('menuModal');
    menuModal.style.opacity = '0';
    setTimeout(() => menuModal.style.display = 'none', 300);
}

function showThesisDescription(description) {
    const descriptionModal = document.getElementById('descriptionModal');
    document.getElementById('thesisDescription').textContent = description;
    descriptionModal.style.display = 'block';
    setTimeout(() => descriptionModal.style.opacity = '1', 10);
}

function closeDescriptionModal() {
    const descriptionModal = document.getElementById('descriptionModal');
    descriptionModal.style.opacity = '0';
    setTimeout(() => descriptionModal.style.display = 'none', 300);
}
</script>

<!-- Responsive CSS -->
<style>
@media (max-width: 768px) {
    .login-box {
        width: 90%;
        padding: 15px;
    }

    table {
        font-size: 0.9rem;
    }

    td {
        padding: 8px;
    }
}

@media (max-width: 480px) {
    h3 {
        font-size: 1.2rem;
    }

    td {
        font-size: 0.8rem;
    }

    button {
        font-size: 0.9rem;
    }
}
</style>

</div>


<!-- JavaScript για το modal -->
<script>
function openMenu() {
    document.getElementById('menuModal').style.display = 'block';
}
function closeMenu() {
    document.getElementById('menuModal').style.display = 'none';
}
</script>


</body>
</html>







