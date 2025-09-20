<?php
session_start();

$servername  = "localhost";
$username_db = "root";
$password_db = "";
$dbname      = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

$errorMsg = '';
if (isset($_SESSION['flash_error'])) {
    $errorMsg = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$theses = [];
$sql    = "SELECT thesis_id, title, description FROM theses";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $theses[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $email    = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);

    $sql    = "SELECT * FROM Users WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row                 = $result->fetch_assoc();
        $_SESSION['email']   = $row['email'];
        $_SESSION['user_type']= $row['user_type'];

        if (strpos($row['email'], 'pr') === 0) {
            header("Location: professor_home.php");
            exit();
        } elseif (strpos($row['email'], 'gr') === 0) {
            header("Location: grammateia_home.php");
            exit();
        } elseif (strpos($row['email'], 'st') === 0) {
            header("Location: student_home.php");
            exit();
        } else {
            $_SESSION['flash_error'] = 'Άγνωστος τύπος χρήστη.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        $_SESSION['flash_error'] = 'Λάθος email ή κωδικός πρόσβασης.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
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
      background-color: rgba(0, 51, 102, 0.92);
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
      font-size: 1.3rem;
      color: white;
      font-weight: bold;
      margin-left: auto;
      text-align: right;
    }

    .subtitle {
      font-size: 0.80rem;
      font-weight: 500;
      color: #d0d8e8;
      margin-top: 5px;
      line-height: 1.3;
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
      background-color: rgba(0, 51, 102, 0.92);
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
      0%, 49.99% { opacity: 0; z-index: 1; }
      50%, 100%  { opacity: 1; z-index: 5; }
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
      background: linear-gradient(to right, rgba(0, 51, 102, 0.92), rgba(0, 51, 102, 0.92));
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
      border: 2px solid #003366;
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
      max-height: 260px;
      object-fit: cover;
      animation: fadeDown 1.2s ease-out forwards;
      opacity: 0;
      transform: translateY(-30px);
    }

    @keyframes fadeDown {
      to { opacity: 1; transform: translateY(0); }
    }

    footer {
      flex-shrink: 0;
      width: 100%;
      background-color: rgba(0, 51, 102, 0.92);
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
      to { opacity: 1; transform: translateY(0); }
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

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(25px); }
      to   { opacity: 1; transform: translateY(0);    }
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
      from { opacity: 0; transform: scale(0.9); }
      to   { opacity: 1; transform: scale(1);   }
    }

    .synthesis-hero {
      width: min(280px, 45%);
      margin: 0 auto 8px;
    }

    .ann-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    .ann-modal-overlay.open {
      display: flex;
    }

    .ann-modal {
      background: #fff;
      width: min(520px, 92vw);
      border-radius: 12px;
      box-shadow: 0 20px 40px rgba(0,0,0,.2);
      padding: 16px 20px;
    }

    .ann-modal header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
      background: transparent;
      box-shadow: none;
      height: auto;
      padding: 0;
    }

    .ann-modal h3 {
      margin: 0;
      font-size: 1.1rem;
      color: #003366;
    }

    .ann-modal .close {
      background: transparent;
      border: 0;
      font-size: 22px;
      cursor: pointer;
      line-height: 1;
    }

    .ann-modal .row {
      display: flex;
      gap: .75rem;
      align-items: center;
      margin: 12px 0;
      flex-wrap: wrap;
    }

    .ann-modal label {
      font-weight: 600;
    }

    .ann-modal input[type="date"] {
      padding: .4rem .5rem;
    }

    .ann-modal .actions {
      display: flex;
      gap: .5rem;
      justify-content: flex-end;
      margin-top: 12px;
    }

    .ann-modal .btn {
      background: #003366;
      color: #fff;
      border: 0;
      padding: .55rem .9rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 700;
    }

    #error-msg {
      position: fixed;
      top: 110px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 10000;
      width: min(820px, 92%);
      background: #fff5f5;
      border: 1px solid #f5c2c7;
      color: #842029;
      padding: 12px 16px;
      border-radius: 10px;
      font-weight: 600;
      box-shadow: 0 10px 24px rgba(0,0,0,.12);
      pointer-events: auto;
    }

    #error-msg.fade-out {
      opacity: 0;
      transform: translate(-50%, -6px);
      max-height: 0;
      margin: 0;
      padding: 0 16px;
      overflow: hidden;
      transition:
        opacity   .4s ease,
        transform .4s ease,
        max-height.4s ease,
        margin    .4s ease,
        padding   .4s ease;
    }
  </style>
</head>
<body>
  <canvas id="particles-canvas"></canvas>

  <header>
    <div class="header-content">
      <div class="logo">
        <img src="ceid_logo.png" alt="Λογότυπο" />
        <div class="logo-text">Computer Engineering<br />& Informatics Department</div>
      </div>
      <div class="title">
        <div>Σύστημα Υποστήριξης Διπλωματικών Εργασιών</div>
        <div class="subtitle">Διαχείριση, παρακολούθηση και αξιολόγηση διπλωματικών εργασιών με σύγχρονα εργαλεία.</div>
      </div>
    </div>
  </header>

  <?php if (!empty($errorMsg)): ?>
    <div id="error-msg" role="alert" aria-live="polite">
      <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div id="particles-js"></div>

  <div class="top-image">
    <img src="ceid_topview.png" alt="ceid" class="banner-image" />
  </div>

  <div class="intro-banner">
    <img
      src="synthesis.png"
      alt="SynThesis"
      class="synthesis-hero"
      loading="lazy"
    />
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
        <span>Χρησιμοποιήστε email για εγγραφή</span>
        <input type="text" placeholder="Όνομα" />
        <input type="email" placeholder="Email" />
        <input type="password" placeholder="Κωδικός" />
        <button>Εγγραφή</button>
      </form>
    </div>

    <div class="form-container sign-in">
      <form method="POST" action="">
        <h1>Σύνδεση</h1>
        <span>Χρησιμοποιήστε email και κωδικό</span>
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
    const container   = document.getElementById("container");
    const registerBtn = document.getElementById("register");
    const loginBtn    = document.getElementById("login");

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

  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"
    crossorigin="anonymous"
  ></script>

  <script>
    const canvas = document.getElementById('particles-canvas');
    const ctx    = canvas.getContext('2d');

    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;

    const icons     = ['\uf0c0', '\uf0e0', '\uf00c', '\uf007', '\uf15c'];
    const particles = [];

    class Particle {
      constructor() {
        this.reset();
      }
      reset() {
        this.x        = Math.random() * canvas.width;
        this.y        = canvas.height + Math.random() * canvas.height;
        this.size     = Math.random() * 18 + 12;
        this.icon     = icons[Math.floor(Math.random() * icons.length)];
        this.speed    = Math.random() * 0.7 + 0.2;
        this.opacity  = Math.random() * 0.5 + 0.3;
      }
      update() {
        this.y -= this.speed;
        if (this.y < -50) this.reset();
      }
      draw() {
        ctx.font      = `${this.size}px "Font Awesome 6 Free"`;
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
      canvas.width  = window.innerWidth;
      canvas.height = window.innerHeight;
    });
  </script>

  <div id="annModal" class="ann-modal-overlay" aria-hidden="true">
    <div class="ann-modal" role="dialog" aria-modal="true" aria-labelledby="annTitle">
      <header>
        <h3 id="annTitle">Ανακοινώσεις Παρουσιάσεων — Εξαγωγή</h3>
        <button type="button" class="close" aria-label="Κλείσιμο" id="annClose">&times;</button>
      </header>

      <div class="row">
        <label for="annFrom">Από:</label>
        <input type="date" id="annFrom" />
        <label for="annTo">Έως:</label>
        <input type="date" id="annTo" />
      </div>

      <div class="actions">
        <button class="btn" id="annJson">Λήψη JSON</button>
        <button class="btn" id="annXml">Λήψη XML</button>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const trigger = document.querySelector('.announcement-main-button');
      const modal   = document.getElementById('annModal');
      if (!trigger || !modal) return;

      const btnJson  = document.getElementById('annJson');
      const btnXml   = document.getElementById('annXml');
      const btnClose = document.getElementById('annClose');
      const fromEl   = document.getElementById('annFrom');
      const toEl     = document.getElementById('annTo');

      function openModal(e) {
        if (e) e.preventDefault();
        setDefaults();
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
      }

      function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
      }

      function setDefaults() {
        const now   = new Date();
        const yyyy  = now.getFullYear();
        const mm    = String(now.getMonth() + 1).padStart(2, '0');
        const dd    = String(now.getDate()).padStart(2, '0');
        const first = `${yyyy}-${mm}-01`;
        const today = `${yyyy}-${mm}-${dd}`;

        if (!fromEl.value) fromEl.value = first;
        if (!toEl.value)   toEl.value   = today;
      }

      function toDdMmYyyy(iso) {
        const [y, m, d] = iso.split('-');
        return d + m + y;
      }

      function download(fmt) {
        const fISO = fromEl.value;
        const tISO = toEl.value;
        if (!fISO || !tISO) {
          alert('Συμπληρώστε ημερομηνίες.');
          return;
        }
        const base = trigger.href;
        const url  = new URL(base, window.location.href);

        url.searchParams.set('from', toDdMmYyyy(fISO));
        url.searchParams.set('to',   toDdMmYyyy(tISO));
        url.searchParams.set('format', fmt);
        url.searchParams.set('download', '1');

        window.open(url.toString(), '_blank');
        closeModal();
      }

      trigger  .addEventListener('click', openModal);
      btnClose .addEventListener('click', closeModal);
      modal    .addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
      document .addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
      btnJson  .addEventListener('click', () => download('json'));
      btnXml   .addEventListener('click', () => download('xml'));
    })();
  </script>

  <script>
    (function () {
      const box = document.getElementById('error-msg');
      if (!box) return;

      setTimeout(() => {
        box.classList.add('fade-out');
        setTimeout(() => {
          if (box && box.parentNode) {
            box.parentNode.removeChild(box);
          }
        }, 450);
      }, 4000);
    })();
  </script>
</body>
</html>
