<?php
/**
 * Email Tracking - Opens, Clicks, Unsubscribes
 */

require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_GET['action'] ?? '';
$code = $_GET['code'] ?? '';

if (empty($code)) {
    die('Invalid tracking code');
}

// Buscar destinatario por tracking code
$stmt = $pdo->prepare("SELECT * FROM email_recipients WHERE tracking_code = ?");
$stmt->execute([$code]);
$recipient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipient) {
    die('Recipient not found');
}

switch ($action) {
    case 'open':
        // Registrar apertura (solo la primera vez)
        if (empty($recipient['opened_at'])) {
            $pdo->prepare("UPDATE email_recipients SET opened_at = NOW() WHERE id = ?")
                ->execute([$recipient['id']]);

            // Incrementar contador de campaña
            $pdo->prepare("UPDATE email_campaigns SET opened_count = opened_count + 1 WHERE id = ?")
                ->execute([$recipient['campaign_id']]);
        }

        // Devolver pixel transparente 1x1
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // GIF transparente de 1x1 pixel
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;

    case 'click':
        // Registrar click (solo el primero)
        if (empty($recipient['clicked_at'])) {
            $pdo->prepare("UPDATE email_recipients SET clicked_at = NOW() WHERE id = ?")
                ->execute([$recipient['id']]);

            // Incrementar contador de campaña
            $pdo->prepare("UPDATE email_campaigns SET clicked_count = clicked_count + 1 WHERE id = ?")
                ->execute([$recipient['campaign_id']]);
        }

        // Redirigir a URL original
        $url = $_GET['url'] ?? '';
        if (!empty($url)) {
            header('Location: ' . $url);
            exit;
        }

        die('No URL specified');

    case 'unsubscribe':
        // Marcar como bounced (no volverá a recibir emails)
        $pdo->prepare("UPDATE email_recipients SET status = 'bounced' WHERE id = ?")
            ->execute([$recipient['id']]);

        // Mostrar página de confirmación
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cancelar Suscripción</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .card {
                    max-width: 500px;
                    border: none;
                    border-radius: 12px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle" style="font-size: 64px; color: #16a34a;"></i>
                    </div>
                    <h3 class="mb-3">Suscripción Cancelada</h3>
                    <p class="text-muted mb-4">
                        Tu email <strong><?= htmlspecialchars($recipient['email']) ?></strong>
                        ha sido eliminado de nuestra lista de correos.
                    </p>
                    <p class="text-muted small">
                        Ya no recibirás emails de esta campaña.
                    </p>
                </div>
            </div>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </body>
        </html>
        <?php
        exit;

    default:
        die('Invalid action');
}
?>
