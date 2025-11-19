<?php
/**
 * Vaciar tabla places_cr para reimportar desde cero
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_places.php';

try {
    $pdo = db_places();

    // Contar registros antes de vaciar
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM places_cr");
    $before = $stmt->fetch()['total'];

    // Vaciar tabla
    $pdo->exec("TRUNCATE TABLE places_cr");

    // Verificar
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM places_cr");
    $after = $stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'message' => 'Tabla vaciada correctamente',
        'before' => (int)$before,
        'after' => (int)$after
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
