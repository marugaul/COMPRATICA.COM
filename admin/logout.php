<?php
require_once __DIR__ . '/../includes/config.php';
$_SESSION['is_admin'] = false;
unset($_SESSION['is_admin'], $_SESSION['admin_user']);
session_regenerate_id(true);
header('Location: login.php');
exit;
