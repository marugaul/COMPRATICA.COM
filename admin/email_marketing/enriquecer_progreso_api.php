<?php
/**
 * API para leer progreso de enriquecimiento de emails en tiempo real
 */

header('Content-Type: application/json');

// Archivo de progreso
$progress_file = __DIR__ . '/../../logs/enrich_progress.json';

// Si el archivo existe, leerlo
if (file_exists($progress_file)) {
    $content = file_get_contents($progress_file);
    $data = json_decode($content, true);

    // Verificar si es reciente (menos de 10 minutos)
    if ($data && isset($data['timestamp'])) {
        $age = time() - $data['timestamp'];
        if ($age < 600) { // 10 minutos
            echo json_encode($data);
            exit;
        }
    }
}

// Si no hay progreso o es viejo, retornar estado inicial
echo json_encode([
    'percent' => 0,
    'message' => 'Esperando...',
    'processed' => 0,
    'total' => 0,
    'found' => 0,
    'timestamp' => time()
]);
