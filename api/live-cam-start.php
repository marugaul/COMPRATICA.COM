<?php
/** POST /api/live-cam-start.php — Inicia sesión de cámara live */
ini_set('display_errors', '0');
error_reporting(0);

$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
require_once __DIR__ . '/../includes/logger.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed']);
        exit;
    }

    $uid = (int)($_SESSION['uid'] ?? 0);
    logError('live-cam.log', 'START request', [
        'uid'          => $uid,
        'session_id'   => session_id(),
        'session_keys' => array_keys($_SESSION),
    ]);

    if (!$uid) {
        logError('live-cam.log', 'START blocked: not logged in');
        echo json_encode(['error' => 'not_logged_in', 'msg' => 'Sesión no encontrada. Recarga la página.']);
        exit;
    }

    $pdo = db();
    logError('live-cam.log', 'DB connected OK');

    // Asegurar columnas y tablas (safe: usa CREATE IF NOT EXISTS / ALTER si falta)
    initLiveCamTables($pdo);
    cleanupOldCamSessions($pdo, $uid);

    $title     = trim($_POST['title'] ?? 'En Vivo con Cámara');
    $sessionId = bin2hex(random_bytes(16));
    logError('live-cam.log', 'Creating session', ['session_id' => $sessionId, 'title' => $title, 'uid' => $uid]);

    // Asegurar directorio de storage
    $baseDir = __DIR__ . '/../storage/live-chunks';
    if (!is_dir($baseDir)) {
        $mkBase = @mkdir($baseDir, 0755, true);
        logError('live-cam.log', 'Created base dir', ['path' => $baseDir, 'result' => $mkBase]);
    }
    $dir = liveCamDir($sessionId);
    $mkDir = @mkdir($dir, 0755, true);
    logError('live-cam.log', 'Created session dir', ['path' => $dir, 'result' => $mkDir, 'exists' => is_dir($dir)]);
    if (!$mkDir && !is_dir($dir)) {
        logError('live-cam.log', 'FAIL: could not create session dir');
        echo json_encode(['error' => 'storage_error', 'msg' => 'No se pudo crear directorio de almacenamiento.']);
        exit;
    }

    // Registrar sesión
    $pdo->prepare("INSERT INTO live_cam_sessions (id, seller_id) VALUES (?,?)")
        ->execute([$sessionId, $uid]);
    logError('live-cam.log', 'Session inserted in DB');

    // Activar live con tipo cámara
    $stmt = $pdo->prepare("
        UPDATE users
        SET is_live=1, live_type='camera', live_session_id=?, live_title=?,
            live_link=NULL, live_started_at=datetime('now')
        WHERE id=?
    ");
    $stmt->execute([$sessionId, $title ?: 'En Vivo con Cámara', $uid]);
    logError('live-cam.log', 'User updated', ['rows_affected' => $stmt->rowCount()]);

    echo json_encode(['ok' => true, 'session_id' => $sessionId]);
    logError('live-cam.log', 'START success', ['session_id' => $sessionId]);

} catch (Throwable $e) {
    logError('live-cam.log', 'EXCEPTION: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'msg' => $e->getMessage()]);
}
