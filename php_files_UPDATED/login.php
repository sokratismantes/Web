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
            header("Location: professor_home.php");
        } elseif (strpos($row['email'], 'gr') === 0) {
            header("Location: grammateia_home.php");
        } elseif (strpos($row['email'], 'st') === 0) {
            header("Location: student_home.php");
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
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: url('ggeffyra.png') no-repeat center center fixed;
            background-size: cover;
        }
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
        .title {
            font-size: 1.4rem;
            margin: 200px;
            color: #003366;
            font-weight: bold;
            text-align: right;
        }
        .login-box {
            text-align: center;
            margin: 110px auto 0;
            padding: 20px;
            width: 280px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 70px;
        }
        .login-box h2 {
            color: #39608f;
            margin-bottom: 20px;
            font-size: 1.3rem;
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
        /* Κουμπί Ανακοινώσεις */
        .announcements-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #003366;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
        }
        .announcements-btn:hover {
            background-color: #003f7f;
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo">
            <img src="ceidlogo.png" alt="Λογότυπο">
        </div>
        <div class="title">Σύστημα Υποστήριξης Διπλωματικών Εργασιών</div>
    </div>
</header>

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

<!-- Κουμπί Ανακοινώσεις -->
<a href="announcements.php?from=01012025&to=31122025&format=json"
   target="_blank"
   class="announcements-btn">
   Ανακοινώσεις
</a>

</body>
</html>
