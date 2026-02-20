<?php
// services/delete-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['agent_id'])) {
    header('Location: login.php');
    exit;
}

$pdo      = db();
$agent_id = (int)$_SESSION['agent_id'];
$id       = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Solo puede eliminar sus propios servicios
    $stmt = $pdo->prepare("DELETE FROM service_listings WHERE id = ? AND agent_id = ?");
    $stmt->execute([$id, $agent_id]);
}

header('Location: dashboard.php?msg=deleted');
exit;
