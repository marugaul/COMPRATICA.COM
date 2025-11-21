<?php
/**
 * API para gestión de plantillas de email
 * Maneja: subida, preview, test de envío, default, activar/desactivar, eliminar
 */

ob_start();

require_once __DIR__ . '/../../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Conectar a BD
$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_REQUEST['action'] ?? '';

ob_clean();
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'upload_template':
            handleUploadTemplate($pdo);
            break;

        case 'test_template':
            handleTestTemplate($pdo);
            break;

        case 'set_default':
            handleSetDefault($pdo);
            break;

        case 'toggle_active':
            handleToggleActive($pdo);
            break;

        case 'delete_template':
            handleDeleteTemplate($pdo);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

ob_end_flush();

/**
 * Subir nueva plantilla
 */
function handleUploadTemplate($pdo) {
    $name = trim($_POST['template_name'] ?? '');
    $company = trim($_POST['template_company'] ?? '');
    $subject = trim($_POST['template_subject'] ?? '');
    $setAsDefault = isset($_POST['set_as_default']);
    $imageDisplay = $_POST['image_display'] ?? 'none';

    if (empty($name) || empty($company) || empty($subject)) {
        echo json_encode(['success' => false, 'error' => 'Todos los campos son requeridos']);
        return;
    }

    // Validar slug
    if (!preg_match('/^[a-z0-9-]+$/', $company)) {
        echo json_encode(['success' => false, 'error' => 'El identificador solo puede contener letras minúsculas, números y guiones']);
        return;
    }

    // Verificar que no exista ya
    $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE company = ?");
    $stmt->execute([$company]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ya existe una plantilla con ese identificador']);
        return;
    }

    // Verificar archivo HTML
    if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Error al subir archivo HTML']);
        return;
    }

    $file = $_FILES['template_file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ['html', 'htm'])) {
        echo json_encode(['success' => false, 'error' => 'Solo se permiten archivos HTML']);
        return;
    }

    // Leer contenido HTML
    $htmlContent = file_get_contents($file['tmp_name']);

    if (empty($htmlContent)) {
        echo json_encode(['success' => false, 'error' => 'El archivo HTML está vacío']);
        return;
    }

    // Procesar imagen si existe
    $imagePath = null;
    $imageCid = null;

    if (isset($_FILES['template_image']) && $_FILES['template_image']['error'] === UPLOAD_ERR_OK) {
        $imageFile = $_FILES['template_image'];

        // Validar tipo de imagen
        $imageType = exif_imagetype($imageFile['tmp_name']);
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];

        if (!in_array($imageType, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Solo se permiten imágenes JPG, PNG, GIF']);
            return;
        }

        // Validar tamaño (5MB)
        if ($imageFile['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'La imagen no debe superar 5MB']);
            return;
        }

        // Crear directorio si no existe
        $imageDir = __DIR__ . '/../../uploads/template_images';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        // Generar nombre único para la imagen
        $imageExtension = image_type_to_extension($imageType, false);
        $imageName = $company . '_' . time() . '.' . $imageExtension;
        $imageFullPath = $imageDir . '/' . $imageName;

        // Mover imagen
        if (!move_uploaded_file($imageFile['tmp_name'], $imageFullPath)) {
            echo json_encode(['success' => false, 'error' => 'Error al guardar la imagen']);
            return;
        }

        $imagePath = $imageName;

        // Si es inline, generar CID único
        if ($imageDisplay === 'inline') {
            $imageCid = 'template_' . uniqid() . '@compratica.com';
        }
    } else {
        // Si no hay imagen, forzar display a 'none'
        $imageDisplay = 'none';
    }

    // Si se marca como default, desmarcar otras
    if ($setAsDefault) {
        $pdo->exec("UPDATE email_templates SET is_default = 0");
    }

    // Insertar plantilla
    $stmt = $pdo->prepare("
        INSERT INTO email_templates (
            name, company, subject, html_content, image_path, image_display, image_cid, variables, is_active, is_default
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");

    $variables = json_encode(['nombre', 'email', 'telefono', 'empresa', 'campaign_id', 'tracking_pixel', 'unsubscribe_link', 'template_image']);

    $stmt->execute([
        $name,
        $company,
        $subject,
        $htmlContent,
        $imagePath,
        $imageDisplay,
        $imageCid,
        $variables,
        $setAsDefault ? 1 : 0
    ]);

    $templateId = $pdo->lastInsertId();

    // Guardar también el archivo HTML físico
    $templateDir = __DIR__ . '/../email_templates';
    if (!is_dir($templateDir)) {
        mkdir($templateDir, 0755, true);
    }
    $fileName = $company . '_template.html';
    file_put_contents($templateDir . '/' . $fileName, $htmlContent);

    echo json_encode([
        'success' => true,
        'message' => 'Plantilla subida exitosamente' . ($imagePath ? ' con imagen' : ''),
        'template_id' => $templateId,
        'has_image' => !empty($imagePath),
        'image_display' => $imageDisplay
    ]);
}

/**
 * Enviar email de prueba con plantilla
 */
function handleTestTemplate($pdo) {
    $templateId = intval($_POST['template_id'] ?? 0);
    $testEmail = trim($_POST['test_email'] ?? '');
    $smtpConfigId = intval($_POST['smtp_config_id'] ?? 0);

    if (!$templateId || !$testEmail || !$smtpConfigId) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        return;
    }

    // Validar email
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Email no válido']);
        return;
    }

    // Obtener plantilla
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada']);
        return;
    }

    // Obtener configuración SMTP
    $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
    $stmt->execute([$smtpConfigId]);
    $smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtpConfig) {
        echo json_encode(['success' => false, 'error' => 'Configuración SMTP no encontrada']);
        return;
    }

    // Reemplazar variables con datos de ejemplo
    $html = $template['html_content'];
    $variables = [
        '{nombre}' => 'Usuario de Prueba',
        '{email}' => $testEmail,
        '{telefono}' => '+506-1234-5678',
        '{empresa}' => 'Empresa Ejemplo S.A.',
        '{campaign_id}' => 'TEST',
        '{tracking_pixel}' => 'https://compratica.com/admin/email_track.php?c=test&r=test&t=open',
        '{unsubscribe_link}' => 'https://compratica.com/admin/email_track.php?c=test&r=test&t=unsubscribe'
    ];

    $html = str_replace(array_keys($variables), array_values($variables), $html);

    // Enviar usando EmailSender
    require_once __DIR__ . '/../email_sender.php';

    $emailSender = new EmailSender($smtpConfig, $pdo);

    $recipient = [
        'email' => $testEmail,
        'nombre' => 'Usuario de Prueba',
        'telefono' => '+506-1234-5678',
        'empresa' => 'Empresa Ejemplo S.A.'
    ];

    $subject = '[TEST] ' . ($template['subject_default'] ?? $template['subject'] ?? 'Email de prueba');

    // Preparar datos de imagen si existen
    $templateData = null;
    if (!empty($template['image_path'])) {
        $templateData = [
            'image_path' => $template['image_path'],
            'image_display' => $template['image_display'] ?? 'none',
            'image_cid' => $template['image_cid'] ?? null
        ];
    }

    // Enviar usando el método con soporte de imágenes
    $result = $emailSender->sendWithTemplateImage($recipient, $html, $subject, null, $templateData, null);

    if ($result['success']) {
        $message = '✓ Email de prueba enviado exitosamente a ' . $testEmail;
        if (!empty($templateData)) {
            $message .= ' (con imagen ' . ($templateData['image_display'] === 'inline' ? 'inline' : 'adjunta') . ')';
        }
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al enviar: ' . $result['message']
        ]);
    }
}

/**
 * Marcar plantilla como predeterminada
 */
function handleSetDefault($pdo) {
    $templateId = intval($_POST['template_id'] ?? 0);

    if (!$templateId) {
        echo json_encode(['success' => false, 'error' => 'ID de plantilla no válido']);
        return;
    }

    // Verificar que existe
    $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada']);
        return;
    }

    // Desmarcar todas las demás
    $pdo->exec("UPDATE email_templates SET is_default = 0");

    // Marcar esta como default
    $stmt = $pdo->prepare("UPDATE email_templates SET is_default = 1 WHERE id = ?");
    $stmt->execute([$templateId]);

    echo json_encode(['success' => true, 'message' => 'Plantilla marcada como predeterminada']);
}

/**
 * Activar/Desactivar plantilla
 */
function handleToggleActive($pdo) {
    $templateId = intval($_POST['template_id'] ?? 0);
    $isActive = intval($_POST['is_active'] ?? 0);

    if (!$templateId) {
        echo json_encode(['success' => false, 'error' => 'ID de plantilla no válido']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE email_templates SET is_active = ? WHERE id = ?");
    $stmt->execute([$isActive, $templateId]);

    echo json_encode([
        'success' => true,
        'message' => 'Plantilla ' . ($isActive ? 'activada' : 'desactivada')
    ]);
}

/**
 * Eliminar plantilla
 */
function handleDeleteTemplate($pdo) {
    $templateId = intval($_POST['template_id'] ?? 0);

    if (!$templateId) {
        echo json_encode(['success' => false, 'error' => 'ID de plantilla no válido']);
        return;
    }

    // Obtener información de la plantilla
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada']);
        return;
    }

    // No permitir eliminar plantillas predeterminadas del sistema
    if (in_array($template['company'], ['mixtico', 'crv-soft', 'compratica'])) {
        echo json_encode(['success' => false, 'error' => 'No se pueden eliminar las plantillas predeterminadas del sistema']);
        return;
    }

    // Verificar si está en uso
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_campaigns WHERE template_id = ?");
    $stmt->execute([$templateId]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usage['count'] > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'No se puede eliminar esta plantilla porque está siendo usada en ' . $usage['count'] . ' campaña(s)'
        ]);
        return;
    }

    // Eliminar archivo físico si existe
    $templateFile = __DIR__ . '/../email_templates/' . $template['company'] . '_template.html';
    if (file_exists($templateFile)) {
        @unlink($templateFile);
    }

    // Eliminar de BD
    $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);

    echo json_encode(['success' => true, 'message' => 'Plantilla eliminada exitosamente']);
}
