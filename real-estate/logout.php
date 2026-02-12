<?php
// real-estate/logout.php
session_start();
unset($_SESSION['agent_id']);
unset($_SESSION['agent_name']);
session_destroy();
header('Location: login.php');
exit;
?>
