<?php
// jobs/logout.php
session_start();
unset($_SESSION['employer_id']);
unset($_SESSION['employer_name']);
session_destroy();
header('Location: login.php');
exit;
?>
