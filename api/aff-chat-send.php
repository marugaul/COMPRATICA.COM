<?php
/**
 * POST /api/aff-chat-send.php
 * Envía un mensaje al chat de un espacio de Venta de Garaje.
 *
 * Body params (application/x-www-form-urlencoded):
 *   sale_id    int     - ID del espacio (sales.id)
 *   message    string  - Texto del mensaje (máx 500 chars)
 *   is_public  int     - 1=público, 0=privado (solo para el afiliado)
 *   private_to int     - users.id destinatario (solo para afiliado en modo privado)
 */

$__sessPath = __DIR__ . '/../sessions';
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';     // containsProfanity()
require_once __DIR__ . '/../includes/aff_chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$uid   = (int)($_SESSION['uid']    ?? 0);
$affId = (int)($_SESSION['aff_id'] ?? 0);

if (!$uid && !$affId) {
    echo json_encode(['error' => 'not_logged_in', 'msg' => 'Debes iniciar sesión para chatear.']);
    exit;
}

$pdo = db();
initAffChatTables($pdo);

$saleId    = (int)($_POST['sale_id']    ?? 0);
$message   = trim($_POST['message']     ?? '');
$isPublic  = (int)($_POST['is_public']  ?? 1);
$privateTo = isset($_POST['private_to']) ? (int)$_POST['private_to'] : null;

if (!$saleId || $message === '') {
    echo json_encode(['error' => 'missing_data']);
    exit;
}
if (mb_strlen($message) > 500) {
    echo json_encode(['error' => 'too_long', 'msg' => 'El mensaje no puede superar 500 caracteres.']);
    exit;
}
if (containsProfanity($message)) {
    echo json_encode(['error' => 'profanity', 'msg' => 'Tu mensaje contiene lenguaje inapropiado y no fue enviado.']);
    exit;
}

// Verificar que el espacio existe
$saleStmt = $pdo->prepare("SELECT affiliate_id, chat_active FROM sales WHERE id=? AND is_active=1 LIMIT 1");
$saleStmt->execute([$saleId]);
$sale = $saleStmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    echo json_encode(['error' => 'invalid_sale']);
    exit;
}

$isAffiliate = ($affId > 0 && $affId === (int)$sale['affiliate_id']);

// Clientes solo pueden escribir si el chat está activo
if (!$isAffiliate && !$sale['chat_active']) {
    echo json_encode(['error' => 'chat_inactive', 'msg' => 'El chat no está activo en este momento.']);
    exit;
}

// Verificar ban (solo clientes)
if (!$isAffiliate && $uid) {
    $s = $pdo->prepare("SELECT id FROM aff_chat_bans WHERE sale_id=? AND banned_user_id=?");
    $s->execute([$saleId, $uid]);
    if ($s->fetchColumn()) {
        echo json_encode(['error' => 'banned', 'msg' => 'Has sido bloqueado en este espacio.']);
        exit;
    }
    // Clientes siempre envían mensajes públicos
    $isPublic  = 1;
    $privateTo = null;
}

// Nombre del remitente
if ($isAffiliate) {
    $nameStmt = $pdo->prepare("SELECT name FROM affiliates WHERE id=?");
    $nameStmt->execute([$affId]);
    $senderName = $nameStmt->fetchColumn() ?: 'Vendedor';
    $senderType = 'affiliate';
    $senderUid  = 0;
} else {
    $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id=?");
    $nameStmt->execute([$uid]);
    $senderName = $nameStmt->fetchColumn() ?: 'Cliente';
    $senderType = 'client';
    $senderUid  = $uid;
}

$ins = $pdo->prepare("
    INSERT INTO aff_chat_messages
        (sale_id, aff_id, sender_uid, sender_name, sender_type, message, is_public, private_to)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([
    $saleId,
    (int)$sale['affiliate_id'],
    $senderUid,
    $senderName,
    $senderType,
    $message,
    $isPublic,
    ($privateTo !== null && $isPublic === 0) ? $privateTo : null,
]);

echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
