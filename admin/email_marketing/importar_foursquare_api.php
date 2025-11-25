<?php
/**
 * API para importar lugares desde Foursquare Places API v2
 * Usa OAuth con client_id y client_secret
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
// IMPORTAR DESDE FOURSQUARE API v2
// ============================================
if ($action === 'importar') {
    updateFoursquareProgress(5, 'Iniciando importación desde Foursquare...', 0, 0);
    set_time_limit(1800); // 30 minutos

    try {
        // Verificar que la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

        // Cargar credenciales de Foursquare
        $foursquare_config_file = __DIR__ . '/../../config/foursquare.php';
        if (!file_exists($foursquare_config_file)) {
            echo json_encode(['success' => false, 'error' => 'Configura las credenciales de Foursquare primero']);
            exit;
        }

        $foursquare_config = require $foursquare_config_file;
        $client_id = $foursquare_config['client_id'] ?? '';
        $client_secret = $foursquare_config['client_secret'] ?? '';
        $api_version = $foursquare_config['api_version'] ?? '20231101';

        if (empty($client_id) || empty($client_secret)) {
            echo json_encode(['success' => false, 'error' => 'Credenciales de Foursquare no configuradas']);
            exit;
        }

        updateFoursquareProgress(10, 'Conectando con Foursquare API v2...', 0, 0);

        // Obtener categorías y ciudades del request
        $categorias_json = $_POST['categorias'] ?? '[]';
        $ciudades_json = $_POST['ciudades'] ?? '[]';

        $categorias_seleccionadas = json_decode($categorias_json, true) ?: [];
        $ciudades_seleccionadas = json_decode($ciudades_json, true) ?: [];

        // Categorías de Foursquare v2 (IDs diferentes a v3)
        $todas_categorias = [
            '4d4b7105d754a06374d81259' => 'Restaurantes',
            '4bf58dd8d48988d1e0931735' => 'Cafés',
            '4bf58dd8d48988d116941735' => 'Bares',
            '4bf58dd8d48988d1fa931735' => 'Hoteles',
            '4d4b7105d754a06378d81259' => 'Tiendas',
            '4d4b7105d754a06375d81259' => 'Servicios',
            '4d4b7104d754a06370d81259' => 'Entretenimiento',
            '4d4b7105d754a06372d81259' => 'Salud',
            '4bf58dd8d48988d1e5941735' => 'Deportes'
        ];

        // Ciudades por defecto si no se especifican
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
            'Guanacaste, Costa Rica',
            'Jacó, Costa Rica',
            'Manuel Antonio, Costa Rica',
            'La Fortuna, Costa Rica',
            'Tamarindo, Costa Rica',
            'Puerto Viejo, Costa Rica'
        ];

        // Usar seleccionados o todos por defecto
        if (empty($categorias_seleccionadas)) {
            $categorias_seleccionadas = array_keys($todas_categorias);
        }

        if (empty($ciudades_seleccionadas)) {
            $ciudades_seleccionadas = $todas_ciudades;
        }

        // Construir mapa de categorías
        $categorias = [];
        foreach ($categorias_seleccionadas as $cat_id) {
            if (isset($todas_categorias[$cat_id])) {
                $categorias[$cat_id] = $todas_categorias[$cat_id];
            }
        }

        // Si no hay categorías válidas, usar todas
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
                direccion = VALUES(direccion),
                ciudad = VALUES(ciudad),
                provincia = VALUES(provincia),
                rating = VALUES(rating),
                data_json = VALUES(data_json),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($ciudades_seleccionadas as $ciudad) {
            foreach ($categorias as $cat_id => $cat_nombre) {
                $busqueda_actual++;
                $progreso = 15 + (($busqueda_actual / $total_busquedas) * 75);

                updateFoursquareProgress(
                    $progreso,
                    "Buscando $cat_nombre en $ciudad...",
                    $total_imported + $total_updated,
                    $total_busquedas * 50
                );

                // Hacer la búsqueda en Foursquare API v2
                $url = "https://api.foursquare.com/v2/venues/search?" . http_build_query([
                    'near' => $ciudad,
                    'categoryId' => $cat_id,
                    'limit' => 50,
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'v' => $api_version
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($http_code !== 200) {
                    error_log("Foursquare API Error: HTTP $http_code - $curl_error - URL: $url");
                    $total_errors++;
                    continue;
                }

                $data = json_decode($response, true);

                if (!isset($data['response']['venues'])) {
                    $total_errors++;
                    continue;
                }

                $venues = $data['response']['venues'];

                foreach ($venues as $venue) {
                    try {
                        // Extraer datos del venue
                        $location = $venue['location'] ?? [];
                        $contact = $venue['contact'] ?? [];

                        $place_ciudad = $location['city'] ?? '';
                        $place_provincia = $location['state'] ?? '';
                        $place_direccion = $location['formattedAddress'] ?? [];
                        $place_direccion_str = is_array($place_direccion) ? implode(', ', $place_direccion) : $place_direccion;

                        // Si no hay ciudad en los datos, usar la ciudad de búsqueda
                        if (empty($place_ciudad)) {
                            $parts = explode(',', $ciudad);
                            $place_ciudad = trim($parts[0] ?? '');
                        }

                        // Extraer teléfono y otros datos de contacto
                        $telefono = $contact['formattedPhone'] ?? ($contact['phone'] ?? '');

                        // Foursquare v2 no siempre tiene email directo, intentar extraer de otros campos
                        $email = '';
                        if (isset($venue['url'])) {
                            // Podríamos intentar extraer email del sitio web después
                        }

                        $stmt->execute([
                            $venue['id'] ?? null,
                            $venue['name'] ?? 'Sin nombre',
                            $cat_nombre,
                            $telefono,
                            $email,
                            $venue['url'] ?? '',
                            $place_direccion_str,
                            $place_ciudad,
                            $place_provincia,
                            $location['lat'] ?? null,
                            $location['lng'] ?? null,
                            $venue['rating'] ?? null,
                            isset($venue['verified']) && $venue['verified'] ? 1 : 0,
                            json_encode($venue, JSON_UNESCAPED_UNICODE)
                        ]);

                        if ($stmt->rowCount() > 0) {
                            $total_imported++;
                        }
                    } catch (PDOException $e) {
                        error_log("Foursquare DB Error: " . $e->getMessage());
                        $total_errors++;
                    }
                }

                // Rate limiting - esperar 300ms entre requests
                usleep(300000);
            }
        }

        updateFoursquareProgress(95, 'Generando estadísticas finales...', $total_imported, $total_busquedas * 50);

        // Estadísticas finales
        $total_db = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE website IS NOT NULL AND website != ''")->fetchColumn();

        updateFoursquareProgress(100, '¡Importación completada!', $total_imported, $total_db);

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
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'Tabla no existe']);
            exit;
        }

        $total = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE website IS NOT NULL AND website != ''")->fetchColumn();

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

// Acción no reconocida
echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
