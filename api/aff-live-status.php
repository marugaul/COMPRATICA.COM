<?php
/**
 * GET /api/aff-live-status.php?affiliate_id=X
 * Devuelve si el afiliado sigue en vivo.
 * Usado por store.php para ocultar el panel cuando termina el live.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';

$affId = (int)($_GET['affiliate_id'] ?? 0);
if (!$affId) { echo json_encode(['is_live' => false]); exit; }

try {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT COALESCE(is_live,0) FROM affiliates WHERE id=? LIMIT 1");
    $stmt->execute([$affId]);
    $live = (bool)$stmt->fetchColumn();
    echo json_encode(['is_live' => $live]);
} catch (Throwable $e) {
    echo json_encode(['is_live' => false]);
}
