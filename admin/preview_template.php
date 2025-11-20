<?php
/**
 * Vista Previa de Plantillas de Email
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Conectar a BD
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Obtener plantillas
$templates = $pdo->query("SELECT * FROM email_templates WHERE is_active=1 ORDER BY company")->fetchAll(PDO::FETCH_ASSOC);

$template_id = $_GET['template_id'] ?? null;
$selectedTemplate = null;

if ($template_id) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id=?");
    $stmt->execute([$template_id]);
    $selectedTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si se pide renderizar
if (isset($_GET['render']) && $selectedTemplate) {
    $html = $selectedTemplate['html_content'];

    // Reemplazar variables con datos de ejemplo
    $variables = [
        '{nombre}' => 'Juan Pérez',
        '{email}' => 'ejemplo@hotel.com',
        '{telefono}' => '+506-8888-8888',
        '{empresa}' => 'Hotel Ejemplo S.A.',
        '{campaign_id}' => '123',
        '{tracking_pixel}' => 'https://compratica.com/admin/email_track.php?c=123&r=456&t=open',
        '{unsubscribe_link}' => 'https://compratica.com/admin/email_track.php?c=123&r=456&t=unsubscribe'
    ];

    $html = str_replace(array_keys($variables), array_values($variables), $html);

    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Previa de Plantillas - Email Marketing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #fbbf24 100%);
            padding: 20px 30px;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        .sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .template-list {
            display: grid;
            gap: 15px;
        }
        .template-item {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .template-item:hover {
            border-color: #dc2626;
            background: #fef3c7;
        }
        .template-item.active {
            border-color: #dc2626;
            background: #fee2e2;
        }
        .template-item h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #dc2626;
        }
        .template-item p {
            font-size: 13px;
            color: #64748b;
        }
        .preview-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .preview-header h2 {
            color: #0f172a;
            font-size: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #b91c1c;
        }
        .btn-secondary {
            background: #0891b2;
        }
        .btn-secondary:hover {
            background: #0e7490;
        }
        iframe {
            width: 100%;
            height: 800px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .grid-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        @media (max-width: 768px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎨 Vista Previa de Plantillas</h1>
        <p>Visualiza cómo se verán tus emails antes de enviarlos</p>
    </div>

    <div class="container">
        <div style="margin-bottom:20px;">
            <a href="email_marketing.php?page=templates" class="btn btn-secondary">← Volver a Plantillas</a>
            <a href="email_marketing.php?page=new-campaign" class="btn">Nueva Campaña</a>
        </div>

        <div class="grid-layout">
            <!-- Sidebar con lista de plantillas -->
            <div>
                <div class="sidebar">
                    <h3 style="margin-bottom:15px;color:#0f172a;">Selecciona una Plantilla:</h3>
                    <div class="template-list">
                        <?php foreach ($templates as $tpl): ?>
                            <a href="?template_id=<?= $tpl['id'] ?>" class="template-item <?= ($template_id == $tpl['id']) ? 'active' : '' ?>">
                                <h3><?= htmlspecialchars($tpl['name']) ?></h3>
                                <p><?= htmlspecialchars($tpl['subject']) ?></p>
                                <p style="margin-top:5px;font-size:12px;color:#94a3b8;">
                                    <?= ucfirst($tpl['company']) ?> • <?= number_format(strlen($tpl['html_content'])) ?> bytes
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($templates) === 0): ?>
                        <p style="color:#64748b;text-align:center;padding:20px 0;">
                            No hay plantillas disponibles.<br>
                            <a href="load_email_templates.php" style="color:#dc2626;">Cargar plantillas</a>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($selectedTemplate): ?>
                <div class="sidebar" style="margin-top:20px;">
                    <h4 style="margin-bottom:10px;color:#0f172a;">Variables Disponibles:</h4>
                    <ul style="font-size:13px;color:#64748b;line-height:1.8;">
                        <li><code>{nombre}</code> - Nombre del destinatario</li>
                        <li><code>{email}</code> - Email del destinatario</li>
                        <li><code>{telefono}</code> - Teléfono del destinatario</li>
                        <li><code>{empresa}</code> - Empresa del destinatario</li>
                        <li><code>{campaign_id}</code> - ID de campaña</li>
                        <li><code>{tracking_pixel}</code> - Pixel de tracking</li>
                        <li><code>{unsubscribe_link}</code> - Link cancelar</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <!-- Preview -->
            <div>
                <div class="preview-container">
                    <?php if ($selectedTemplate): ?>
                        <div class="preview-header">
                            <div>
                                <h2><?= htmlspecialchars($selectedTemplate['name']) ?></h2>
                                <p style="color:#64748b;font-size:14px;margin-top:5px;">
                                    <strong>Asunto:</strong> <?= htmlspecialchars($selectedTemplate['subject']) ?>
                                </p>
                            </div>
                        </div>
                        <iframe src="?template_id=<?= $selectedTemplate['id'] ?>&render=1"></iframe>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Selecciona una plantilla</h3>
                            <p>Haz clic en una plantilla de la izquierda para ver su vista previa</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
