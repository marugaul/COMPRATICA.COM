<?php
/**
 * API Backend para Email Marketing
 * Maneja creación de campañas, procesamiento de Excel, envío de emails
 */

session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación de admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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

    if (empty($campaign_name) || empty($subject) || empty($template_id) || empty($smtp_config_id)) {
        throw new Exception('Todos los campos son obligatorios');
    }

    // Crear la campaña
    $stmt = $pdo->prepare("
        INSERT INTO email_campaigns
        (name, smtp_config_id, template_id, subject, source_type, created_by, status)
        VALUES (?, ?, ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([
        $campaign_name,
        $smtp_config_id,
        $template_id,
        $subject,
        $source_type,
        $_SESSION['user_id']
    ]);

    $campaign_id = $pdo->lastInsertId();

    // Procesar destinatarios según el origen
    $recipients = [];

    if ($source_type === 'excel') {
        $recipients = processExcelUpload($campaign_id);
    } elseif ($source_type === 'database') {
        $recipients = processDatabase Campaign($campaign_id);
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

    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Campaña creada exitosamente con {$total} destinatarios. <a href='email_marketing.php?page=campaign-send&id={$campaign_id}'>Enviar ahora</a>"
    ];

    header('Location: email_marketing.php?page=campaigns');
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

    if (empty($categories)) {
        throw new Exception('Debe seleccionar al menos una categoría');
    }

    // Guardar filtro de categorías
    $pdo->prepare("UPDATE email_campaigns SET filter_categories = ? WHERE id = ?")
        ->execute([json_encode($categories), $campaign_id]);

    // Extraer lugares con emails
    $placeholders = str_repeat('?,', count($categories) - 1) . '?';

    $stmt = $pdo->prepare("
        SELECT name, phone, tags
        FROM places_cr
        WHERE type IN ($placeholders)
        AND tags IS NOT NULL
        AND tags != ''
    ");
    $stmt->execute($categories);

    $recipients = [];

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
    $name = $_POST['name'] ?? '';
    $from_email = $_POST['from_email'] ?? '';
    $from_name = $_POST['from_name'] ?? '';
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? 587;
    $smtp_username = $_POST['smtp_username'] ?? '';
    $smtp_password = $_POST['smtp_password'] ?? '';
    $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';

    if ($id) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE email_smtp_configs
            SET name=?, from_email=?, from_name=?, smtp_host=?, smtp_port=?,
                smtp_username=?, smtp_password=?, smtp_encryption=?
            WHERE id=?
        ");
        $stmt->execute([
            $name, $from_email, $from_name, $smtp_host, $smtp_port,
            $smtp_username, $smtp_password, $smtp_encryption, $id
        ]);
    } else {
        // Crear nuevo
        $stmt = $pdo->prepare("
            INSERT INTO email_smtp_configs
            (name, from_email, from_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $from_email, $from_name, $smtp_host, $smtp_port,
            $smtp_username, $smtp_password, $smtp_encryption
        ]);
    }

    $_SESSION['message'] = ['type' => 'success', 'text' => 'Configuración SMTP guardada'];
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
?>
