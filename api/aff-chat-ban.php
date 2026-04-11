<?php
/**
 * POST /api/aff-chat-ban.php
 * Banea o desbanea un usuario del chat de un espacio de Venta de Garaje.
 * Solo el afiliado dueño del espacio puede ejecutar esta acción.
 *
 * Body params:
 *   sale_id        int    - ID del espacio
 *   banned_user_id int    - users.id del usuario a banear/desbanear
 *   action         string - 'ban' | 'unban'
 */

$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/aff_chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$affId = (int)($_SESSION['aff_id'] ?? 0);
if (!$affId) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$pdo = db();
initAffChatTables($pdo);

$saleId       = (int)($_POST['sale_id']        ?? 0);
$bannedUserId = (int)($_POST['banned_user_id'] ?? 0);
$action       = trim($_POST['action']           ?? '');

if (!$saleId || !$bannedUserId || !in_array($action, ['ban', 'unban'], true)) {
    echo json_encode(['error' => 'missing_data']);
    exit;
}

// Verificar que el espacio pertenece a este afiliado
$check = $pdo->prepare("SELECT id FROM sales WHERE id=? AND affiliate_id=? LIMIT 1");
$check->execute([$saleId, $affId]);
if (!$check->fetchColumn()) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($action === 'ban') {
    $pdo->prepare("INSERT OR IGNORE INTO aff_chat_bans (sale_id, banned_user_id) VALUES (?,?)")
        ->execute([$saleId, $bannedUserId]);
} else {
    $pdo->prepare("DELETE FROM aff_chat_bans WHERE sale_id=? AND banned_user_id=?")
        ->execute([$saleId, $bannedUserId]);
}

echo json_encode(['ok' => true, 'action' => $action === 'ban' ? 'banned' : 'unbanned']);
