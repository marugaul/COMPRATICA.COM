<?php
/**
 * API Backend para Email Marketing - VERSION DEBUG
 * Con logging detallado para diagnosticar errores
 */

// Logging function
function apiLog($message, $data = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $logFile = $logDir . '/email_api_debug.log';

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $line .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Mostrar errores en desarrollo
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_email_api_errors.log');

apiLog('API INICIADA', [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'action' => $_REQUEST['action'] ?? 'no action',
    'POST_keys' => array_keys($_POST),
    'GET_keys' => array_keys($_GET)
]);

try {
    // Cargar config que maneja sesiones
    apiLog('Cargando config.php');
    require_once __DIR__ . '/../includes/config.php';

    apiLog('Config cargado', [
        'session_id' => session_id(),
        'is_admin' => $_SESSION['is_admin'] ?? 'not set',
        'admin_user' => $_SESSION['admin_user'] ?? 'not set'
    ]);

    // Verificar autenticación de admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        apiLog('AUTENTICACION FALLIDA');
        http_response_code(403);
        die(json_encode(['error' => 'No autorizado']));
    }

    apiLog('Autenticación OK');

    // Conectar a BD
    apiLog('Conectando a BD');
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    apiLog('BD conectada');

    $action = $_REQUEST['action'] ?? '';
    apiLog('Ejecutando acción', ['action' => $action]);

    switch ($action) {
        case 'save_smtp_config':
            apiLog('Iniciando save_smtp_config');

            $id = $_POST['config_id'] ?? null;
            $from_email = $_POST['from_email'] ?? '';
            $from_name = $_POST['from_name'] ?? '';
            $smtp_host = $_POST['smtp_host'] ?? '';
            $smtp_port = $_POST['smtp_port'] ?? 587;
            $smtp_username = $_POST['smtp_username'] ?? '';
            $smtp_password = $_POST['smtp_password'] ?? '';
            $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';

            apiLog('Datos recibidos', [
                'id' => $id,
                'from_email' => $from_email,
                'from_name' => $from_name,
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_password_length' => strlen($smtp_password),
                'smtp_encryption' => $smtp_encryption
            ]);

            if (!$id) {
                apiLog('ERROR: ID vacío');
                throw new Exception('ID de configuración no válido');
            }

            // Si la contraseña está vacía, no actualizarla
            if (empty($smtp_password)) {
                apiLog('Actualizando SIN contraseña');
                $stmt = $pdo->prepare("
                    UPDATE email_smtp_configs
                    SET from_email=?, from_name=?, smtp_host=?, smtp_port=?,
                        smtp_username=?, smtp_encryption=?
                    WHERE id=?
                ");
                $result = $stmt->execute([
                    $from_email, $from_name, $smtp_host, $smtp_port,
                    $smtp_username, $smtp_encryption, $id
                ]);
                apiLog('Update ejecutado (sin password)', ['result' => $result, 'affected_rows' => $stmt->rowCount()]);
            } else {
                apiLog('Actualizando CON contraseña');
                $stmt = $pdo->prepare("
                    UPDATE email_smtp_configs
                    SET from_email=?, from_name=?, smtp_host=?, smtp_port=?,
                        smtp_username=?, smtp_password=?, smtp_encryption=?
                    WHERE id=?
                ");
                $result = $stmt->execute([
                    $from_email, $from_name, $smtp_host, $smtp_port,
                    $smtp_username, $smtp_password, $smtp_encryption, $id
                ]);
                apiLog('Update ejecutado (con password)', ['result' => $result, 'affected_rows' => $stmt->rowCount()]);
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Configuración SMTP guardada exitosamente'];
            apiLog('Mensaje de sesión configurado, redirigiendo');

            header('Location: email_marketing.php?page=smtp-config');
            exit;

        default:
            apiLog('Acción no válida', ['action' => $action]);
            throw new Exception('Acción no válida: ' . $action);
    }

} catch (Exception $e) {
    apiLog('EXCEPCION CAPTURADA', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    header('Location: email_marketing.php?page=smtp-config');
    exit;
}
?>
