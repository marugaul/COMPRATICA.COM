<?php
/**
 * API para obtener cotización de Mooving
 * POST /mooving/ajax_mooving_quote.php
 *
 * Body JSON: {
 *   "seller_id": 123,
 *   "destination_address": "100m norte del parque, San José",
 *   "destination_lat": 9.9281,
 *   "destination_lng": -84.0907,
 *   "package_value": 5000
 * }
 */

header('Content-Type: application/json');

$__sessPath = dirname(__DIR__) . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/MovingAPI.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sellerId = (int)($body['seller_id'] ?? 0);
$destAddress = trim($body['destination_address'] ?? '');
$destLat = (float)($body['destination_lat'] ?? 0);
$destLng = (float)($body['destination_lng'] ?? 0);
$packageValue = (float)($body['package_value'] ?? 0);

if ($sellerId <= 0 || empty($destAddress) || $destLat == 0 || $destLng == 0) {
    echo json_encode([
        'ok' => false,
        'error' => 'Datos incompletos. Se requiere seller_id, dirección y coordenadas.'
    ]);
    exit;
}

try {
    $pdo = db();

    // Obtener ubicación de origen del vendedor (puedes configurarlo en una tabla)
    // Por ahora, usar ubicación por defecto de Costa Rica
    $origin = [
        'lat' => 9.9281, // San José, Costa Rica
        'lng' => -84.0907,
        'address' => 'San José, Costa Rica'
    ];

    $destination = [
        'lat' => $destLat,
        'lng' => $destLng,
        'address' => $destAddress
    ];

    $package = [
        'weight' => 1.0, // kg por defecto
        'value' => $packageValue,
        'description' => 'Productos de emprendedora'
    ];

    $mooving = new MovingAPI($pdo, $sellerId);

    if (!$mooving->isConfigured()) {
        // Si no está configurado, devolver precio estimado fijo
        echo json_encode([
            'ok' => true,
            'price' => 2500, // Precio fijo por defecto en CRC
            'currency' => 'CRC',
            'estimated_time' => 60,
            'is_estimate' => true,
            'message' => 'Precio estimado. Mooving no está completamente configurado.'
        ]);
        exit;
    }

    $quote = $mooving->getQuote($origin, $destination, $package);

    echo json_encode($quote);

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener cotización: ' . $e->getMessage(),
        'price' => 2500, // Precio de respaldo
        'currency' => 'CRC'
    ]);
}
