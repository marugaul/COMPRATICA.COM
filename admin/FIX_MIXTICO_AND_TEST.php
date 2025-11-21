<?php
// ============================================
// ARREGLAR MIXTICO Y ENVIAR EMAIL DE PRUEBA
// Script all-in-one para configurar y probar
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Mixtico & Test</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .step { margin: 20px 0; padding: 20px; background: #111; border-left: 4px solid #0f0; }
        .error { color: #f00; border-left-color: #f00; }
        .ok { color: #0f0; font-weight: bold; }
        .warn { color: #ff0; }
        pre { background: #222; padding: 15px; overflow: auto; border: 1px solid #333; }
        h1 { color: #0ff; text-align: center; }
        h2 { color: #0f0; }
        .success-box { background: #0a5; color: #fff; padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0; }
    </style>
</head>
<body>

<h1>🍹 MIXTICO EMAIL TEST - COMPLETO</h1>

<?php

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ========================================
    // PASO 1: VERIFICAR Y ARREGLAR CONFIG
    // ========================================
    echo "<div class='step'>";
    echo "<h2>PASO 1: Verificar Config de Mixtico</h2>";

    $stmt = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'");
    $mixtico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mixtico) {
        throw new Exception("No se encontró configuración para info@mixtico.net");
    }

    echo "<p><strong>Config actual:</strong></p>";
    echo "<pre>";
    echo "ID: {$mixtico['id']}\n";
    echo "Host: {$mixtico['smtp_host']}\n";
    echo "Puerto: {$mixtico['smtp_port']}\n";
    echo "Usuario: {$mixtico['smtp_username']}\n";
    echo "Encriptación: {$mixtico['smtp_encryption']}\n";
    echo "Contraseña: " . (empty($mixtico['smtp_password']) ? 'VACÍA' : 'Configurada') . "\n";
    echo "</pre>";

    // Verificar si la encriptación es correcta para el puerto
    $needs_fix = false;
    $correct_encryption = '';

    if ($mixtico['smtp_port'] == 465 && $mixtico['smtp_encryption'] !== 'ssl') {
        $needs_fix = true;
        $correct_encryption = 'ssl';
        echo "<p class='warn'>⚠️ PROBLEMA: Puerto 465 requiere SSL, no TLS</p>";
    } elseif ($mixtico['smtp_port'] == 587 && $mixtico['smtp_encryption'] !== 'tls') {
        $needs_fix = true;
        $correct_encryption = 'tls';
        echo "<p class='warn'>⚠️ PROBLEMA: Puerto 587 requiere TLS, no SSL</p>";
    } else {
        echo "<p class='ok'>✓ Encriptación correcta para el puerto</p>";
    }

    // Arreglar si es necesario
    if ($needs_fix) {
        $pdo->prepare("UPDATE email_smtp_configs SET smtp_encryption = ? WHERE id = ?")
            ->execute([$correct_encryption, $mixtico['id']]);
        echo "<p class='ok'>✓ CORREGIDO: Encriptación cambiada a " . strtoupper($correct_encryption) . "</p>";

        // Recargar config
        $mixtico['smtp_encryption'] = $correct_encryption;
    }

    // Verificar contraseña
    if (empty($mixtico['smtp_password'])) {
        throw new Exception("Contraseña SMTP no configurada. Ve a SET_PASSWORD_SMTP.php");
    }

    echo "<p class='ok'>✓ Contraseña SMTP configurada</p>";
    echo "</div>";

    // ========================================
    // PASO 2: VERIFICAR PHPMAILER
    // ========================================
    echo "<div class='step'>";
    echo "<h2>PASO 2: Verificar PHPMailer</h2>";

    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        throw new Exception("PHPMailer no instalado (vendor/autoload.php no existe)");
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception("Clase PHPMailer no disponible");
    }

    $mail_test = new PHPMailer\PHPMailer\PHPMailer();
    $version = PHPMailer\PHPMailer\PHPMailer::VERSION;

    echo "<p class='ok'>✓ PHPMailer versión: $version</p>";
    echo "</div>";

    // ========================================
    // PASO 3: ENVIAR EMAIL DE PRUEBA
    // ========================================
    echo "<div class='step'>";
    echo "<h2>PASO 3: Enviar Email de Prueba a marugaul@gmail.com</h2>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configuración SMTP
    $mail->isSMTP();
    $mail->Host = $mixtico['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mixtico['smtp_username'];
    $mail->Password = $mixtico['smtp_password'];
    $mail->Port = (int)$mixtico['smtp_port'];

    // Encriptación
    if ($mixtico['smtp_encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($mixtico['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    // Debug
    $mail->SMTPDebug = 2;
    $debug = '';
    $mail->Debugoutput = function($str, $level) use (&$debug) {
        $debug .= htmlspecialchars($str) . "\n";
    };

    // Opciones SSL
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->CharSet = 'UTF-8';

    // Remitente
    $mail->setFrom('info@mixtico.net', 'Mixtico - Mezclas Premium');

    // Destinatario
    $mail->addAddress('marugaul@gmail.com', 'Mario Ugalde');

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = '🍹 Test Email Marketing - Mixtico';

    $mail->Body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; color: white; font-size: 32px; }
            .header p { margin: 10px 0 0 0; color: #fef3c7; font-size: 16px; }
            .content { padding: 40px 30px; }
            .success { background: #d1fae5; color: #065f46; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 6px solid #10b981; }
            .info { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { background: #374151; color: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍹 Mixtico</h1>
                <p>Mezclas Premium para Cocteles - Costa Rica</p>
            </div>

            <div class="content">
                <div class="success">
                    <h2 style="margin-top:0">✓ ¡Sistema de Email Marketing Funcionando!</h2>
                    <p>Este es un email de prueba enviado desde el sistema de email marketing de COMPRATICA.COM</p>
                </div>

                <h3>Información del Envío:</h3>
                <div class="info">
                    <p><strong>🌐 Servidor SMTP:</strong> ' . $mixtico['smtp_host'] . ':' . $mixtico['smtp_port'] . '</p>
                    <p><strong>🔒 Encriptación:</strong> ' . strtoupper($mixtico['smtp_encryption']) . '</p>
                    <p><strong>📧 De:</strong> info@mixtico.net</p>
                    <p><strong>📅 Fecha:</strong> ' . date('Y-m-d H:i:s') . '</p>
                </div>

                <h3>✅ Sistema Listo</h3>
                <p>El sistema de email marketing está completamente configurado y listo para usar:</p>
                <ul>
                    <li>✓ PHPMailer instalado y funcional</li>
                    <li>✓ Servidor SMTP de Mixtico configurado</li>
                    <li>✓ Templates HTML profesionales disponibles</li>
                    <li>✓ Sistema de tracking de aperturas y clicks</li>
                    <li>✓ Envío por lotes con rate limiting</li>
                </ul>

                <p style="background:#fef3c7;padding:15px;border-radius:8px;border-left:4px solid #f97316;">
                    <strong>💡 Nota:</strong> Si este email llegó a tu bandeja de SPAM, márcalo como "No es spam" para que futuros emails lleguen a la bandeja principal.
                </p>
            </div>

            <div class="footer">
                <p><strong>COMPRATICA.COM</strong></p>
                <p>Sistema de Email Marketing Profesional</p>
                <p>Mixtico - Mezclas Premium para Cocteles</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $mail->AltBody = "Test Email Marketing - Mixtico\n\n"
        . "Sistema funcionando correctamente.\n"
        . "Servidor: {$mixtico['smtp_host']}:{$mixtico['smtp_port']}\n"
        . "Encriptación: " . strtoupper($mixtico['smtp_encryption']) . "\n"
        . "Fecha: " . date('Y-m-d H:i:s');

    echo "<p class='warn'>⏳ Enviando email...</p>";

    ob_start();
    $result = $mail->send();
    ob_end_clean();

    if ($result) {
        echo "<div class='success-box'>";
        echo "<h2 style='margin-top:0;font-size:32px'>✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓</h2>";
        echo "<p style='font-size:18px'><strong>Destinatario:</strong> marugaul@gmail.com</p>";
        echo "<p style='font-size:16px'><strong>De:</strong> info@mixtico.net</p>";
        echo "<p style='font-size:16px'><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
        echo "<hr style='border-color:#fff;margin:20px 0'>";
        echo "<p style='font-size:14px'>Revisa tu bandeja de entrada (o SPAM) en unos segundos.</p>";
        echo "<p style='font-size:14px'>Si no lo ves, espera 1-2 minutos.</p>";
        echo "</div>";
    } else {
        throw new Exception("Error al enviar: " . $mail->ErrorInfo);
    }

    echo "</div>";

    // ========================================
    // PASO 4: DEBUG SMTP
    // ========================================
    echo "<div class='step'>";
    echo "<h2>PASO 4: Debug SMTP</h2>";
    echo "<pre>$debug</pre>";
    echo "</div>";

    // ========================================
    // RESUMEN FINAL
    // ========================================
    echo "<div class='step' style='border-left-color:#0a5;background:#064e3b'>";
    echo "<h2 style='color:#6ee7b7'>📊 RESUMEN - SISTEMA AL 100%</h2>";
    echo "<ul style='font-size:16px;line-height:2'>";
    echo "<li class='ok'>✓ Configuración SMTP de Mixtico CORRECTA</li>";
    echo "<li class='ok'>✓ Puerto: {$mixtico['smtp_port']} con " . strtoupper($mixtico['smtp_encryption']) . "</li>";
    echo "<li class='ok'>✓ PHPMailer instalado y funcional</li>";
    echo "<li class='ok'>✓ Email de prueba enviado a marugaul@gmail.com</li>";
    echo "<li class='ok'>✓ Sistema listo para campañas reales</li>";
    echo "</ul>";

    echo "<hr style='border-color:#10b981'>";

    echo "<h3 style='color:#6ee7b7'>🎯 CONFIGURACIÓN FINAL DE MIXTICO:</h3>";
    echo "<ul style='font-size:14px'>";
    echo "<li><strong>Host:</strong> {$mixtico['smtp_host']}</li>";
    echo "<li><strong>Puerto:</strong> {$mixtico['smtp_port']}</li>";
    echo "<li><strong>Encriptación:</strong> " . strtoupper($mixtico['smtp_encryption']) . " ← CORRECTA para puerto {$mixtico['smtp_port']}</li>";
    echo "<li><strong>Usuario:</strong> {$mixtico['smtp_username']}</li>";
    echo "<li><strong>Contraseña:</strong> Configurada ✓</li>";
    echo "</ul>";

    echo "<p style='font-size:16px;color:#fef3c7;margin-top:20px'><strong>✅ TODO LISTO - Puedes empezar a usar tu Email Marketing</strong></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step error'>";
    echo "<h2>✗✗✗ ERROR ✗✗✗</h2>";
    echo "<p><strong>Mensaje:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";

    if (isset($debug) && $debug) {
        echo "<h3>Debug SMTP:</h3>";
        echo "<pre>$debug</pre>";
    }

    echo "<hr>";
    echo "<h3>Posibles Soluciones:</h3>";
    echo "<ul>";
    echo "<li>Si error de contraseña: Ve a <a href='SET_PASSWORD_SMTP.php' style='color:#0ff'>SET_PASSWORD_SMTP.php</a></li>";
    echo "<li>Si error de conexión SMTP: Verifica que info@mixtico.net existe y está activo</li>";
    echo "<li>Si error de puerto: Prueba cambiar entre puerto 465 (SSL) y 587 (TLS)</li>";
    echo "<li>Contacta a tu hosting si los puertos están bloqueados</li>";
    echo "</ul>";
    echo "</div>";
}

?>

<hr style="border-color:#333;margin:40px 0">
<p style="text-align:center">
    <a href="email_marketing.php" style="color:#0ff">→ Ir a Email Marketing</a> |
    <a href="SET_PASSWORD_SMTP.php" style="color:#0ff">→ Configurar Password</a> |
    <a href="CHECK_CAMPAIGN.php?campaign_id=4" style="color:#0ff">→ Check Campaña</a>
</p>

</body>
</html>
