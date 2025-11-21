<?php
/**
 * Script de Envío de Campañas
 * Procesa el envío de emails en batch con rate limiting
 */

// Habilitar error reporting completo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_message = '';
$result = null;
$campaign = null;

try {
    require_once __DIR__ . '/../includes/config.php';

    // Verificar auth
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: login.php');
        exit;
    }

    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $campaign_id = $_GET['campaign_id'] ?? 0;

    if (!$campaign_id) {
        throw new Exception('Campaign ID requerido');
    }

    // Obtener campaña
    $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        throw new Exception('Campaña no encontrada (ID: ' . $campaign_id . ')');
    }

    // Obtener SMTP config
    $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
    $stmt->execute([$campaign['smtp_config_id']]);
    $smtp_config = $stmt->fetch();

    if (!$smtp_config) {
        throw new Exception('Configuración SMTP no encontrada (ID: ' . $campaign['smtp_config_id'] . ')');
    }

    // Verificar que PHPMailer esté disponible
    if (!file_exists(__DIR__ . '/../includes/PHPMailer/PHPMailer.php')) {
        throw new Exception('PHPMailer no encontrado en /includes/PHPMailer/');
    }

    require_once __DIR__ . '/email_sender.php';

    if (!class_exists('EmailSender')) {
        throw new Exception('Clase EmailSender no encontrada');
    }

    $mailer = new EmailSender($smtp_config, $pdo);

    // Procesar en modo batch
    $batch_size = 50; // Enviar 50 emails por batch
    $delay = 2; // 2 segundos entre emails

    $result = $mailer->sendCampaign($campaign_id, $batch_size, $delay);

} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Email Marketing Send Error: " . $error_message);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviando Campaña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            max-width: 600px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 25px;
            text-align: center;
        }
        .progress {
            height: 30px;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card w-100">
        <div class="card-header">
            <h3 style="margin: 0;"><i class="fas fa-paper-plane"></i> Enviando Campaña</h3>
        </div>
        <div class="card-body p-4">
            <?php if ($error_message): ?>
                <!-- MOSTRAR ERROR -->
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Error al Enviar Campaña</h5>
                    <hr>
                    <strong>Mensaje de Error:</strong><br>
                    <code><?= htmlspecialchars($error_message) ?></code>
                    <hr>
                    <p class="mb-0">
                        <a href="email_diagnostic.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-stethoscope"></i> Ejecutar Diagnóstico
                        </a>
                        <a href="email_marketing.php?page=campaigns" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver a Campañas
                        </a>
                    </p>
                </div>

                <?php if ($campaign): ?>
                    <h6 class="mt-3">Información de la Campaña:</h6>
                    <pre><?= print_r($campaign, true) ?></pre>
                <?php endif; ?>

            <?php elseif (!$result): ?>
                <!-- NO HAY RESULTADO -->
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i> No se pudo procesar la campaña. Sin resultado.
                </div>

            <?php else: ?>
                <!-- PROCESO NORMAL -->
                <h5><?= htmlspecialchars($campaign['name']) ?></h5>

                <div class="alert alert-info">
                    <strong>Progreso del Envío:</strong><br>
                    Enviados: <strong><?= $result['sent'] ?></strong><br>
                    Fallidos: <strong><?= $result['failed'] ?></strong><br>
                    Pendientes: <strong><?= $result['pending'] ?></strong>
                </div>

            <?php if ($result['pending'] > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-spinner fa-spin"></i> Quedan <?= $result['pending'] ?> emails pendientes.
                    La página se recargará automáticamente para continuar enviando...
                </div>

                <div class="progress mb-3">
                    <?php
                    $total = $campaign['total_recipients'];
                    $sent_total = $campaign['sent_count'] + $result['sent'];
                    $percentage = round(($sent_total / $total) * 100);
                    ?>
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                         style="width: <?= $percentage ?>%">
                        <?= $percentage ?>%
                    </div>
                </div>

                <p class="text-center text-muted">
                    Procesando batch de <?= $batch_size ?> emails cada 5 segundos...
                </p>

                <script>
                // Auto-recargar para continuar enviando
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
                </script>

            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <strong>¡Campaña Enviada Completamente!</strong><br>
                    Total enviados: <?= $campaign['sent_count'] + $result['sent'] ?><br>
                    Total fallidos: <?= $campaign['failed_count'] + $result['failed'] ?>
                </div>

                <div class="progress mb-3">
                    <div class="progress-bar bg-success" style="width: 100%">
                        100%
                    </div>
                </div>

                <div class="text-center">
                    <a href="email_marketing.php?page=campaigns" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Volver a Campañas
                    </a>
                    <a href="email_marketing.php?page=reports" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar"></i> Ver Reportes
                    </a>
                </div>
            <?php endif; ?>
            <?php endif; ?> <!-- Cierre del else principal -->
        </div>
    </div>
</body>
</html>
