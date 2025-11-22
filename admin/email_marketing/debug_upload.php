<?php
/**
 * Debug ultra-robusto - captura TODOS los errores posibles
 */

// Función de shutdown para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal de PHP',
            'details' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

// Iniciar output buffering inmediatamente
ob_start();

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', '0');

$debugLog = [];
$debugLog[] = "=== DEBUG ULTRA-ROBUSTO ===";
$debugLog[] = "Timestamp: " . date('Y-m-d H:i:s');
$debugLog[] = "PHP Version: " . PHP_VERSION;
$debugLog[] = "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');

try {
    // 1. Verificar que es POST
    $debugLog[] = "1. Método: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Debe ser POST, es: " . $_SERVER['REQUEST_METHOD']);
    }

    // 2. Verificar action
    $debugLog[] = "2. Action recibido: " . ($_POST['action'] ?? ($_REQUEST['action'] ?? 'NO RECIBIDO'));

    // 3. Intentar cargar config
    $debugLog[] = "3. Intentando cargar config...";
    $configPath = __DIR__ . '/../../includes/config.php';
    $debugLog[] = "   Config path: $configPath";
    $debugLog[] = "   Config existe: " . (file_exists($configPath) ? 'SI' : 'NO');

    if (!file_exists($configPath)) {
        throw new Exception("Config no existe en: $configPath");
    }

    require_once $configPath;
    $debugLog[] = "   ✓ Config cargado";

    // 4. Verificar sesión
    $debugLog[] = "4. Verificando sesión...";
    $debugLog[] = "   Session ID: " . (session_id() ?: 'No iniciada');
    $debugLog[] = "   is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'no definido');

    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        throw new Exception("No es admin");
    }
    $debugLog[] = "   ✓ Admin verificado";

    // 5. Conectar BD
    $debugLog[] = "5. Conectando a BD...";
    $dbConfigPath = __DIR__ . '/../../config/database.php';
    $debugLog[] = "   DB config path: $dbConfigPath";
    $debugLog[] = "   DB config existe: " . (file_exists($dbConfigPath) ? 'SI' : 'NO');

    if (!file_exists($dbConfigPath)) {
        throw new Exception("DB config no existe");
    }

    $config = require $dbConfigPath;
    $debugLog[] = "   Host: " . ($config['host'] ?? 'no definido');
    $debugLog[] = "   Database: " . ($config['database'] ?? 'no definido');

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $debugLog[] = "   ✓ BD conectada";

    // 6. Verificar datos POST
    $debugLog[] = "6. Datos POST recibidos:";
    $debugLog[] = "   template_name: " . ($_POST['template_name'] ?? 'NO');
    $debugLog[] = "   template_company: " . ($_POST['template_company'] ?? 'NO');
    $debugLog[] = "   template_subject: " . ($_POST['template_subject'] ?? 'NO');
    $debugLog[] = "   set_as_default: " . (isset($_POST['set_as_default']) ? 'SI' : 'NO');
    $debugLog[] = "   image_display: " . ($_POST['image_display'] ?? 'NO');

    // 7. Verificar FILES
    $debugLog[] = "7. Archivos recibidos:";
    $debugLog[] = "   template_file isset: " . (isset($_FILES['template_file']) ? 'SI' : 'NO');
    if (isset($_FILES['template_file'])) {
        $debugLog[] = "   template_file error: " . $_FILES['template_file']['error'];
        $debugLog[] = "   template_file name: " . $_FILES['template_file']['name'];
        $debugLog[] = "   template_file size: " . $_FILES['template_file']['size'];
        $debugLog[] = "   template_file type: " . $_FILES['template_file']['type'];
    }

    $debugLog[] = "   template_image isset: " . (isset($_FILES['template_image']) ? 'SI' : 'NO');
    if (isset($_FILES['template_image'])) {
        $debugLog[] = "   template_image error: " . $_FILES['template_image']['error'];
        $debugLog[] = "   template_image name: " . ($_FILES['template_image']['name'] ?? 'sin nombre');
        $debugLog[] = "   template_image size: " . ($_FILES['template_image']['size'] ?? 0);
    }

    // 8. Validar campos requeridos
    $debugLog[] = "8. Validando campos...";
    $name = trim($_POST['template_name'] ?? '');
    $company = trim($_POST['template_company'] ?? '');
    $subject = trim($_POST['template_subject'] ?? '');

    if (empty($name)) throw new Exception("Nombre vacío");
    if (empty($company)) throw new Exception("Company vacío");
    if (empty($subject)) throw new Exception("Subject vacío");
    $debugLog[] = "   ✓ Campos básicos OK";

    // 9. Procesar company slug
    $debugLog[] = "9. Procesando slug...";
    $debugLog[] = "   Original: '$company'";
    $company = strtolower($company);
    $company = str_replace(['_', ' ', '.'], '-', $company);
    $company = preg_replace('/[^a-z0-9-]/', '', $company);
    $company = preg_replace('/-+/', '-', $company);
    $company = trim($company, '-');
    $debugLog[] = "   Procesado: '$company'";

    if (empty($company)) throw new Exception("Slug vacío después de procesar");
    $debugLog[] = "   ✓ Slug OK";

    // 10. Verificar archivo HTML
    $debugLog[] = "10. Verificando archivo HTML...";
    if (!isset($_FILES['template_file'])) {
        throw new Exception("No se recibió template_file en FILES");
    }

    $uploadError = $_FILES['template_file']['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Archivo excede upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Archivo excede MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco',
            UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
        ];
        throw new Exception("Error upload: " . ($errorMessages[$uploadError] ?? "Código: $uploadError"));
    }
    $debugLog[] = "   ✓ Archivo recibido sin errores";

    // 11. Leer HTML
    $debugLog[] = "11. Leyendo contenido HTML...";
    $tmpName = $_FILES['template_file']['tmp_name'];
    $debugLog[] = "   Temp file: $tmpName";
    $debugLog[] = "   Temp file exists: " . (file_exists($tmpName) ? 'SI' : 'NO');

    if (!file_exists($tmpName)) {
        throw new Exception("Archivo temporal no existe");
    }

    $htmlContent = file_get_contents($tmpName);
    $debugLog[] = "   HTML size: " . strlen($htmlContent) . " bytes";

    if (empty($htmlContent)) {
        throw new Exception("HTML está vacío");
    }
    $debugLog[] = "   ✓ HTML leído correctamente";

    // 12. Verificar exif_imagetype
    $debugLog[] = "12. Verificando función exif_imagetype...";
    $debugLog[] = "   Disponible: " . (function_exists('exif_imagetype') ? 'SI' : 'NO');

    // 13. Verificar duplicados
    $debugLog[] = "13. Verificando duplicados...";
    $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE company = ?");
    $stmt->execute([$company]);
    if ($stmt->fetch()) {
        throw new Exception("Ya existe plantilla con slug: $company");
    }
    $debugLog[] = "   ✓ No hay duplicados";

    // ÉXITO
    $debugLog[] = "\n=== ✓ TODOS LOS CHECKS PASARON ===";
    $debugLog[] = "La plantilla se puede insertar sin problemas";

    // Guardar log en archivo
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/template_upload_debug.log', implode("\n", $debugLog) . "\n");

    // Limpiar buffer y enviar respuesta
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Debug completado - Todo OK. Log guardado en /logs/template_upload_debug.log',
        'debug_log' => $debugLog
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    $debugLog[] = "\n❌ ERROR CAPTURADO:";
    $debugLog[] = "Mensaje: " . $e->getMessage();
    $debugLog[] = "Archivo: " . basename($e->getFile());
    $debugLog[] = "Línea: " . $e->getLine();

    // Guardar log en archivo
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/template_upload_debug.log', implode("\n", $debugLog) . "\n");

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'log_file' => '/logs/template_upload_debug.log',
        'debug_log' => $debugLog
    ], JSON_PRETTY_PRINT);
    exit;
} catch (Throwable $e) {
    $debugLog[] = "\n❌ ERROR FATAL:";
    $debugLog[] = "Tipo: " . get_class($e);
    $debugLog[] = "Mensaje: " . $e->getMessage();

    // Guardar log en archivo
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/template_upload_debug.log', implode("\n", $debugLog) . "\n");

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Error fatal: ' . $e->getMessage(),
        'log_file' => '/logs/template_upload_debug.log',
        'debug_log' => $debugLog
    ], JSON_PRETTY_PRINT);
    exit;
}
