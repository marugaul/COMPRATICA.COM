<?php
/**
 * Eliminar producto del emprendedor.
 * URL: /emprendedores-producto-eliminar?id=N
 */
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Verificar sesión
$userId = (int)($_SESSION['uid'] ?? 0);
if ($userId <= 0) {
    header('Location: /login');
    exit;
}

$pdo   = db();
$id    = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: /emprendedores-dashboard');
    exit;
}

// Verificar que el producto pertenece al usuario
$stmt = $pdo->prepare("SELECT id FROM entrepreneur_products WHERE id=? AND user_id=? LIMIT 1");
$stmt->execute([$id, $userId]);
if (!$stmt->fetchColumn()) {
    header('Location: /emprendedores-dashboard?error=no_permission');
    exit;
}

// Eliminar el producto
$pdo->prepare("DELETE FROM entrepreneur_products WHERE id=? AND user_id=?")->execute([$id, $userId]);

header('Location: /emprendedores-dashboard?deleted=1');
exit;
