<?php
session_start();


// Διαγραφή δεδομένων συνεδρίας
session_unset();
session_destroy();


// Ανακατεύθυνση στη σελίδα σύνδεσης
header("Location: login.php");
exit();
?>