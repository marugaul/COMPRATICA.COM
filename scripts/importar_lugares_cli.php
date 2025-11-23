#!/usr/bin/env php
<?php
/**
 * Importador CLI de Lugares Comerciales desde OpenStreetMap
 * Ejecutar: php scripts/importar_lugares_cli.php
 * O configurar como cron job
 */

// Configuración
$config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
];

$log_file = __DIR__ . '/../logs/importar_lugares.log';

// Función para logging
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

set_time_limit(300); // 5 minutos

try {
    log_message("=== Inicio de importación de lugares comerciales ===");

    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    log_message("✓ Conexión a base de datos exitosa");

    // Verificar que la tabla existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
    if (!$check) {
        log_message("✗ ERROR: La tabla lugares_comerciales no existe");
        log_message("→ Ejecuta primero: php scripts/cron_setup_lugares.php");
        exit(1);
    }

    log_message("✓ Tabla lugares_comerciales encontrada");

    // Limpiar tabla antes de importar (opcional - comentar si quieres mantener datos existentes)
    // $pdo->exec("TRUNCATE TABLE lugares_comerciales");
    // log_message("→ Tabla limpiada");

    log_message("→ Descargando datos desde OpenStreetMap (esto puede tomar 2-3 minutos)...");

    // Query de Overpass API para Costa Rica
    $overpass_query = '[out:json][timeout:180];
area["name"="Costa Rica"]["type"="boundary"]->.a;
(
  node["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);
  way["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);
  node["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);
  way["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);
  node["shop"](area.a);
  way["shop"](area.a);
  node["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);
  way["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);
  node["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  way["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  node["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);
  way["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);
  node["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);
  way["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);
  node["office"](area.a);
  way["office"](area.a);
  node["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);
  way["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);
  node["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  way["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  node["amenity"~"spa"](area.a);
  way["amenity"~"spa"](area.a);
);
out center;';

    // Hacer request a Overpass API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://overpass-api.de/api/interpreter");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode($overpass_query));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Error en Overpass API: HTTP $http_code - $curl_error");
    }

    log_message("✓ Descarga completada desde OpenStreetMap");

    $data = json_decode($response, true);
    if (!isset($data['elements'])) {
        throw new Exception("Respuesta inválida de Overpass API");
    }

    $elements = $data['elements'];
    log_message("→ Total de lugares encontrados: " . count($elements));
    log_message("→ Importando a base de datos...");

    $imported = 0;
    $errors = 0;
    $skipped = 0;

    $stmt = $pdo->prepare("
        INSERT INTO lugares_comerciales (
            nombre, tipo, categoria, subtipo, descripcion, direccion, ciudad, provincia,
            codigo_postal, telefono, email, website, facebook, instagram, horario,
            latitud, longitud, osm_id, osm_type, capacidad, estrellas, wifi, parking,
            discapacidad_acceso, tarjetas_credito, delivery, takeaway, tags_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            telefono = VALUES(telefono),
            email = VALUES(email),
            updated_at = CURRENT_TIMESTAMP
    ");

    foreach ($elements as $index => $element) {
        $tags = $element['tags'] ?? [];

        // Nombre del lugar
        $nombre = $tags['name'] ?? ($tags['brand'] ?? 'Sin nombre');

        // Tipo y categoría
        $tipo = $tags['amenity'] ?? $tags['tourism'] ?? $tags['shop'] ?? $tags['office'] ?? $tags['leisure'] ?? 'other';

        $categoria = '';
        if (isset($tags['amenity'])) $categoria = 'amenity';
        elseif (isset($tags['tourism'])) $categoria = 'tourism';
        elseif (isset($tags['shop'])) $categoria = 'shop';
        elseif (isset($tags['office'])) $categoria = 'office';
        elseif (isset($tags['leisure'])) $categoria = 'leisure';

        // Coordenadas
        $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
        $lon = $element['lon'] ?? ($element['center']['lon'] ?? null);

        try {
            $stmt->execute([
                $nombre,
                $tipo,
                $categoria,
                $tags['cuisine'] ?? '',
                $tags['description'] ?? '',
                trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? '')),
                $tags['addr:city'] ?? '',
                $tags['addr:province'] ?? '',
                $tags['addr:postcode'] ?? '',
                $tags['phone'] ?? $tags['contact:phone'] ?? '',
                $tags['email'] ?? $tags['contact:email'] ?? '',
                $tags['website'] ?? $tags['url'] ?? '',
                $tags['contact:facebook'] ?? $tags['facebook'] ?? '',
                $tags['contact:instagram'] ?? $tags['instagram'] ?? '',
                $tags['opening_hours'] ?? '',
                $lat,
                $lon,
                $element['id'] ?? null,
                $element['type'] ?? 'node',
                is_numeric($tags['capacity'] ?? '') ? intval($tags['capacity']) : null,
                is_numeric($tags['stars'] ?? '') ? intval($tags['stars']) : null,
                in_array($tags['internet_access'] ?? '', ['yes', 'wlan', 'wifi']) ? 1 : 0,
                in_array($tags['parking'] ?? '', ['yes', 'surface']) ? 1 : 0,
                ($tags['wheelchair'] ?? '') === 'yes' ? 1 : 0,
                in_array($tags['payment:credit_cards'] ?? '', ['yes']) ? 1 : 0,
                ($tags['delivery'] ?? '') === 'yes' ? 1 : 0,
                ($tags['takeaway'] ?? '') === 'yes' ? 1 : 0,
                json_encode($tags, JSON_UNESCAPED_UNICODE)
            ]);

            $imported++;

            // Mostrar progreso cada 500 registros
            if ($imported % 500 === 0) {
                log_message("  → Progreso: $imported lugares importados...");
            }

        } catch (PDOException $e) {
            // Si es error de duplicado, contar como skipped
            if ($e->getCode() == 23000) {
                $skipped++;
            } else {
                $errors++;
                if ($errors <= 5) {
                    log_message("  ✗ Error en registro $index: " . $e->getMessage());
                }
            }
        }
    }

    log_message("✓✓✓ IMPORTACIÓN COMPLETADA ✓✓✓");
    log_message("  → Total encontrados: " . count($elements));
    log_message("  → Importados: $imported");
    log_message("  → Actualizados: $skipped");
    log_message("  → Errores: $errors");

    // Estadísticas finales
    log_message("");
    log_message("=== ESTADÍSTICAS FINALES ===");

    $total = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
    $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != '' AND email IS NOT NULL")->fetchColumn();
    $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE telefono != '' AND telefono IS NOT NULL")->fetchColumn();
    $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website != '' AND website IS NOT NULL")->fetchColumn();

    log_message("  → Total en BD: " . number_format($total));
    log_message("  → Con email: " . number_format($with_email) . " (" . round($with_email/$total*100, 1) . "%)");
    log_message("  → Con teléfono: " . number_format($with_phone) . " (" . round($with_phone/$total*100, 1) . "%)");
    log_message("  → Con website: " . number_format($with_website) . " (" . round($with_website/$total*100, 1) . "%)");

    // Top 10 categorías
    log_message("");
    log_message("=== TOP 10 CATEGORÍAS ===");
    $top_cats = $pdo->query("
        SELECT categoria, COUNT(*) as count
        FROM lugares_comerciales
        WHERE categoria IS NOT NULL AND categoria != ''
        GROUP BY categoria
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($top_cats as $cat) {
        log_message("  → " . str_pad($cat['categoria'], 15) . ": " . number_format($cat['count']));
    }

    // Top 10 tipos
    log_message("");
    log_message("=== TOP 10 TIPOS ===");
    $top_types = $pdo->query("
        SELECT tipo, COUNT(*) as count
        FROM lugares_comerciales
        WHERE tipo IS NOT NULL AND tipo != ''
        GROUP BY tipo
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($top_types as $type) {
        log_message("  → " . str_pad($type['tipo'], 20) . ": " . number_format($type['count']));
    }

    log_message("");
    log_message("=== IMPORTACIÓN FINALIZADA EXITOSAMENTE ===");
    log_message("Los datos están listos para usar en Email Marketing → Nueva Campaña");
    log_message("Log guardado en: $log_file");

    exit(0);

} catch (PDOException $e) {
    log_message("✗✗✗ ERROR DE BASE DE DATOS ✗✗✗");
    log_message("Error: " . $e->getMessage());
    log_message("Archivo: " . $e->getFile() . ":" . $e->getLine());
    exit(1);

} catch (Exception $e) {
    log_message("✗✗✗ ERROR GENERAL ✗✗✗");
    log_message("Error: " . $e->getMessage());
    exit(1);
}
