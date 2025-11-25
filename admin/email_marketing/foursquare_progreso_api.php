<?php
/**
 * API para obtener el progreso de importación de Foursquare
 */

header('Content-Type: application/json');

$progress_file = __DIR__ . '/../../logs/foursquare_progress.json';

if (file_exists($progress_file)) {
    $content = file_get_contents($progress_file);
    $data = json_decode($content, true);

    // Verificar que los datos no sean muy viejos (más de 10 minutos)
    if (isset($data['timestamp']) && (time() - $data['timestamp']) > 600) {
        echo json_encode([
            'percent' => 0,
            'message' => 'Sin importación activa',
            'imported' => 0,
            'total' => 0
        ]);
    } else {
        echo json_encode($data);
    }
} else {
    echo json_encode([
        'percent' => 0,
        'message' => 'Sin importación activa',
        'imported' => 0,
        'total' => 0
    ]);
}
