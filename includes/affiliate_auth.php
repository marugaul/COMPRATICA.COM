<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function aff_logged_in(){ return !empty($_SESSION['aff_id']); }
function aff_require_login(){ if (!aff_logged_in()){ header('Location: login.php'); exit; } }
?>