<?php
/**
 * GET /api/live-cam-serve.php?session_id=X&index=N
 * Sirve un chunk WebM de la sesión indicada.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/live_cam.php';

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['session_id'] ?? '');
$index     = (int)($_GET['index'] ?? -1);

if (!$sessionId || $index < 0) { http_response_code(400); exit('Bad request'); }

// Verificar que la sesión existe (no necesariamente activa — viewer puede pedir chunks tras fin)
$pdo = db();
initLiveCamTables($pdo);
$stmt = $pdo->prepare("SELECT id FROM live_cam_sessions WHERE id=?");
$stmt->execute([$sessionId]);
if (!$stmt->fetchColumn()) { http_response_code(404); exit('Session not found'); }

$file = liveCamDir($sessionId) . '/' . str_pad($index, 5, '0', STR_PAD_LEFT) . '.webm';

if (!file_exists($file)) {
    http_response_code(404);
    exit('Chunk not found');
}

header('Content-Type: video/webm');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');
readfile($file);
