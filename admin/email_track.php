<?php
/**
 * Email Tracking System
 * Registra aperturas, clicks y desuscripciones
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
$tracking_code = $_GET['code'] ?? '';

if (empty($tracking_code)) {
    http_response_code(404);
    exit;
}

// Buscar destinatario
$recipient = $pdo->prepare("SELECT * FROM email_recipients WHERE tracking_code = ?");
$recipient->execute([$tracking_code]);
$recipient = $recipient->fetch(PDO::FETCH_ASSOC);

if (!$recipient) {
    http_response_code(404);
    exit;
}

switch ($action) {
    case 'open':
        trackOpen($pdo, $recipient);
        break;

    case 'click':
        trackClick($pdo, $recipient);
        break;

    case 'unsubscribe':
        trackUnsubscribe($pdo, $recipient);
        break;

    default:
        http_response_code(400);
        exit;
}

/**
 * Tracking de apertura
 */
function trackOpen($pdo, $recipient) {
    // Solo registrar la primera apertura
    if (empty($recipient['opened_at'])) {
        $pdo->prepare("UPDATE email_recipients SET opened_at = NOW() WHERE id = ?")
            ->execute([$recipient['id']]);

        // Actualizar contador de campaña
        $pdo->prepare("UPDATE email_campaigns SET opened_count = opened_count + 1 WHERE id = ?")
            ->execute([$recipient['campaign_id']]);
    }

    // Devolver pixel transparente 1x1
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Pixel transparente de 1x1 en base64
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    echo $pixel;
    exit;
}

/**
 * Tracking de clicks
 */
function trackClick($pdo, $recipient) {
    $url = $_GET['url'] ?? '';

    if (empty($url)) {
        http_response_code(400);
        exit;
    }

    // Registrar primer click
    if (empty($recipient['clicked_at'])) {
        $pdo->prepare("UPDATE email_recipients SET clicked_at = NOW() WHERE id = ?")
            ->execute([$recipient['id']]);

        // Actualizar contador de campaña
        $pdo->prepare("UPDATE email_campaigns SET clicked_count = clicked_count + 1 WHERE id = ?")
            ->execute([$recipient['campaign_id']]);
    }

    // Log detallado de click (opcional - para análisis avanzado)
    try {
        $pdo->prepare("
            INSERT INTO email_click_logs (recipient_id, campaign_id, url, clicked_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$recipient['id'], $recipient['campaign_id'], $url]);
    } catch (PDOException $e) {
        // Tabla opcional - ignorar error si no existe
    }

    // Redirigir al URL original
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Desuscripción
 */
function trackUnsubscribe($pdo, $recipient) {
    // Marcar como desuscrito
    $pdo->prepare("UPDATE email_recipients SET status = 'unsubscribed' WHERE id = ?")
        ->execute([$recipient['id']]);

    // Crear entrada en lista de bloqueados global (opcional)
    try {
        $pdo->prepare("
            INSERT IGNORE INTO email_unsubscribes (email, unsubscribed_at)
            VALUES (?, NOW())
        ")->execute([$recipient['email']]);
    } catch (PDOException $e) {
        // Tabla opcional
    }

    // Mostrar página de confirmación
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Desuscripción Exitosa</title>
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
            .card-header {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                color: white;
                border-radius: 12px 12px 0 0 !important;
                padding: 25px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;"><i class="fas fa-check-circle"></i> Desuscripción Exitosa</h3>
                </div>
                <div class="card-body text-center p-5">
                    <i class="fas fa-envelope-open-text fa-4x text-muted mb-4"></i>
                    <h5>Has sido desuscrito correctamente</h5>
                    <p class="text-muted">
                        El email <strong><?= htmlspecialchars($recipient['email']) ?></strong>
                        ha sido removido de nuestra lista de correos.
                    </p>
                    <p class="small text-muted mt-4">
                        Lamentamos que te vayas. Si cambiás de opinión, podés
                        volver a suscribirte en cualquier momento.
                    </p>
                    <hr>
                    <a href="https://compratica.com" class="btn btn-outline-primary">
                        Volver al sitio
                    </a>
                </div>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}
?>
