<?php
/**
 * POST /api/chat-send.php
 * Envía un mensaje al chat de una tienda.
 *
 * Body params:
 *   seller_id  int
 *   message    string
 *   is_public  int  (1=público, 0=privado) — solo válido para el vendedor
 *   private_to int  (user_id destinatario) — solo válido para el vendedor
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
    echo json_encode(['error' => 'not_logged_in', 'msg' => 'Debes iniciar sesión para chatear.']);
    exit;
}

$pdo = db();
initChatTables($pdo);

$sellerId  = (int)($_POST['seller_id']  ?? 0);
$message   = trim($_POST['message']     ?? '');
$isPublic  = (int)($_POST['is_public']  ?? 1);
$privateTo = isset($_POST['private_to']) ? (int)$_POST['private_to'] : null;

if (!$sellerId || $message === '') {
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

$isSeller = ($uid === $sellerId);

// Verificar ban (solo aplica a clientes)
if (!$isSeller) {
    $s = $pdo->prepare("SELECT id FROM live_chat_bans WHERE seller_id=? AND banned_user_id=?");
    $s->execute([$sellerId, $uid]);
    if ($s->fetchColumn()) {
        echo json_encode(['error' => 'banned', 'msg' => 'Has sido bloqueado por esta emprendedora.']);
        exit;
    }
    // Clientes siempre envían público (su pregunta es visible para todos)
    $isPublic  = 1;
    $privateTo = null;
}

// Nombre del remitente
$nameStmt = $pdo->prepare("SELECT name FROM users WHERE id=?");
$nameStmt->execute([$uid]);
$senderName = $nameStmt->fetchColumn() ?: 'Usuario';

$senderType = $isSeller ? 'seller' : 'client';

$ins = $pdo->prepare("
    INSERT INTO live_chat_messages
        (seller_id, sender_id, sender_name, sender_type, message, is_public, private_to)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([
    $sellerId,
    $uid,
    $senderName,
    $senderType,
    $message,
    $isPublic,
    ($privateTo && $isPublic === 0) ? $privateTo : null,
]);

echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
