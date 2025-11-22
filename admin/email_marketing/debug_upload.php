<?php
/**
 * Debug script para encontrar el error de subida de plantillas
 */

// Capturar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Log de inicio
$debugLog = [];
$debugLog[] = "=== INICIO DEBUG ===";
$debugLog[] = "Timestamp: " . date('Y-m-d H:i:s');

try {
    $debugLog[] = "1. Cargando config...";
    require_once __DIR__ . '/../../includes/config.php';
    $debugLog[] = "✓ Config cargado";

    $debugLog[] = "2. Verificando sesión admin...";
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        throw new Exception("No es admin");
    }
    $debugLog[] = "✓ Admin verificado";

    $debugLog[] = "3. Conectando a BD...";
    $config = require __DIR__ . '/../../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $debugLog[] = "✓ BD conectada";

    $debugLog[] = "4. Verificando datos POST...";
    $debugLog[] = "POST keys: " . implode(', ', array_keys($_POST));
    $debugLog[] = "FILES keys: " . implode(', ', array_keys($_FILES));

    $name = trim($_POST['template_name'] ?? '');
    $company = trim($_POST['template_company'] ?? '');
    $subject = trim($_POST['template_subject'] ?? '');

    $debugLog[] = "Nombre: '$name'";
    $debugLog[] = "Company: '$company'";
    $debugLog[] = "Subject: '$subject'";

    if (empty($name) || empty($company) || empty($subject)) {
        throw new Exception("Campos vacíos");
    }
    $debugLog[] = "✓ Datos básicos OK";

    $debugLog[] = "5. Procesando company slug...";
    $company = strtolower($company);
    $company = str_replace(['_', ' ', '.'], '-', $company);
    $company = preg_replace('/[^a-z0-9-]/', '', $company);
    $company = preg_replace('/-+/', '-', $company);
    $company = trim($company, '-');
    $debugLog[] = "Company procesado: '$company'";

    if (empty($company)) {
        throw new Exception("Company slug vacío después de procesar");
    }
    $debugLog[] = "✓ Company slug OK";

    $debugLog[] = "6. Verificando archivo HTML...";
    if (!isset($_FILES['template_file'])) {
        throw new Exception("No se recibió archivo template_file");
    }
    $debugLog[] = "Error code: " . $_FILES['template_file']['error'];
    $debugLog[] = "Nombre: " . $_FILES['template_file']['name'];
    $debugLog[] = "Tamaño: " . $_FILES['template_file']['size'];
    $debugLog[] = "Tipo: " . $_FILES['template_file']['type'];

    if ($_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al subir archivo: " . $_FILES['template_file']['error']);
    }
    $debugLog[] = "✓ Archivo recibido OK";

    $debugLog[] = "7. Leyendo contenido HTML...";
    $htmlContent = file_get_contents($_FILES['template_file']['tmp_name']);
    $debugLog[] = "Tamaño HTML: " . strlen($htmlContent) . " bytes";

    if (empty($htmlContent)) {
        throw new Exception("HTML vacío");
    }
    $debugLog[] = "✓ HTML leído OK";

    $debugLog[] = "8. Verificando imagen...";
    if (isset($_FILES['template_image']) && $_FILES['template_image']['error'] === UPLOAD_ERR_OK) {
        $debugLog[] = "Imagen recibida";
        $debugLog[] = "Imagen tamaño: " . $_FILES['template_image']['size'];
        $debugLog[] = "Imagen tipo: " . $_FILES['template_image']['type'];

        // Verificar si exif_imagetype está disponible
        if (!function_exists('exif_imagetype')) {
            throw new Exception("FUNCIÓN exif_imagetype NO DISPONIBLE EN ESTE SERVIDOR");
        }
        $debugLog[] = "✓ exif_imagetype disponible";

        $imageType = @exif_imagetype($_FILES['template_image']['tmp_name']);
        $debugLog[] = "Image type: " . $imageType;
    } else {
        $debugLog[] = "Sin imagen";
    }

    $debugLog[] = "9. Verificando duplicados...";
    $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE company = ?");
    $stmt->execute([$company]);
    if ($stmt->fetch()) {
        throw new Exception("Ya existe plantilla con ese slug");
    }
    $debugLog[] = "✓ No hay duplicados";

    $debugLog[] = "\n=== TODO OK - LISTO PARA INSERTAR ===";

    // Mostrar resultado
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Debug completado sin errores',
        'debug_log' => $debugLog
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $debugLog[] = "❌ ERROR: " . $e->getMessage();
    $debugLog[] = "Archivo: " . $e->getFile();
    $debugLog[] = "Línea: " . $e->getLine();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_log' => $debugLog
    ], JSON_PRETTY_PRINT);
}
