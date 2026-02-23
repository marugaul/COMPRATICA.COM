<?php
// jobs/delete-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar sesión
if (!isset($_SESSION['employer_id']) || $_SESSION['employer_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$employer_id = (int)$_SESSION['employer_id'];

// Obtener ID de la publicación
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($listing_id <= 0) {
  header('Location: dashboard.php?error=invalid_id');
  exit;
}

// Verificar que la publicación pertenece al empleador
$stmt = $pdo->prepare("SELECT id FROM job_listings WHERE id = ? AND employer_id = ?");
$stmt->execute([$listing_id, $employer_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
  header('Location: dashboard.php?error=not_found');
  exit;
}

// Eliminar la publicación
try {
  $stmt = $pdo->prepare("DELETE FROM job_listings WHERE id = ? AND employer_id = ?");
  $stmt->execute([$listing_id, $employer_id]);

  header('Location: dashboard.php?success=deleted');
  exit;
} catch (Exception $e) {
  header('Location: dashboard.php?error=delete_failed');
  exit;
}
