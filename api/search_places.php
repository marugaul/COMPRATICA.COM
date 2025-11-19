<?php
/**
 * API de búsqueda de lugares en Costa Rica
 * Usa MySQL FULLTEXT search para resultados rápidos
 *
 * Parámetros:
 * - q: query de búsqueda (mínimo 2 caracteres)
 * - limit: número máximo de resultados (default: 10, max: 50)
 *
 * Retorna JSON con lugares encontrados
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db_places.php';

try {
    // Validar parámetros
    $query = $_GET['q'] ?? '';
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;

    if (strlen($query) < 2) {
        echo json_encode([
            'success' => false,
            'error' => 'Query debe tener al menos 2 caracteres',
            'results' => []
        ]);
        exit;
    }

    $pdo = db_places();
    $startTime = microtime(true);

    // Buscar usando LIKE para compatibilidad
    $searchQuery = '%' . $query . '%';
    $queryStart = $query . '%';

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            type,
            category,
            city,
            district,
            canton,
            province,
            lat,
            lng,
            priority,
            source
        FROM places_cr
        WHERE
            is_active = 1
            AND (
                name LIKE ?
                OR city LIKE ?
                OR district LIKE ?
                OR province LIKE ?
                OR address LIKE ?
            )
        ORDER BY
            -- Priorizar coincidencias exactas al inicio
            CASE
                WHEN name LIKE ? THEN 1
                WHEN city LIKE ? THEN 2
                ELSE 3
            END,
            priority DESC,
            name ASC
        LIMIT ?
    ");

    $stmt->execute([
        $searchQuery,
        $searchQuery,
        $searchQuery,
        $searchQuery,
        $searchQuery,
        $queryStart,
        $queryStart,
        $limit
    ]);

    $results = $stmt->fetchAll();

    // Calcular tiempo de búsqueda
    $searchTime = round((microtime(true) - $startTime) * 1000); // ms

    // Registrar estadísticas de búsqueda
    try {
        $statsStmt = $pdo->prepare("
            INSERT INTO search_stats (query, results_count, search_time_ms)
            VALUES (:query, :count, :time)
        ");
        $statsStmt->execute([
            ':query' => substr($query, 0, 255),
            ':count' => count($results),
            ':time' => $searchTime
        ]);
    } catch (Exception $e) {
        // No fallar si no se pueden guardar estadísticas
        error_log("Error guardando stats: " . $e->getMessage());
    }

    // Mapear iconos según categoría/tipo
    $iconMap = [
        'airport' => 'plane',
        'port' => 'ship',
        'city' => 'city',
        'beach' => 'umbrella-beach',
        'park' => 'tree',
        'hotel' => 'hotel',
        'mall' => 'shopping-bag',
        'supermarket' => 'store',
        'hospital' => 'hospital',
        'bank' => 'building-columns',
        'gas_station' => 'gas-pump',
        'restaurant' => 'utensils',
        'pharmacy' => 'pills',
        'attraction' => 'camera'
    ];

    // Formatear resultados
    $formattedResults = array_map(function($place) use ($iconMap) {
        // Determinar icono
        $icon = $iconMap[$place['type']] ?? $iconMap[$place['category']] ?? 'map-pin';

        // Construir subtítulo con información disponible
        $subtitle_parts = array_filter([
            $place['district'],
            $place['city'],
            $place['province']
        ]);
        $subtitle = !empty($subtitle_parts)
            ? implode(', ', $subtitle_parts)
            : $place['province'] ?? 'Costa Rica';

        return [
            'id' => $place['id'],
            'name' => $place['name'],
            'type' => $place['type'],
            'category' => $place['category'],
            'city' => $place['city'],
            'province' => $place['province'],
            'subtitle' => $subtitle,
            'icon' => $icon,
            'priority' => $place['priority'],
            'source' => $place['source'],
            'lat' => $place['lat'] ? (float)$place['lat'] : null,
            'lng' => $place['lng'] ? (float)$place['lng'] : null
        ];
    }, $results);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'query' => $query,
        'count' => count($formattedResults),
        'search_time_ms' => $searchTime,
        'results' => $formattedResults
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos',
        'message' => $e->getMessage(),
        'results' => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno',
        'message' => $e->getMessage(),
        'results' => []
    ]);
}
?>
