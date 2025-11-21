<?php
/**
 * Procesar Campañas Programadas
 * Este script debe ejecutarse cada minuto via cron para enviar campañas programadas
 */

// Cargar configuración
require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Buscar campañas programadas cuya hora ya llegó
$stmt = $pdo->query("
    SELECT * FROM email_campaigns
    WHERE status = 'scheduled'
    AND scheduled_at <= NOW()
    ORDER BY scheduled_at ASC
");

$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($campaigns)) {
    echo date('Y-m-d H:i:s') . " - No hay campañas programadas para enviar\n";
    exit(0);
}

echo date('Y-m-d H:i:s') . " - Encontradas " . count($campaigns) . " campañas programadas\n";

// Procesar cada campaña
foreach ($campaigns as $campaign) {
    echo "Procesando campaña ID {$campaign['id']}: {$campaign['name']}\n";

    try {
        // Marcar como enviando
        $pdo->prepare("
            UPDATE email_campaigns
            SET status = 'sending', started_at = NOW()
            WHERE id = ?
        ")->execute([$campaign['id']]);

        // Verificar que tenga destinatarios
        $recipient_count = $pdo->query("
            SELECT COUNT(*) FROM email_recipients
            WHERE campaign_id = {$campaign['id']} AND status = 'pending'
        ")->fetchColumn();

        if ($recipient_count == 0) {
            echo "  ⚠️  Campaña sin destinatarios pendientes, marcando como completada\n";
            $pdo->prepare("
                UPDATE email_campaigns
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ")->execute([$campaign['id']]);
            continue;
        }

        // Cargar EmailSender
        require_once __DIR__ . '/email_sender.php';

        // Obtener SMTP config
        $smtp_config = $pdo->query("
            SELECT * FROM email_smtp_configs
            WHERE id = {$campaign['smtp_config_id']}
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$smtp_config) {
            throw new Exception("Configuración SMTP no encontrada");
        }

        $mailer = new EmailSender($smtp_config, $pdo);

        // Enviar en batch (50 emails por vez con delay de 2 segundos)
        $batch_size = 50;
        $delay = 2;

        $result = $mailer->sendCampaign($campaign['id'], $batch_size, $delay);

        echo "  ✅ Enviados: {$result['sent']}, Fallidos: {$result['failed']}, Pendientes: {$result['pending']}\n";

        // Si quedan pendientes, dejar en 'sending' para el próximo ciclo
        // Si no quedan pendientes, se marcará automáticamente como 'completed' por sendCampaign()

    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";

        // Marcar campaña como fallida
        $pdo->prepare("
            UPDATE email_campaigns
            SET status = 'failed'
            WHERE id = ?
        ")->execute([$campaign['id']]);
    }
}

echo date('Y-m-d H:i:s') . " - Proceso completado\n";
