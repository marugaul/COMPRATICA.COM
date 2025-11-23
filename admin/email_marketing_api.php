<?php
/**
 * API Backend para Email Marketing
 * Maneja creación de campañas, procesamiento de Excel, envío de emails
 */

// Cargar config que maneja sesiones
require_once __DIR__ . '/../includes/config.php';

// Verificar autenticación de admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado']));
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'create_campaign':
            handleCreateCampaign();
            break;

        case 'send_campaign':
            handleSendCampaign();
            break;

        case 'delete_campaign':
            handleDeleteCampaign();
            break;

        case 'get_campaign_progress':
            getCampaignProgress();
            break;

        case 'download_template':
            downloadExcelTemplate();
            break;

        case 'save_smtp_config':
            saveSMTPConfig();
            break;

        case 'test_smtp':
            testSMTPConnection();
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    // Si es una petición AJAX (get_campaign_progress), devolver JSON
    if ($action === 'get_campaign_progress') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    header('Location: email_marketing.php');
    exit;
}

/**
 * Crear nueva campaña
 */
function handleCreateCampaign() {
    global $pdo;

    $campaign_name = $_POST['campaign_name'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $source_type = $_POST['source_type'] ?? '';
    $template_id = $_POST['template_id'] ?? '';
    $smtp_config_id = $_POST['smtp_config_id'] ?? '';
    $send_type = $_POST['send_type'] ?? 'draft';
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;
    $generic_greeting = trim($_POST['generic_greeting'] ?? 'Estimado propietario');

    if (empty($campaign_name) || empty($subject) || empty($template_id) || empty($smtp_config_id)) {
        throw new Exception('Todos los campos son obligatorios');
    }

    // Determinar el estado inicial de la campaña
    $initial_status = 'draft';
    $scheduled_at = null;
    $started_at = null;

    if ($send_type === 'now') {
        $initial_status = 'sending';
        $started_at = date('Y-m-d H:i:s');
    } elseif ($send_type === 'scheduled' && !empty($scheduled_datetime)) {
        $initial_status = 'scheduled';
        $scheduled_at = $scheduled_datetime;
    }

    // Crear la campaña
    $stmt = $pdo->prepare("
        INSERT INTO email_campaigns
        (name, smtp_config_id, template_id, subject, generic_greeting, source_type, status, scheduled_at, started_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $campaign_name,
        $smtp_config_id,
        $template_id,
        $subject,
        $generic_greeting,
        $source_type,
        $initial_status,
        $scheduled_at,
        $started_at
    ]);

    $campaign_id = $pdo->lastInsertId();

    // Procesar destinatarios según el origen
    $recipients = [];

    if ($source_type === 'excel') {
        $recipients = processExcelUpload($campaign_id);
    } elseif ($source_type === 'database') {
        $recipients = processDatabaseCampaign($campaign_id);
    } elseif ($source_type === 'lugares_comerciales') {
        $recipients = processLugaresComercialesCampaign($campaign_id);
    } elseif ($source_type === 'manual') {
        $recipients = processManualRecipients($campaign_id);
    }

    // Insertar destinatarios
    $total = count($recipients);
    if ($total > 0) {
        foreach ($recipients as $recipient) {
            $tracking_code = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("
                INSERT INTO email_recipients
                (campaign_id, email, name, phone, custom_data, tracking_code)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $campaign_id,
                $recipient['email'],
                $recipient['name'] ?? null,
                $recipient['phone'] ?? null,
                isset($recipient['custom_data']) ? json_encode($recipient['custom_data']) : null,
                $tracking_code
            ]);
        }

        // Actualizar total de destinatarios
        $pdo->prepare("UPDATE email_campaigns SET total_recipients = ? WHERE id = ?")
            ->execute([$total, $campaign_id]);
    }

    // Manejar archivo adjunto
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/email_attachments/';
        @mkdir($upload_dir, 0755, true);

        $filename = uniqid('attachment_') . '_' . basename($_FILES['attachment']['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
            $pdo->prepare("UPDATE email_campaigns SET attachment_path = ? WHERE id = ?")
                ->execute([$filename, $campaign_id]);
        }
    }

    // Mensaje de éxito según el tipo de envío
    $message_text = "Campaña creada exitosamente con {$total} destinatarios. ";

    // Si es una petición AJAX (detectado por el header), devolver JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'campaign_id' => $campaign_id,
            'total_recipients' => $total,
            'message' => "Campaña creada exitosamente con {$total} destinatarios"
        ]);
        exit;
    }

    if ($send_type === 'now') {
        // Redirigir directamente a la pantalla de envío
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Campaña creada. Iniciando envío de {$total} emails..."
        ];
        header('Location: email_marketing_send.php?campaign_id=' . $campaign_id);
        exit;
    } elseif ($send_type === 'scheduled') {
        $message_text .= "Programada para: " . date('d/m/Y H:i', strtotime($scheduled_datetime));
    } else {
        $message_text .= "<a href='email_marketing.php?page=campaign-detail&id={$campaign_id}'>Ver detalles</a>";
    }

    $_SESSION['message'] = [
        'type' => 'success',
        'text' => $message_text
    ];

    header('Location: email_marketing.php?page=campaigns');
    exit;
}

/**
 * Programar o enviar campaña
 */
function handleSendCampaign() {
    global $pdo;

    $campaign_id = $_POST['campaign_id'] ?? '';
    $send_type = $_POST['send_type'] ?? 'now'; // 'now' o 'scheduled'
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;

    if (empty($campaign_id)) {
        throw new Exception('ID de campaña no válido');
    }

    // Verificar que la campaña existe
    $campaign = $pdo->query("SELECT * FROM email_campaigns WHERE id = $campaign_id")->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        throw new Exception('Campaña no encontrada');
    }

    if ($campaign['total_recipients'] == 0) {
        throw new Exception('La campaña no tiene destinatarios');
    }

    if ($send_type === 'scheduled' && $scheduled_datetime) {
        // Programar envío
        $stmt = $pdo->prepare("UPDATE email_campaigns SET status = 'scheduled', scheduled_at = ? WHERE id = ?");
        $stmt->execute([$scheduled_datetime, $campaign_id]);

        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Campaña programada para: " . date('d/m/Y H:i', strtotime($scheduled_datetime))
        ];
    } else {
        // Enviar ahora - cambiar estado a 'sending'
        $stmt = $pdo->prepare("UPDATE email_campaigns SET status = 'sending', started_at = NOW() WHERE id = ?");
        $stmt->execute([$campaign_id]);

        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Campaña iniciada. Los emails se están enviando en segundo plano."
        ];
    }

    header('Location: email_marketing.php?page=campaign-detail&id=' . $campaign_id);
    exit;
}

/**
 * Procesar archivo Excel
 */
function processExcelUpload($campaign_id) {
    if (empty($_FILES['excel_file']['name'])) {
        throw new Exception('Debe subir un archivo Excel');
    }

    $file = $_FILES['excel_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception('Formato de archivo no válido. Use .xlsx, .xls o .csv');
    }

    // Verificar si PhpSpreadsheet está disponible
    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        // Fallback: procesar CSV manualmente
        if ($ext === 'csv') {
            return processCSV($file['tmp_name']);
        } else {
            throw new Exception('PhpSpreadsheet no está instalado. Use archivo CSV.');
        }
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    $recipients = [];
    $header = array_shift($rows); // Primera fila como encabezado

    foreach ($rows as $row) {
        if (empty($row[0])) continue; // Skip empty rows

        $email = filter_var(trim($row[0]), FILTER_VALIDATE_EMAIL);
        if (!$email) continue;

        $recipients[] = [
            'email' => $email,
            'name' => $row[1] ?? null,
            'phone' => $row[2] ?? null
        ];
    }

    return $recipients;
}

/**
 * Procesar CSV simple
 */
function processCSV($filepath) {
    $recipients = [];
    $handle = fopen($filepath, 'r');

    if ($handle) {
        $header = fgetcsv($handle); // Skip header

        while (($row = fgetcsv($handle)) !== FALSE) {
            if (empty($row[0])) continue;

            $email = filter_var(trim($row[0]), FILTER_VALIDATE_EMAIL);
            if (!$email) continue;

            $recipients[] = [
                'email' => $email,
                'name' => $row[1] ?? null,
                'phone' => $row[2] ?? null
            ];
        }

        fclose($handle);
    }

    return $recipients;
}

/**
 * Procesar selección de base de datos
 */
function processDatabaseCampaign($campaign_id) {
    global $pdo;

    $categories = $_POST['categories'] ?? [];
    $selected_places = $_POST['selected_places'] ?? [];

    if (empty($categories)) {
        throw new Exception('Debe seleccionar al menos una categoría');
    }

    // Guardar filtro de categorías
    $pdo->prepare("UPDATE email_campaigns SET filter_categories = ? WHERE id = ?")
        ->execute([json_encode($categories), $campaign_id]);

    $recipients = [];

    // Si hay lugares específicos seleccionados, usar solo esos
    if (!empty($selected_places)) {
        foreach ($selected_places as $placeJson) {
            $place = json_decode($placeJson, true);

            if ($place && isset($place['email']) && filter_var($place['email'], FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'email' => $place['email'],
                    'name' => $place['name'],
                    'phone' => $place['phone'] !== 'N/A' ? $place['phone'] : null,
                    'custom_data' => $place['tags'] ?? []
                ];
            }
        }
    } else {
        // Si no hay lugares específicos, usar todas las categorías seleccionadas
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';

        $stmt = $pdo->prepare("
            SELECT name, phone, tags
            FROM places_cr
            WHERE type IN ($placeholders)
            AND tags IS NOT NULL
            AND tags != ''
        ");
        $stmt->execute($categories);

        while ($place = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tags = json_decode($place['tags'], true);
            $email = $tags['email'] ?? null;

            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'email' => $email,
                    'name' => $place['name'],
                    'phone' => $place['phone'] ?? $tags['phone'] ?? null,
                    'custom_data' => $tags
                ];
            }
        }
    }

    return $recipients;
}

/**
 * Procesar destinatarios de lugares comerciales (OpenStreetMap)
 */
function processLugaresComercialesCampaign($campaign_id) {
    global $pdo;

    $tipos = $_POST['lugares_tipos'] ?? [];
    $selected_lugares = $_POST['selected_lugares'] ?? [];

    if (empty($tipos) && empty($selected_lugares)) {
        throw new Exception('Debe seleccionar al menos un tipo de lugar');
    }

    // Guardar filtro de tipos
    $pdo->prepare("UPDATE email_campaigns SET filter_categories = ? WHERE id = ?")
        ->execute([json_encode($tipos), $campaign_id]);

    $recipients = [];

    // Si hay lugares específicos seleccionados, usar solo esos
    if (!empty($selected_lugares)) {
        foreach ($selected_lugares as $lugarJson) {
            $lugar = json_decode($lugarJson, true);

            if ($lugar && isset($lugar['email']) && filter_var($lugar['email'], FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'email' => $lugar['email'],
                    'name' => $lugar['nombre'] ?? 'Estimado propietario',
                    'phone' => $lugar['telefono'] ?? null,
                    'custom_data' => [
                        'ciudad' => $lugar['ciudad'] ?? '',
                        'direccion' => $lugar['direccion'] ?? '',
                        'tipo' => $lugar['tipo'] ?? '',
                        'categoria' => $lugar['categoria'] ?? ''
                    ]
                ];
            }
        }
    } else {
        // Si no hay lugares específicos, usar todos los tipos seleccionados
        $placeholders = str_repeat('?,', count($tipos) - 1) . '?';

        $stmt = $pdo->prepare("
            SELECT nombre, email, telefono, direccion, ciudad, tipo, categoria
            FROM lugares_comerciales
            WHERE tipo IN ($placeholders)
            AND email IS NOT NULL
            AND email != ''
        ");
        $stmt->execute($tipos);

        while ($lugar = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (filter_var($lugar['email'], FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'email' => $lugar['email'],
                    'name' => $lugar['nombre'] ?? 'Estimado propietario',
                    'phone' => $lugar['telefono'] ?? null,
                    'custom_data' => [
                        'ciudad' => $lugar['ciudad'] ?? '',
                        'direccion' => $lugar['direccion'] ?? '',
                        'tipo' => $lugar['tipo'] ?? '',
                        'categoria' => $lugar['categoria'] ?? ''
                    ]
                ];
            }
        }
    }

    return $recipients;
}

/**
 * Procesar destinatarios manuales
 */
function processManualRecipients($campaign_id) {
    $manual_text = $_POST['manual_recipients'] ?? '';
    $lines = explode("\n", $manual_text);

    $recipients = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = explode(',', $line);
        $email = filter_var(trim($parts[0]), FILTER_VALIDATE_EMAIL);

        if (!$email) continue;

        $recipients[] = [
            'email' => $email,
            'name' => isset($parts[1]) ? trim($parts[1]) : null,
            'phone' => isset($parts[2]) ? trim($parts[2]) : null
        ];
    }

    return $recipients;
}

/**
 * Descargar plantilla Excel
 */
function downloadExcelTemplate() {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=plantilla_email_marketing.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['email', 'nombre', 'telefono']);
    fputcsv($output, ['ejemplo@hotel.com', 'Hotel Ejemplo', '+506-1234-5678']);
    fputcsv($output, ['info@restaurante.cr', 'Restaurante Demo', '+506-8765-4321']);
    fclose($output);
    exit;
}

/**
 * Guardar configuración SMTP
 */
function saveSMTPConfig() {
    global $pdo;

    $id = $_POST['config_id'] ?? null;
    $from_email = $_POST['from_email'] ?? '';
    $from_name = $_POST['from_name'] ?? '';
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? 587;
    $smtp_username = $_POST['smtp_username'] ?? '';
    $smtp_password = $_POST['smtp_password'] ?? '';
    $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';

    if (!$id) {
        throw new Exception('ID de configuración no válido');
    }

    // Si la contraseña está vacía, no actualizarla (mantener la actual)
    if (empty($smtp_password)) {
        $stmt = $pdo->prepare("
            UPDATE email_smtp_configs
            SET from_email=?, from_name=?, smtp_host=?, smtp_port=?,
                smtp_username=?, smtp_encryption=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $from_email, $from_name, $smtp_host, $smtp_port,
            $smtp_username, $smtp_encryption, $id
        ]);
    } else {
        // Actualizar incluyendo contraseña
        $stmt = $pdo->prepare("
            UPDATE email_smtp_configs
            SET from_email=?, from_name=?, smtp_host=?, smtp_port=?,
                smtp_username=?, smtp_password=?, smtp_encryption=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $from_email, $from_name, $smtp_host, $smtp_port,
            $smtp_username, $smtp_password, $smtp_encryption, $id
        ]);
    }

    $_SESSION['message'] = ['type' => 'success', 'text' => 'Configuración SMTP guardada exitosamente'];
    header('Location: email_marketing.php?page=smtp-config');
    exit;
}

/**
 * Probar conexión SMTP
 */
function testSMTPConnection() {
    global $pdo;

    $id = $_POST['smtp_id'] ?? '';

    if (empty($id)) {
        throw new Exception('ID de configuración no válido');
    }

    $config = $pdo->query("SELECT * FROM email_smtp_configs WHERE id = $id")->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        throw new Exception('Configuración no encontrada');
    }

    // Probar con PHPMailer
    require_once __DIR__ . '/email_sender.php';

    $mailer = new EmailSender($config);
    $result = $mailer->testConnection();

    $_SESSION['message'] = [
        'type' => $result['success'] ? 'success' : 'danger',
        'text' => $result['message']
    ];

    header('Location: email_marketing.php?page=smtp-config');
    exit;
}

/**
 * Eliminar campaña y sus destinatarios
 */
function handleDeleteCampaign() {
    global $pdo;

    $campaign_id = $_REQUEST['campaign_id'] ?? '';

    if (empty($campaign_id)) {
        throw new Exception('ID de campaña no válido');
    }

    // Verificar que la campaña existe
    $campaign = $pdo->query("SELECT * FROM email_campaigns WHERE id = $campaign_id")->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        throw new Exception('Campaña no encontrada');
    }

    // Eliminar archivo adjunto si existe
    if (!empty($campaign['attachment_path'])) {
        $attachment_file = __DIR__ . '/../uploads/email_attachments/' . $campaign['attachment_path'];
        if (file_exists($attachment_file)) {
            @unlink($attachment_file);
        }
    }

    // Eliminar logs de envío
    $pdo->prepare("DELETE FROM email_send_logs WHERE campaign_id = ?")->execute([$campaign_id]);

    // Eliminar destinatarios
    $pdo->prepare("DELETE FROM email_recipients WHERE campaign_id = ?")->execute([$campaign_id]);

    // Eliminar campaña
    $pdo->prepare("DELETE FROM email_campaigns WHERE id = ?")->execute([$campaign_id]);

    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Campaña '{$campaign['name']}' eliminada exitosamente"
    ];

    header('Location: email_marketing.php?page=campaigns');
    exit;
}

/**
 * Obtener progreso de envío de una campaña en tiempo real
 */
function getCampaignProgress() {
    global $pdo;

    header('Content-Type: application/json');

    $campaign_id = $_GET['campaign_id'] ?? '';

    if (empty($campaign_id)) {
        echo json_encode(['success' => false, 'error' => 'ID de campaña no válido']);
        exit;
    }

    // Verificar que la campaña existe
    $campaign = $pdo->query("SELECT * FROM email_campaigns WHERE id = $campaign_id")->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['success' => false, 'error' => 'Campaña no encontrada']);
        exit;
    }

    // Obtener estadísticas de envío desde email_recipients
    $stats_query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM email_recipients
        WHERE campaign_id = ?
    ";

    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$campaign_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener los últimos 10 logs de envío (ordenados por más recientes primero)
    $logs_query = "
        SELECT r.email, r.status, l.error_message, l.created_at
        FROM email_recipients r
        LEFT JOIN email_send_logs l ON l.recipient_id = r.id
        WHERE r.campaign_id = ? AND r.status IN ('sent', 'failed')
        ORDER BY l.created_at DESC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($logs_query);
    $stmt->execute([$campaign_id]);
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear los logs
    $formatted_logs = array_map(function($log) {
        return [
            'email' => $log['email'],
            'status' => $log['status'],
            'error' => $log['error_message'],
            'created_at' => $log['created_at']
        ];
    }, $recent_logs);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$stats['total'],
            'sent' => (int)$stats['sent'],
            'failed' => (int)$stats['failed'],
            'pending' => (int)$stats['pending']
        ],
        'recent_logs' => $formatted_logs,
        'campaign_status' => $campaign['status']
    ]);
    exit;
}
?>
