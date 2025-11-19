<?php
/**
 * Funciones compartidas para importación OSM
 */

function getImportCategories() {
    return [
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
}

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
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                       "User-Agent: CompraTica/1.0 (shuttle service)\r\n",
            'content' => 'data=' . urlencode($query),
            'timeout' => 120
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("Error fetching from Overpass API");
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['elements'])) {
        error_log("Invalid response from Overpass API");
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
            continue;
        }
    }

    return $imported;
}
?>
