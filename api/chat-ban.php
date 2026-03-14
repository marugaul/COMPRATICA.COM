<?php
/**
 * POST /api/chat-ban.php
 * El vendedor banea o desbanea a un cliente.
 *
 * Body params:
 *   banned_user_id int
 *   action         'ban' | 'unban'
 */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

$pdo = db();
initChatTables($pdo);

$bannedUserId = (int)($_POST['banned_user_id'] ?? 0);
$action       = in_array($_POST['action'] ?? '', ['ban','unban']) ? $_POST['action'] : 'ban';

if (!$bannedUserId || $bannedUserId === $uid) {
    echo json_encode(['error' => 'invalid_user']);
    exit;
}

if ($action === 'ban') {
    $pdo->prepare("INSERT OR IGNORE INTO live_chat_bans (seller_id, banned_user_id) VALUES (?,?)")
        ->execute([$uid, $bannedUserId]);
    echo json_encode(['ok' => true, 'action' => 'banned']);
} else {
    $pdo->prepare("DELETE FROM live_chat_bans WHERE seller_id=? AND banned_user_id=?")
        ->execute([$uid, $bannedUserId]);
    echo json_encode(['ok' => true, 'action' => 'unbanned']);
}
