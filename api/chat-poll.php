<?php
/**
 * GET /api/chat-poll.php?seller_id=X&last_id=Y
 * Devuelve mensajes nuevos y estado de ban del usuario actual.
 */
$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$sellerId = (int)($_GET['seller_id'] ?? 0);
$lastId   = (int)($_GET['last_id']   ?? 0);
$uid      = (int)($_SESSION['uid']   ?? 0);

if (!$sellerId) {
    echo json_encode(['error' => 'missing_seller_id']);
    exit;
}

$pdo = db();
initChatTables($pdo);

// ── Estado de ban ────────────────────────────────────────────────────────────
$isBanned = false;
if ($uid && $uid !== $sellerId) {
    $s = $pdo->prepare("SELECT id FROM live_chat_bans WHERE seller_id=? AND banned_user_id=?");
    $s->execute([$sellerId, $uid]);
    $isBanned = (bool)$s->fetchColumn();
}

// ── Mensajes ─────────────────────────────────────────────────────────────────
if ($uid === $sellerId) {
    // El vendedor ve todo
    $stmt = $pdo->prepare("
        SELECT id, sender_id, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM live_chat_messages
        WHERE seller_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 100
    ");
    $stmt->execute([$sellerId, $lastId]);
} elseif ($uid) {
    // Cliente autenticado: ve mensajes públicos + sus propios + privados a él
    $stmt = $pdo->prepare("
        SELECT id, sender_id, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM live_chat_messages
        WHERE seller_id = ? AND id > ?
          AND (is_public = 1 OR sender_id = ? OR private_to = ?)
        ORDER BY id ASC
        LIMIT 100
    ");
    $stmt->execute([$sellerId, $lastId, $uid, $uid]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, sender_id, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM live_chat_messages
        WHERE seller_id = ? AND id > ? AND is_public = 1
        ORDER BY id ASC
        LIMIT 100
    ");
    $stmt->execute([$sellerId, $lastId]);
}
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Para el vendedor: lista de usuarios baneados ──────────────────────────────
$bans = [];
if ($uid === $sellerId) {
    $s = $pdo->prepare("
        SELECT b.banned_user_id AS user_id, u.name
        FROM live_chat_bans b
        JOIN users u ON u.id = b.banned_user_id
        WHERE b.seller_id = ?
    ");
    $s->execute([$sellerId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bans[$row['user_id']] = $row['name'];
    }
}

echo json_encode([
    'messages'  => $messages,
    'is_banned' => $isBanned,
    'bans'      => $bans,
]);
