<?php
/**
 * API para gestionar la Blacklist Global
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';

// Log de inicio
logError('error_Blacklist.log', 'blacklist_api.php - INICIO', [
    'action' => $_POST['action'] ?? 'none',
    'session_admin' => isset($_SESSION['is_admin']) ? 'yes' : 'no'
]);

// Verificar autenticación
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    logError('error_Blacklist.log', 'blacklist_api.php - ACCESO DENEGADO');
    http_response_code(403);
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Acceso denegado'];
    header('Location: email_marketing.php?page=blacklist');
    exit;
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_POST['action'] ?? '';

logError('error_Blacklist.log', 'blacklist_api.php - Acción recibida: ' . $action);

try {
    switch ($action) {
        case 'create_table':
            // Crear tabla de blacklist
            header('Content-Type: application/json');

            // Verificar si ya existe
            $tableExists = $pdo->query("SHOW TABLES LIKE 'email_blacklist'")->fetch();

            if ($tableExists) {
                echo json_encode([
                    'success' => false,
                    'error' => 'La tabla email_blacklist ya existe'
                ]);
                exit;
            }

            // Crear tabla
            $sql = "CREATE TABLE email_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                reason VARCHAR(500) DEFAULT NULL,
                campaign_id INT DEFAULT NULL,
                source VARCHAR(50) DEFAULT 'unsubscribe',
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(100) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                UNIQUE KEY unique_email (email),
                INDEX idx_email (email),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $pdo->exec($sql);

            // Migrar emails bounced
            $bouncedEmails = $pdo->query("
                SELECT DISTINCT email, campaign_id
                FROM email_recipients
                WHERE status = 'bounced'
                LIMIT 1000
            ")->fetchAll(PDO::FETCH_ASSOC);

            $migrated = 0;
            foreach ($bouncedEmails as $bounced) {
                try {
                    $pdo->prepare("
                        INSERT INTO email_blacklist (email, reason, campaign_id, source)
                        VALUES (?, 'Migrado automáticamente (bounced)', ?, 'migration')
                        ON DUPLICATE KEY UPDATE campaign_id = VALUES(campaign_id)
                    ")->execute([$bounced['email'], $bounced['campaign_id']]);
                    $migrated++;
                } catch (Exception $e) {
                    // Ignorar duplicados
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Tabla creada exitosamente. Migrados {$migrated} emails de campañas anteriores."
            ]);
            exit;

        case 'add':
            $email = trim($_POST['email'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Agregado manualmente');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }

            $admin_user = $_SESSION['admin_user'] ?? 'admin';

            $stmt = $pdo->prepare("
                INSERT INTO email_blacklist (email, reason, source, created_by, notes)
                VALUES (?, ?, 'manual', ?, ?)
            ");
            $stmt->execute([$email, $reason, $admin_user, $notes]);

            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "Email <strong>{$email}</strong> agregado a la blacklist"
            ];
            break;

        case 'remove':
            $id = $_POST['id'] ?? 0;

            if (empty($id)) {
                throw new Exception('ID no válido');
            }

            // Obtener email antes de eliminar para mostrar en mensaje
            $email = $pdo->query("SELECT email FROM email_blacklist WHERE id = {$id}")->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM email_blacklist WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "Email <strong>{$email}</strong> eliminado de la blacklist"
            ];
            break;

        case 'bulk_add':
            $emails = $_POST['emails'] ?? '';
            $reason = trim($_POST['reason'] ?? 'Importación masiva');

            $emailList = array_filter(array_map('trim', explode("\n", $emails)));
            $added = 0;
            $errors = [];

            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $pdo->prepare("
                            INSERT INTO email_blacklist (email, reason, source, created_by)
                            VALUES (?, ?, 'manual', ?)
                            ON DUPLICATE KEY UPDATE reason = VALUES(reason)
                        ")->execute([$email, $reason, $_SESSION['admin_user'] ?? 'admin']);
                        $added++;
                    } catch (Exception $e) {
                        $errors[] = $email;
                    }
                } else {
                    $errors[] = $email . ' (inválido)';
                }
            }

            $message = "Agregados {$added} emails a la blacklist";
            if (!empty($errors)) {
                $message .= ". Errores: " . implode(', ', array_slice($errors, 0, 5));
            }

            $_SESSION['message'] = [
                'type' => $added > 0 ? 'success' : 'warning',
                'text' => $message
            ];
            break;

        default:
            logError('error_Blacklist.log', 'blacklist_api.php - Acción no válida: ' . $action);
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    logError('error_Blacklist.log', 'blacklist_api.php - EXCEPCIÓN CAPTURADA', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => 'Error: ' . $e->getMessage()
    ];
}

logError('error_Blacklist.log', 'blacklist_api.php - Redirigiendo a blacklist page');
header('Location: email_marketing.php?page=blacklist');
exit;
