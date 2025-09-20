-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Εξυπηρετητής: 127.0.0.1
-- Χρόνος δημιουργίας: 20 Σεπ 2025 στις 10:39:10
-- Έκδοση διακομιστή: 10.4.32-MariaDB
-- Έκδοση PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Βάση δεδομένων: `vasst`
--

DELIMITER $$
--
-- Διαδικασίες
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddThesis` (IN `p_title` VARCHAR(255), IN `p_description` TEXT, IN `p_status` ENUM('Υπό Ανάθεση','Ενεργή','Υπό Εξέταση','Περατωμένη'), IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_supervisor_id` INT)   BEGIN
    INSERT INTO Theses (title, description, status, start_date, end_date, supervisor_id)
    VALUES (p_title, p_description, p_status, p_start_date, p_end_date, p_supervisor_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `AssignThesisToStudent` (IN `p_thesis_id` INT, IN `p_student_id` INT, IN `p_professor_id` INT, IN `p_title` VARCHAR(255), IN `p_description` TEXT)   BEGIN
    -- Ενημέρωση με σωστή στήλη
    UPDATE theses
    SET student_id    = p_student_id,
        supervisor_id = p_professor_id,   -- εδώ η διόρθωση
        title         = p_title,
        description   = p_description
    WHERE thesis_id = p_thesis_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cancelThesis` (IN `p_thesis_id` INT, IN `p_reason` TEXT, IN `p_gs_number` INT, IN `p_gs_year` INT)   BEGIN
    UPDATE Theses
    SET 
        status = 'Ακυρωμένη',
        cancellation_reason = p_reason,   -- ΔΙΟΡΘΩΜΕΝΟ
        cancel_gs_number = p_gs_number,
        cancel_gs_year = p_gs_year,
        end_date = CURDATE()
    WHERE thesis_id = p_thesis_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `completeThesis` (IN `p_thesis_id` INT)   BEGIN
    UPDATE Theses
    SET 
        status = 'Περατωμένη',
        end_date = CURDATE()
    WHERE thesis_id = p_thesis_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetProfessorNotifications` (IN `p_professor_id` INT)   BEGIN
    SELECT notification_id, student_id, sent_at
    FROM professors_notifications
    WHERE professor_id = p_professor_id
    ORDER BY sent_at DESC, notification_id DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `SendInvitationToProfessor` (IN `studentId` INT, IN `thesisId` INT, IN `professorId` INT, IN `comments` TEXT)   BEGIN
    INSERT INTO professors_notifications (student_id, thesis_id, professor_id, comments)
    VALUES (studentId, thesisId, professorId, comments);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateUserProfile` (IN `p_user_id` INT, IN `p_user_type` VARCHAR(20), IN `p_street` VARCHAR(255), IN `p_number` VARCHAR(10), IN `p_city` VARCHAR(100), IN `p_postcode` VARCHAR(20), IN `p_mobile_phone` VARCHAR(20), IN `p_landline_phone` VARCHAR(20), IN `p_department` VARCHAR(100), IN `p_university` VARCHAR(100), IN `p_phone` VARCHAR(20))   BEGIN
    IF p_user_type = 'student' THEN
        UPDATE students 
        SET street = p_street, 
            number = p_number, 
            city = p_city, 
            postcode = p_postcode, 
            mobile_telephone = p_mobile_phone, 
            landline_telephone = p_landline_phone
        WHERE student_id = p_user_id;
    ELSEIF p_user_type = 'professor' THEN
        UPDATE professors 
        SET mobile = p_mobile_phone, 
            landline = p_landline_phone, 
            department = p_department, 
            university = p_university
        WHERE professor_id = p_user_id;
    ELSEIF p_user_type = 'secretary' THEN
        UPDATE grammateia 
        SET phone = p_phone
        WHERE grammateia_id = p_user_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_assign_gs` (IN `p_thesis_id` INT, IN `p_assign_gs_number` INT)   BEGIN
    UPDATE Theses
    SET assign_gs_number = p_assign_gs_number
    WHERE thesis_id = p_thesis_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `title` varchar(255) NOT NULL,
  `announcement_text` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `announcements`
--

INSERT INTO `announcements` (`id`, `date`, `time`, `title`, `announcement_text`) VALUES
(1, '2025-09-10', '11:00:00', 'Δημόσια Παρουσίαση Διπλωματικής Ιωάννη Παπαδόπουλου', 'Την 10/09/2025 και ώρα 11:00 θα παρουσιαστεί η διπλωματική του Ιωάννη Παπαδόπουλου στην αίθουσα Α1.'),
(2, '2025-09-12', '13:30:00', 'Δημόσια Παρουσίαση Διπλωματικής Μαρίας Κωνσταντίνου', 'Την 12/09/2025 και ώρα 13:30 θα παρουσιαστεί η διπλωματική της Μαρίας Κωνσταντίνου στην αίθουσα Β2.'),
(3, '2025-09-15', '12:00:00', 'Δημόσια Παρουσίαση Διπλωματικής Τάδε Ταδόπουλου', 'Την 15/09/2025 και ώρα 12:00 θα παρουσιαστεί η διπλωματική του Τάδε Ταδόπουλου στην αίθουσα Β1.'),
(4, '2025-09-18', '09:30:00', 'Δημόσια Παρουσίαση Διπλωματικής Άννας Γεωργίου', 'Την 18/09/2025 και ώρα 09:30 θα παρουσιαστεί η διπλωματική της Άννας Γεωργίου στην αίθουσα Γ1.'),
(5, '2025-09-20', '10:30:00', 'Δημόσια Παρουσίαση Διπλωματικής Δείνα Δεινόπουλου', 'Την 20/09/2025 και ώρα 10:30 θα παρουσιαστεί η διπλωματική του Δείνα Δεινόπουλου στην αίθουσα Γ2.'),
(6, '2025-09-22', '14:00:00', 'Δημόσια Παρουσίαση Διπλωματικής Χρήστου Νικολάου', 'Την 22/09/2025 και ώρα 14:00 θα παρουσιαστεί η διπλωματική του Χρήστου Νικολάου στην αίθουσα Δ1.'),
(7, '2025-09-25', '16:00:00', 'Δημόσια Παρουσίαση Διπλωματικής Ελένης Δημητρίου', 'Την 25/09/2025 και ώρα 16:00 θα παρουσιαστεί η διπλωματική της Ελένης Δημητρίου στην αίθουσα Ε2.'),
(8, '2025-09-28', '11:45:00', 'Δημόσια Παρουσίαση Διπλωματικής Σπύρου Οικονόμου', 'Την 28/09/2025 και ώρα 11:45 θα παρουσιαστεί η διπλωματική του Σπύρου Οικονόμου στην αίθουσα Ζ1.'),
(9, '2025-10-02', '09:00:00', 'Δημόσια Παρουσίαση Διπλωματικής Αικατερίνης Σταμάτη', 'Την 02/10/2025 και ώρα 09:00 θα παρουσιαστεί η διπλωματική της Αικατερίνης Σταμάτη στην αίθουσα Η1.'),
(10, '2025-10-05', '12:15:00', 'Δημόσια Παρουσίαση Διπλωματικής Δημήτρη Παναγιώτου', 'Την 05/10/2025 και ώρα 12:15 θα παρουσιαστεί η διπλωματική του Δημήτρη Παναγιώτου στην αίθουσα Θ2.');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `attachments`
--

CREATE TABLE `attachments` (
  `attachment_id` int(11) NOT NULL,
  `thesis_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `attachments`
--

INSERT INTO `attachments` (`attachment_id`, `thesis_id`, `student_id`, `filename`, `uploaded_at`) VALUES
(6, 2, 101, 'draft_t2s101_20250902_201217.pdf', '2025-09-02 21:12:17'),
(7, 3, 104, 'draft_t3s104_20250916_161849.pdf', '2025-09-16 17:18:50'),
(8, 83, 119, 'draft_t83s119_20250917_151756.pdf', '2025-09-17 16:17:56'),
(9, 1, 106, 'draft_t1s106_20250919_110239.pdf', '2025-09-19 12:02:39');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `committeeinvitations`
--

CREATE TABLE `committeeinvitations` (
  `invitation_id` int(11) NOT NULL,
  `thesis_id` int(11) DEFAULT NULL,
  `invited_professor_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `committeeinvitations`
--

INSERT INTO `committeeinvitations` (`invitation_id`, `thesis_id`, `invited_professor_id`, `status`, `sent_at`, `responded_at`, `comments`) VALUES
(1, 4, 210, 'Rejected', '2025-01-15 04:45:00', '2025-08-17 18:14:58', 'Παρακαλώ απαντήστε έως 2025-01-25.'),
(2, 13, 215, 'Pending', '2025-02-05 09:00:00', NULL, 'Πρόσκληση για αξιολόγηση διπλωματικής εργασίας.'),
(3, 12, 211, 'Pending', '2025-02-20 11:00:00', NULL, 'Παρακαλώ επιβεβαιώστε τη διαθεσιμότητά σας.'),
(4, 7, 218, 'Rejected', '2025-01-20 10:00:00', '2025-01-20 12:45:00', 'Λυπάμαι, αλλά λόγω φόρτου εργασίας δεν μπορώ να συμμετάσχω.'),
(5, 10, 200, 'Rejected', '2025-01-19 07:00:00', '2025-01-20 05:00:00', 'Δυστυχώς δεν μπορώ να παραστώ λόγω προγραμματισμένων ταξιδιών.'),
(6, 11, 205, 'Rejected', '2025-01-25 12:30:00', '2025-01-26 06:45:00', 'Δεν μπορώ να συμμετάσχω λόγω ανειλημμένων υποχρεώσεων.'),
(7, 6, 208, 'Accepted', '2025-01-20 06:15:00', '2025-01-21 07:30:00', 'Θα χαρώ να συμμετάσχω στην επιτροπή.'),
(8, 5, 211, 'Accepted', '2025-02-01 05:30:00', '2025-02-01 10:00:00', 'Ευχαριστώ. Είμαι διαθέσιμος για τη συνεδρία.'),
(9, 7, 200, 'Accepted', '2025-02-15 06:00:00', '2025-02-16 04:00:00', 'Επιβεβαιώνω τη συμμετοχή μου στην επιτροπή.'),
(10, 11, 218, 'Accepted', '2025-01-18 08:00:00', '2025-01-19 06:00:00', 'Θα συμμετάσχω.');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `committees`
--

CREATE TABLE `committees` (
  `committee_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `member1_id` int(11) NOT NULL,
  `member2_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `committees`
--

INSERT INTO `committees` (`committee_id`, `thesis_id`, `supervisor_id`, `member1_id`, `member2_id`) VALUES
(1, 3, 204, 209, 210),
(2, 5, 200, 211, 212),
(3, 6, 216, 219, 208),
(4, 7, 205, 200, 217),
(5, 2, 201, 209, 210),
(6, 8, 202, 200, 219),
(7, 9, 203, 216, 218),
(8, 1, 206, 216, 214),
(9, 10, 207, 217, 215),
(10, 11, 208, 219, 218),
(11, 16, 200, 203, 204),
(22, 19, 200, 204, 201),
(23, 21, 200, 203, 204),
(24, 17, 200, 207, 218),
(25, 24, 200, 217, 0),
(26, 25, 204, 213, 215),
(27, 52, 217, 215, 216),
(28, 53, 218, 217, 219),
(29, 54, 219, 210, 211),
(30, 55, 210, 212, 213),
(31, 56, 211, 214, 215),
(32, 57, 212, 216, 217),
(33, 58, 213, 218, 219),
(34, 59, 214, 210, 212),
(35, 60, 215, 213, 214),
(36, 61, 216, 217, 218),
(37, 62, 217, 219, 210),
(38, 65, 210, 211, 212),
(39, 66, 211, 213, 214),
(40, 67, 212, 215, 216),
(41, 68, 213, 217, 218),
(42, 69, 214, 219, 210),
(43, 70, 215, 211, 212),
(44, 71, 216, 213, 214),
(45, 72, 217, 215, 216),
(46, 73, 218, 217, 219),
(47, 74, 219, 210, 211),
(48, 75, 210, 212, 213),
(49, 76, 211, 214, 215),
(50, 77, 212, 216, 217),
(51, 78, 213, 218, 219),
(52, 79, 214, 210, 212),
(53, 80, 215, 213, 214),
(54, 81, 216, 217, 218),
(55, 82, 217, 219, 210),
(56, 33, 200, 211, 216),
(57, 83, 220, 219, 202),
(59, 12, 209, 220, 0),
(60, 26, 200, 220, 213);

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `examinations`
--

CREATE TABLE `examinations` (
  `exam_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `exam_time` time NOT NULL,
  `exam_mode` enum('δια ζώσης','διαδικτυακά') NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `announcements` mediumtext DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `examinations`
--

INSERT INTO `examinations` (`exam_id`, `thesis_id`, `exam_date`, `exam_time`, `exam_mode`, `room`, `announcements`, `link`) VALUES
(3, 3, '2025-08-23', '12:00:00', 'δια ζώσης', 'Αμφιθέατρο 2', NULL, NULL),
(4, 1, '2025-08-17', '15:30:00', 'δια ζώσης', 'Αμφιθέατρο 1', NULL, NULL),
(8, 11, '2025-09-07', '20:36:00', 'δια ζώσης', 'Αμφιθέατρο 3', NULL, NULL),
(9, 1, '2025-08-24', '18:00:00', 'δια ζώσης', 'Αμφιθεατρο 4', NULL, NULL),
(10, 1, '2025-08-01', '10:00:00', 'δια ζώσης', 'Αμφιθέατρο 2', NULL, NULL),
(11, 1, '2025-08-31', '17:11:00', 'δια ζώσης', 'Αμφιθέατρο 1', NULL, NULL),
(12, 16, '2025-08-15', '11:13:00', 'δια ζώσης', 'Αμφιθέατρο 3', 'ΑΝΑΚΟΙΝΩΣΗ ΠΑΡΟΥΣΙΑΣΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ ΕΡΓΑΣΙΑΣ\n\nΗ παρουσίαση της διπλωματικής εργασίας με τίτλο:\n«Ανάπτυξη Συστήματος Διαχείρισης Δεδομένων σε Περιβάλλον Web»\n\nτου φοιτητή Ιωάννη Παπαδόπουλου (ΑΜ: 12345),\nθα πραγματοποιηθεί την Τρίτη 10 Σεπτεμβρίου 2025 και ώρα 12:00,\nστην αίθουσα Β2.13, Τμήμα Μηχανικών Η/Υ και Πληροφορικής, Πανεπιστήμιο Πατρών.\n\nΗ εργασία εκπονήθηκε υπό την επίβλεψη του κ. Δρ. Νίκου Παπακωνσταντίνου,\nκαι θα παρουσιαστεί ενώπιον της τριμελούς εξεταστικής επιτροπής.\n\nΗ παρουσίαση είναι ανοικτή σε μέλη του Τμήματος.', NULL),
(13, 16, '2025-08-29', '11:15:00', 'δια ζώσης', 'Αμφιθέατρο 5', NULL, NULL),
(14, 16, '0000-00-00', '00:00:00', 'δια ζώσης', NULL, NULL, NULL),
(15, 16, '0000-00-00', '00:00:00', 'δια ζώσης', NULL, NULL, NULL),
(16, 2, '2025-09-05', '11:30:00', 'δια ζώσης', 'Αμφιθεατρο 4', NULL, NULL),
(17, 3, '2025-09-17', '09:00:00', 'δια ζώσης', 'Αμφιθέατρο 1', NULL, NULL),
(18, 83, '2025-09-30', '12:00:00', 'δια ζώσης', 'Αμφιθέατρο 2', 'ΑΝΑΚΟΙΝΩΣΗ ΠΑΡΟΥΣΙΑΣΗΣ ΔΙΠΛΩΜΑΤΙΚΗΣ\n\nΤίτλος: Χρήση μεγάλων γλωσσικών μοντέλων στην ανίχνευση κυβερνοεπιθέσεων\nΦοιτητής/τρια: Konstantina Papadimitriou\nΗμερομηνία: 2025-09-30\nΏρα: 12:00\nΤρόπος: δια ζώσης\nΧώρος/Σύνδεσμος: Αμφιθέατρο 2\n\nΣας προσκαλούμε στην παρουσίαση της διπλωματικής εργασίας.', NULL),
(20, 1, '2025-09-28', '15:00:00', 'δια ζώσης', 'Αμφιθεατρο 4', NULL, NULL);

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `exam_results`
--

CREATE TABLE `exam_results` (
  `result_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `grade` decimal(3,2) DEFAULT NULL,
  `report` text DEFAULT NULL,
  `exam_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `exam_results`
--

INSERT INTO `exam_results` (`result_id`, `thesis_id`, `professor_id`, `grade`, `report`, `exam_date`, `created_at`) VALUES
(1, 3, 204, 9.00, NULL, '2025-07-25', '2025-07-25 18:54:37'),
(2, 3, 209, 8.50, NULL, '2025-07-25', '2025-07-25 18:54:37'),
(3, 3, 210, 9.00, NULL, '2025-07-25', '2025-07-25 18:54:37'),
(4, 5, 200, 9.50, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(5, 5, 211, 9.00, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(6, 5, 212, 9.50, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(7, 6, 216, 9.99, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(8, 6, 219, 9.50, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(9, 6, 208, 9.00, NULL, '2025-07-25', '2025-07-25 18:54:39'),
(13, 16, 204, 7.50, NULL, '2025-08-31', '2025-08-25 10:23:11'),
(14, 16, 200, 7.00, NULL, '2025-08-31', '2025-08-25 12:57:15'),
(15, 16, 203, 8.50, NULL, '2025-08-31', '2025-09-02 22:11:35'),
(16, 83, 220, 9.99, NULL, '2025-09-30', '2025-09-18 12:02:17'),
(17, 26, 220, 9.00, NULL, '2025-10-31', '2025-09-18 12:04:58'),
(18, 83, 202, 9.00, NULL, '2025-09-30', '2025-09-18 12:07:42'),
(19, 83, 219, 9.50, NULL, '2025-09-30', '2025-09-18 12:08:50');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `grammateia`
--

CREATE TABLE `grammateia` (
  `grammateia_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `grammateia`
--

INSERT INTO `grammateia` (`grammateia_id`, `full_name`, `phone`) VALUES
(300, 'Γεωργία Παπαδοπούλου', '2610123456'),
(301, 'Κωνσταντίνος Δημόπουλος', '2610654321'),
(302, 'Αντωνία Λεμονή', '2610111222'),
(303, 'Σπυρίδων Καραγιάννης', '2610333444'),
(304, 'Ελένη Καρρά', '2610555666'),
(305, 'Παναγιώτης Μαρκόπουλος', '2610777888'),
(306, 'Δημήτριος Παπαγεωργίου', '2610999000'),
(307, 'Αναστασία Κωνσταντίνου', '2610222333'),
(308, 'Γιώργος Σταματίου', '2610444555'),
(309, 'Μαρία Ζαχαροπούλου', '2610666777'),
(310, 'Αλέξανδρος Τσιόλης', '2610888999'),
(311, 'Νίκη Περράκη', '2610111000'),
(312, 'Χριστίνα Παναγοπούλου', '2610333666');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `professors`
--

CREATE TABLE `professors` (
  `professor_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `topic` varchar(100) DEFAULT NULL,
  `landline` varchar(15) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `university` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `professors`
--

INSERT INTO `professors` (`professor_id`, `name`, `surname`, `topic`, `landline`, `mobile`, `department`, `university`, `email`) VALUES
(200, 'Andreas', 'Komninos', 'Network-centric systems', '2610996915', '6977998877', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(201, 'Vasilis', 'Foukaras', 'Integrated Systems', '2610885511', '6988812345', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(202, 'Basilis', 'Karras', 'Artificial Intelligence', '23', '545', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(203, 'Eleni', 'Voyiatzaki', 'WEB', '34', '245', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(204, 'Andrew', 'Hozier Byrne', 'Artificial Intelligence', '2610170390', '6917031990', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(205, 'Nikos', 'Korobos', 'Data Engineering', '2610324365', '6978530352', 'Computer Engineering & Informatics', 'University of Patras', NULL),
(206, 'Kostas', 'Karanikolos', 'informatics', '2610324242', '6934539920', 'Economics', 'University of Patras', NULL),
(207, 'Jim', 'Nikolaou', 'Artificial Intelligence', '26109876543', '6979876543', 'Economics', 'University of Patras', NULL),
(208, 'Sophia', 'Michailidi', 'Economic Theory', '23105432109', '6985432109', 'Economics', 'Athens University of Economics and Business', NULL),
(209, 'Michael', 'Papadreou', 'Renewable Energy Systems', '26104455667', '6974455667', 'Economics', 'University of Ioannina', NULL),
(210, 'George', 'Panagiotopoulos', 'Machine Learning', '26104455678', '6974455678', 'Electrical & Computer Engineering', 'University of Patras', NULL),
(211, 'Maria', 'Alexiou', 'Robotics', '2610778899', '6977788899', 'Electrical & Computer Engineering', 'University of Patras', NULL),
(212, 'Ioannis', 'Tsoukalas', 'Data Science', '2310334455', '6940334455', 'Electrical & Computer Engineering', 'Aristotle University of Thessaloniki', NULL),
(213, 'Eleni', 'Papadimitriou', 'Environmental Engineering', '2310445566', '6980445566', 'Electrical & Computer Engineering', 'University of Ioannina', NULL),
(214, 'Dimitris', 'Lazaridis', 'Cybersecurity', '2610556677', '6977556677', 'Electrical & Computer Engineering', 'University of Patras', NULL),
(215, 'Anna', 'Kollia', 'Cloud Computing', '2101234567', '6981234567', 'Electrical & Computer Engineering', 'National Technical University of Athens', NULL),
(216, 'Christos', 'Papanikolaou', 'Bioinformatics', '2109876543', '6949876543', 'Environmental Engineering', 'University of Crete', NULL),
(217, 'Fotis', 'Markou', 'Blockchain Technology', '2610112233', '6970112233', 'Environmental Engineering', 'University of Patras', NULL),
(218, 'Stella', 'Karagianni', 'Cultural Economics', '2104567890', '6984567890', 'Environmental Engineering', 'Athens University of Economics and Business', NULL),
(219, 'Petros', 'Zaharopoulos', 'Renewable Energy Systems', '2610667788', '6976667788', 'Environmental Engineering', 'University of Ioannina', NULL),
(220, 'Dimitrios', 'Alexiou', 'Machine Learning', '2610696978', '6987988113', 'Electrical & Computer Engineering', 'University of Patras', NULL);

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `professors_notifications`
--

CREATE TABLE `professors_notifications` (
  `notification_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `professors_notifications`
--

INSERT INTO `professors_notifications` (`notification_id`, `student_id`, `thesis_id`, `professor_id`, `status`, `sent_at`, `responded_at`, `response_message`, `comments`) VALUES
(1, 108, 11, 206, 'Pending', '2025-08-02 12:13:33', NULL, NULL, NULL),
(2, 108, 11, 207, 'Pending', '2025-08-02 12:13:33', NULL, NULL, NULL),
(3, 108, 11, 200, 'Accepted', '2025-08-02 15:14:44', '2025-09-09 15:04:18', NULL, NULL),
(4, 108, 11, 200, 'Accepted', '2025-08-02 15:14:50', '2025-09-16 21:46:10', NULL, NULL),
(5, 108, 11, 202, 'Pending', '2025-08-02 15:15:53', NULL, NULL, NULL),
(6, 108, 11, 205, 'Pending', '2025-08-02 15:16:25', NULL, NULL, NULL),
(7, 108, 11, 213, 'Pending', '2025-08-02 15:26:43', NULL, NULL, NULL),
(8, 108, 11, 204, 'Pending', '2025-08-02 16:06:31', NULL, NULL, NULL),
(9, 108, 11, 207, 'Pending', '2025-08-02 16:06:31', NULL, NULL, NULL),
(10, 108, 11, 205, 'Pending', '2025-08-02 16:13:04', NULL, NULL, NULL),
(11, 108, 11, 207, 'Pending', '2025-08-02 16:13:04', NULL, NULL, NULL),
(12, 108, 11, 201, 'Pending', '2025-08-02 16:17:29', NULL, NULL, NULL),
(13, 108, 11, 204, 'Pending', '2025-08-02 16:17:29', NULL, NULL, NULL),
(14, 108, 11, 202, 'Pending', '2025-08-02 16:26:02', NULL, NULL, NULL),
(15, 108, 11, 204, 'Pending', '2025-08-02 16:26:02', NULL, NULL, NULL),
(16, 108, 11, 212, 'Pending', '2025-08-02 16:29:52', NULL, NULL, NULL),
(17, 108, 11, 213, 'Pending', '2025-08-02 16:29:52', NULL, NULL, NULL),
(18, 108, 11, 216, 'Pending', '2025-08-02 16:43:06', NULL, NULL, NULL),
(19, 108, 11, 217, 'Pending', '2025-08-02 16:43:06', NULL, NULL, NULL),
(20, 108, 11, 204, 'Pending', '2025-08-02 16:49:10', NULL, NULL, NULL),
(21, 108, 11, 205, 'Pending', '2025-08-02 16:49:10', NULL, NULL, NULL),
(22, 108, 11, 210, 'Pending', '2025-08-02 16:50:39', NULL, NULL, NULL),
(23, 108, 11, 215, 'Pending', '2025-08-02 16:50:39', NULL, NULL, NULL),
(24, 108, 11, 207, 'Pending', '2025-08-02 16:51:28', NULL, NULL, NULL),
(25, 108, 11, 208, 'Pending', '2025-08-02 16:51:28', NULL, NULL, NULL),
(26, 108, 11, 200, 'Pending', '2025-08-02 16:52:27', NULL, NULL, NULL),
(27, 108, 11, 212, 'Pending', '2025-08-02 16:52:27', NULL, NULL, NULL),
(28, 108, 11, 213, 'Pending', '2025-08-02 16:54:22', NULL, NULL, NULL),
(29, 108, 11, 217, 'Pending', '2025-08-02 16:54:22', NULL, NULL, NULL),
(30, 108, 11, 201, 'Pending', '2025-08-02 19:18:39', NULL, NULL, NULL),
(31, 108, 11, 204, 'Pending', '2025-08-02 19:18:39', NULL, NULL, NULL),
(32, 108, 11, 202, 'Pending', '2025-08-02 20:04:47', NULL, NULL, NULL),
(33, 108, 11, 205, 'Pending', '2025-08-02 20:04:47', NULL, NULL, NULL),
(34, 108, 11, 201, 'Pending', '2025-08-02 20:42:11', NULL, NULL, NULL),
(35, 108, 11, 204, 'Pending', '2025-08-02 20:42:11', NULL, NULL, NULL),
(36, 108, 11, 203, 'Pending', '2025-08-02 20:49:10', NULL, NULL, NULL),
(37, 108, 11, 204, 'Pending', '2025-08-02 20:49:10', NULL, NULL, NULL),
(38, 108, 11, 202, 'Pending', '2025-08-02 20:51:19', NULL, NULL, NULL),
(39, 108, 11, 205, 'Pending', '2025-08-02 20:51:19', NULL, NULL, NULL),
(40, 108, 11, 213, 'Pending', '2025-08-02 20:52:52', NULL, NULL, NULL),
(41, 108, 11, 214, 'Pending', '2025-08-02 20:52:52', NULL, NULL, NULL),
(42, 108, 11, 201, 'Pending', '2025-08-02 20:56:45', NULL, NULL, NULL),
(43, 108, 11, 204, 'Pending', '2025-08-02 20:56:45', NULL, NULL, NULL),
(44, 108, 11, 200, 'Pending', '2025-08-02 21:02:23', NULL, NULL, NULL),
(45, 108, 11, 203, 'Pending', '2025-08-02 21:02:23', NULL, NULL, NULL),
(46, 108, 11, 200, 'Rejected', '2025-08-02 21:02:51', NULL, NULL, NULL),
(47, 108, 11, 203, 'Pending', '2025-08-02 21:02:51', NULL, NULL, NULL),
(48, 108, 11, 201, 'Pending', '2025-08-02 21:03:32', NULL, NULL, NULL),
(49, 108, 11, 205, 'Pending', '2025-08-02 21:03:32', NULL, NULL, NULL),
(50, 108, 11, 205, 'Pending', '2025-08-02 21:06:26', NULL, NULL, NULL),
(51, 108, 11, 208, 'Pending', '2025-08-02 21:06:26', NULL, NULL, NULL),
(52, 108, 11, 200, 'Accepted', '2025-08-02 21:15:56', '2025-09-09 15:04:13', NULL, NULL),
(53, 108, 11, 203, 'Pending', '2025-08-02 21:15:56', NULL, NULL, NULL),
(54, 108, 11, 210, 'Pending', '2025-08-02 21:19:47', NULL, NULL, NULL),
(55, 108, 11, 211, 'Pending', '2025-08-02 21:19:47', NULL, NULL, NULL),
(56, 108, 11, 201, 'Pending', '2025-08-02 21:20:57', NULL, NULL, NULL),
(57, 108, 11, 204, 'Pending', '2025-08-02 21:20:57', NULL, NULL, NULL),
(58, 108, 11, 202, 'Pending', '2025-08-02 21:23:37', NULL, NULL, NULL),
(59, 108, 11, 205, 'Pending', '2025-08-02 21:23:37', NULL, NULL, NULL),
(60, 108, 11, 202, 'Pending', '2025-08-02 21:55:39', NULL, NULL, NULL),
(61, 108, 11, 206, 'Pending', '2025-08-02 21:55:39', NULL, NULL, NULL),
(62, 108, 11, 204, 'Pending', '2025-08-02 23:15:11', NULL, NULL, 'test'),
(63, 108, 11, 206, 'Pending', '2025-08-02 23:15:11', NULL, NULL, 'test'),
(64, 108, 11, 216, 'Pending', '2025-08-02 23:28:38', NULL, NULL, 'τεστ'),
(65, 108, 11, 219, 'Pending', '2025-08-02 23:28:38', NULL, NULL, 'τεστ'),
(66, 108, 11, 200, 'Accepted', '2025-08-02 23:31:29', NULL, NULL, 'γεια'),
(67, 108, 11, 201, 'Pending', '2025-08-02 23:31:29', NULL, NULL, 'γεια'),
(68, 102, 8, 200, 'Accepted', '2025-08-18 17:00:05', '2025-08-23 14:01:03', NULL, ''),
(69, 102, 8, 201, 'Accepted', '2025-08-18 17:00:05', '2025-09-02 00:13:13', NULL, ''),
(70, 106, 1, 201, 'Accepted', '2025-08-18 17:40:44', '2025-08-23 13:56:47', NULL, ''),
(71, 106, 1, 206, 'Pending', '2025-08-18 17:40:44', NULL, NULL, ''),
(74, 113, 16, 203, 'Pending', '2025-08-18 17:47:43', NULL, NULL, ''),
(75, 113, 16, 208, 'Pending', '2025-08-18 17:47:43', NULL, NULL, ''),
(76, 113, 16, 203, 'Accepted', '2025-08-18 17:49:15', NULL, NULL, ''),
(77, 113, 16, 217, 'Pending', '2025-08-18 17:49:15', NULL, NULL, ''),
(80, 108, 11, 203, 'Accepted', '2025-08-19 17:43:41', NULL, NULL, ''),
(81, 108, 11, 207, 'Pending', '2025-08-19 17:43:41', NULL, NULL, ''),
(82, 113, 16, 204, 'Accepted', '2025-08-19 18:19:44', NULL, NULL, ''),
(83, 113, 16, 206, 'Pending', '2025-08-19 18:19:44', NULL, NULL, ''),
(86, 115, 21, 203, 'Accepted', '2025-08-23 14:05:57', '2025-08-23 14:06:48', NULL, ''),
(87, 115, 21, 204, 'Accepted', '2025-08-23 14:05:57', '2025-08-25 12:22:03', NULL, ''),
(88, 117, 17, 207, 'Accepted', '2025-09-02 00:23:20', '2025-09-02 00:24:39', NULL, ''),
(89, 117, 17, 218, 'Accepted', '2025-09-02 00:23:20', '2025-09-02 00:25:42', NULL, ''),
(90, 115, 21, 203, 'Pending', '2025-09-02 18:21:08', NULL, NULL, ''),
(91, 115, 21, 206, 'Accepted', '2025-09-02 18:21:08', '2025-09-02 18:23:33', NULL, ''),
(92, 109, 24, 208, 'Pending', '2025-09-02 18:30:51', NULL, NULL, ''),
(93, 109, 24, 217, 'Accepted', '2025-09-02 18:30:51', '2025-09-02 18:31:28', NULL, ''),
(94, 112, 25, 213, 'Accepted', '2025-09-02 21:50:21', '2025-09-02 21:52:09', NULL, ''),
(95, 112, 25, 215, 'Accepted', '2025-09-02 21:50:21', '2025-09-02 21:53:40', NULL, ''),
(96, 118, 33, 211, 'Accepted', '2025-09-16 10:15:15', '2025-09-16 10:16:39', NULL, ''),
(97, 118, 33, 216, 'Accepted', '2025-09-16 10:15:15', '2025-09-17 12:01:37', NULL, ''),
(98, 119, 83, 202, 'Accepted', '2025-09-17 10:53:49', '2025-09-17 11:40:43', NULL, ''),
(99, 119, 83, 219, 'Accepted', '2025-09-17 10:53:49', '2025-09-17 11:39:46', NULL, ''),
(100, 115, 12, 200, 'Pending', '2025-09-17 12:58:06', NULL, NULL, ''),
(101, 115, 12, 220, 'Accepted', '2025-09-17 12:58:06', '2025-09-17 13:07:42', NULL, ''),
(102, 115, 12, 214, 'Accepted', '2025-09-17 12:58:23', '2025-09-17 13:02:49', NULL, ''),
(103, 115, 12, 220, 'Accepted', '2025-09-17 12:58:23', '2025-09-17 13:00:39', NULL, ''),
(104, 116, 6, 213, 'Pending', '2025-09-17 12:59:15', NULL, NULL, ''),
(105, 116, 6, 220, 'Pending', '2025-09-17 12:59:15', NULL, NULL, ''),
(106, 116, 6, 210, 'Pending', '2025-09-17 16:07:59', NULL, NULL, ''),
(107, 116, 6, 213, 'Pending', '2025-09-17 16:07:59', NULL, NULL, ''),
(108, 111, 26, 213, 'Accepted', '2025-09-17 16:13:33', '2025-09-17 16:15:14', NULL, ''),
(109, 111, 26, 220, 'Accepted', '2025-09-17 16:13:33', '2025-09-17 16:14:26', NULL, ''),
(110, 100, 5, 200, 'Pending', '2025-09-18 16:52:33', NULL, NULL, ''),
(111, 100, 5, 201, 'Pending', '2025-09-18 16:52:33', NULL, NULL, ''),
(112, 117, 17, 206, 'Pending', '2025-09-18 16:56:07', NULL, NULL, ''),
(113, 117, 17, 212, 'Pending', '2025-09-18 16:56:07', NULL, NULL, '');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `professor_notes`
--

CREATE TABLE `professor_notes` (
  `note_id` int(11) NOT NULL,
  `thesis_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `note_text` varchar(300) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `professor_notes`
--

INSERT INTO `professor_notes` (`note_id`, `thesis_id`, `professor_id`, `note_text`, `created_at`) VALUES
(1, 16, 200, 'Δοκιμαστικη Σημειωση', '2025-08-25 12:58:10'),
(2, 21, 200, 'Νεα Δοκιμαστικη Σημειωση', '2025-09-02 18:37:18'),
(3, 16, 200, 'test! η Μαρία είναι υπέροχη και θα πάρει πτυχίο φέτος!!!!', '2025-09-09 15:03:44'),
(4, 83, 220, 'Δοκιμαστική Σημείωση', '2025-09-17 13:11:50');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `street` varchar(100) DEFAULT NULL,
  `number` varchar(10) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `father_name` varchar(50) DEFAULT NULL,
  `landline_telephone` varchar(15) DEFAULT NULL,
  `mobile_telephone` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `students`
--

INSERT INTO `students` (`student_id`, `name`, `surname`, `student_number`, `street`, `number`, `city`, `postcode`, `father_name`, `landline_telephone`, `mobile_telephone`) VALUES
(100, 'Μaria', 'Nikolaou', '10434047', 'Achilleos', '29', 'Athens', '10437', 'Dimitris', '2109278909', '6945533213'),
(101, 'Eleni', 'Fotiou', '10434048', 'Adrianou', '65', 'Athens', '10556', 'Nikos', '2108745645', '6978989000'),
(102, 'Xara', 'Fanouriou', '10434049', 'Chaonias', '54', 'Athens', '10441', 'Petros', '2108724324', '6945622222'),
(103, 'Nikos', 'Panagiotou', '10434050', 'Chomatianou', '32', 'Athens', '10439', 'Giorgos', '2107655555', '6941133333'),
(104, 'Petros', 'Daidalos', '10434051', 'Dafnidos', '4', 'Athens', '11364', 'Pavlos', '2108534566', '6976644333'),
(105, 'Giannis', 'Ioannou', '10434052', 'Danais', '9', 'Athens', '11631', 'Kostas', '2107644999', '6976565655'),
(106, 'Elena', 'Antoniou', '10434054', 'Ermou', '24', 'Athens', '10563', 'Nikolaos', '2105678901', '6935678901'),
(107, 'Ioannis', 'Panagiotou', '10434055', 'Kyprou', '42', 'Patra', '26441', 'Kwstas', '2610123456', '6981234567'),
(108, 'George', 'Karamalis', '10434056', 'Kolokotroni', '10', 'Larissa', '41222', 'Petros', '2410456789', '6974567890'),
(109, 'Kyriakos', 'Papapetrou', '10434057', 'Zakunthou', '36', 'Volos', '10654', 'Apostolos', '2106789012', '6956789012'),
(110, 'Kostas', 'Papadopoulos', '10434060', 'Lykourgou', '16', 'Athens', '11524', 'Christos', '2107654321', '6937654321'),
(111, 'Maria', 'Gavala', '10434061', 'Platonos', '32', 'Thessaloniki', '54634', 'Antonis', '2310123789', '6951237890'),
(112, 'Fotis', 'Maniatis', '10434062', 'Artemidos', '18', 'Heraklion', '71306', 'Andreas', '2810654321', '6976543210'),
(113, 'Elena', 'Tzoumakis', '10434063', 'Perikleous', '7', 'Ioannina', '45444', 'Dimitrios', '26510456789', '698-4567891'),
(114, 'Christina', 'Petraki', '10434064', 'Dionysiou', '45', 'Volos', '38334', 'Manolis', '24210987654', '6949876543'),
(115, 'Nikolas', 'Tzortzis', '10434065', 'Aristotelous', '23', 'Larissa', '41234', 'Stavros', '2410123456', '6971234567'),
(116, 'Anna', 'Kalliga', '10434066', 'Thiseos', '12', 'Athens', '11743', 'Vasilis', '2105432198', '6935432198'),
(117, 'Alexandros', 'Karas', '10434067', 'Dimosthenous', '12', 'Patra', '26222', 'Konstantinos', '2610765432', '6957654321'),
(190, 'Makis', 'Makopoulos', '10433999', 'test street', '45', 'test city', '39955', 'Orestis', '2610333000', '6939096979'),
(191, 'John', 'Lennon', '10434000', 'Ermou', '18', 'Athens', '10431', 'George', '2610123456', '6970001112'),
(192, 'Petros', 'Verikokos', '10434001', 'Adrianou', '20', 'Thessaloniki', '54248', 'Giannis', '2610778899', '6970001112'),
(193, 'test', 'name', '10434002', 'str', '1', 'patra', '26222', 'father', '2610123456', '6912345678'),
(118, 'Maria', 'Athanasiou', '10657792', 'Athinas', '12', 'Thessaloniki', '26504', 'Nikolaos', '2610999999', '6987654321'),
(119, 'Konstantina', 'Papadimitriou', '10498031', 'Maizonos', '62', 'Patra', '26223', 'Alexios', '2610755744', '6945531118');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `theses`
--

CREATE TABLE `theses` (
  `thesis_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Υπό Ανάθεση','Ενεργή','Υπό Εξέταση','Περατωμένη','Ακυρωμένη') NOT NULL,
  `start_date` date DEFAULT NULL,
  `cancel_gs_year` int(11) DEFAULT NULL,
  `assign_gs_year` int(11) DEFAULT NULL,
  `cancel_gs_number` int(11) DEFAULT NULL,
  `assign_gs_number` int(11) DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `final_grade` decimal(3,2) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `repository_link` varchar(255) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancellation_ga_number` varchar(50) DEFAULT NULL,
  `cancellation_ga_year` int(11) DEFAULT NULL,
  `approval_gs_number` varchar(20) DEFAULT NULL,
  `links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`links`)),
  `topic_pdf` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `theses`
--

INSERT INTO `theses` (`thesis_id`, `title`, `description`, `status`, `start_date`, `cancel_gs_year`, `assign_gs_year`, `cancel_gs_number`, `assign_gs_number`, `end_date`, `final_grade`, `student_id`, `supervisor_id`, `repository_link`, `cancellation_reason`, `cancellation_ga_number`, `cancellation_ga_year`, `approval_gs_number`, `links`, `topic_pdf`) VALUES
(1, 'Ανάπτυξη Εφαρμογής Διαχείρισης Εργασιών με AI', 'Εφαρμογή που χρησιμοποιεί AI για ταξινόμηση και παρακολούθηση καθημερινών εργασιών.', 'Υπό Εξέταση', '2025-10-01', NULL, NULL, NULL, NULL, NULL, NULL, 106, 206, NULL, NULL, NULL, NULL, '2024/001', '[\"https://www.upatras.gr/education/undergraduate-studies/school-of-engineering/department-of-computer-engineering-and-informatics/\"]', NULL),
(2, 'Ανάλυση Δεδομένων Κοινωνικών Δικτύων', 'Εξαγωγή και ανάλυση δεδομένων από Twitter για την ανίχνευση τάσεων.', 'Υπό Εξέταση', '2025-09-15', NULL, NULL, NULL, NULL, NULL, NULL, 101, 201, NULL, NULL, NULL, NULL, '2024/002', '[\"https:\\/\\/www.youtube.com\\/results?search_query=night+mode+button\"]', NULL),
(3, 'Διαχείριση Εφοδιαστικής Αλυσίδας με Blockchain', 'Σχεδιασμός διαφανούς συστήματος διαχείρισης εφοδιαστικής αλυσίδας.', 'Υπό Εξέταση', '2024-09-01', NULL, NULL, NULL, NULL, '2025-06-01', 8.83, 104, 204, 'https://repository.university.gr/thesis/123', NULL, NULL, NULL, '2023/015', '[\"https://www.youtube.com/watch?v=gFgSh8v3rEo&list=LL&index=4\",\"https://nemertes.library.upatras.gr/home\",\"https://www.remove.bg/el/upload\",\"https://www.upatras.gr/education/undergraduate-studies/school-of-engineering/department-of-computer-engineering-and-informatics/\"]', NULL),
(4, 'Ανάπτυξη Συστημάτων Ανίχνευσης Κακόβουλου Λογισμικού', 'Εφαρμογή ανίχνευσης malware με μηχανική μάθηση.', 'Ενεργή', '2025-08-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(5, 'Σύστημα Προβλέψεων Καιρού με Χρήση Machine Learning', 'Δημιουργία αλγορίθμου πρόβλεψης καιρού με χρήση ιστορικών δεδομένων.', 'Περατωμένη', '2024-08-01', NULL, NULL, NULL, NULL, '2025-05-15', 9.33, 100, 200, 'https://nemertes.library.upatras.gr/search?bbm.page=6&spc.page=4&spc.sf=dc.date.accessioned&spc.sd=DESC', NULL, NULL, NULL, '2023/020', '[]', NULL),
(6, 'Ανάπτυξη Εφαρμογής Οπτικής Αναγνώρισης Χειρόγραφων Κειμένων', 'Ανάπτυξη συστήματος OCR για χειρόγραφα κείμενα σε πολυγλωσσικό περιβάλλον.', 'Περατωμένη', '2024-10-01', NULL, NULL, NULL, NULL, '2025-04-30', 9.50, 116, 216, 'https://repository.university.gr/thesis/125', NULL, NULL, NULL, '2023/021', '[]', NULL),
(7, 'Εφαρμογή Εντοπισμού Διαρροών σε Δίκτυα Ύδρευσης', 'Ανάλυση δεδομένων από αισθητήρες για εντοπισμό διαρροών σε δίκτυα ύδρευσης.', 'Περατωμένη', '2024-06-15', NULL, NULL, NULL, NULL, '2025-09-19', 8.25, 105, 220, 'https://repository.university.gr/thesis/126', NULL, NULL, NULL, '2023/018', '[\"https:\\/\\/www.upatras.gr\\/education\\/undergraduate-studies\\/school-of-engineering\\/department-of-computer-engineering-and-informatics\\/\"]', NULL),
(8, 'Ανάπτυξη Εικονικών Περιβαλλόντων για Εκπαίδευση με AR/VR', 'Δημιουργία εικονικών περιβαλλόντων για εξ αποστάσεως εκπαίδευση.', 'Υπό Εξέταση', '2025-01-15', NULL, NULL, NULL, NULL, NULL, NULL, 102, 202, NULL, NULL, NULL, NULL, '2024/003', '[]', NULL),
(9, 'Αυτοματισμός Οικιακών Συστημάτων με IoT', 'Ανάπτυξη συστήματος ελέγχου και αυτοματοποίησης για έξυπνα σπίτια.', 'Υπό Εξέταση', '2025-03-01', NULL, NULL, NULL, NULL, NULL, NULL, 103, 203, NULL, NULL, NULL, NULL, '2024/004', '[]', NULL),
(10, 'Ανάλυση Συναισθήματος σε Κείμενα με AI', 'Εξαγωγή συναισθηματικών χαρακτηριστικών από κείμενα χρησιμοποιώντας AI.', 'Ενεργή', '2025-05-01', NULL, NULL, NULL, NULL, NULL, NULL, 107, 207, NULL, NULL, NULL, NULL, '2024/005', '[]', NULL),
(11, 'Βελτιστοποίηση Αλγορίθμων Αναζήτησης σε Μεγάλες Βάσεις Δεδομένων', 'Ανάπτυξη και βελτιστοποίηση αλγορίθμων αναζήτησης σε NoSQL βάσεις.', 'Υπό Εξέταση', '2025-02-15', NULL, NULL, NULL, NULL, NULL, NULL, 108, 208, NULL, NULL, NULL, NULL, '2024/006', '[\"https:\\/\\/www.remove.bg\\/el\\/upload\",\"https:\\/\\/www.ceid.upatras.gr\\/programma-exetaseon-septemvrioy-2025\\/\"]', NULL),
(12, 'Διαχείριση Ενέργειας με Συστήματα IoT', 'Ανάπτυξη συστήματος παρακολούθησης και βελτιστοποίησης κατανάλωσης ενέργειας.', 'Περατωμένη', NULL, NULL, NULL, NULL, NULL, '2025-09-19', 9.25, 116, 209, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(13, 'Αυτόματη Διάγνωση Ιατρικών Εικόνων με AI', 'Ανάπτυξη εφαρμογής ανάλυσης ιατρικών εικόνων για διάγνωση ασθενειών.', 'Υπό Ανάθεση', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(16, 'τεστ', 'τεστ', 'Υπό Εξέταση', '2023-08-17', NULL, NULL, NULL, NULL, '2025-11-09', 7.67, 113, 200, 'https://repository.university.gr/thesis/134', NULL, NULL, NULL, NULL, '[]', NULL),
(17, 'test', 'test', 'Ενεργή', '2022-08-17', NULL, NULL, NULL, NULL, '2025-08-31', NULL, NULL, 200, NULL, 'από Διδάσκοντα', '139/Z', 2025, NULL, '[]', NULL),
(19, 'τεστ2', 'τεστ222', 'Υπό Ανάθεση', '2025-08-31', NULL, NULL, NULL, NULL, '2027-09-28', NULL, NULL, 200, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(20, 'test4', 'test4', 'Υπό Ανάθεση', '2025-08-31', NULL, NULL, NULL, NULL, '2025-11-02', NULL, NULL, 204, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(21, 'Σύγχρονες μέθοδοι μηχανικής και βαθιάς μάθησης για χρηματοοικονομική ανάλυση και πρόβλεψη', 'Στόχος αυτής της διπλωματικής εργασίας είναι η μελέτη των θεμελιωδών αρχών της Βαθιάς Μάθησης και των εφαρμογών της στον χώρο των χρηματοοικονομικών.', 'Ενεργή', '2025-08-31', NULL, NULL, NULL, NULL, '2026-06-30', NULL, 115, 200, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(22, 'Αξιολόγηση και συγκριτική μελέτη τεχνικών κωδικοποίησης κατηγορικών μεταβλητών στην επιβλεπόμενη μηχανική μάθηση', 'Αντικείμενο της παρούσας Διπλωματικής Εργασίας αποτελεί η συστηματική διερεύνηση της επίδρασης διαφόρων τεχνικών κωδικοποίησης κατηγορικών μεταβλητών στην απόδοση και την αποτελεσματικότητα αλγορίθμων ταξινόμησης στο πλαίσιο της εποπτευόμενης Μηχανικής Μάθησης (Supervised Machine Learning).', 'Υπό Ανάθεση', '2002-09-01', NULL, NULL, NULL, NULL, '2026-04-30', NULL, NULL, 203, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(23, 'Σύγχρονες μέθοδοι μηχανικής και βαθιάς μάθησης για χρηματοοικονομική ανάλυση και πρόβλεψη', 'Στόχος αυτής της διπλωματικής εργασίας είναι η μελέτη των θεμελιωδών αρχών της Βαθιάς Μάθησης και των εφαρμογών της στον χώρο των χρηματοοικονομικών. Ειδικότερα, η εργασία εστιάζει στην ανάπτυξη των μοντέλων μηχανικής μάθησης και βαθιάς μάθησης με τη χρήση Python, για την πρόβλεψη χρονοσειρών και τη λήψη επενδυτικών αποφάσεων.', 'Υπό Ανάθεση', '2025-08-01', NULL, NULL, NULL, NULL, '2026-06-30', NULL, NULL, 203, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/20250825_191355_a7c34f16_1754092726____________________________.pdf'),
(24, 'Σύγχρονες τεχνικές για την ανάπτυξη διαλογικού βοηθού ανοιχτού τομέα (open-domain chatbot)', 'Αυτή η διπλωματική εργασία ερευνά τις σύγχρονες τεχνικές για τη δημιουργία chatbots ανοιχτού τομέα, δίνοντας έμφαση στις προκλήσεις, τις καινοτομίες και τις μεθόδους αξιολόγησης που χαρακτηρίζουν αυτό το δυναμικό πεδίο.', 'Υπό Ανάθεση', '2025-09-01', NULL, NULL, NULL, NULL, '2026-05-31', NULL, 109, 200, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(25, 'Σχεδιασμός και ανάπτυξη πλατφόρμας κοινωνικής δικτύωσης για καλλιτέχνες με επίκεντρο τις επιχειρήσεις και την απασχόληση​', 'Στην εποχή μας, το διαδίκτυο έχει αλλάξει εντελώς τον τρόπο που κάποιος ψάχνει δουλειά. Μέσα από πλατφόρμες κοινωνικής δικτύωσης, το επαγγελματικό δίκτυο του καθενός μπορεί να διευρυνθεί χωρίς γεωγραφικούς περιορισμούς.', 'Ενεργή', '2025-10-01', NULL, NULL, NULL, NULL, '2026-02-28', NULL, 112, 204, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/social_media_25.pdf'),
(26, 'Bελτιώσεις μεθοδολογιών επαλήθευσης δέκτη για συστήματα SerDes πολύ υψηλών ταχυτήτων', 'Τα συστήματα Serializer/Deserializer (SerDes) υψηλής ταχύτητας είναι καθοριστικά για την πρόοδο των σύγχρονων τεχνολογιών επικοινωνιών, επιτρέποντας τη γρήγορη και αποτελεσματική μεταφορά μεγάλων όγκων δεδομένων σε διάφορες πλατφόρμες.', 'Περατωμένη', '2026-01-31', NULL, NULL, NULL, NULL, '2025-09-19', 8.75, 111, 200, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/_SerDes___26_26.pdf'),
(29, 'Πρόβλεψη αποτελεσμάτων ποδοσφαιρικών αγώνων με τη χρήση μηχανικής μάθησης', 'Στην παρούσα διπλωματική εργασία, μελετάται η χρήση τεχνικών μηχανικής μάθησης και νευρωνικών δικτύων για την πρόβλεψη της έκβασης ποδοσφαιρικών αγώνων, καθώς και διαφόρων στατιστικών όπως τα αναμενόμενα γκολ (xG), φάουλ (xF) και κόρνερ (xC).', 'Υπό Ανάθεση', '2025-10-01', NULL, NULL, NULL, NULL, '2026-05-31', NULL, NULL, 209, NULL, NULL, NULL, NULL, NULL, '[]', NULL),
(30, 'Χρήση μεγάλων γλωσσικών μοντέλων στην ανίχνευση κυβερνοεπιθέσεων', 'Η κυβερνοασφάλεια αποτελεί έναν από τους κλάδους της επιστήμης των υπολογιστών με τη μεγαλύτερη άνθιση τα τελευταία χρόνια, από την αυξανόμενη ανάγκη προστασίας των συστημάτων από επιθέσεις. Ταυτόχρονα, η εξέλιξη κλάδων όπως η τεχνητή νοημοσύνη έχει διαμορφώσει αναλόγως την κυβερνοασφάλεια, όπου χρησιμοποιείται τόσο στην αντιμετώπιση των επιθέσεων όσο και στην δημιουργία νέων. Σε αυτή την διπλωματική εργασία μελετάται η εφαρμογή καινοτόμων τεχνικών βαθιάς μάθησης και συγκεκριμένα Μεγάλων Γλωσσικών Μοντέλων επάνω σε προβλήματα κυβερνοασφάλειας.', 'Υπό Ανάθεση', '2025-10-01', NULL, NULL, NULL, NULL, '2026-02-20', NULL, NULL, 203, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/__30.pdf'),
(31, 'Τεστ4444', 'τεστ', 'Υπό Ανάθεση', '2025-01-01', NULL, NULL, NULL, NULL, '2026-02-10', NULL, NULL, 203, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/__31.pdf'),
(32, 'maia', 'iaiiaia', 'Υπό Ανάθεση', '2025-01-01', NULL, NULL, NULL, NULL, '2026-12-31', NULL, NULL, 203, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/thesis_32.pdf'),
(33, 'τεστ8', 'τεστ8', 'Ενεργή', '2025-09-30', NULL, NULL, NULL, NULL, '2025-10-31', NULL, 118, 200, NULL, NULL, NULL, NULL, NULL, '[]', 'uploads/theses_pdfs/thesis_33.pdf'),
(45, 'Ανάπτυξη Chatbot για Υποστήριξη Μαθητών', 'Chatbot για ερωτήσεις μαθημάτων.', 'Υπό Εξέταση', '2025-10-05', NULL, NULL, NULL, NULL, NULL, 8.50, 100, 210, 'https://repo.example.com/thesis45', NULL, NULL, NULL, '2024/041', '[]', NULL),
(46, 'Ανάλυση Συναισθημάτων Κοινωνικών Δικτύων', 'Κατηγοριοποίηση συναισθημάτων σε tweets.', 'Υπό Εξέταση', '2025-10-06', NULL, NULL, NULL, NULL, NULL, 7.75, 101, 211, 'https://repo.example.com/thesis46', NULL, NULL, NULL, '2024/042', '[]', NULL),
(47, 'Σύστημα Παρακολούθησης Αθλητικών Δραστηριοτήτων', 'Καταγραφή προόδου αθλητών.', 'Υπό Εξέταση', '2025-10-07', NULL, NULL, NULL, NULL, NULL, 9.00, 102, 212, 'https://repo.example.com/thesis47', NULL, NULL, NULL, '2024/043', '[]', NULL),
(48, 'Προγνωστικά Μοντέλα Καιρού', 'Πρόβλεψη βροχής και θερμοκρασιών.', 'Υπό Εξέταση', '2025-10-08', NULL, NULL, NULL, NULL, NULL, 8.25, 103, 213, 'https://repo.example.com/thesis48', NULL, NULL, NULL, '2024/044', '[]', NULL),
(49, 'Ανίχνευση Ασφάλειας Δικτύου', 'Ανίχνευση επιθέσεων σε εταιρικά δίκτυα.', 'Υπό Εξέταση', '2025-10-09', NULL, NULL, NULL, NULL, NULL, 7.50, 104, 214, 'https://repo.example.com/thesis49', NULL, NULL, NULL, '2024/045', '[]', NULL),
(50, 'Σύστημα Συστάσεων Ταινιών', 'Προτάσεις ταινιών με Collaborative Filtering.', 'Υπό Εξέταση', '2025-10-10', NULL, NULL, NULL, NULL, NULL, 8.00, 105, 215, 'https://repo.example.com/thesis50', NULL, NULL, NULL, '2024/046', '[]', NULL),
(51, 'Ανάλυση Εικόνων Ιατρικής Απεικόνισης', 'Ταξινόμηση MRI για ανίχνευση ασθενειών.', 'Υπό Εξέταση', '2025-10-11', NULL, NULL, NULL, NULL, NULL, 9.25, 106, 216, 'https://repo.example.com/thesis51', NULL, NULL, NULL, '2024/047', '[]', NULL),
(52, 'Ανάλυση Δεδομένων IoT', 'Εξαγωγή συμπερασμάτων από έξυπνες συσκευές.', 'Υπό Εξέταση', '2025-10-12', NULL, NULL, NULL, NULL, NULL, 7.80, 107, 217, 'https://repo.example.com/thesis52', NULL, NULL, NULL, '2024/048', '[]', NULL),
(53, 'Ανάπτυξη Mobile App για Παρακολούθηση Διατροφής', 'Καταγραφή γευμάτων και θερμίδων.', 'Υπό Εξέταση', '2025-10-13', NULL, NULL, NULL, NULL, NULL, 8.10, 108, 218, 'https://repo.example.com/thesis53', NULL, NULL, NULL, '2024/049', '[]', NULL),
(54, 'Σύστημα Αναγνώρισης Ομιλίας', 'Μετατροπή ομιλίας σε κείμενο.', 'Υπό Εξέταση', '2025-10-14', NULL, NULL, NULL, NULL, NULL, 7.95, 109, 219, 'https://repo.example.com/thesis54', NULL, NULL, NULL, '2024/050', '[]', NULL),
(55, 'Ανάλυση Κίνησης Οχημάτων', 'Παρακολούθηση κυκλοφορίας πόλης.', 'Υπό Εξέταση', '2025-10-15', NULL, NULL, NULL, NULL, NULL, 8.60, 110, 210, 'https://repo.example.com/thesis55', NULL, NULL, NULL, '2024/051', '[]', NULL),
(56, 'Εφαρμογή Τηλεϊατρικής', 'Σύνδεση ασθενών με γιατρούς.', 'Υπό Εξέταση', '2025-10-16', NULL, NULL, NULL, NULL, NULL, 9.00, 111, 211, 'https://repo.example.com/thesis56', NULL, NULL, NULL, '2024/052', '[]', NULL),
(57, 'Ανάλυση Βίντεο Αθλητικών Αγώνων', 'Παρακολούθηση αθλητικών κινήσεων.', 'Υπό Εξέταση', '2025-10-17', NULL, NULL, NULL, NULL, NULL, 8.30, 112, 212, 'https://repo.example.com/thesis57', NULL, NULL, NULL, '2024/053', '[]', NULL),
(58, 'Αυτόματο Σύστημα Ελέγχου Ποιότητας Προϊόντων', 'Ανίχνευση ελαττωμάτων σε προϊόντα.', 'Υπό Εξέταση', '2025-10-18', NULL, NULL, NULL, NULL, NULL, 7.70, 113, 213, 'https://repo.example.com/thesis58', NULL, NULL, NULL, '2024/054', '[]', NULL),
(59, 'Σύστημα Συστάσεων Μουσικής', 'Προτάσεις μουσικής σε χρήστες.', 'Υπό Εξέταση', '2025-10-19', NULL, NULL, NULL, NULL, NULL, 8.90, 114, 214, 'https://repo.example.com/thesis59', NULL, NULL, NULL, '2024/055', '[]', NULL),
(60, 'Ανάλυση Δεδομένων Κινητών Συσκευών', 'Ανάλυση συμπεριφοράς χρηστών κινητών.', 'Υπό Εξέταση', '2025-10-20', NULL, NULL, NULL, NULL, NULL, 7.85, 115, 215, 'https://repo.example.com/thesis60', NULL, NULL, NULL, '2024/056', '[]', NULL),
(61, 'Ανάπτυξη Mobile App Παρακολούθησης Εργασιών', 'Παρακολούθηση καθηκόντων χρηστών.', 'Υπό Εξέταση', '2025-10-21', NULL, NULL, NULL, NULL, NULL, 8.20, 116, 216, 'https://repo.example.com/thesis61', NULL, NULL, NULL, '2024/057', '[]', NULL),
(62, 'Ανάλυση Κινητικότητας Χρηστών Web', 'Παρακολούθηση συμπεριφοράς χρηστών.', 'Υπό Εξέταση', '2025-10-22', NULL, NULL, NULL, NULL, NULL, 7.95, 117, 217, 'https://repo.example.com/thesis62', NULL, NULL, NULL, '2024/058', '[]', NULL),
(65, 'Ανάπτυξη Εφαρμογής Παρακολούθησης Υγείας', 'Εφαρμογή για παρακολούθηση ζωτικών σημείων.', 'Υπό Ανάθεση', '2025-10-05', NULL, NULL, NULL, NULL, NULL, NULL, 100, 210, NULL, NULL, NULL, NULL, '2024/021', '[]', NULL),
(66, 'Σύστημα Αναγνώρισης Χειρονομιών', 'Ανάλυση κινήσεων χεριών με AI.', 'Ακυρωμένη', '2025-10-06', 2025, NULL, 11, NULL, '2025-09-12', NULL, 101, 211, NULL, 'test', NULL, NULL, '2024/022', '[]', NULL),
(67, 'Ανάλυση Δεδομένων IoT Σπιτιού', 'Παρακολούθηση και έλεγχος έξυπνου σπιτιού.', 'Ενεργή', '2025-10-07', NULL, NULL, NULL, NULL, NULL, NULL, 102, 212, NULL, NULL, NULL, NULL, '2024/023', '[]', NULL),
(68, 'Πρόβλεψη Κίνησης Χρηματιστηρίου', 'Χρήση αλγορίθμων ML για χρηματιστηριακές προβλέψεις.', 'Ενεργή', '2025-10-08', NULL, NULL, NULL, NULL, NULL, NULL, 103, 213, NULL, NULL, NULL, NULL, '2024/024', '[]', NULL),
(69, 'Ανάλυση Ήχου για Αναγνώριση Συμβάντων', 'Ανίχνευση συγκεκριμένων ήχων σε ηχογραφήσεις.', 'Ενεργή', '2025-10-09', NULL, NULL, NULL, 2659, NULL, NULL, 104, 214, NULL, NULL, NULL, NULL, '2024/025', '[]', NULL),
(70, 'Web App για Online Μαθήματα', 'Πλατφόρμα διαχείρισης μαθημάτων και ασκήσεων.', 'Ενεργή', '2025-10-10', NULL, NULL, NULL, 2069, NULL, NULL, 105, 215, NULL, NULL, NULL, NULL, '2024/026', '[]', NULL),
(71, 'Αυτόματο Σύστημα Αναγνώρισης Συναισθημάτων Φωνής', 'Εξαγωγή συναισθημάτων από φωνητικά δείγματα.', 'Ενεργή', '2025-10-11', NULL, NULL, NULL, NULL, NULL, NULL, 106, 216, NULL, NULL, NULL, NULL, '2024/027', '[]', NULL),
(72, 'Ανάλυση Κυκλοφορίας Δεδομένων Δικτύου', 'Παρακολούθηση και ανάλυση δικτυακής κυκλοφορίας.', 'Ενεργή', '2025-10-12', NULL, NULL, NULL, 1123, NULL, NULL, 107, 217, NULL, NULL, NULL, NULL, '2024/028', '[]', NULL),
(73, 'Σύστημα Συστάσεων Βιβλίων', 'Προτάσεις βιβλίων βάσει προτιμήσεων χρηστών.', 'Ενεργή', '2025-10-13', NULL, NULL, NULL, NULL, NULL, NULL, 108, 218, NULL, NULL, NULL, NULL, '2024/029', '[]', NULL),
(74, 'Ανίχνευση Spam με Τεχνικές Επεξεργασίας Φυσικής Γλώσσας', 'Φιλτράρισμα spam με ML αλγόριθμους.', 'Υπό Ανάθεση', '2025-10-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 220, NULL, NULL, NULL, NULL, '2024/030', '[]', NULL),
(75, 'Ανάπτυξη Εφαρμογής Τηλεϊατρικής', 'Σύνδεση ασθενών και γιατρών μέσω διαδικτύου.', 'Ενεργή', '2025-10-15', NULL, NULL, NULL, NULL, NULL, NULL, 110, 210, NULL, NULL, NULL, NULL, '2024/031', '[]', NULL),
(76, 'Ανάλυση Αστικής Κίνησης με Κάμερες', 'Παρακολούθηση κυκλοφορίας σε πόλεις.', 'Ενεργή', '2025-10-16', NULL, NULL, NULL, 1145, NULL, NULL, 111, 211, NULL, NULL, NULL, NULL, '2024/032', '[]', NULL),
(77, 'Αυτόματο Σύστημα Ελέγχου Ποιότητας Εικόνας', 'Ανίχνευση σφαλμάτων σε εικόνες προϊόντων.', 'Ακυρωμένη', '2025-10-17', 2025, NULL, 17, NULL, '2025-09-19', NULL, 112, 212, NULL, 'Κατόπιν αίτησης φοιτητή/τριας', NULL, NULL, '2024/033', '[]', NULL),
(78, 'Εφαρμογή Παρακολούθησης Διατροφής', 'Καταγραφή και ανάλυση διατροφικών συνηθειών.', 'Ενεργή', '2025-10-18', NULL, NULL, NULL, NULL, NULL, NULL, 113, 213, NULL, NULL, NULL, NULL, '2024/034', '[]', NULL),
(79, 'Σύστημα Ανάλυσης Κινήσεων Αθλητών', 'Ανάλυση τεχνικής αθλητών με βίντεο.', 'Ενεργή', '2025-10-19', NULL, NULL, NULL, NULL, NULL, NULL, 114, 214, NULL, NULL, NULL, NULL, '2024/035', '[]', NULL),
(80, 'Ανίχνευση Κακοήθων Ενεργειών σε Δίκτυο', 'Συστήματα ασφάλειας δικτύου με ML.', 'Ενεργή', '2022-10-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 219, NULL, 'από Διδάσκοντα', '149/A', 2025, '2024/036', '[]', NULL),
(81, 'Ανάπτυξη Mobile App για Διαχείριση Εργασιών', 'App για παρακολούθηση προσωπικών καθηκόντων.', 'Ενεργή', '2025-10-21', NULL, NULL, NULL, NULL, NULL, NULL, 116, 216, NULL, NULL, NULL, NULL, '2024/037', '[]', NULL),
(82, 'Ανάλυση Συμπεριφοράς Χρηστών Web', 'Παρακολούθηση και ανάλυση χρηστών ιστοσελίδων.', 'Υπό Ανάθεση', '2025-10-22', NULL, NULL, NULL, 2566, NULL, NULL, 117, 217, NULL, NULL, NULL, NULL, '2024/038', '[]', NULL),
(83, 'Χρήση μεγάλων γλωσσικών μοντέλων στην ανίχνευση κυβερνοεπιθέσεων', 'Σε αυτή την διπλωματική εργασία μελετάται η εφαρμογή καινοτόμων τεχνικών βαθιάς μάθησης και συγκεκριμένα Μεγάλων Γλωσσικών Μοντέλων επάνω σε προβλήματα κυβερνοασφάλειας.', 'Υπό Εξέταση', '2025-10-01', NULL, NULL, NULL, NULL, '2025-09-19', 9.50, 119, 220, 'https://nemertes.library.upatras.gr/search?bbm.page=6&spc.page=4&spc.sf=dc.date.accessioned&spc.sd=DESC', NULL, NULL, NULL, NULL, '[\"https://nemertes.library.upatras.gr/home\"]', 'uploads/theses_pdfs/CyberAttack_83.pdf'),
(84, 'testt', 'testt', 'Υπό Ανάθεση', '2025-09-26', NULL, NULL, NULL, NULL, '2025-09-28', NULL, NULL, 218, NULL, NULL, NULL, NULL, NULL, '[]', NULL);

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','professor','secretary') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Άδειασμα δεδομένων του πίνακα `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `user_type`) VALUES
(100, 'st10434047@upnet.gr', 'studpass1', 'student'),
(101, 'st10434048@upnet.gr', 'studpass2', 'student'),
(102, 'st10434049@upnet.gr', 'studpass3', 'student'),
(103, 'st10434050@upnet.gr', 'studpass4', 'student'),
(104, 'st10434051@upnet.gr', 'studpass5', 'student'),
(105, 'st10434052@upnet.gr', 'studpass6', 'student'),
(106, 'st10434054@upnet.gr', 'studpass8', 'student'),
(107, 'st10434055@upnet.gr', 'studpass9', 'student'),
(108, 'st10434056@upnet.gr', 'studpass10', 'student'),
(109, 'st10434057@upnet.gr', 'studpass11', 'student'),
(110, 'st10434060@upnet.gr', 'studpass14', 'student'),
(111, 'st10434061@upnet.gr', 'studpass15', 'student'),
(112, 'st10434062@upnet.gr', 'studpass16', 'student'),
(113, 'st10434063@upnet.gr', 'studpass17', 'student'),
(114, 'st10434064@upnet.gr', 'studpass18', 'student'),
(115, 'st10434065@upnet.gr', 'studpass19', 'student'),
(116, 'st10434066@upnet.gr', 'studpass20', 'student'),
(117, 'st10434067@upnet.gr', 'studpass21', 'student'),
(200, 'prakomninos@upnet.gr', 'profpass1', 'professor'),
(201, 'prvasfou@upnet.gr', 'profpass2', 'professor'),
(202, 'prkarras@upnet.gr', 'profpass3', 'professor'),
(203, 'preleni@upnet.gr', 'profpass4', 'professor'),
(204, 'prhozier@upnet.gr', 'profpass5', 'professor'),
(205, 'prnikoskorobos@upnet.gr', 'profpass6', 'professor'),
(206, 'prkostkaranik@upnet.gr', 'profpass7', 'professor'),
(207, 'prjimnik@upnet.gr', 'profpass8', 'professor'),
(208, 'prsophiamich@upnet.gr', 'profpass9', 'professor'),
(209, 'prmichaelpap@upnet.gr', 'profpass10', 'professor'),
(210, 'prgeopan@upnet.gr', 'profpass11', 'professor'),
(211, 'prmariaalex@upnet.gr', 'profpass12', 'professor'),
(212, 'priannis@upnet.gr', 'profpass13', 'professor'),
(213, 'prelenipap@upnet.gr', 'profpass14', 'professor'),
(214, 'prdimitrislaz@upnet.gr', 'profpass15', 'professor'),
(215, 'prannakollia@upnet.gr', 'profpass16', 'professor'),
(216, 'prchrispapa@upnet.gr', 'profpass17', 'professor'),
(217, 'prfotismark@upnet.gr', 'profpass18', 'professor'),
(218, 'prstellakara@upnet.gr', 'profpass19', 'professor'),
(219, 'prpetroszach@upnet.gr', 'profpass20', 'professor'),
(300, 'grpapadopoulou@upnet.gr', 'grampass0', 'secretary'),
(301, 'grdimopoulos@upnet.gr', 'grampass1', 'secretary'),
(302, 'gralemoni@upnet.gr', 'grampass2', 'secretary'),
(303, 'grkaragiannis@upnet.gr', 'grampass3', 'secretary'),
(304, 'grkarras@upnet.gr', 'grampass4', 'secretary'),
(305, 'grmarkopoulos@upnet.gr', 'grampass5', 'secretary'),
(306, 'grpapageorgiou@upnet.gr', 'grampass6', 'secretary'),
(307, 'grakonstantinou@upnet.gr', 'grampass7', 'secretary'),
(308, 'grstamatiou@upnet.gr', 'grampass8', 'secretary'),
(309, 'grzacharopoulou@upnet.gr', 'grampass9', 'secretary'),
(310, 'grtsiolis@upnet.gr', 'grampass10', 'secretary'),
(311, 'grperraki@upnet.gr', 'grampass11', 'secretary'),
(312, 'grpanagopoulou@upnet.gr', 'grampass12', 'secretary'),
(118, 'st10657792@upnet.gr', 'studpass22', 'student'),
(220, 'prdimalexiou@upnet.gr', 'profpass21', 'professor'),
(119, 'st10498031@upnet.gr', 'studpass23', 'student'),
(193, 'st10434002@upnet.gr', 'studpass24', 'student');

--
-- Ευρετήρια για άχρηστους πίνακες
--

--
-- Ευρετήρια για πίνακα `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`attachment_id`);

--
-- Ευρετήρια για πίνακα `committees`
--
ALTER TABLE `committees`
  ADD PRIMARY KEY (`committee_id`);

--
-- Ευρετήρια για πίνακα `examinations`
--
ALTER TABLE `examinations`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `fk_examinations_thesis` (`thesis_id`);

--
-- Ευρετήρια για πίνακα `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Ευρετήρια για πίνακα `professors`
--
ALTER TABLE `professors`
  ADD UNIQUE KEY `email` (`email`);

--
-- Ευρετήρια για πίνακα `professors_notifications`
--
ALTER TABLE `professors_notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Ευρετήρια για πίνακα `professor_notes`
--
ALTER TABLE `professor_notes`
  ADD PRIMARY KEY (`note_id`);

--
-- Ευρετήρια για πίνακα `theses`
--
ALTER TABLE `theses`
  ADD PRIMARY KEY (`thesis_id`);

--
-- AUTO_INCREMENT για άχρηστους πίνακες
--

--
-- AUTO_INCREMENT για πίνακα `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT για πίνακα `attachments`
--
ALTER TABLE `attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT για πίνακα `committees`
--
ALTER TABLE `committees`
  MODIFY `committee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT για πίνακα `examinations`
--
ALTER TABLE `examinations`
  MODIFY `exam_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT για πίνακα `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT για πίνακα `professors_notifications`
--
ALTER TABLE `professors_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT για πίνακα `professor_notes`
--
ALTER TABLE `professor_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT για πίνακα `theses`
--
ALTER TABLE `theses`
  MODIFY `thesis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- Περιορισμοί για άχρηστους πίνακες
--

--
-- Περιορισμοί για πίνακα `examinations`
--
ALTER TABLE `examinations`
  ADD CONSTRAINT `fk_examinations_thesis` FOREIGN KEY (`thesis_id`) REFERENCES `theses` (`thesis_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
