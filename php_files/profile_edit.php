<?php
session_start();

// Έλεγχος αν ο χρήστης έχει συνδεθεί
if (!isset($_SESSION['email'])) {
    header("Location: log.php");
    exit();
}

$email = $_SESSION['email'];
$userData = null;

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "vasst";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
}

// Ανάκτηση τύπου χρήστη και δεδομένων
$sql = "SELECT user_type FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $user_type = $row['user_type'];

    switch ($user_type) {
        case 'student':
            $sql = "SELECT * FROM students INNER JOIN users ON students.student_id = users.user_id WHERE users.email = ?";
            break;
        case 'professor':
            $sql = "SELECT * FROM professors INNER JOIN users ON professors.professor_id = users.user_id WHERE users.email = ?";
            break;
        case 'secretary':
            $sql = "SELECT * FROM grammateia INNER JOIN users ON grammateia.grammateia_id = users.user_id WHERE users.email = ?";
            break;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
    }
} else {
    die("Δεν βρέθηκαν στοιχεία για τον συνδεδεμένο χρήστη.");
}

$conn->close();

// Ενημέρωση δεδομένων αν υποβληθεί η φόρμα
// Ενημέρωση δεδομένων αν υποβληθεί η φόρμα
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    if ($conn->connect_error) {
        die("Η σύνδεση με τη βάση δεδομένων απέτυχε: " . $conn->connect_error);
    }

    $sql = "CALL UpdateUserProfile(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Προετοιμασία των μεταβλητών
    $user_id = $userData['student_id'] ?? $userData['professor_id'] ?? $userData['grammateia_id'];
    $street = $_POST['street'] ?? null;
    $number = $_POST['number'] ?? null;
    $city = $_POST['city'] ?? null;
    $postcode = $_POST['postcode'] ?? null;
    $mobile = $_POST['mobile'] ?? $_POST['mobile_telephone'] ?? null;
    $landline = $_POST['landline'] ?? $_POST['landline_telephone'] ?? null;
    $department = $_POST['department'] ?? null;
    $university = $_POST['university'] ?? null;
    $phone = $_POST['phone'] ?? null;

    // Δέσιμο των παραμέτρων με την procedure
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
        $stmt->close();
        $conn->close();
        echo "<script>
                alert('Τα στοιχεία ενημερώθηκαν με επιτυχία!');
                window.location.href = 'student_home.php';
              </script>";
        exit();
    }
    
    
  

    $stmt->close();
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Προφίλ</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            margin-top: 20px;
        }

        .container h1 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        input {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        button {
            padding: 10px;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #003d7a;
        }
    </style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        fetch('fetch_profile_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    // Γεμίζουμε τα πεδία της φόρμας με τα δεδομένα
                    if (data.user_type === 'student') {
                        document.querySelector('[name="street"]').value = data.street || '';
                        document.querySelector('[name="number"]').value = data.number || '';
                        document.querySelector('[name="city"]').value = data.city || '';
                        document.querySelector('[name="postcode"]').value = data.postcode || '';
                        document.querySelector('[name="mobile_telephone"]').value = data.mobile_telephone || '';
                        document.querySelector('[name="landline_telephone"]').value = data.landline_telephone || '';
                    } else if (data.user_type === 'professor') {
                        document.querySelector('[name="mobile"]').value = data.mobile || '';
                        document.querySelector('[name="landline"]').value = data.landline || '';
                        document.querySelector('[name="department"]').value = data.department || '';
                        document.querySelector('[name="university"]').value = data.university || '';
                    } else if (data.user_type === 'secretary') {
                        document.querySelector('[name="phone"]').value = data.phone || '';
                    }
                }
            })
            .catch(error => console.error('Error fetching profile data:', error));
    });
</script>
<script>
    document.querySelector('form').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('profile_edit_submit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Τα στοιχεία ενημερώθηκαν με επιτυχία!');
            } else {
                alert('Σφάλμα: ' + data.error);
            }
        })
        .catch(error => console.error('Error updating profile:', error));
    });
</script>

</head>
<body>
    <div class="container">
        <h1>Επεξεργασία Προφίλ</h1>
        <form method="POST">
            <?php if ($user_type === 'student'): ?>
                <label for="street">Οδός</label>
                <input type="text" name="street" value="<?php echo htmlspecialchars($userData['street'] ?? ''); ?>" required>
                <label for="number">Αριθμός</label>
                <input type="text" name="number" value="<?php echo htmlspecialchars($userData['number'] ?? ''); ?>" required>
                <label for="city">Πόλη</label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" required>
                <label for="postcode">Ταχυδρομικός Κώδικας</label>
                <input type="text" name="postcode" value="<?php echo htmlspecialchars($userData['postcode'] ?? ''); ?>" required>
                <label for="mobile_telephone">Κινητό Τηλέφωνο</label>
                <input type="text" name="mobile_telephone" value="<?php echo htmlspecialchars($userData['mobile_telephone'] ?? ''); ?>" required>
                <label for="landline_telephone">Σταθερό Τηλέφωνο</label>
                <input type="text" name="landline_telephone" value="<?php echo htmlspecialchars($userData['landline_telephone'] ?? ''); ?>" required>
            <?php elseif ($user_type === 'professor'): ?>
                <label for="mobile">Κινητό Τηλέφωνο</label>
                <input type="text" name="mobile" value="<?php echo htmlspecialchars($userData['mobile'] ?? ''); ?>" required>
                <label for="landline">Σταθερό Τηλέφωνο</label>
                <input type="text" name="landline" value="<?php echo htmlspecialchars($userData['landline'] ?? ''); ?>" required>
                <label for="department">Τμήμα</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($userData['department'] ?? ''); ?>" required>
                <label for="university">Πανεπιστήμιο</label>
                <input type="text" name="university" value="<?php echo htmlspecialchars($userData['university'] ?? ''); ?>" required>
            <?php elseif ($user_type === 'secretary'): ?>
                <label for="phone">Τηλέφωνο</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" required>
            <?php endif; ?>
            <button type="submit">Αποθήκευση Αλλαγών</button>
        </form>
    </div>
</body>
</html>

