<?php
/**
 * API para importar lugares desde Foursquare Places API
 * 50,000 llamadas gratis al mes
 */

// Cargar configuración
require_once __DIR__ . '/../../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Función para actualizar progreso
function updateFoursquareProgress($percent, $message, $imported = 0, $total = 0) {
    $progress_file = __DIR__ . '/../../logs/foursquare_progress.json';
    $progress_dir = dirname($progress_file);
    if (!is_dir($progress_dir)) {
        @mkdir($progress_dir, 0755, true);
    }

    $data = [
        'percent' => $percent,
        'message' => $message,
        'imported' => $imported,
        'total' => $total,
        'timestamp' => time()
    ];

    file_put_contents($progress_file, json_encode($data));
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
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
        if ($check) {
            echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
            exit;
        }

        // Leer el SQL del archivo
        $sql_file = __DIR__ . '/../../mysql-pendientes/003-crear-tabla-foursquare.sql';
        if (!file_exists($sql_file)) {
            echo json_encode(['success' => false, 'error' => 'Archivo SQL no encontrado']);
            exit;
        }

        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);

        echo json_encode([
            'success' => true,
            'message' => '✓ Tabla lugares_foursquare creada exitosamente'
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================
// GUARDAR API KEY
// ============================================
if ($action === 'guardar_api_key') {
    $api_key = $_POST['api_key'] ?? '';

    if (empty($api_key)) {
        echo json_encode(['success' => false, 'error' => 'API Key requerida']);
        exit;
    }

    // Guardar en archivo de configuración
    $config_file = __DIR__ . '/../../config/foursquare.php';
    $config_dir = dirname($config_file);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    $content = "<?php\nreturn [\n    'api_key' => " . var_export($api_key, true) . "\n];\n";
    file_put_contents($config_file, $content);

    echo json_encode(['success' => true, 'message' => 'API Key guardada']);
    exit;
}

// ============================================
// IMPORTAR DESDE FOURSQUARE
// ============================================
if ($action === 'importar') {
    updateFoursquareProgress(5, 'Iniciando importación desde Foursquare...', 0, 0);
    set_time_limit(600); // 10 minutos

    try {
        // Verificar que la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

        // Cargar API Key
        $foursquare_config_file = __DIR__ . '/../../config/foursquare.php';
        if (!file_exists($foursquare_config_file)) {
            echo json_encode(['success' => false, 'error' => 'Configura tu API Key de Foursquare primero']);
            exit;
        }

        $foursquare_config = require $foursquare_config_file;
        $api_key = $foursquare_config['api_key'] ?? '';

        if (empty($api_key)) {
            echo json_encode(['success' => false, 'error' => 'API Key no configurada']);
            exit;
        }

        updateFoursquareProgress(10, 'Conectando con Foursquare API...', 0, 0);

        // Categorías principales para buscar
        $categorias = [
            'Restaurantes' => '13065',
            'Cafés' => '13034,13035',
            'Bares' => '13003,13004',
            'Hoteles' => '19014',
            'Tiendas' => '17000',
            'Servicios' => '12000',
            'Entretenimiento' => '10000'
        ];

        $total_imported = 0;
        $total_updated = 0;
        $total_errors = 0;
        $total_estimado = count($categorias) * 50; // Estimado

        // Preparar statement
        $stmt = $pdo->prepare("
            INSERT INTO lugares_foursquare (
                foursquare_id, nombre, categoria, telefono, email, website,
                direccion, ciudad, provincia, latitud, longitud,
                rating, verificado, data_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                telefono = VALUES(telefono),
                email = VALUES(email),
                website = VALUES(website),
                updated_at = CURRENT_TIMESTAMP
        ");

        $current_progress = 0;
        foreach ($categorias as $cat_nombre => $cat_id) {
            updateFoursquareProgress(
                15 + ($current_progress / count($categorias) * 70),
                "Importando $cat_nombre...",
                $total_imported + $total_updated,
                $total_estimado
            );

            // Buscar en Costa Rica
            $url = "https://api.foursquare.com/v3/places/search?" . http_build_query([
                'near' => 'Costa Rica',
                'categories' => $cat_id,
                'limit' => 50
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: $api_key",
                "Accept: application/json"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                $total_errors++;
                continue;
            }

            $data = json_decode($response, true);
            $results = $data['results'] ?? [];

            foreach ($results as $place) {
                try {
                    $stmt->execute([
                        $place['fsq_id'] ?? null,
                        $place['name'] ?? 'Sin nombre',
                        $cat_nombre,
                        $place['tel'] ?? '',
                        $place['email'] ?? '',
                        $place['website'] ?? '',
                        $place['location']['formatted_address'] ?? '',
                        $place['location']['locality'] ?? '',
                        $place['location']['region'] ?? '',
                        $place['geocodes']['main']['latitude'] ?? null,
                        $place['geocodes']['main']['longitude'] ?? null,
                        $place['rating'] ?? null,
                        isset($place['verified']) ? 1 : 0,
                        json_encode($place, JSON_UNESCAPED_UNICODE)
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $total_imported++;
                    } else {
                        $total_updated++;
                    }
                } catch (PDOException $e) {
                    $total_errors++;
                }
            }

            $current_progress++;
            usleep(500000); // 0.5 segundos entre categorías (rate limiting)
        }

        updateFoursquareProgress(95, 'Generando estadísticas finales...', $total_imported + $total_updated, $total_estimado);

        // Estadísticas finales
        $total_db = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE website != ''")->fetchColumn();

        updateFoursquareProgress(100, '¡Importación completada!', $total_imported + $total_updated, $total_estimado);

        echo json_encode([
            'success' => true,
            'imported' => $total_imported,
            'updated' => $total_updated,
            'errors' => $total_errors,
            'stats' => [
                'total' => $total_db,
                'with_email' => $with_email,
                'with_phone' => $with_phone,
                'with_website' => $with_website,
                'email_percent' => $total_db > 0 ? round($with_email/$total_db*100, 1) : 0,
                'phone_percent' => $total_db > 0 ? round($with_phone/$total_db*100, 1) : 0,
                'website_percent' => $total_db > 0 ? round($with_website/$total_db*100, 1) : 0
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// Acción no reconocida
echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
