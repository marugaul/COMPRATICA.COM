<?php
/**
 * API: Ejecutar un lote de importación OSM
 * Procesa una categoría a la vez, llamado repetidamente desde el frontend
 */

header('Content-Type: application/json');
set_time_limit(120); // 2 minutos por categoría

require_once __DIR__ . '/../includes/db_places.php';
require_once __DIR__ . '/import_functions.php';

try {
    $pdo = db_places();

    // Verificar estado actual
    $stmt = $pdo->query("SELECT * FROM import_progress WHERE id = 1");
    $current = $stmt->fetch();

    if (!$current) {
        // Inicializar registro
        $pdo->exec("
            INSERT INTO import_progress (id, status, current_category, current_category_index, started_at)
            VALUES (1, 'running', 'Iniciando', 0, NOW())
        ");
        $current = ['status' => 'running', 'current_category_index' => 0, 'total_imported' => 0];
    }

    // Si ya completó, no hacer nada
    if ($current['status'] === 'completed') {
        echo json_encode([
            'success' => true,
            'completed' => true,
            'message' => 'Importación ya completada'
        ]);
        exit;
    }

    // Si está pausado, no continuar
    if ($current['status'] === 'paused') {
        echo json_encode([
            'success' => true,
            'paused' => true,
            'message' => 'Importación pausada'
        ]);
        exit;
    }

    // Definir categorías
    $categories = getImportCategories();
    $totalCategories = count($categories);
    $currentIndex = (int)$current['current_category_index'];

    // Si ya procesamos todas las categorías, marcar como completado
    if ($currentIndex >= $totalCategories) {
        updateProgress($pdo, 'completed', 'Completado', $totalCategories, $totalCategories,
            $current['total_imported'], 'Importación completada', '¡Proceso finalizado!');

        echo json_encode([
            'success' => true,
            'completed' => true,
            'total_imported' => $current['total_imported']
        ]);
        exit;
    }

    // Procesar la categoría actual
    $cat = $categories[$currentIndex];

    updateProgress($pdo, 'running', $cat['name'], $currentIndex + 1, $totalCategories,
        $current['total_imported'], "Importando {$cat['name']}...", "Consultando Overpass API...");

    // Construir y ejecutar query
    $overpassQuery = buildOverpassQuery($cat['query']);
    $places = fetchFromOverpass($overpassQuery);

    if ($places === false) {
        // Error en la consulta, pero continuar
        updateProgress($pdo, 'running', $cat['name'], $currentIndex + 1, $totalCategories,
            $current['total_imported'], "Error en {$cat['name']}, continuando...", "Error de API, saltando categoría");

        // Avanzar al siguiente
        $pdo->exec("UPDATE import_progress SET current_category_index = current_category_index + 1 WHERE id = 1");

        echo json_encode([
            'success' => true,
            'continue' => true,
            'imported' => 0,
            'category' => $cat['name'],
            'error' => 'Error consultando Overpass API'
        ]);
        exit;
    }

    // Importar lugares
    $imported = importPlaces($pdo, $places, $cat);

    // Actualizar contador total
    $newTotal = $current['total_imported'] + $imported;
    $progress = (($currentIndex + 1) / $totalCategories) * 100;

    updateProgress($pdo, 'running', $cat['name'], $currentIndex + 1, $totalCategories,
        $newTotal, "Importados {$imported} lugares de {$cat['name']}", "Completada categoría {$cat['name']}");

    // Avanzar al siguiente índice
    $pdo->exec("UPDATE import_progress SET current_category_index = current_category_index + 1 WHERE id = 1");

    echo json_encode([
        'success' => true,
        'continue' => true,
        'imported' => $imported,
        'total_imported' => $newTotal,
        'category' => $cat['name'],
        'progress' => $progress
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
