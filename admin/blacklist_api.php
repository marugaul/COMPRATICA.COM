<?php
/**
 * API para gestionar la Blacklist Global
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar autenticación
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
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

try {
    switch ($action) {
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
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => 'Error: ' . $e->getMessage()
    ];
}

header('Location: email_marketing.php?page=blacklist');
exit;
