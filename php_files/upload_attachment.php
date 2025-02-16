<?php
$dsn = "mysql:host=localhost;dbname=vasst";
$dbusername = "root";
$dbpassword = "";

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $thesis_id = intval($_POST['thesis_id']);
        $uploader_id = 100; // Πρέπει να πάρεις το ID του χρήστη από τη συνεδρία.
        $uploader_type = 'Student'; // Ή 'Teacher' ανάλογα με τον χρήστη.

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileName = $_FILES['attachment']['name'];
            $fileType = pathinfo($fileName, PATHINFO_EXTENSION);

            // Καθαρισμός ονόματος αρχείου (αφαίρεση ειδικών χαρακτήρων)
            $fileName = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $fileName);

            // Δημιουργία φακέλου αν δεν υπάρχει
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Δημιουργία μοναδικής διαδρομής αποθήκευσης αρχείου
            $destination = $uploadDir . time() . "_" . $fileName;

            if (move_uploaded_file($fileTmpPath, $destination)) {
                // Αποθήκευση στη βάση δεδομένων
                $stmt = $pdo->prepare("
                    INSERT INTO attachments (thesis_id, uploader_type, uploader_id, file_name, file_url, file_type)
                    VALUES (:thesis_id, :uploader_type, :uploader_id, :file_name, :file_url, :file_type)
                ");
                $stmt->execute([
                    'thesis_id' => $thesis_id,
                    'uploader_type' => $uploader_type,
                    'uploader_id' => $uploader_id,
                    'file_name' => $fileName,
                    'file_url' => $destination,
                    'file_type' => $fileType
                ]);

                // Ανακατεύθυνση πίσω στο view_student.php με μήνυμα επιτυχίας
                header("Location: view_student.php?thesis_id=$thesis_id&message=success");
                exit();
            } else {
                // Ανακατεύθυνση με μήνυμα αποτυχίας
                header("Location: view_student.php?thesis_id=$thesis_id&message=error");
                exit();
            }
        } else {
            // Ανακατεύθυνση αν δεν επιλέχθηκε αρχείο
            header("Location: view_student.php?thesis_id=$thesis_id&message=no_file");
            exit();
        }
    }
} catch (PDOException $e) {
    // Ανακατεύθυνση με μήνυμα σφάλματος
    header("Location: view_student.php?thesis_id=$thesis_id&message=db_error");
    exit();
}
?>

