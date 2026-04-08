<?php
/** POST /api/live-cam-stop.php — Detiene la sesión de cámara live */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';
require_once __DIR__ . '/../includes/logger.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit; }

$uid    = (int)($_SESSION['uid']     ?? 0);
$affId  = (int)($_SESSION['aff_id'] ?? 0);
$isAffiliate = $affId > 0;
$sellerId    = $isAffiliate ? $affId : $uid;
if (!$sellerId) {
    logError('live-cam.log', 'STOP blocked: not logged in');
    echo json_encode(['error' => 'not_logged_in']); exit;
}

$pdo = db();
initLiveCamTables($pdo);

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
$col = $isAffiliate ? 'affiliate_id' : 'seller_id';
logError('live-cam.log', 'STOP request', ['seller_id'=>$sellerId,'affiliate'=>$isAffiliate,'session_id'=>$sessionId]);

if ($sessionId) {
    $pdo->prepare("UPDATE live_cam_sessions SET status='ended', ended_at=datetime('now') WHERE id=? AND $col=?")
        ->execute([$sessionId, $sellerId]);
}

// Apagar live en la tabla correcta
$table = $isAffiliate ? 'affiliates' : 'users';
$pdo->prepare("UPDATE $table SET is_live=0, live_type='link', live_session_id=NULL,
               live_title=NULL, live_link=NULL, live_started_at=NULL WHERE id=?")
    ->execute([$sellerId]);

logError('live-cam.log', 'STOP success', ['uid'=>$uid]);
echo json_encode(['ok' => true]);
