<?php
/**
 * API para consultar el progreso de importación de Páginas Amarillas
 */

header('Content-Type: application/json');

$progress_file = __DIR__ . '/../../logs/paginas_amarillas_progress.json';

if (file_exists($progress_file)) {
    $data = json_decode(file_get_contents($progress_file), true);

    if (isset($data['timestamp']) && (time() - $data['timestamp']) > 3600) {
        $data = [
            'percent' => 0,
            'message' => 'Sin actividad reciente',
            'imported' => 0,
            'total' => 0
        ];
    }

    echo json_encode($data);
} else {
    echo json_encode([
        'percent' => 0,
        'message' => 'Sin progreso',
        'imported' => 0,
        'total' => 0
    ]);
}
