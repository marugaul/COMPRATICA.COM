<?php
/**
 * API: Resetear estado de importación
 * Permite reiniciar la importación desde cero
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_places.php';

try {
    $pdo = db_places();

    // Resetear progreso
    $pdo->exec("
        UPDATE import_progress
        SET status = 'idle',
            current_category = 'No iniciado',
            current_category_index = 0,
            total_imported = 0,
            progress = 0,
            message = 'Listo para importar',
            last_log = NULL,
            started_at = NULL
        WHERE id = 1
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Estado de importación reseteado'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
