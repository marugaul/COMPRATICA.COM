<?php
/**
 * GET  /api/emp-shipping.php?seller_id=X   → opciones de envío del vendedor
 * POST /api/emp-shipping.php               → guarda elección de envío en sesión
 *   body JSON: { seller_id, method, address, zone_name, zone_price }
 */
header('Content-Type: application/json');

$__sessPath = dirname(__DIR__) . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/shipping_emprendedoras.php';

$pdo = db();

// ── GET: devuelve opciones del vendedor ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sellerId = (int)($_GET['seller_id'] ?? 0);
    if ($sellerId <= 0) { echo json_encode(['ok'=>false,'error'=>'seller_id requerido']); exit; }
    $cfg = getShippingConfig($pdo, $sellerId);
    // Incluir selección actual del comprador si existe
    $chosen = $_SESSION['emp_shipping'][$sellerId] ?? null;
    echo json_encode(['ok'=>true, 'config'=>$cfg, 'chosen'=>$chosen]);
    exit;
}

// ── POST: guarda elección en sesión ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sellerId  = (int)($body['seller_id'] ?? 0);
    $method    = $body['method']     ?? '';
    $address   = trim($body['address']   ?? '');
    $zoneName  = trim($body['zone_name'] ?? '');
    $zonePrice = (int)($body['zone_price'] ?? 0);

    $allowed = ['free', 'pickup', 'express', 'mooving'];
    if ($sellerId <= 0 || !in_array($method, $allowed)) {
        echo json_encode(['ok'=>false,'error'=>'datos inválidos']); exit;
    }

    // Verificar que el método elegido está activo para ese vendedor
    $cfg = getShippingConfig($pdo, $sellerId);
    $valid = match($method) {
        'free'    => (bool)$cfg['enable_free_shipping'],
        'pickup'  => (bool)$cfg['enable_pickup'],
        'express' => (bool)$cfg['enable_express'],
        'mooving' => (bool)($cfg['enable_mooving'] ?? 0),
        default   => false,
    };
    if (!$valid) { echo json_encode(['ok'=>false,'error'=>'método no disponible']); exit; }

    if (!isset($_SESSION['emp_shipping'])) $_SESSION['emp_shipping'] = [];
    $_SESSION['emp_shipping'][$sellerId] = [
        'method'     => $method,
        'address'    => ($method === 'express' || $method === 'mooving') ? $address : '',
        'zone_name'  => ($method === 'express' || $method === 'mooving') ? $zoneName  : '',
        'zone_price' => ($method === 'express' || $method === 'mooving') ? $zonePrice : 0,
    ];

    echo json_encode(['ok'=>true, 'shipping'=>$_SESSION['emp_shipping'][$sellerId]]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'método HTTP no soportado']);
