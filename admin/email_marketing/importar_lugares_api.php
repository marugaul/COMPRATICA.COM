<?php
/**
 * API para importar lugares comerciales
 * Maneja las peticiones AJAX de importación
 */

// Cargar configuración
require_once __DIR__ . '/../../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

// Configuración de BD
$config = require __DIR__ . '/../../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';

// ============================================
// CREAR TABLA
// ============================================
if ($action === 'crear_tabla') {
    try {
        // Verificar si ya existe
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
        if ($check) {
            echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
            exit;
        }

        // Crear tabla
        $sql = "CREATE TABLE lugares_comerciales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            tipo VARCHAR(100),
            categoria VARCHAR(100),
            subtipo VARCHAR(100),
            descripcion TEXT,
            direccion VARCHAR(500),
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            codigo_postal VARCHAR(20),
            telefono VARCHAR(50),
            email VARCHAR(255),
            website VARCHAR(500),
            facebook VARCHAR(255),
            instagram VARCHAR(255),
            horario TEXT,
            latitud DECIMAL(10, 8),
            longitud DECIMAL(11, 8),
            osm_id BIGINT,
            osm_type VARCHAR(10),
            capacidad INT,
            estrellas TINYINT,
            wifi BOOLEAN DEFAULT FALSE,
            parking BOOLEAN DEFAULT FALSE,
            discapacidad_acceso BOOLEAN DEFAULT FALSE,
            tarjetas_credito BOOLEAN DEFAULT FALSE,
            delivery BOOLEAN DEFAULT FALSE,
            takeaway BOOLEAN DEFAULT FALSE,
            tags_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tipo (tipo),
            INDEX idx_categoria (categoria),
            INDEX idx_ciudad (ciudad),
            INDEX idx_provincia (provincia),
            INDEX idx_email (email),
            INDEX idx_osm_id (osm_id),
            FULLTEXT idx_nombre (nombre, descripcion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);

        echo json_encode([
            'success' => true,
            'message' => '✓ Tabla creada exitosamente con 28 campos'
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================
// IMPORTAR LUGARES
// ============================================
if ($action === 'importar') {
    set_time_limit(300); // 5 minutos

    try {
        // Verificar que la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

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

        $data = json_decode($response, true);
        if (!isset($data['elements'])) {
            throw new Exception("Respuesta inválida de Overpass API");
        }

        $elements = $data['elements'];
        $imported = 0;
        $updated = 0;
        $errors = 0;

        // Preparar statement
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

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];

            $nombre = $tags['name'] ?? ($tags['brand'] ?? 'Sin nombre');
            $tipo = $tags['amenity'] ?? $tags['tourism'] ?? $tags['shop'] ?? $tags['office'] ?? $tags['leisure'] ?? 'other';

            $categoria = '';
            if (isset($tags['amenity'])) $categoria = 'amenity';
            elseif (isset($tags['tourism'])) $categoria = 'tourism';
            elseif (isset($tags['shop'])) $categoria = 'shop';
            elseif (isset($tags['office'])) $categoria = 'office';
            elseif (isset($tags['leisure'])) $categoria = 'leisure';

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

                if ($stmt->rowCount() > 0) {
                    $imported++;
                } else {
                    $updated++;
                }

            } catch (PDOException $e) {
                $errors++;
            }
        }

        // Estadísticas finales
        $total = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();

        $top_categorias = $pdo->query("
            SELECT categoria, COUNT(*) as count
            FROM lugares_comerciales
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        $top_tipos = $pdo->query("
            SELECT tipo, COUNT(*) as count
            FROM lugares_comerciales
            WHERE tipo IS NOT NULL AND tipo != ''
            GROUP BY tipo
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => count($elements),
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'stats' => [
                'total' => $total,
                'with_email' => $with_email,
                'with_phone' => $with_phone,
                'email_percent' => $total > 0 ? round($with_email/$total*100, 1) : 0,
                'phone_percent' => $total > 0 ? round($with_phone/$total*100, 1) : 0,
                'top_categorias' => $top_categorias,
                'top_tipos' => $top_tipos
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// Acción no reconocida
echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
