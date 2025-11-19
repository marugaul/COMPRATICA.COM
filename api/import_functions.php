<?php
/**
 * Funciones compartidas para importación OSM
 */

function getImportCategories() {
    return [
        // ALOJAMIENTO Y TURISMO
        [
            'name' => 'Hoteles y Alojamientos',
            'query' => '["tourism"~"hotel|hostel|guest_house|motel|apartment|chalet|camp_site"]',
            'type' => 'hotel',
            'category' => 'accommodation',
            'priority' => 8
        ],
        [
            'name' => 'Atracciones Turísticas',
            'query' => '["tourism"~"attraction|museum|gallery|viewpoint|zoo|aquarium|theme_park|artwork"]',
            'type' => 'attraction',
            'category' => 'tourism',
            'priority' => 7
        ],

        // COMIDA Y BEBIDA
        [
            'name' => 'Restaurantes y Cafés',
            'query' => '["amenity"~"restaurant|cafe|fast_food|food_court|ice_cream|bistro"]',
            'type' => 'restaurant',
            'category' => 'food',
            'priority' => 7
        ],
        [
            'name' => 'Bares y Vida Nocturna',
            'query' => '["amenity"~"bar|pub|nightclub|biergarten"]',
            'type' => 'bar',
            'category' => 'nightlife',
            'priority' => 6
        ],

        // COMPRAS
        [
            'name' => 'Supermercados',
            'query' => '["shop"~"supermarket|convenience|department_store|mall"]',
            'type' => 'supermarket',
            'category' => 'shopping',
            'priority' => 7
        ],
        [
            'name' => 'Tiendas Especializadas',
            'query' => '["shop"~"clothes|shoes|electronics|furniture|bakery|butcher|florist|jewelry|books|sports"]',
            'type' => 'shop',
            'category' => 'shopping',
            'priority' => 5
        ],

        // SERVICIOS FINANCIEROS
        [
            'name' => 'Bancos y Cajeros',
            'query' => '["amenity"~"bank|atm"]',
            'type' => 'bank',
            'category' => 'finance',
            'priority' => 7
        ],

        // TRANSPORTE
        [
            'name' => 'Gasolineras',
            'query' => '["amenity"="fuel"]',
            'type' => 'gas_station',
            'category' => 'transport',
            'priority' => 7
        ],
        [
            'name' => 'Transporte Público',
            'query' => '["amenity"~"bus_station|taxi"]',
            'type' => 'public_transport',
            'category' => 'transport',
            'priority' => 7
        ],
        [
            'name' => 'Aeropuertos y Terminales',
            'query' => '["aeroway"~"aerodrome|terminal"]',
            'type' => 'airport',
            'category' => 'transport',
            'priority' => 8
        ],
        [
            'name' => 'Estacionamientos',
            'query' => '["amenity"="parking"]',
            'type' => 'parking',
            'category' => 'transport',
            'priority' => 5
        ],

        // SALUD
        [
            'name' => 'Hospitales y Clínicas',
            'query' => '["amenity"~"hospital|clinic|doctors|dentist"]',
            'type' => 'hospital',
            'category' => 'health',
            'priority' => 8
        ],
        [
            'name' => 'Farmacias',
            'query' => '["amenity"="pharmacy"]',
            'type' => 'pharmacy',
            'category' => 'health',
            'priority' => 7
        ],

        // EDUCACIÓN
        [
            'name' => 'Escuelas y Colegios',
            'query' => '["amenity"~"school|college|kindergarten"]',
            'type' => 'school',
            'category' => 'education',
            'priority' => 6
        ],
        [
            'name' => 'Universidades',
            'query' => '["amenity"="university"]',
            'type' => 'university',
            'category' => 'education',
            'priority' => 7
        ],

        // GOBIERNO Y SERVICIOS PÚBLICOS
        [
            'name' => 'Oficinas Gubernamentales',
            'query' => '["amenity"~"townhall|courthouse|embassy|police|fire_station|post_office"]',
            'type' => 'government',
            'category' => 'public_service',
            'priority' => 7
        ],

        // CULTURA Y ENTRETENIMIENTO
        [
            'name' => 'Cines y Teatros',
            'query' => '["amenity"~"cinema|theatre|arts_centre"]',
            'type' => 'entertainment',
            'category' => 'culture',
            'priority' => 6
        ],
        [
            'name' => 'Bibliotecas',
            'query' => '["amenity"="library"]',
            'type' => 'library',
            'category' => 'culture',
            'priority' => 6
        ],

        // DEPORTES Y RECREACIÓN
        [
            'name' => 'Instalaciones Deportivas',
            'query' => '["leisure"~"sports_centre|stadium|swimming_pool|fitness_centre|pitch"]',
            'type' => 'sports',
            'category' => 'recreation',
            'priority' => 6
        ],
        [
            'name' => 'Parques y Áreas Verdes',
            'query' => '["leisure"~"park|garden|playground|nature_reserve"]',
            'type' => 'park',
            'category' => 'recreation',
            'priority' => 6
        ],
        [
            'name' => 'Playas',
            'query' => '["natural"="beach"]',
            'type' => 'beach',
            'category' => 'recreation',
            'priority' => 8
        ],

        // RELIGIÓN
        [
            'name' => 'Iglesias y Lugares de Culto',
            'query' => '["amenity"="place_of_worship"]',
            'type' => 'worship',
            'category' => 'religion',
            'priority' => 6
        ],

        // SERVICIOS PERSONALES
        [
            'name' => 'Servicios Personales',
            'query' => '["shop"~"hairdresser|beauty|laundry"]',
            'type' => 'personal_service',
            'category' => 'services',
            'priority' => 5
        ],

        // LUGARES (SIEMPRE AL FINAL - MÁS IMPORTANTE)
        [
            'name' => 'Ciudades y Poblaciones',
            'query' => '["place"~"city|town|village|hamlet|suburb|neighbourhood"]',
            'type' => 'city',
            'category' => 'places',
            'priority' => 9
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

    // Usar cURL en lugar de file_get_contents (cPanel tiene allow_url_fopen deshabilitado)
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'data=' . urlencode($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'CompraTica/1.0 (shuttle service)',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("Error fetching from Overpass API: HTTP $httpCode, Error: $error");
        return false;
    }

    $data = json_decode($response, true);

    if (!isset($data['elements'])) {
        error_log("Invalid response from Overpass API: " . substr($response, 0, 200));
        return false;
    }

    return $data['elements'];
}

function importPlaces($pdo, $places, $category) {
    $result = importPlacesWithDebug($pdo, $places, $category);
    return $result['imported'];
}

function importPlacesWithDebug($pdo, $places, $category) {
    $imported = 0;
    $noName = 0;
    $noCoords = 0;
    $errors = 0;

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
        // Filtrar lugares sin tags
        if (!isset($place['tags']) || empty($place['tags'])) {
            continue;
        }

        // Filtrar lugares sin nombre
        if (empty($place['tags']['name'])) {
            $noName++;
            continue;
        }

        $tags = $place['tags'];

        // Determinar lat/lng
        $lat = $place['lat'] ?? null;
        $lng = $place['lon'] ?? null;

        // Si es un way, calcular centro
        if (!$lat && isset($place['center'])) {
            $lat = $place['center']['lat'];
            $lng = $place['center']['lon'];
        }

        // Saltar si no tiene coordenadas
        if (!$lat || !$lng) {
            $noCoords++;
            continue;
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
            $errors++;
            error_log("Error importing place: " . $e->getMessage());
            continue;
        }
    }

    return [
        'imported' => $imported,
        'debug' => [
            'no_name' => $noName,
            'no_coords' => $noCoords,
            'errors' => $errors
        ]
    ];
}
?>
