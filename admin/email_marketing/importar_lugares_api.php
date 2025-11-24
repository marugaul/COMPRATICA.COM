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
    echo json_encode(['success' => false, 'error' => 'Función de importar aún no implementada en esta versión']);
    exit;
}

// Acción desconocida
logit("Acción desconocida: '$action'");
echo json_encode(['success' => false, 'error' => "Acción no reconocida: '$action'"]);
