<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');


 error_reporting(E_ALL);
 ini_set('display_errors', 1);

if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "Μη εξουσιοδοτημένη πρόσβαση"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Η μέθοδος πρέπει να είναι POST"]);
    exit();
}

if (!isset($_FILES['json_file'])) {
    echo json_encode(["status"=>"error","message"=>"Δεν στάλθηκε αρχείο"]);
    exit();
}

if (!isset($_POST['type'])) {
    echo json_encode(["status"=>"error","message"=>"Δεν στάλθηκε τύπος δεδομένων"]);
    exit();
}

$fileTmpPath = $_FILES['json_file']['tmp_name'];
if (!file_exists($fileTmpPath)) {
    echo json_encode(["status"=>"error","message"=>"Το αρχείο δεν βρέθηκε στο tmp"]);
    exit();
}

$jsonData = file_get_contents($fileTmpPath);
if ($jsonData === false) {
    echo json_encode(["status"=>"error","message"=>"Αποτυχία ανάγνωσης του αρχείου"]);
    exit();
}

$data = json_decode($jsonData, true);
if ($data === null) {
    echo json_encode(["status"=>"error","message"=>"Μη έγκυρο JSON"]);
    exit();
}

$conn = new mysqli("localhost", "root", "", "vasst");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Αποτυχία σύνδεσης στη βάση"]);
    exit();
}

$type = $_POST['type'];
$inserted = 0;

/* STUDENTS*/
if ($type === "students") {
    if (!isset($data['students']) || !is_array($data['students'])) {
        echo json_encode(["status" => "error", "message" => "Δεν βρέθηκε λίστα students στο JSON"]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO Students 
        (student_id, name, surname, student_number, street, number, city, postcode, father_name, landline_telephone, mobile_telephone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            surname=VALUES(surname),
            student_number=VALUES(student_number),
            street=VALUES(street),
            number=VALUES(number),
            city=VALUES(city),
            postcode=VALUES(postcode),
            father_name=VALUES(father_name),
            landline_telephone=VALUES(landline_telephone),
            mobile_telephone=VALUES(mobile_telephone)
    ");

    foreach ($data['students'] as $row) {
        if (!isset($row['id'], $row['name'], $row['surname'], $row['student_number'])) {
            echo json_encode(["status"=>"error","message"=>"Λείπουν υποχρεωτικά πεδία σε φοιτητή"]);
            exit();
        }

        // Ορισμός μεταβλητών
        $id = (int)$row['id'];
        $name = $row['name'];
        $surname = $row['surname'];
        $student_number = $row['student_number'];
        $street = $row['street'] ?? '';
        $number = $row['number'] ?? '';
        $city = $row['city'] ?? '';
        $postcode = $row['postcode'] ?? '';
        $father_name = $row['father_name'] ?? '';
        $landline = $row['landline_telephone'] ?? '';
        $mobile = $row['mobile_telephone'] ?? '';

        $stmt->bind_param(
            "issssssssss",
            $id,
            $name,
            $surname,
            $student_number,
            $street,
            $number,
            $city,
            $postcode,
            $father_name,
            $landline,
            $mobile
        );

        if ($stmt->execute()) {
            $inserted++;
        } else {
            echo json_encode(["status"=>"error","message"=>"Σφάλμα στη βάση: ".$stmt->error]);
            exit();
        }
    }
    $stmt->close();
}


/* PROFESSORS */
elseif ($type === "professors") {
    if (!isset($data['professors']) || !is_array($data['professors'])) {
        echo json_encode(["status" => "error", "message" => "Δεν βρέθηκε λίστα professors στο JSON"]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO Professors
        (professor_id, name, surname, topic, landline, mobile, department, university)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            surname=VALUES(surname),
            topic=VALUES(topic),
            landline=VALUES(landline),
            mobile=VALUES(mobile),
            department=VALUES(department),
            university=VALUES(university)
    ");

    foreach ($data['professors'] as $row) {
        if (!isset($row['id'], $row['name'], $row['surname'])) {
            echo json_encode(["status"=>"error","message"=>"Λείπουν υποχρεωτικά πεδία σε καθηγητή"]);
            exit();
        }

        $prof_id = (int)$row['id'];
        $prof_name = $row['name'];
        $prof_surname = $row['surname'];
        $prof_topic = $row['topic'] ?? '';
        $prof_landline = $row['landline'] ?? '';
        $prof_mobile = $row['mobile'] ?? '';
        $prof_department = $row['department'] ?? '';
        $prof_university = $row['university'] ?? '';

        $stmt->bind_param(
            "isssssss",
            $prof_id,
            $prof_name,
            $prof_surname,
            $prof_topic,
            $prof_landline,
            $prof_mobile,
            $prof_department,
            $prof_university
        );

        if ($stmt->execute()) $inserted++;
        else {
            echo json_encode(["status"=>"error","message"=>"Σφάλμα στη βάση: ".$stmt->error]);
            exit();
        }
    }
    $stmt->close();
}

else {
    echo json_encode(["status"=>"error","message"=>"Άγνωστος τύπος δεδομένων"]);
    $conn->close();
    exit();
}

$conn->close();

echo json_encode([
    "status" => "success",
    "message" => "Εισήχθησαν $inserted εγγραφές στον πίνακα $type"
]);
?>