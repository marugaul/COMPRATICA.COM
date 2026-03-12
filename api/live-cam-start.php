<?php
/** POST /api/live-cam-start.php — Inicia sesión de cámara live */
ini_set('display_errors', '0');
error_reporting(0);

$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
        exit;
    }

    $uid = (int)($_SESSION['uid'] ?? 0);
    if (!$uid) {
        echo json_encode(['error' => 'not_logged_in', 'msg' => 'Sesión no encontrada. Recarga la página.']);
        exit;
    }

    $pdo = db();

    // Asegurar columnas y tablas (safe: usa CREATE IF NOT EXISTS / ALTER si falta)
    initLiveCamTables($pdo);
    cleanupOldCamSessions($pdo, $uid);

    $title     = trim($_POST['title'] ?? 'En Vivo con Cámara');
    $sessionId = bin2hex(random_bytes(16));

    // Asegurar directorio de storage
    $baseDir = __DIR__ . '/../storage/live-chunks';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }
    $dir = liveCamDir($sessionId);
    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo json_encode(['error' => 'storage_error', 'msg' => 'No se pudo crear directorio de almacenamiento.']);
        exit;
    }

    // Registrar sesión
    $pdo->prepare("INSERT INTO live_cam_sessions (id, seller_id) VALUES (?,?)")
        ->execute([$sessionId, $uid]);

    // Activar live con tipo cámara
    $pdo->prepare("
        UPDATE users
        SET is_live=1, live_type='camera', live_session_id=?, live_title=?,
            live_link=NULL, live_started_at=datetime('now')
        WHERE id=?
    ")->execute([$sessionId, $title ?: 'En Vivo con Cámara', $uid]);

    echo json_encode(['ok' => true, 'session_id' => $sessionId]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'msg' => $e->getMessage()]);
}
