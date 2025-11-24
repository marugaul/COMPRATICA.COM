<?php
/**
 * API Importar Lugares - VERSION CON LOGGING COMPLETO
 */

// LOGGING INMEDIATO - Primera cosa que hace el script
$log_file = __DIR__ . '/../../logs/importar_lugares_api.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
file_put_contents($log_file, "\n\n========== NUEVO REQUEST " . date('Y-m-d H:i:s') . " ==========\n", FILE_APPEND);

function logit($msg) {
    global $log_file;
    $line = "[" . date('H:i:s') . "] $msg\n";
    file_put_contents($log_file, $line, FILE_APPEND);
}

logit("Script iniciado");

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
logit("Error reporting configurado");

// Header JSON
header('Content-Type: application/json');
logit("Header JSON enviado");

// Intentar cargar config
logit("Intentando cargar config...");
try {
    $config_path = __DIR__ . '/../../includes/config.php';
    logit("Config path: $config_path");

    if (!file_exists($config_path)) {
        logit("ERROR: Config file no existe!");
        echo json_encode(['success' => false, 'error' => 'Config file no existe']);
        exit;
    }

    require_once $config_path;
    logit("Config cargado OK");
} catch (Exception $e) {
    $error = "Error cargando config: " . $e->getMessage();
    logit("ERROR: $error");
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// Verificar sesión
logit("Verificando sesión...");
if (!isset($_SESSION)) {
    logit("ADVERTENCIA: Sesión no iniciada");
}

if (!isset($_SESSION['is_admin'])) {
    logit("ERROR: Variable is_admin no existe en sesión");
    echo json_encode(['success' => false, 'error' => 'Sesión no válida - is_admin no definido']);
    exit;
}

if ($_SESSION['is_admin'] !== true) {
    logit("ERROR: Usuario no es admin");
    echo json_encode(['success' => false, 'error' => 'Acceso denegado - no admin']);
    exit;
}

logit("Admin verificado OK");

// Cargar database config
logit("Cargando database config...");
try {
    $db_config_path = __DIR__ . '/../../config/database.php';
    logit("DB config path: $db_config_path");

    if (!file_exists($db_config_path)) {
        logit("ERROR: Database config no existe!");
        echo json_encode(['success' => false, 'error' => 'Database config no existe']);
        exit;
    }

    $config = require $db_config_path;
    logit("Database config cargado OK");
} catch (Exception $e) {
    $error = "Error cargando database config: " . $e->getMessage();
    logit("ERROR: $error");
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// Conectar PDO
logit("Conectando a MySQL...");
try {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    logit("DSN: $dsn");
    logit("User: {$config['username']}");

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    logit("PDO conectado OK");
} catch (PDOException $e) {
    $error = "Error PDO: " . $e->getMessage();
    logit("ERROR: $error");
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// Obtener action
$action = $_POST['action'] ?? '';
logit("Action recibida: '$action'");

// CREAR TABLA
if ($action === 'crear_tabla') {
    logit("Ejecutando crear_tabla");

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
        if ($check) {
            logit("Tabla ya existe");
            echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
            exit;
        }

        logit("Creando tabla...");
        $sql = "CREATE TABLE lugares_comerciales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            tipo VARCHAR(100),
            categoria VARCHAR(100),
            email VARCHAR(255),
            telefono VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $pdo->exec($sql);
        logit("Tabla creada OK");

        echo json_encode(['success' => true, 'message' => 'Tabla creada exitosamente']);
        exit;

    } catch (PDOException $e) {
        $error = "Error creando tabla: " . $e->getMessage();
        logit("ERROR: $error");
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}

// IMPORTAR
if ($action === 'importar') {
    logit("Ejecutando importar");

    set_time_limit(300); // 5 minutos

    try {
        // Verificar que la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
        if (!$check) {
            logit("ERROR: Tabla no existe");
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

        logit("Tabla verificada OK");
        logit("Preparando query Overpass...");

        // Query compacta de Overpass API
        $overpass_query = '[out:json][timeout:180];area["name"="Costa Rica"]["type"="boundary"]->.a;(node["amenity"~"restaurant|bar|cafe|fast_food|pub"](area.a);way["amenity"~"restaurant|bar|cafe|fast_food|pub"](area.a);node["tourism"~"hotel|motel|guest_house|hostel"](area.a);way["tourism"~"hotel|motel|guest_house|hostel"](area.a);node["shop"](area.a);way["shop"](area.a););out center;';

        logit("Haciendo request a Overpass API...");

        // Request a Overpass API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://overpass-api.de/api/interpreter");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode($overpass_query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $error = "Error curl: $curl_error";
            logit("ERROR: $error");
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        if ($http_code !== 200) {
            $error = "HTTP $http_code de Overpass";
            logit("ERROR: $error");
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        logit("Response recibido OK");

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "Error JSON: " . json_last_error_msg();
            logit("ERROR: $error");
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        if (!isset($data['elements'])) {
            logit("ERROR: Respuesta inválida de Overpass");
            echo json_encode(['success' => false, 'error' => 'Respuesta inválida de Overpass API']);
            exit;
        }

        $elements = $data['elements'];
        $total = count($elements);
        logit("Elementos recibidos: $total");

        $imported = 0;
        $updated = 0;
        $errors = 0;

        // Preparar statement
        $stmt = $pdo->prepare("
            INSERT INTO lugares_comerciales (nombre, tipo, categoria, email, telefono, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");

        logit("Iniciando importación...");

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];

            $nombre = $tags['name'] ?? ($tags['brand'] ?? 'Sin nombre');
            $tipo = $tags['amenity'] ?? $tags['tourism'] ?? $tags['shop'] ?? 'other';

            $categoria = '';
            if (isset($tags['amenity'])) $categoria = 'amenity';
            elseif (isset($tags['tourism'])) $categoria = 'tourism';
            elseif (isset($tags['shop'])) $categoria = 'shop';

            try {
                $stmt->execute([
                    $nombre,
                    $tipo,
                    $categoria,
                    $tags['email'] ?? $tags['contact:email'] ?? '',
                    $tags['phone'] ?? $tags['contact:phone'] ?? ''
                ]);

                if ($stmt->rowCount() > 0) {
                    $imported++;
                } else {
                    $updated++;
                }

            } catch (PDOException $e) {
                $errors++;
            }

            // Log progreso cada 100
            if (($imported + $updated) % 100 === 0) {
                logit("Progreso: $imported importados, $updated actualizados");
            }
        }

        logit("Importación completada: $imported importados, $updated actualizados, $errors errores");

        // Estadísticas
        $total_db = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != ''")->fetchColumn();

        logit("Total en BD: $total_db, Con email: $with_email");

        echo json_encode([
            'success' => true,
            'total' => $total,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'stats' => [
                'total' => $total_db,
                'with_email' => $with_email,
                'with_phone' => 0,
                'email_percent' => $total_db > 0 ? round($with_email/$total_db*100, 1) : 0,
                'phone_percent' => 0,
                'top_categorias' => [],
                'top_tipos' => []
            ]
        ]);

        logit("Respuesta enviada OK");
        exit;

    } catch (Exception $e) {
        $error = "Error general: " . $e->getMessage();
        logit("ERROR: $error");
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}

// Acción desconocida
logit("Acción desconocida: '$action'");
echo json_encode(['success' => false, 'error' => "Acción no reconocida: '$action'"]);
