<?php
/** POST /api/live-cam-chunk.php — Recibe un chunk de vídeo WebM del vendedor */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit; }

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) { echo json_encode(['error' => 'not_logged_in']); exit; }

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
$index     = (int)($_POST['index'] ?? -1);

if (!$sessionId || $index < 0 || empty($_FILES['chunk'])) {
    echo json_encode(['error' => 'missing_data']); exit;
}
if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'upload_error', 'code' => $_FILES['chunk']['error']]); exit;
}
// Límite de tamaño por chunk: 2 MB
if ($_FILES['chunk']['size'] > 2 * 1024 * 1024) {
    echo json_encode(['error' => 'chunk_too_large']); exit;
}

$pdo = db();
initLiveCamTables($pdo);

// Verificar que la sesión pertenece a este usuario y está activa
$row = $pdo->prepare("SELECT chunk_count FROM live_cam_sessions WHERE id=? AND seller_id=? AND status='active'");
$row->execute([$sessionId, $uid]);
$session = $row->fetch(PDO::FETCH_ASSOC);
if (!$session) { echo json_encode(['error' => 'session_not_found']); exit; }

$dir = liveCamDir($sessionId);
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$filename = $dir . '/' . str_pad($index, 5, '0', STR_PAD_LEFT) . '.webm';
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $filename)) {
    echo json_encode(['error' => 'save_failed']); exit;
}

$newCount = max((int)$session['chunk_count'], $index + 1);
$pdo->prepare("UPDATE live_cam_sessions SET chunk_count=? WHERE id=?")
    ->execute([$newCount, $sessionId]);

echo json_encode(['ok' => true, 'index' => $index, 'count' => $newCount]);
