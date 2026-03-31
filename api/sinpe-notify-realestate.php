<?php
/**
 * api/sinpe-notify-realestate.php
 * Marca una publicación de bienes raíces como pendiente de verificación SINPE.
 * POST { listing_id: int }
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$agent_id = (int)($_SESSION['agent_id'] ?? $_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
if ($agent_id <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$body       = json_decode((string)file_get_contents('php://input'), true) ?? [];
$listing_id = (int)($body['listing_id'] ?? 0);

if ($listing_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'listing_id requerido']);
    exit;
}

try {
    $pdo = db();

    // Verificar que la publicación pertenece al agente
    $s = $pdo->prepare("SELECT id, payment_status FROM real_estate_listings WHERE id = ? AND agent_id = ? LIMIT 1");
    $s->execute([$listing_id, $agent_id]);
    $listing = $s->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Publicación no encontrada']);
        exit;
    }

    if ($listing['payment_status'] === 'confirmed') {
        echo json_encode(['ok' => true, 'message' => 'Publicación ya activa']);
        exit;
    }

    $pdo->prepare("
        UPDATE real_estate_listings
        SET payment_status = 'pending',
            updated_at     = datetime('now')
        WHERE id = ?
    ")->execute([$listing_id]);

    echo json_encode(['ok' => true, 'message' => 'Pago SINPE registrado. Tu publicación será activada una vez verificado.']);

} catch (Throwable $e) {
    error_log('[sinpe-notify-realestate] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno']);
}
