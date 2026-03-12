<?php
/** POST /api/live-cam-chunk.php — Recibe un chunk de vídeo WebM del vendedor */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
require_once __DIR__ . '/../includes/logger.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit; }

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) {
    logError('live-cam.log', 'CHUNK blocked: not logged in');
    echo json_encode(['error' => 'not_logged_in']); exit;
}

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
$index     = (int)($_POST['index'] ?? -1);

if (!$sessionId || $index < 0 || empty($_FILES['chunk'])) {
    logError('live-cam.log', 'CHUNK missing data', ['session_id'=>$sessionId,'index'=>$index,'files'=>array_keys($_FILES)]);
    echo json_encode(['error' => 'missing_data']); exit;
}
if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    logError('live-cam.log', 'CHUNK upload error', ['code'=>$_FILES['chunk']['error']]);
    echo json_encode(['error' => 'upload_error', 'code' => $_FILES['chunk']['error']]); exit;
}
// Límite de tamaño por chunk: 2 MB
if ($_FILES['chunk']['size'] > 2 * 1024 * 1024) {
    logError('live-cam.log', 'CHUNK too large', ['size'=>$_FILES['chunk']['size']]);
    echo json_encode(['error' => 'chunk_too_large']); exit;
}

try {
    $pdo = db();
    initLiveCamTables($pdo);

    // Verificar que la sesión pertenece a este usuario y está activa
    $row = $pdo->prepare("SELECT chunk_count FROM live_cam_sessions WHERE id=? AND seller_id=? AND status='active'");
    $row->execute([$sessionId, $uid]);
    $session = $row->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        logError('live-cam.log', 'CHUNK session not found', ['session_id'=>$sessionId,'uid'=>$uid]);
        echo json_encode(['error' => 'session_not_found']); exit;
    }

    $dir = liveCamDir($sessionId);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $filename = $dir . '/' . str_pad($index, 5, '0', STR_PAD_LEFT) . '.webm';
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $filename)) {
        logError('live-cam.log', 'CHUNK save failed', ['filename'=>$filename,'dir_exists'=>is_dir($dir)]);
        echo json_encode(['error' => 'save_failed']); exit;
    }

    $newCount = max((int)$session['chunk_count'], $index + 1);
    $pdo->prepare("UPDATE live_cam_sessions SET chunk_count=? WHERE id=?")
        ->execute([$newCount, $sessionId]);

    if ($index < 3 || $index % 20 === 0) {
        logError('live-cam.log', "CHUNK $index saved OK", ['session_id'=>$sessionId,'size'=>$_FILES['chunk']['size'],'total'=>$newCount]);
    }

    echo json_encode(['ok' => true, 'index' => $index, 'count' => $newCount]);

} catch (Throwable $e) {
    logError('live-cam.log', 'CHUNK EXCEPTION: ' . $e->getMessage(), ['file'=>$e->getFile(),'line'=>$e->getLine()]);
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'msg' => $e->getMessage()]);
}
