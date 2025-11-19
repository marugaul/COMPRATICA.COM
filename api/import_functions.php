<?php
/**
 * Funciones compartidas para importación OSM
 * VERSIÓN EXPANDIDA: 106 categorías para máxima cobertura
 */

function getImportCategories() {
    return [
        // ==================== ALOJAMIENTO Y TURISMO ====================
        ['name' => 'Hoteles', 'query' => '["tourism"~"hotel|motel"]', 'type' => 'hotel', 'category' => 'accommodation', 'priority' => 8],
        ['name' => 'Hostales y Pensiones', 'query' => '["tourism"~"hostel|guest_house|apartment"]', 'type' => 'hostel', 'category' => 'accommodation', 'priority' => 7],
        ['name' => 'Campamentos', 'query' => '["tourism"~"camp_site|caravan_site|wilderness_hut|alpine_hut"]', 'type' => 'camping', 'category' => 'accommodation', 'priority' => 6],
        ['name' => 'Atracciones Turísticas', 'query' => '["tourism"~"attraction|viewpoint"]', 'type' => 'attraction', 'category' => 'tourism', 'priority' => 7],
        ['name' => 'Museos y Galerías', 'query' => '["tourism"~"museum|gallery|artwork"]', 'type' => 'museum', 'category' => 'culture', 'priority' => 7],
        ['name' => 'Zoológicos y Acuarios', 'query' => '["tourism"~"zoo|aquarium|theme_park"]', 'type' => 'zoo', 'category' => 'tourism', 'priority' => 7],
        ['name' => 'Centros de Información', 'query' => '["tourism"="information"]', 'type' => 'info', 'category' => 'tourism', 'priority' => 6],

        // ==================== COMIDA Y BEBIDA ====================
        ['name' => 'Restaurantes', 'query' => '["amenity"~"restaurant|bistro"]', 'type' => 'restaurant', 'category' => 'food', 'priority' => 7],
        ['name' => 'Cafeterías', 'query' => '["amenity"="cafe"]', 'type' => 'cafe', 'category' => 'food', 'priority' => 7],
        ['name' => 'Comida Rápida', 'query' => '["amenity"~"fast_food|food_court"]', 'type' => 'fast_food', 'category' => 'food', 'priority' => 6],
        ['name' => 'Heladerías', 'query' => '["amenity"="ice_cream"]', 'type' => 'ice_cream', 'category' => 'food', 'priority' => 5],
        ['name' => 'Bares', 'query' => '["amenity"~"bar|pub"]', 'type' => 'bar', 'category' => 'nightlife', 'priority' => 6],
        ['name' => 'Discotecas', 'query' => '["amenity"="nightclub"]', 'type' => 'nightclub', 'category' => 'nightlife', 'priority' => 5],

        // ==================== COMPRAS ====================
        ['name' => 'Supermercados', 'query' => '["shop"~"supermarket|mall"]', 'type' => 'supermarket', 'category' => 'shopping', 'priority' => 8],
        ['name' => 'Tiendas de Conveniencia', 'query' => '["shop"~"convenience|kiosk|general"]', 'type' => 'convenience', 'category' => 'shopping', 'priority' => 7],
        ['name' => 'Centros Comerciales', 'query' => '["shop"="department_store"]', 'type' => 'mall', 'category' => 'shopping', 'priority' => 7],
        ['name' => 'Ropa y Calzado', 'query' => '["shop"~"clothes|shoes|fashion|boutique|tailor"]', 'type' => 'clothing', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Electrónica', 'query' => '["shop"~"electronics|computer|mobile_phone|hifi"]', 'type' => 'electronics', 'category' => 'shopping', 'priority' => 6],
        ['name' => 'Muebles y Hogar', 'query' => '["shop"~"furniture|interior_decoration|houseware|kitchen"]', 'type' => 'furniture', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Ferreterías', 'query' => '["shop"~"hardware|doityourself|trade"]', 'type' => 'hardware', 'category' => 'shopping', 'priority' => 6],
        ['name' => 'Panaderías', 'query' => '["shop"~"bakery|pastry|confectionery"]', 'type' => 'bakery', 'category' => 'food_shop', 'priority' => 6],
        ['name' => 'Carnicerías', 'query' => '["shop"~"butcher|seafood|deli"]', 'type' => 'butcher', 'category' => 'food_shop', 'priority' => 5],
        ['name' => 'Fruterías', 'query' => '["shop"~"greengrocer|farm"]', 'type' => 'greengrocer', 'category' => 'food_shop', 'priority' => 5],
        ['name' => 'Licores', 'query' => '["shop"~"alcohol|wine|beverages"]', 'type' => 'liquor', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Farmacias (Tiendas)', 'query' => '["shop"="chemist"]', 'type' => 'chemist', 'category' => 'shopping', 'priority' => 6],
        ['name' => 'Ópticas', 'query' => '["shop"="optician"]', 'type' => 'optician', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Joyerías', 'query' => '["shop"~"jewelry|watches"]', 'type' => 'jewelry', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Librerías', 'query' => '["shop"~"books|stationery|newsagent"]', 'type' => 'bookstore', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Tiendas Deportivas', 'query' => '["shop"~"sports|bicycle|fishing|outdoor"]', 'type' => 'sports_shop', 'category' => 'shopping', 'priority' => 5],
        ['name' => 'Jugueterías', 'query' => '["shop"="toys"]', 'type' => 'toys', 'category' => 'shopping', 'priority' => 4],
        ['name' => 'Tiendas de Mascotas', 'query' => '["shop"~"pet|pet_grooming"]', 'type' => 'pet_shop', 'category' => 'shopping', 'priority' => 4],
        ['name' => 'Floristerías', 'query' => '["shop"~"florist|garden_centre"]', 'type' => 'florist', 'category' => 'shopping', 'priority' => 4],
        ['name' => 'Mercados', 'query' => '["amenity"="marketplace"]', 'type' => 'market', 'category' => 'shopping', 'priority' => 6],

        // ==================== SERVICIOS FINANCIEROS ====================
        ['name' => 'Bancos', 'query' => '["amenity"="bank"]', 'type' => 'bank', 'category' => 'finance', 'priority' => 8],
        ['name' => 'Cajeros Automáticos', 'query' => '["amenity"="atm"]', 'type' => 'atm', 'category' => 'finance', 'priority' => 7],
        ['name' => 'Oficinas de Cambio', 'query' => '["amenity"="bureau_de_change"]', 'type' => 'exchange', 'category' => 'finance', 'priority' => 5],

        // ==================== TRANSPORTE ====================
        ['name' => 'Gasolineras', 'query' => '["amenity"="fuel"]', 'type' => 'gas_station', 'category' => 'transport', 'priority' => 8],
        ['name' => 'Estaciones de Bus', 'query' => '["amenity"="bus_station"]', 'type' => 'bus_station', 'category' => 'transport', 'priority' => 7],
        ['name' => 'Paradas de Bus', 'query' => '["highway"="bus_stop"]', 'type' => 'bus_stop', 'category' => 'transport', 'priority' => 5],
        ['name' => 'Taxis', 'query' => '["amenity"="taxi"]', 'type' => 'taxi', 'category' => 'transport', 'priority' => 6],
        ['name' => 'Aeropuertos', 'query' => '["aeroway"~"aerodrome|terminal"]', 'type' => 'airport', 'category' => 'transport', 'priority' => 9],
        ['name' => 'Helipuertos', 'query' => '["aeroway"="helipad"]', 'type' => 'heliport', 'category' => 'transport', 'priority' => 5],
        ['name' => 'Estacionamientos', 'query' => '["amenity"="parking"]', 'type' => 'parking', 'category' => 'transport', 'priority' => 5],
        ['name' => 'Talleres Mecánicos', 'query' => '["shop"~"car_repair|car"]', 'type' => 'car_repair', 'category' => 'automotive', 'priority' => 6],
        ['name' => 'Lavado de Autos', 'query' => '["amenity"="car_wash"]', 'type' => 'car_wash', 'category' => 'automotive', 'priority' => 5],
        ['name' => 'Alquiler de Autos', 'query' => '["amenity"="car_rental"]', 'type' => 'car_rental', 'category' => 'transport', 'priority' => 6],

        // ==================== SALUD ====================
        ['name' => 'Hospitales', 'query' => '["amenity"="hospital"]', 'type' => 'hospital', 'category' => 'health', 'priority' => 9],
        ['name' => 'Clínicas', 'query' => '["amenity"~"clinic|doctors"]', 'type' => 'clinic', 'category' => 'health', 'priority' => 8],
        ['name' => 'Farmacias', 'query' => '["amenity"="pharmacy"]', 'type' => 'pharmacy', 'category' => 'health', 'priority' => 8],
        ['name' => 'Dentistas', 'query' => '["amenity"="dentist"]', 'type' => 'dentist', 'category' => 'health', 'priority' => 7],
        ['name' => 'Veterinarias', 'query' => '["amenity"="veterinary"]', 'type' => 'veterinary', 'category' => 'health', 'priority' => 6],
        ['name' => 'Laboratorios', 'query' => '["healthcare"~"laboratory|blood_donation"]', 'type' => 'laboratory', 'category' => 'health', 'priority' => 6],

        // ==================== EDUCACIÓN ====================
        ['name' => 'Universidades', 'query' => '["amenity"="university"]', 'type' => 'university', 'category' => 'education', 'priority' => 8],
        ['name' => 'Colegios', 'query' => '["amenity"="college"]', 'type' => 'college', 'category' => 'education', 'priority' => 7],
        ['name' => 'Escuelas', 'query' => '["amenity"="school"]', 'type' => 'school', 'category' => 'education', 'priority' => 7],
        ['name' => 'Guarderías', 'query' => '["amenity"="kindergarten"]', 'type' => 'kindergarten', 'category' => 'education', 'priority' => 6],
        ['name' => 'Academias', 'query' => '["amenity"~"driving_school|language_school|music_school"]', 'type' => 'academy', 'category' => 'education', 'priority' => 5],

        // ==================== GOBIERNO Y SERVICIOS PÚBLICOS ====================
        ['name' => 'Municipalidades', 'query' => '["amenity"="townhall"]', 'type' => 'townhall', 'category' => 'government', 'priority' => 8],
        ['name' => 'Tribunales', 'query' => '["amenity"="courthouse"]', 'type' => 'courthouse', 'category' => 'government', 'priority' => 7],
        ['name' => 'Embajadas', 'query' => '["amenity"="embassy"]', 'type' => 'embassy', 'category' => 'government', 'priority' => 7],
        ['name' => 'Policía', 'query' => '["amenity"="police"]', 'type' => 'police', 'category' => 'emergency', 'priority' => 8],
        ['name' => 'Bomberos', 'query' => '["amenity"="fire_station"]', 'type' => 'fire_station', 'category' => 'emergency', 'priority' => 8],
        ['name' => 'Correos', 'query' => '["amenity"="post_office"]', 'type' => 'post_office', 'category' => 'public_service', 'priority' => 7],
        ['name' => 'Oficinas Públicas', 'query' => '["office"="government"]', 'type' => 'government_office', 'category' => 'government', 'priority' => 6],

        // ==================== CULTURA Y ENTRETENIMIENTO ====================
        ['name' => 'Cines', 'query' => '["amenity"="cinema"]', 'type' => 'cinema', 'category' => 'entertainment', 'priority' => 6],
        ['name' => 'Teatros', 'query' => '["amenity"="theatre"]', 'type' => 'theatre', 'category' => 'culture', 'priority' => 6],
        ['name' => 'Centros Culturales', 'query' => '["amenity"="arts_centre"]', 'type' => 'arts_centre', 'category' => 'culture', 'priority' => 6],
        ['name' => 'Bibliotecas', 'query' => '["amenity"="library"]', 'type' => 'library', 'category' => 'culture', 'priority' => 7],
        ['name' => 'Casinos', 'query' => '["amenity"~"casino|gambling"]', 'type' => 'casino', 'category' => 'entertainment', 'priority' => 5],

        // ==================== DEPORTES Y RECREACIÓN ====================
        ['name' => 'Centros Deportivos', 'query' => '["leisure"="sports_centre"]', 'type' => 'sports_centre', 'category' => 'recreation', 'priority' => 7],
        ['name' => 'Estadios', 'query' => '["leisure"="stadium"]', 'type' => 'stadium', 'category' => 'recreation', 'priority' => 7],
        ['name' => 'Piscinas', 'query' => '["leisure"="swimming_pool"]', 'type' => 'swimming_pool', 'category' => 'recreation', 'priority' => 6],
        ['name' => 'Gimnasios', 'query' => '["leisure"="fitness_centre"]', 'type' => 'gym', 'category' => 'recreation', 'priority' => 6],
        ['name' => 'Canchas Deportivas', 'query' => '["leisure"="pitch"]', 'type' => 'pitch', 'category' => 'recreation', 'priority' => 5],
        ['name' => 'Campos de Golf', 'query' => '["leisure"="golf_course"]', 'type' => 'golf', 'category' => 'recreation', 'priority' => 6],
        ['name' => 'Parques', 'query' => '["leisure"~"park|dog_park"]', 'type' => 'park', 'category' => 'recreation', 'priority' => 7],
        ['name' => 'Jardines', 'query' => '["leisure"="garden"]', 'type' => 'garden', 'category' => 'recreation', 'priority' => 6],
        ['name' => 'Áreas de Juegos', 'query' => '["leisure"="playground"]', 'type' => 'playground', 'category' => 'recreation', 'priority' => 6],
        ['name' => 'Reservas Naturales', 'query' => '["leisure"="nature_reserve"]', 'type' => 'nature_reserve', 'category' => 'nature', 'priority' => 7],
        ['name' => 'Playas', 'query' => '["natural"="beach"]', 'type' => 'beach', 'category' => 'nature', 'priority' => 8],
        ['name' => 'Cascadas', 'query' => '["waterway"="waterfall"]', 'type' => 'waterfall', 'category' => 'nature', 'priority' => 7],
        ['name' => 'Volcanes', 'query' => '["natural"="volcano"]', 'type' => 'volcano', 'category' => 'nature', 'priority' => 8],
        ['name' => 'Cuevas', 'query' => '["natural"="cave_entrance"]', 'type' => 'cave', 'category' => 'nature', 'priority' => 6],

        // ==================== RELIGIÓN ====================
        ['name' => 'Iglesias Católicas', 'query' => '["amenity"="place_of_worship"]["religion"="christian"]', 'type' => 'church', 'category' => 'religion', 'priority' => 6],
        ['name' => 'Lugares de Culto General', 'query' => '["amenity"="place_of_worship"]', 'type' => 'worship', 'category' => 'religion', 'priority' => 6],

        // ==================== SERVICIOS PERSONALES ====================
        ['name' => 'Peluquerías', 'query' => '["shop"="hairdresser"]', 'type' => 'hairdresser', 'category' => 'personal_service', 'priority' => 5],
        ['name' => 'Salones de Belleza', 'query' => '["shop"="beauty"]', 'type' => 'beauty', 'category' => 'personal_service', 'priority' => 5],
        ['name' => 'Lavanderías', 'query' => '["shop"="laundry"]', 'type' => 'laundry', 'category' => 'personal_service', 'priority' => 5],
        ['name' => 'Spas', 'query' => '["leisure"~"spa|sauna"]', 'type' => 'spa', 'category' => 'personal_service', 'priority' => 5],
        ['name' => 'Funerarias', 'query' => '["shop"="funeral_directors"]', 'type' => 'funeral', 'category' => 'services', 'priority' => 5],

        // ==================== OFICINAS ====================
        ['name' => 'Oficinas Corporativas', 'query' => '["office"~"company|office"]', 'type' => 'office', 'category' => 'business', 'priority' => 4],
        ['name' => 'Abogados', 'query' => '["office"="lawyer"]', 'type' => 'lawyer', 'category' => 'professional', 'priority' => 5],
        ['name' => 'Seguros', 'query' => '["office"="insurance"]', 'type' => 'insurance', 'category' => 'professional', 'priority' => 5],
        ['name' => 'Inmobiliarias', 'query' => '["office"="estate_agent"]', 'type' => 'real_estate', 'category' => 'professional', 'priority' => 5],

        // ==================== EMERGENCIAS ====================
        ['name' => 'Cruz Roja', 'query' => '["amenity"="emergency_service"]', 'type' => 'emergency', 'category' => 'emergency', 'priority' => 7],

        // ==================== LUGARES (SIEMPRE AL FINAL - MÁS IMPORTANTE) ====================
        ['name' => 'Ciudades', 'query' => '["place"="city"]', 'type' => 'city', 'category' => 'places', 'priority' => 10],
        ['name' => 'Pueblos', 'query' => '["place"="town"]', 'type' => 'town', 'category' => 'places', 'priority' => 9],
        ['name' => 'Aldeas', 'query' => '["place"="village"]', 'type' => 'village', 'category' => 'places', 'priority' => 8],
        ['name' => 'Caseríos', 'query' => '["place"="hamlet"]', 'type' => 'hamlet', 'category' => 'places', 'priority' => 7],
        ['name' => 'Barrios', 'query' => '["place"~"suburb|neighbourhood|quarter"]', 'type' => 'neighbourhood', 'category' => 'places', 'priority' => 6]
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

function importPlacesWithDebug($pdo, $places, $category) {
    $imported = 0;
    $noName = 0;
    $noCoords = 0;
    $errors = 0;

    foreach ($places as $place) {
        if (!isset($place['tags']) || empty($place['tags'])) {
            continue;
        }

        // Nombre
        $name = $place['tags']['name'] ?? null;
        if (empty($name)) {
            $noName++;
            continue;
        }

        // Coordenadas
        $lat = $place['lat'] ?? ($place['center']['lat'] ?? null);
        $lng = $place['lon'] ?? ($place['center']['lon'] ?? null);

        if (!$lat || !$lng) {
            $noCoords++;
            continue;
        }

        // Ciudad/localidad
        $city = $place['tags']['addr:city'] ??
                $place['tags']['addr:town'] ??
                $place['tags']['addr:village'] ??
                null;

        // Dirección
        $address = $place['tags']['addr:street'] ?? null;
        if ($address && isset($place['tags']['addr:housenumber'])) {
            $address = $place['tags']['addr:housenumber'] . ' ' . $address;
        }

        // Teléfono
        $phone = $place['tags']['phone'] ?? $place['tags']['contact:phone'] ?? null;

        // Website
        $website = $place['tags']['website'] ?? $place['tags']['contact:website'] ?? null;

        // Descripción
        $description = $place['tags']['description'] ?? null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO places_cr
                (name, type, category, lat, lng, city, address, phone, website, priority, osm_id, osm_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    type = VALUES(type),
                    category = VALUES(category),
                    lat = VALUES(lat),
                    lng = VALUES(lng),
                    city = VALUES(city),
                    address = VALUES(address),
                    phone = VALUES(phone),
                    website = VALUES(website),
                    priority = VALUES(priority)
            ");

            $stmt->execute([
                $name,
                $category['type'],
                $category['category'],
                $lat,
                $lng,
                $city,
                $address,
                $phone,
                $website,
                $category['priority'],
                $place['id'],
                $place['type']
            ]);

            $imported++;
        } catch (PDOException $e) {
            error_log("Error inserting place: " . $e->getMessage());
            $errors++;
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
