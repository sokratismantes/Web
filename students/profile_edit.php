<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

$email = $_SESSION['email'];


$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}


$sql = "SELECT user_type, user_id FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    $conn->close();
    die("Δεν βρέθηκαν στοιχεία για τον συνδεδεμένο χρήστη.");
}
$row = $res->fetch_assoc();
$user_type = $row['user_type'];
$user_id   = (int)$row['user_id'];
$stmt->close();


$userData = [
    // student
    'street' => '', 'number' => '', 'city' => '', 'postcode' => '',
    'mobile_telephone' => '', 'landline_telephone' => '',
    // professor
    'mobile' => '', 'landline' => '', 'department' => '', 'university' => '',
    // secretary
    'phone' => ''
];

if ($user_type === 'student') {
    
    $q = "SELECT street, `number`, city, postcode, mobile_telephone, landline_telephone 
          FROM students WHERE student_id = ?";
    if ($st = $conn->prepare($q)) {
        $st->bind_param("i", $user_id);
        if ($st->execute()) {
            $r = $st->get_result();
            if ($r && $r->num_rows) {
                $userData = array_merge($userData, $r->fetch_assoc());
            }
        }
        $st->close();
    }
} elseif ($user_type === 'professor') {
    
    $q = "SELECT mobile, landline, department, university 
          FROM professors WHERE professor_id = ?";
    if ($st = $conn->prepare($q)) {
        $st->bind_param("i", $user_id);
        if ($st->execute()) {
            $r = $st->get_result();
            if ($r && $r->num_rows) {
                $userData = array_merge($userData, $r->fetch_assoc());
            }
        }
        $st->close();
    }
} elseif ($user_type === 'secretary') {
    
    $q = "SELECT phone FROM grammateia WHERE grammateia_id = ?";
    if ($st = $conn->prepare($q)) {
        $st->bind_param("i", $user_id);
        if ($st->execute()) {
            $r = $st->get_result();
            if ($r && $r->num_rows) {
                $userData = array_merge($userData, $r->fetch_assoc());
            }
        }
        $st->close();
    }
}

// Υποβολή φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $street    = $_POST['street'] ?? null;
    $number    = $_POST['number'] ?? null;
    $city      = $_POST['city'] ?? null;
    $postcode  = $_POST['postcode'] ?? null;

    $mobile    = $_POST['mobile_telephone'] ?? $_POST['mobile'] ?? null;
    $landline  = $_POST['landline_telephone'] ?? $_POST['landline'] ?? null;

    $department = $_POST['department'] ?? null;
    $university = $_POST['university'] ?? null;

    $phone      = $_POST['phone'] ?? null;

    
    $stmt = $conn->prepare("CALL UpdateUserProfile(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "issssssssss",
        $user_id,
        $user_type,
        $street,
        $number,
        $city,
        $postcode,
        $mobile,
        $landline,
        $department,
        $university,
        $phone
    );

    if ($stmt->execute()) {
        // Σελίδα ανακατεύθυνσης βάσει τύπου χρήστη
        $redirectPage = ($user_type === 'student')
            ? 'student_home.php'
            : (($user_type === 'professor') ? 'professor_home.php' : 'grammateia_home.php');

        echo "<script>
                alert('Τα στοιχεία ενημερώθηκαν με επιτυχία!');
                window.location.href = '$redirectPage';
              </script>";
        $stmt->close();
        $conn->close();
        exit();
    } else {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        die('Σφάλμα: ' . htmlspecialchars($err));
    }
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8" />
    <title>Επεξεργασία Προφίλ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Fonts + Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

    <style>
        :root{
          --brand:#0056b3;
          --brand-2:#0b5ed7;
          --ink:#003d7a;
          --muted:#7a8594;
          --bg-grad: linear-gradient(to right, #e2e2e2, #c9d6ff);
        }

        html, body { height: 100%; margin: 0; padding: 0; display: flex; flex-direction: column; }
        body{
          font-family: 'Roboto', system-ui, -apple-system, Segoe UI, Arial, sans-serif;
          background: var(--bg-grad);
          color:#333; font-size: .96rem; min-height:100vh; position:relative;
        }
        body::before{
          content:""; position:fixed; inset:0; z-index:-1;
          background-color: hsla(211, 32.3%, 51.4%, .35);
        }

        /* Header */
        .site-header{
          display:flex; justify-content:space-between; align-items:center;
          padding: 20px 40px;
          background-color: rgba(0, 51, 102, 0.92);
          color:#fff; box-shadow:0 8px 8px -4px rgba(0,0,0,.2);
          margin-bottom: 12px; height: 120px; position:relative; z-index:10;
          border-bottom-left-radius:14px; border-bottom-right-radius:14px;
        }
        .site-header .left{ display:flex; align-items:center; gap:10px; }
        .site-header .logo{ width:95px; height:80px; object-fit:contain; }
        .system-name{ font-size:20px; font-weight:600; }
        .site-header .right{ display:flex; align-items:center; gap:20px; }
        .site-header .right nav a{ color:#fff; text-decoration:none; margin-right:15px; }
        .site-header .user-info{ font-weight:500; }

        /* Shell */
        .center-wrapper{
          min-height: calc(100vh - 140px);
          display:flex; align-items:flex-start; justify-content:center;
          padding: 24px 16px;
        }
        .container{
          width: min(780px, 92%);
          margin: 0 auto;
          border-radius: 14px;
          background:#fff;
          box-shadow: 0 10px 24px rgba(0,0,0,.08);
          padding: 24px;
        }
        .container h1{
          margin: 0 0 16px;
          font-size: 1.35rem;
          color: var(--ink);
          text-align: center;
          font-weight: 700;
        }

        /* Cards / fields */
        .card{
          background:#fff;
          border:1px solid #e8eef7;
          border-radius:12px;
          padding:16px;
          margin-bottom:16px;
          box-shadow: 0 6px 16px rgba(0,0,0,.04);
        }
        .card h2{
          font-size:1rem; margin:0 0 12px;
          color:#0b4ba6; font-weight:700;
        }

        .form-grid{
          display:grid; grid-template-columns: 1fr 1fr;
          gap: 12px 16px;
        }
        @media (max-width: 640px){
          .form-grid{ grid-template-columns: 1fr; }
        }

        .input-group{ position:relative; }
        .input-group i{
          position:absolute; left:10px; top:50%; transform: translateY(-50%);
          color: var(--muted); opacity:.9; font-size:.95rem;
        }
        .input-group input{
          width:100%; padding:10px 12px 10px 36px;
          border:1px solid #dde6f2; border-radius:10px;
          background:#f9fbff;
          transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .input-group input:focus{
          outline:none; background:#fff; border-color:#4da8da;
          box-shadow: 0 0 0 3px rgba(77,168,218,.2);
        }

        /* Actions */
        .actions{
          display:flex; gap:10px; justify-content:center; margin-top: 6px;
        }
        button[type="submit"]{
          padding:10px 18px; border:none; border-radius:999px;
          background: var(--brand); color:#fff; font-weight:600; cursor:pointer;
          box-shadow: 0 10px 16px rgba(0,86,179,.18);
        }
        button[type="submit"]:hover{ background: var(--brand-2); }
        .btn-ghost{
          padding:9px 16px; border:2px solid var(--brand); border-radius:999px;
          color: var(--brand); text-decoration:none; font-weight:600;
        }
        .btn-ghost:hover{ background: var(--brand); color:#fff; }

        /* Footer */
        footer{
          flex-shrink:0; width:100%;
          background-color: rgba(0, 51, 102, 0.92);
          color:#fff; text-align:center; padding:30px; margin-top:20px;
        }

        /* Στοιχεία Επικοινωνίας */
        .contact-card .form-grid{
        display: grid;
        grid-template-columns: repeat(2, minmax(260px, 340px)); 
        justify-content: center;  
        gap: 12px 16px;           
        }

        .contact-card .input-group{
        max-width: 340px;   
        margin: 0 auto;     
        }

        
        @media (max-width: 640px){
        .contact-card .form-grid{
            grid-template-columns: 1fr;
        }
        .contact-card .input-group{
            max-width: 100%;
        }
        }

        
        .contact-card .input-group input{
        height: 34px;
        padding: 6px 10px 6px 34px;
        font-size: .9rem;
        }

        
        .address-card .form-grid{
        display: grid;
        grid-template-columns: repeat(2, minmax(260px, 340px)); 
        justify-content: center;   
        gap: 12px 16px;           
        }
        .address-card .input-group{
        max-width: 340px;          
        margin: 0 auto;
        }
        
        .address-card .input-group input{
        height: 34px;
        padding: 6px 10px 6px 34px;
        font-size: .9rem;
        }

        
        @media (max-width: 640px){
        .address-card .form-grid{
            grid-template-columns: 1fr;
        }
        .address-card .input-group{
            max-width: 100%;
        }
        }

        /* Στοιχεία Καθηγητή */
        .professor-card .form-grid{
        display: grid;
        grid-template-columns: repeat(2, minmax(260px, 340px)); 
        justify-content: center; 
        gap: 12px 16px;
        }
        .professor-card .input-group{
        max-width: 340px;   
        margin: 0 auto;
        }
        .professor-card .input-group input{
        height: 34px;
        padding: 6px 10px 6px 34px;
        font-size: .9rem;
        }

        
        @media (max-width: 640px){
        .professor-card .form-grid{ grid-template-columns: 1fr; }
        .professor-card .input-group{ max-width: 100%; }
        }

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
            <a href="<?php echo ($user_type==='student')?'student_home.php':(($user_type==='professor')?'professor_home.php':'grammateia_home.php'); ?>">Αρχική</a>
            <a href="profile_edit.php">Το Προφίλ Μου</a>
        </nav>
        <span class="user-info"><a href="loginn.php" style="color:#ccc">Έξοδος</a></span>
    </div>
</header>

<div class="center-wrapper">
  <div class="container">
    <h1>Επεξεργασία Προφίλ</h1>

    <form method="POST">
      <?php if ($user_type === 'student'): ?>
        <!-- Διεύθυνση -->
        <div class="card address-card">
        <h2><i class="fa-solid fa-map-location-dot"></i> Διεύθυνση</h2>

        <div class="form-grid">
            <div class="input-group">
            <i class="fa-solid fa-road"></i>
            <input type="text" name="street" placeholder="Οδός"
                    value="<?php echo htmlspecialchars($userData['street'] ?? ''); ?>" required>
            </div>

            <div class="input-group">
            <i class="fa-solid fa-hashtag"></i>
            <input type="text" name="number" placeholder="Αριθμός"
                    value="<?php echo htmlspecialchars($userData['number'] ?? ''); ?>" required>
            </div>

            <div class="input-group">
            <i class="fa-solid fa-city"></i>
            <input type="text" name="city" placeholder="Πόλη"
                    value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" required>
            </div>

            <div class="input-group">
            <i class="fa-solid fa-envelope"></i>
            <input type="text" name="postcode" placeholder="Τ.Κ."
                    value="<?php echo htmlspecialchars($userData['postcode'] ?? ''); ?>" required>
            </div>
        </div>
        </div>

        <!-- Επικοινωνία -->
        <div class="card contact-card">
        <h2><i class="fa-solid fa-address-book"></i> Στοιχεία Επικοινωνίας</h2>

        <div class="form-grid">
            <div class="input-group">
            <i class="fa-solid fa-mobile-screen-button"></i>
            <input type="text" name="mobile_telephone" placeholder="Κινητό Τηλέφωνο"
                    value="<?php echo htmlspecialchars($userData['mobile_telephone'] ?? ''); ?>" required>
            </div>

            <div class="input-group">
            <i class="fa-solid fa-phone"></i>
            <input type="text" name="landline_telephone" placeholder="Σταθερό Τηλέφωνο"
                    value="<?php echo htmlspecialchars($userData['landline_telephone'] ?? ''); ?>" required>
            </div>
        </div>
        </div>

      <?php elseif ($user_type === 'professor'): ?>
    <div class="card professor-card">
        <h2><i class="fa-solid fa-user-tie"></i> Στοιχεία Καθηγητή</h2>

        <div class="form-grid">
        <div class="input-group">
            <i class="fa-solid fa-mobile-screen-button"></i>
            <input type="text" name="mobile" placeholder="Κινητό Τηλέφωνο"
                value="<?php echo htmlspecialchars($userData['mobile'] ?? ''); ?>" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-phone"></i>
            <input type="text" name="landline" placeholder="Σταθερό Τηλέφωνο"
                value="<?php echo htmlspecialchars($userData['landline'] ?? ''); ?>" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-building-columns"></i>
            <input type="text" name="department" placeholder="Τμήμα"
                value="<?php echo htmlspecialchars($userData['department'] ?? ''); ?>" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-school"></i>
            <input type="text" name="university" placeholder="Πανεπιστήμιο"
                value="<?php echo htmlspecialchars($userData['university'] ?? ''); ?>" required>
        </div>
        </div>
    </div>

      <?php else:  ?>
        <!-- Γραμματεία -->
        <div class="card">
          <h2><i class="fa-solid fa-headset"></i> Στοιχεία Γραμματείας</h2>
          <div class="form-grid">
            <div class="input-group" style="grid-column: 1 / -1;">
              <i class="fa-solid fa-phone"></i>
              <input type="text" name="phone" placeholder="Τηλέφωνο"
                     value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" required>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="actions">
        <button type="submit">Αποθήκευση Αλλαγών</button>
        <?php
          $redirectPage = ($user_type === 'student') ? 'student_home.php' : (($user_type === 'professor') ? 'professor_home.php' : 'grammateia_home.php');
        ?>
        <a class="btn-ghost" href="<?php echo $redirectPage; ?>">Ακύρωση</a>
      </div>
    </form>
  </div>
</div>

<footer>
  <p>Οδός Ν. Καζαντζάκη (25ής Μαρτίου) | 26504 Ρίο, Πανεπιστημιούπολη Πατρών</p>
  <p>Email: secretary@ceid.upatras.gr | Τηλ: 2610996939, 2610996940, 2610996941</p>
</footer>

</body>
</html>
