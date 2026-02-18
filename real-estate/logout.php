<?php
// real-estate/logout.php
// Usar config.php para arrancar la sesión con los mismos parámetros de cookie
require_once __DIR__ . '/../includes/config.php';

unset($_SESSION['agent_id']);
unset($_SESSION['agent_name']);
unset($_SESSION['uid']);
unset($_SESSION['name']);
session_destroy();
header('Location: login.php');
exit;
