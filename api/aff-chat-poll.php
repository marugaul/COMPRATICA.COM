<?php
/**
 * GET /api/aff-chat-poll.php?sale_id=X&last_id=Y
 * Devuelve mensajes nuevos del chat de un espacio de Venta de Garaje.
 */

$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/aff_chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$saleId = (int)($_GET['sale_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);
$uid    = (int)($_SESSION['uid']    ?? 0);
$affId  = (int)($_SESSION['aff_id'] ?? 0);

if (!$saleId) {
    echo json_encode(['error' => 'missing_sale_id']);
    exit;
}

$pdo = db();
initAffChatTables($pdo);

// Obtener affiliate_id del espacio
$saleRow = $pdo->prepare("SELECT affiliate_id FROM sales WHERE id=? LIMIT 1");
$saleRow->execute([$saleId]);
$saleAffId = (int)($saleRow->fetchColumn() ?: 0);

$isAffiliate = ($affId > 0 && $affId === $saleAffId);

// Ban check para clientes
$isBanned = false;
if (!$isAffiliate && $uid) {
    $s = $pdo->prepare("SELECT id FROM aff_chat_bans WHERE sale_id=? AND banned_user_id=?");
    $s->execute([$saleId, $uid]);
    $isBanned = (bool)$s->fetchColumn();
}

// Consulta de mensajes según rol
if ($isAffiliate) {
    // El afiliado dueño ve todo
    $stmt = $pdo->prepare("
        SELECT id, sender_uid, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM aff_chat_messages
        WHERE sale_id=? AND id>?
        ORDER BY id ASC LIMIT 100
    ");
    $stmt->execute([$saleId, $lastId]);
} elseif ($uid) {
    // Cliente autenticado: mensajes públicos + propios + privados a él
    $stmt = $pdo->prepare("
        SELECT id, sender_uid, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM aff_chat_messages
        WHERE sale_id=? AND id>?
          AND (is_public=1 OR sender_uid=? OR private_to=?)
        ORDER BY id ASC LIMIT 100
    ");
    $stmt->execute([$saleId, $lastId, $uid, $uid]);
} else {
    // Anónimo: solo mensajes públicos
    $stmt = $pdo->prepare("
        SELECT id, sender_uid, sender_name, sender_type, message, is_public, private_to,
               strftime('%H:%M', created_at) AS time
        FROM aff_chat_messages
        WHERE sale_id=? AND id>? AND is_public=1
        ORDER BY id ASC LIMIT 100
    ");
    $stmt->execute([$saleId, $lastId]);
}
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lista de baneados (solo para el afiliado dueño)
$bans = [];
if ($isAffiliate) {
    $s = $pdo->prepare("
        SELECT b.banned_user_id AS user_id, u.name
        FROM aff_chat_bans b
        JOIN users u ON u.id = b.banned_user_id
        WHERE b.sale_id=?
    ");
    $s->execute([$saleId]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bans[$row['user_id']] = $row['name'];
    }
}

echo json_encode([
    'messages'  => $messages,
    'is_banned' => $isBanned,
    'bans'      => $bans,
]);
