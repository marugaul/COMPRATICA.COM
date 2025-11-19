<?php
/**
 * API: Detener/Pausar importación OSM
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_places.php';

try {
    $pdo = db_places();

    // Actualizar estado a "paused"
    $pdo->exec("
        UPDATE import_progress
        SET status = 'paused',
            message = 'Importación pausada por el usuario'
        WHERE id = 1
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Importación pausada'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
