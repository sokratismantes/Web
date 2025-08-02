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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
  />
  <title>Σύστημα Υποστήριξης Διπλωματικών</title>

  <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap');

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Montserrat', sans-serif;
    }

    body {
      background: linear-gradient(to right, #e2e2e2, #c9d6ff);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      min-height: 100vh;
    }

    header {
    display: flex;
    justify-content: center;
    background-color:rgba(0, 51, 102, 0.92);
    padding: 10px 0;
    width: 100%;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    height: 100px; 
    }

    .header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 90%;
    max-width: 1300px;
    }

    .logo {
    display: flex;
    align-items: center;
    gap: 15px;
    }

    .logo img {
    height: 60px;
    width: auto;
    }

    .logo-text {
    color: white;
    font-size: 0.95rem;
    font-weight: 400;
    line-height: 1.3;
    max-width: 200px;
    }

    .title {
    font-size: 1.3rem; /* μικρότερη γραμματοσειρά */
    color: white;
    font-weight: bold;
    margin-left: auto;
    text-align: right;
    }

    .container {
    background-color: #fff;
    border-radius: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35);
    position: relative;
    overflow: hidden;
    width: 1020px;
    max-width: 100%;
    min-height: 550px;
    margin-top: 60px;
    margin-bottom: 80px;

    opacity: 0;
    transform: translateY(30px);
    animation: fadeInContainer 1.2s ease-out forwards;
    }


    .container p {
      font-size: 14px;
      line-height: 20px;
      letter-spacing: 0.3px;
      margin: 20px 0;
    }

    .container span {
      font-size: 12px;
    }

    .container a {
      color: #333;
      font-size: 13px;
      text-decoration: none;
      margin: 15px 0 10px;
    }

    .container button {
      background-color:rgba(0, 51, 102, 0.92);
      color: #fff;
      font-size: 12px;
      padding: 10px 45px;
      border: 1px solid transparent;
      border-radius: 8px;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-top: 10px;
      cursor: pointer;
    }

    .container button.hidden {
      background-color: transparent;
      border-color: #fff;
    }

    .container form {
      background-color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      padding: 0 40px;
      height: 100%;
    }

    .container input {
      background-color: #eee;
      border: none;
      margin: 8px 0;
      padding: 10px 15px;
      font-size: 13px;
      border-radius: 8px;
      width: 100%;
      outline: none;
    }

    .form-container {
      position: absolute;
      top: 0;
      height: 100%;
      transition: all 0.6s ease-in-out;
    }

    .sign-in {
      left: 0;
      width: 50%;
      z-index: 2;
    }

    .container.active .sign-in {
      transform: translateX(100%);
    }

    .sign-up {
      left: 0;
      width: 50%;
      opacity: 0;
      z-index: 1;
    }

    .container.active .sign-up {
      transform: translateX(100%);
      opacity: 1;
      z-index: 5;
      animation: move 0.6s;
    }

    @keyframes move {
      0%, 49.99% {
        opacity: 0;
        z-index: 1;
      }
      50%, 100% {
        opacity: 1;
        z-index: 5;
      }
    }

    .social-icons {
      margin: 20px 0;
    }

    .social-icons a {
      border: 1px solid #ccc;
      border-radius: 20%;
      display: inline-flex;
      justify-content: center;
      align-items: center;
      margin: 0 3px;
      width: 40px;
      height: 40px;
    }

    .toggle-container {
      position: absolute;
      top: 0;
      left: 50%;
      width: 50%;
      height: 100%;
      overflow: hidden;
      transition: all 0.6s ease-in-out;
      border-radius: 150px 0 0 100px;
      z-index: 1000;
    }

    .container.active .toggle-container {
      transform: translateX(-100%);
      border-radius: 0 150px 100px 0;
    }

    .toggle {
      background: linear-gradient(to right,rgba(0, 51, 102, 0.92), rgba(0, 51, 102, 0.92));
      color: #fff;
      position: relative;
      left: -100%;
      height: 100%;
      width: 200%;
      transform: translateX(0);
      transition: all 0.6s ease-in-out;
    }

    .container.active .toggle {
      transform: translateX(50%);
    }

    .toggle-panel {
      position: absolute;
      width: 50%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      padding: 0 30px;
      text-align: center;
      top: 0;
      transition: all 0.6s ease-in-out;
    }

    .toggle-left {
      transform: translateX(-200%);
    }

    .container.active .toggle-left {
      transform: translateX(0);
    }

    .toggle-right {
      right: 0;
      transform: translateX(0);
    }

    .container.active .toggle-right {
      transform: translateX(200%);
    }

    .transparent-button {
    background-color: transparent;
    border: 2px solid #003366; /* μπλε περίγραμμα */
    color: #003366;
    font-weight: bold;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease, color 0.3s ease;
    }

    .transparent-button:hover {
    background-color: #003366;
    color: white;
    }

    .transparent-button {
    background-color: transparent;
    border: 2px solid #003366; /* μπλε περίγραμμα */
    color: #003366;
    font-weight: bold;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s ease, color 0.3s ease;
    }

    .transparent-button:hover {
    background-color: #003366;
    color: white;
    }

    .top-image {
  width: 100%;
  max-height: 220px;
  overflow: hidden;
}

.top-image img {
  width: 100%;
  object-fit: cover;
  display: block;
}

.banner-image {
  width: 100%;
  max-height: 260px; /* ή όσο θέλεις */
  object-fit: cover;
  animation: fadeDown 1.2s ease-out forwards;
  opacity: 0;
  transform: translateY(-30px);
}

.subtitle {
  font-size: 0.80rem; 
  font-weight: 500;
  color: #d0d8e8;
  margin-top: 5px;
  line-height: 1.3;
}

@keyframes fadeDown {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

footer {
    flex-shrink: 0;
    width: 100%;
    background-color: rgba(0, 51, 102, 0.92);
    background-color:;
    color: white;
    text-align: center;
    padding: 30px;
    margin-top: 20px;
}

#particles-js {
  position: fixed;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  z-index: -1;
}
#particles-canvas {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: -1;
}

@keyframes fadeInContainer {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.intro-banner {
  text-align: center;
  margin-top: 40px;
  padding: 10px 20px;
  animation: fadeInUp 1.3s ease-out;
}

.intro-banner h2 {
  font-size: 1.6rem;
  font-weight: 700;
  color: #003366;
  margin-bottom: 8px;
}

.intro-banner p {
  font-size: 1rem;
  color:  #003366;
  font-weight: 500;
}

/* Fade in with slight upward movement */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(25px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.announcement-banner {
  text-align: center;
  margin-top: 35px;
  animation: fadeInScale 0.8s ease-in-out;
}

.announcement-main-button {
  background-color: #003366;
  color: white;
  padding: 10px 22px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 400;
  font-size: 0.95rem;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  transition: background-color 0.3s, transform 0.3s;
}

.announcement-main-button:hover {
  background-color: #001e40;
  transform: scale(1.03);
}

@keyframes fadeInScale {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
  </style>
</head>
<body>
<canvas id="particles-canvas"></canvas>
  <header>
  <div class="header-content">
    <div class="logo">
      <img src="ceid_logo.png" alt="Λογότυπο">
      <div class="logo-text">Computer Engineering<br>& Informatics Department</div>
    </div>
    <div class="title">
      <div>Σύστημα Υποστήριξης Διπλωματικών Εργασιών</div>
      <div class="subtitle">Διαχείριση, παρακολούθηση και αξιολόγηση διπλωματικών εργασιών με σύγχρονα εργαλεία.</div>
    </div>
  </div>
</header>

<div id="particles-js"></div>

  <div class="top-image">
  <img src="ceid_topview.png" alt="ceid" class="banner-image">

</div>
 <div class="intro-banner">
  <h2>Η Διπλωματική Εργασία δεν είναι ατομική υπόθεση.</h2>
  <p>➤ Συνεργασία. Επιτήρηση. Αξιολόγηση. Όλοι μαζί, σε ένα σύστημα.</p>
  <div class="announcement-banner">
  <a href="announcements.php" class="announcement-main-button">ΑΝΑΚΟΙΝΩΣΕΙΣ ΠΑΡΟΥΣΙΑΣΕΩΝ</a>
</div>
</div>
  <div class="container" id="container">
    <div class="form-container sign-up">
      <form>
        <h1>Δημιουργία Λογαριασμού</h1>
        <div class="social-icons">
          <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
          <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
          <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
        <span>ή χρησιμοποιήστε email για εγγραφή</span>
        <input type="text" placeholder="Όνομα" />
        <input type="email" placeholder="Email" />
        <input type="password" placeholder="Κωδικός" />
        <button>Εγγραφή</button>
      </form>
    </div>

    <div class="form-container sign-in">
      <form method="POST" action="">
  <h1>Σύνδεση</h1>
  <div class="social-icons">
    <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
    <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
  </div>
  <span>ή χρησιμοποιήστε email και κωδικό</span>
  <input type="email" name="username" placeholder="Email" required />
  <input type="password" name="password" placeholder="Κωδικός" required />
  <a href="#">Ξεχάσατε τον κωδικό;</a>
  <button type="submit">Σύνδεση</button>
</form>

    </div>

    <div class="toggle-container">
      <div class="toggle">
        <div class="toggle-panel toggle-left">
          <h1>Καλώς Ήρθες Πίσω!</h1>
          <p>Συμπληρώστε τα στοιχεία σας για να συνδεθείτε</p>
          <button class="hidden" id="login">Σύνδεση</button>
        </div>
        <div class="toggle-panel toggle-right">
          <h1>Γεια Σου!</h1>
          <p>Δημιούργησε λογαριασμό για να έχεις πρόσβαση σε όλες τις λειτουργίες του συστήματος</p>
          <button class="hidden" id="register">Εγγραφή</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const container = document.getElementById("container");
    const registerBtn = document.getElementById("register");
    const loginBtn = document.getElementById("login");

    registerBtn.addEventListener("click", () => {
      container.classList.add("active");
    });

    loginBtn.addEventListener("click", () => {
      container.classList.remove("active");
    });
  </script>

  <footer>
    <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
    <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
<script>
  const canvas = document.getElementById('particles-canvas');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  const icons = ['\uf0c0', '\uf0e0', '\uf00c', '\uf007', '\uf15c']; // users, envelope, check, user, file
  const particles = [];

  class Particle {
    constructor() {
      this.reset();
    }

    reset() {
      this.x = Math.random() * canvas.width;
      this.y = canvas.height + Math.random() * canvas.height;
      this.size = Math.random() * 18 + 12;
      this.icon = icons[Math.floor(Math.random() * icons.length)];
      this.speed = Math.random() * 0.7 + 0.2;
      this.opacity = Math.random() * 0.5 + 0.3;
    }

    update() {
      this.y -= this.speed;
      if (this.y < -50) this.reset();
    }

    draw() {
      ctx.font = `${this.size}px "Font Awesome 6 Free"`;
      ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
      ctx.fillText(this.icon, this.x, this.y);
    }
  }

  for (let i = 0; i < 100; i++) {
    particles.push(new Particle());
  }

  function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let p of particles) {
      p.update();
      p.draw();
    }
    requestAnimationFrame(animate);
  }

  animate();

  window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  });
</script>

</body>
</html>
