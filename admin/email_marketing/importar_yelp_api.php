<?php
/**
 * API para importar lugares desde Yelp Fusion API
 * 500 llamadas/día gratis - Incluye teléfonos!
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
function updateYelpProgress($percent, $message, $imported = 0, $total = 0) {
    $progress_file = __DIR__ . '/../../logs/yelp_progress.json';
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
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
        if ($check) {
            echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
            exit;
        }

        $sql_file = __DIR__ . '/../../mysql-pendientes/004-crear-tabla-yelp.sql';
        if (!file_exists($sql_file)) {
            echo json_encode(['success' => false, 'error' => 'Archivo SQL no encontrado']);
            exit;
        }

        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);

        echo json_encode([
            'success' => true,
            'message' => 'Tabla lugares_yelp creada exitosamente'
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

    $config_file = __DIR__ . '/../../config/yelp.php';
    $content = "<?php\n/**\n * Configuración de Yelp Fusion API\n * 500 llamadas/día gratis - Incluye teléfonos!\n */\nreturn [\n    'api_key' => " . var_export($api_key, true) . ",\n    'base_url' => 'https://api.yelp.com/v3'\n];\n";
    file_put_contents($config_file, $content);

    echo json_encode(['success' => true, 'message' => 'API Key guardada correctamente']);
    exit;
}

// ============================================
// IMPORTAR DESDE YELP
// ============================================
if ($action === 'importar') {
    updateYelpProgress(5, 'Iniciando importación desde Yelp...', 0, 0);
    set_time_limit(1800);

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

        // Cargar API Key
        $yelp_config_file = __DIR__ . '/../../config/yelp.php';
        if (!file_exists($yelp_config_file)) {
            echo json_encode(['success' => false, 'error' => 'Configura tu API Key de Yelp primero']);
            exit;
        }

        $yelp_config = require $yelp_config_file;
        $api_key = $yelp_config['api_key'] ?? '';

        if (empty($api_key)) {
            echo json_encode(['success' => false, 'error' => 'API Key no configurada. Obtén una en https://www.yelp.com/developers']);
            exit;
        }

        updateYelpProgress(10, 'Conectando con Yelp Fusion API...', 0, 0);

        // Obtener categorías y ciudades del request
        $categorias_json = $_POST['categorias'] ?? '[]';
        $ciudades_json = $_POST['ciudades'] ?? '[]';

        $categorias_seleccionadas = json_decode($categorias_json, true) ?: [];
        $ciudades_seleccionadas = json_decode($ciudades_json, true) ?: [];

        // Categorías de Yelp (aliases)
        $todas_categorias = [
            'restaurants' => 'Restaurantes',
            'cafes' => 'Cafés',
            'bars' => 'Bares',
            'hotels' => 'Hoteles',
            'shopping' => 'Tiendas',
            'localservices' => 'Servicios',
            'nightlife' => 'Vida Nocturna',
            'health' => 'Salud',
            'fitness' => 'Deportes',
            'beautysvc' => 'Belleza',
            'auto' => 'Automotriz',
            'professional' => 'Profesionales'
        ];

        // Ciudades de Costa Rica
        $todas_ciudades = [
            'San José, Costa Rica',
            'Alajuela, Costa Rica',
            'Cartago, Costa Rica',
            'Heredia, Costa Rica',
            'Liberia, Costa Rica',
            'Puntarenas, Costa Rica',
            'Limón, Costa Rica',
            'Escazú, Costa Rica',
            'Santa Ana, Costa Rica',
            'Jacó, Costa Rica',
            'Manuel Antonio, Costa Rica',
            'La Fortuna, Costa Rica',
            'Tamarindo, Costa Rica',
            'Puerto Viejo, Costa Rica',
            'Guanacaste, Costa Rica'
        ];

        if (empty($categorias_seleccionadas)) {
            $categorias_seleccionadas = array_keys($todas_categorias);
        }

        if (empty($ciudades_seleccionadas)) {
            $ciudades_seleccionadas = $todas_ciudades;
        }

        $categorias = [];
        foreach ($categorias_seleccionadas as $cat_id) {
            if (isset($todas_categorias[$cat_id])) {
                $categorias[$cat_id] = $todas_categorias[$cat_id];
            }
        }

        if (empty($categorias)) {
            $categorias = $todas_categorias;
        }

        $total_imported = 0;
        $total_updated = 0;
        $total_errors = 0;
        $total_busquedas = count($categorias) * count($ciudades_seleccionadas);
        $busqueda_actual = 0;

        // Preparar statement
        $stmt = $pdo->prepare("
            INSERT INTO lugares_yelp (
                yelp_id, nombre, categoria, telefono, email, website,
                direccion, ciudad, provincia, latitud, longitud,
                rating, review_count, price, is_closed, yelp_url, image_url, data_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                telefono = VALUES(telefono),
                website = VALUES(website),
                direccion = VALUES(direccion),
                ciudad = VALUES(ciudad),
                provincia = VALUES(provincia),
                rating = VALUES(rating),
                review_count = VALUES(review_count),
                price = VALUES(price),
                is_closed = VALUES(is_closed),
                data_json = VALUES(data_json),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($ciudades_seleccionadas as $ciudad) {
            foreach ($categorias as $cat_id => $cat_nombre) {
                $busqueda_actual++;
                $progreso = 15 + (($busqueda_actual / $total_busquedas) * 75);

                updateYelpProgress(
                    $progreso,
                    "Buscando $cat_nombre en $ciudad...",
                    $total_imported + $total_updated,
                    $total_busquedas * 50
                );

                // Hacer la búsqueda en Yelp Fusion API
                $url = "https://api.yelp.com/v3/businesses/search?" . http_build_query([
                    'location' => $ciudad,
                    'categories' => $cat_id,
                    'limit' => 50,
                    'sort_by' => 'rating'
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $api_key,
                    'Accept: application/json'
                ]);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($http_code !== 200) {
                    error_log("Yelp API Error: HTTP $http_code - $curl_error - URL: $url");
                    $total_errors++;

                    // Si es error de rate limit, esperar más
                    if ($http_code === 429) {
                        sleep(60);
                    }
                    continue;
                }

                $data = json_decode($response, true);

                if (!isset($data['businesses'])) {
                    $total_errors++;
                    continue;
                }

                $businesses = $data['businesses'];

                foreach ($businesses as $biz) {
                    try {
                        $location = $biz['location'] ?? [];
                        $coords = $biz['coordinates'] ?? [];

                        $direccion_parts = array_filter([
                            $location['address1'] ?? '',
                            $location['address2'] ?? '',
                            $location['address3'] ?? ''
                        ]);
                        $direccion = implode(', ', $direccion_parts);

                        // Yelp incluye teléfono!
                        $telefono = $biz['display_phone'] ?? ($biz['phone'] ?? '');

                        $stmt->execute([
                            $biz['id'] ?? null,
                            $biz['name'] ?? 'Sin nombre',
                            $cat_nombre,
                            $telefono,
                            '', // Yelp no proporciona email directamente
                            '', // Website requiere llamada adicional
                            $direccion,
                            $location['city'] ?? '',
                            $location['state'] ?? '',
                            $coords['latitude'] ?? null,
                            $coords['longitude'] ?? null,
                            $biz['rating'] ?? null,
                            $biz['review_count'] ?? 0,
                            $biz['price'] ?? '',
                            isset($biz['is_closed']) && $biz['is_closed'] ? 1 : 0,
                            $biz['url'] ?? '',
                            $biz['image_url'] ?? '',
                            json_encode($biz, JSON_UNESCAPED_UNICODE)
                        ]);

                        if ($stmt->rowCount() > 0) {
                            $total_imported++;
                        }
                    } catch (PDOException $e) {
                        error_log("Yelp DB Error: " . $e->getMessage());
                        $total_errors++;
                    }
                }

                // Rate limiting - Yelp tiene límite de 500/día, ser conservador
                usleep(500000); // 500ms entre requests
            }
        }

        updateYelpProgress(95, 'Generando estadísticas finales...', $total_imported, $total_busquedas * 50);

        // Estadísticas finales
        $total_db = $pdo->query("SELECT COUNT(*) FROM lugares_yelp")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE website IS NOT NULL AND website != ''")->fetchColumn();

        updateYelpProgress(100, '¡Importación completada!', $total_imported, $total_db);

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

// ============================================
// OBTENER ESTADÍSTICAS
// ============================================
if ($action === 'estadisticas') {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'Tabla no existe']);
            exit;
        }

        $total = $pdo->query("SELECT COUNT(*) FROM lugares_yelp")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE website IS NOT NULL AND website != ''")->fetchColumn();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'with_email' => $with_email,
                'with_phone' => $with_phone,
                'with_website' => $with_website,
                'email_percent' => $total > 0 ? round($with_email/$total*100, 1) : 0,
                'phone_percent' => $total > 0 ? round($with_phone/$total*100, 1) : 0,
                'website_percent' => $total > 0 ? round($with_website/$total*100, 1) : 0
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
