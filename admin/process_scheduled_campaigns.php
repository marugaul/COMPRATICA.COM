<?php
/**
 * Procesar Campañas Programadas
 * Este script debe ejecutarse cada minuto via cron para enviar campañas programadas
 */

// Configurar logging
$logFile = '/home/comprati/CampanasProgramadas';
$logSeparator = str_repeat('=', 80);

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // También output para cron
}

writeLog($logSeparator);
writeLog("INICIO - Procesador de Campañas Programadas");
writeLog("Hora del servidor (UTC): " . date('Y-m-d H:i:s'));
writeLog("Hora Costa Rica (UTC-6): " . date('Y-m-d H:i:s', strtotime('-6 hours')));

try {
    // Cargar configuración
    writeLog("Cargando configuración...");
    require_once __DIR__ . '/../includes/config.php';

    $config = require __DIR__ . '/../config/database.php';
    writeLog("Conectando a base de datos: {$config['database']}@{$config['host']}");

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    writeLog("✓ Conexión a BD exitosa");

    // Buscar campañas programadas cuya hora ya llegó
    writeLog("Buscando campañas programadas...");

    $stmt = $pdo->query("
        SELECT * FROM email_campaigns
        WHERE status = 'scheduled'
        AND scheduled_at <= NOW()
        ORDER BY scheduled_at ASC
    ");

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // También mostrar TODAS las campañas programadas (para debug)
    $allScheduled = $pdo->query("
        SELECT id, name, status, scheduled_at,
               TIMESTAMPDIFF(MINUTE, NOW(), scheduled_at) as minutes_remaining
        FROM email_campaigns
        WHERE status = 'scheduled'
        ORDER BY scheduled_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allScheduled)) {
        writeLog("--- Todas las Campañas Programadas en BD ---");
        foreach ($allScheduled as $sc) {
            writeLog("  ID: {$sc['id']} | {$sc['name']} | Programada: {$sc['scheduled_at']} | Faltan: {$sc['minutes_remaining']} min");
        }
    } else {
        writeLog("No hay campañas con status='scheduled' en la base de datos");
    }

    if (empty($campaigns)) {
        writeLog("✓ No hay campañas programadas listas para enviar en este momento");
        writeLog($logSeparator);
        exit(0);
    }

    writeLog("¡Encontradas " . count($campaigns) . " campañas listas para enviar!");

    // Procesar cada campaña
    foreach ($campaigns as $campaign) {
        writeLog("");
        writeLog("--- Procesando Campaña ID: {$campaign['id']} ---");
        writeLog("Nombre: {$campaign['name']}");
        writeLog("Programada para: {$campaign['scheduled_at']}");
        writeLog("SMTP Config ID: {$campaign['smtp_config_id']}");
        writeLog("Template ID: {$campaign['template_id']}");

        try {
            // Marcar como enviando
            writeLog("Marcando campaña como 'sending'...");
            $pdo->prepare("
                UPDATE email_campaigns
                SET status = 'sending', started_at = NOW()
                WHERE id = ?
            ")->execute([$campaign['id']]);
            writeLog("✓ Campaña marcada como 'sending'");

            // Verificar que tenga destinatarios
            $recipient_count = $pdo->query("
                SELECT COUNT(*) FROM email_recipients
                WHERE campaign_id = {$campaign['id']} AND status = 'pending'
            ")->fetchColumn();

            writeLog("Destinatarios pendientes: {$recipient_count}");

            if ($recipient_count == 0) {
                writeLog("⚠️  Campaña sin destinatarios pendientes, marcando como completada");
                $pdo->prepare("
                    UPDATE email_campaigns
                    SET status = 'completed', completed_at = NOW()
                    WHERE id = ?
                ")->execute([$campaign['id']]);
                continue;
            }

            // Verificar PHPMailer
            $phpmailerPath = __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
            if (!file_exists($phpmailerPath)) {
                throw new Exception("PHPMailer no encontrado en: {$phpmailerPath}");
            }
            writeLog("✓ PHPMailer encontrado");

            // Cargar EmailSender
            require_once __DIR__ . '/email_sender.php';
            writeLog("✓ EmailSender cargado");

            // Obtener SMTP config
            $smtp_config = $pdo->query("
                SELECT * FROM email_smtp_configs
                WHERE id = {$campaign['smtp_config_id']}
            ")->fetch(PDO::FETCH_ASSOC);

            if (!$smtp_config) {
                throw new Exception("Configuración SMTP no encontrada (ID: {$campaign['smtp_config_id']})");
            }

            writeLog("✓ SMTP Config: {$smtp_config['smtp_host']}:{$smtp_config['smtp_port']}");
            writeLog("  Usuario SMTP: {$smtp_config['smtp_username']}");

            $mailer = new EmailSender($smtp_config, $pdo);
            writeLog("✓ Mailer inicializado");

            // Enviar en batch (50 emails por vez con delay de 2 segundos)
            $batch_size = 50;
            $delay = 2;

            writeLog("Iniciando envío: batch_size={$batch_size}, delay={$delay}s");
            $result = $mailer->sendCampaign($campaign['id'], $batch_size, $delay);

            writeLog("✅ RESULTADO DEL ENVÍO:");
            writeLog("   Enviados: {$result['sent']}");
            writeLog("   Fallidos: {$result['failed']}");
            writeLog("   Pendientes: {$result['pending']}");

            if ($result['pending'] > 0) {
                writeLog("ℹ️  Quedan emails pendientes, se enviarán en el próximo ciclo");
            }

        } catch (Exception $e) {
            writeLog("❌ ERROR procesando campaña ID {$campaign['id']}:");
            writeLog("   Mensaje: " . $e->getMessage());
            writeLog("   Archivo: " . $e->getFile() . ":" . $e->getLine());
            writeLog("   Trace: " . $e->getTraceAsString());

            // Marcar campaña como fallida
            $pdo->prepare("
                UPDATE email_campaigns
                SET status = 'failed'
                WHERE id = ?
            ")->execute([$campaign['id']]);
            writeLog("   Campaña marcada como 'failed'");
        }
    }

    writeLog("");
    writeLog("✓ Proceso completado exitosamente");

} catch (Exception $e) {
    writeLog("❌ ERROR FATAL:");
    writeLog("   Mensaje: " . $e->getMessage());
    writeLog("   Archivo: " . $e->getFile() . ":" . $e->getLine());
    writeLog("   Trace: " . $e->getTraceAsString());
}

writeLog($logSeparator);
writeLog("");

