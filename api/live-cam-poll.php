<?php
/** GET /api/live-cam-poll.php?session_id=X — El viewer pregunta cuántos chunks hay */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['session_id'] ?? '');
if (!$sessionId) { echo json_encode(['error' => 'missing_session']); exit; }

$pdo = db();
initLiveCamTables($pdo);

$stmt = $pdo->prepare("SELECT chunk_count, status FROM live_cam_sessions WHERE id=?");
$stmt->execute([$sessionId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ended' => true, 'chunk_count' => 0]);
    exit;
}

echo json_encode([
    'chunk_count' => (int)$row['chunk_count'],
    'ended'       => ($row['status'] === 'ended'),
]);
