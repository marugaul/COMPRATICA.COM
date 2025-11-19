<?php
/**
 * Script de importación OSM - Ejecuta en background
 * Importa lugares de Costa Rica desde OpenStreetMap Overpass API
 */

// Aumentar tiempo de ejecución
set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/db_places.php';

// Verificar token simple para evitar ejecuciones no autorizadas
$expectedToken = md5('osm_import_' . date('Y-m-d'));
if (!isset($_GET['token']) || $_GET['token'] !== $expectedToken) {
    http_response_code(403);
    die('Token inválido');
}

try {
    $pdo = db_places();

    // Definir categorías a importar
    $categories = [
        [
            'name' => 'Hoteles y Alojamientos',
            'query' => '["tourism"~"hotel|hostel|guest_house|motel|apartment"]',
            'type' => 'hotel',
            'category' => 'hotels',
            'priority' => 7
        ],
        [
            'name' => 'Restaurantes y Cafés',
            'query' => '["amenity"~"restaurant|cafe|fast_food|bar|food_court"]',
            'type' => 'restaurant',
            'category' => 'restaurants',
            'priority' => 6
        ],
        [
            'name' => 'Bancos y Cajeros',
            'query' => '["amenity"~"bank|atm"]',
            'type' => 'bank',
            'category' => 'banks',
            'priority' => 6
        ],
        [
            'name' => 'Gasolineras',
            'query' => '["amenity"="fuel"]',
            'type' => 'gas_station',
            'category' => 'gas_stations',
            'priority' => 6
        ],
        [
            'name' => 'Supermercados y Tiendas',
            'query' => '["shop"~"supermarket|mall|department_store|convenience"]',
            'type' => 'supermarket',
            'category' => 'supermarkets',
            'priority' => 6
        ],
        [
            'name' => 'Hospitales y Farmacias',
            'query' => '["amenity"~"hospital|clinic|pharmacy|doctors"]',
            'type' => 'hospital',
            'category' => 'hospitals',
            'priority' => 7
        ],
        [
            'name' => 'Atracciones Turísticas',
            'query' => '["tourism"~"attraction|museum|viewpoint|zoo|theme_park"]',
            'type' => 'attraction',
            'category' => 'attractions',
            'priority' => 7
        ],
        [
            'name' => 'Ciudades y Poblaciones',
            'query' => '["place"~"city|town|village|hamlet"]',
            'type' => 'city',
            'category' => 'cities',
            'priority' => 8
        ]
    ];

    $totalCategories = count($categories);
    $totalImported = 0;

    // Procesar cada categoría
    for ($i = 0; $i < $totalCategories; $i++) {
        // Verificar si se pausó
        $stmt = $pdo->query("SELECT status FROM import_progress WHERE id = 1");
        $status = $stmt->fetchColumn();

        if ($status === 'paused') {
            updateProgress($pdo, 'paused', $categories[$i]['name'], $i, $totalCategories, $totalImported,
                'Importación pausada', 'Importación detenida por el usuario');
            exit;
        }

        $cat = $categories[$i];
        updateProgress($pdo, 'running', $cat['name'], $i + 1, $totalCategories, $totalImported,
            "Importando {$cat['name']}...", "Consultando Overpass API...");

        // Construir query Overpass QL
        $overpassQuery = buildOverpassQuery($cat['query']);

        // Ejecutar query
        $places = fetchFromOverpass($overpassQuery);

        if ($places === false) {
            updateProgress($pdo, 'error', $cat['name'], $i + 1, $totalCategories, $totalImported,
                'Error al consultar Overpass API', "Error en categoría: {$cat['name']}");
            sleep(10); // Esperar antes de continuar
            continue;
        }

        // Importar lugares
        $imported = importPlaces($pdo, $places, $cat);
        $totalImported += $imported;

        $progress = (($i + 1) / $totalCategories) * 100;
        updateProgress($pdo, 'running', $cat['name'], $i + 1, $totalCategories, $totalImported,
            "Importados {$imported} lugares de {$cat['name']}", "Categoría {$cat['name']} completada");

        // Esperar para no sobrecargar la API
        sleep(2);
    }

    // Completado
    updateProgress($pdo, 'completed', 'Completado', $totalCategories, $totalCategories, $totalImported,
        "Importación completada: {$totalImported} lugares", "¡Importación finalizada exitosamente!");

} catch (Exception $e) {
    error_log("Error en importación OSM: " . $e->getMessage());
    updateProgress($pdo, 'error', 'Error', 0, 8, 0,
        'Error: ' . $e->getMessage(), 'Error crítico en la importación');
}

// === FUNCIONES AUXILIARES ===

function updateProgress($pdo, $status, $category, $catIndex, $totalCat, $totalImported, $message, $log) {
    $progress = ($catIndex / $totalCat) * 100;

    $stmt = $pdo->prepare("
        UPDATE import_progress
        SET status = ?,
            current_category = ?,
            current_category_index = ?,
            total_categories = ?,
            total_imported = ?,
            progress = ?,
            message = ?,
            last_log = ?
        WHERE id = 1
    ");

    $stmt->execute([
        $status,
        $category,
        $catIndex,
        $totalCat,
        $totalImported,
        $progress,
        $message,
        $log
    ]);
}

function buildOverpassQuery($filter) {
    // Query para Costa Rica (bbox aproximado)
    // Costa Rica: lat 8.0° - 11.2°, lon -86.0° - -82.5°
    return "[out:json][timeout:90][bbox:8.0,-86.0,11.2,-82.5];
(
  node{$filter};
  way{$filter};
);
out body;
>;
out skel qt;";
}

function fetchFromOverpass($query) {
    $url = 'https://overpass-api.de/api/interpreter';

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'data=' . urlencode($query),
            'timeout' => 120
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['elements'])) {
        return false;
    }

    return $data['elements'];
}

function importPlaces($pdo, $places, $category) {
    $imported = 0;

    $stmt = $pdo->prepare("
        INSERT INTO places_cr (
            osm_id, osm_type, name, type, category, lat, lng,
            address, street, city, province, phone, website,
            tags, priority, source, is_active
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'osm', 1
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            lat = VALUES(lat),
            lng = VALUES(lng),
            updated_at = NOW()
    ");

    foreach ($places as $place) {
        // Filtrar lugares sin nombre
        if (empty($place['tags']['name'])) {
            continue;
        }

        $tags = $place['tags'] ?? [];

        // Determinar lat/lng
        $lat = $place['lat'] ?? null;
        $lng = $place['lon'] ?? null;

        // Si es un way, calcular centro
        if (!$lat && isset($place['center'])) {
            $lat = $place['center']['lat'];
            $lng = $place['center']['lon'];
        }

        // Extraer información de dirección
        $street = $tags['addr:street'] ?? '';
        $city = $tags['addr:city'] ?? '';
        $province = $tags['addr:province'] ?? $tags['addr:state'] ?? '';

        $address = implode(', ', array_filter([
            $tags['addr:housenumber'] ?? '',
            $street,
            $city
        ]));

        try {
            $stmt->execute([
                $place['id'],
                $place['type'],
                $tags['name'],
                $category['type'],
                $category['category'],
                $lat,
                $lng,
                $address ?: null,
                $street ?: null,
                $city ?: null,
                $province ?: null,
                $tags['phone'] ?? null,
                $tags['website'] ?? null,
                json_encode($tags),
                $category['priority']
            ]);

            $imported++;
        } catch (PDOException $e) {
            // Ignorar duplicados y otros errores
            error_log("Error importando lugar: " . $e->getMessage());
            continue;
        }
    }

    return $imported;
}
?>
