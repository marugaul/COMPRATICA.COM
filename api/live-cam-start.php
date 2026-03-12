<?php
/** POST /api/live-cam-start.php — Inicia sesión de cámara live */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit; }

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) { echo json_encode(['error' => 'not_logged_in']); exit; }

$pdo = db();
initLiveCamTables($pdo);
cleanupOldCamSessions($pdo, $uid);

$title     = trim($_POST['title'] ?? 'En Vivo con Cámara');
$sessionId = bin2hex(random_bytes(16));

// Crear directorio de chunks
$dir = liveCamDir($sessionId);
if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
    echo json_encode(['error' => 'storage_error']); exit;
}

// Registrar sesión
$pdo->prepare("INSERT INTO live_cam_sessions (id, seller_id) VALUES (?,?)")
    ->execute([$sessionId, $uid]);

// Activar live con tipo cámara
$pdo->prepare("UPDATE users SET is_live=1, live_type='camera', live_session_id=?, live_title=?,
               live_link=NULL, live_started_at=datetime('now') WHERE id=?")
    ->execute([$sessionId, $title ?: 'En Vivo con Cámara', $uid]);

echo json_encode(['ok' => true, 'session_id' => $sessionId]);
