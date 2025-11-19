<?php
/**
 * API: Iniciar importación OSM
 * Lanza el proceso de importación en background
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_places.php';

try {
    $pdo = db_places();

    // Verificar estado actual
    $stmt = $pdo->query("SELECT status FROM import_progress WHERE id = 1");
    $current = $stmt->fetch();

    if ($current && $current['status'] === 'running') {
        echo json_encode([
            'success' => false,
            'message' => 'Ya hay una importación en curso'
        ]);
        exit;
    }

    // Actualizar estado a "running"
    $pdo->exec("
        UPDATE import_progress
        SET status = 'running',
            started_at = NOW(),
            current_category_index = 0,
            progress = 0,
            message = 'Iniciando importación...'
        WHERE id = 1
    ");

    // Iniciar importación en background usando file_get_contents asíncrono
    $scriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . dirname($_SERVER['PHP_SELF']) . '/run_import.php';

    // Trigger import script sin esperar respuesta
    $context = stream_context_create([
        'http' => [
            'timeout' => 1, // Timeout rápido para no esperar
            'ignore_errors' => true
        ]
    ]);

    // Ejecutar en background
    @file_get_contents($scriptUrl . '?token=' . md5('osm_import_' . date('Y-m-d')), false, $context);

    echo json_encode([
        'success' => true,
        'message' => 'Importación iniciada correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
