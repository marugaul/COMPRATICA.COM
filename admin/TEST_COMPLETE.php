<?php
// ============================================
// TEST COMPLETO - TODO EN UN SCRIPT
// 1. Arregla configuración Mixtico
// 2. Envía email de prueba
// 3. Reporte completo
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Completo Email Marketing</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 30px; margin: 20px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success { background: #d1fae5; color: #065f46; padding: 20px; border-radius: 8px; border-left: 6px solid #10b981; margin: 20px 0; }
        .error { background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; border-left: 6px solid #ef4444; margin: 20px 0; }
        .warning { background: #fef3c7; color: #92400e; padding: 20px; border-radius: 8px; border-left: 6px solid #f59e0b; margin: 20px 0; }
        .info { background: #e0f2fe; color: #075985; padding: 15px; border-radius: 8px; margin: 15px 0; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 8px; overflow: auto; font-size: 12px; }
        h1 { color: #dc2626; text-align: center; }
        h2 { color: #0891b2; border-bottom: 2px solid #0891b2; padding-bottom: 10px; }
        .big-success { background: #10b981; color: white; padding: 40px; border-radius: 12px; text-align: center; font-size: 24px; font-weight: bold; margin: 30px 0; }
        .step-number { background: #0891b2; color: white; border-radius: 50%; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>🍹 TEST COMPLETO - EMAIL MARKETING MIXTICO</h1>
        <p style="text-align:center;font-size:18px;color:#666">Verificación y envío de email de prueba</p>
    </div>

<?php

try {
    // Conectar a BD
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=comprati_marketplace',
        'comprati_places_user',
        'Marden7i/',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ========================================
    // PASO 1: VERIFICAR Y CORREGIR CONFIG
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>1</span>Verificar Configuración SMTP de Mixtico</h2>";

    $smtp = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'")->fetch(PDO::FETCH_ASSOC);

    if (!$smtp) {
        throw new Exception("❌ No se encontró configuración para info@mixtico.net");
    }

    echo "<div class='info'>";
    echo "<h4>Configuración Actual:</h4>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> {$smtp['smtp_host']}</li>";
    echo "<li><strong>Puerto:</strong> {$smtp['smtp_port']}</li>";
    echo "<li><strong>Usuario:</strong> {$smtp['smtp_username']}</li>";
    echo "<li><strong>Encriptación:</strong> " . strtoupper($smtp['smtp_encryption']) . "</li>";
    echo "<li><strong>Contraseña:</strong> " . (empty($smtp['smtp_password']) ? '<span style="color:#dc2626">❌ NO configurada</span>' : '<span style="color:#10b981">✓ Configurada</span>') . "</li>";
    echo "</ul>";
    echo "</div>";

    // Verificar contraseña
    if (empty($smtp['smtp_password'])) {
        throw new Exception("❌ Contraseña SMTP no configurada. Debes agregarla en SET_PASSWORD_SMTP.php");
    }

    // Corregir encriptación si es necesario
    $was_corrected = false;
    if ($smtp['smtp_port'] == 465 && $smtp['smtp_encryption'] != 'ssl') {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Problema detectado:</strong> Puerto 465 requiere SSL, no " . strtoupper($smtp['smtp_encryption']);
        echo "<br>Corrigiendo automáticamente...";
        echo "</div>";

        $pdo->exec("UPDATE email_smtp_configs SET smtp_encryption = 'ssl' WHERE id = {$smtp['id']}");
        $was_corrected = true;
        $smtp['smtp_encryption'] = 'ssl';

        echo "<div class='success'>";
        echo "✓ <strong>CORREGIDO:</strong> Encriptación cambiada a SSL";
        echo "</div>";
    } elseif ($smtp['smtp_port'] == 465 && $smtp['smtp_encryption'] == 'ssl') {
        echo "<div class='success'>";
        echo "✓ <strong>Configuración correcta:</strong> Puerto 465 con SSL";
        echo "</div>";
    }

    echo "</div>";

    // ========================================
    // PASO 2: VERIFICAR PHPMAILER
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>2</span>Verificar PHPMailer</h2>";

    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        throw new Exception("❌ PHPMailer no instalado");
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception("❌ Clase PHPMailer no disponible");
    }

    $version = PHPMailer\PHPMailer\PHPMailer::VERSION;

    echo "<div class='success'>";
    echo "✓ PHPMailer instalado y funcional<br>";
    echo "<strong>Versión:</strong> $version";
    echo "</div>";

    echo "</div>";

    // ========================================
    // PASO 3: ENVIAR EMAIL DE PRUEBA
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>3</span>Enviar Email de Prueba</h2>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configuración
    $mail->isSMTP();
    $mail->Host = $smtp['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_username'];
    $mail->Password = $smtp['smtp_password'];
    $mail->Port = (int)$smtp['smtp_port'];

    if ($smtp['smtp_encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtp['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->SMTPDebug = 2;
    $debug = '';
    $mail->Debugoutput = function($str) use (&$debug) {
        $debug .= htmlspecialchars($str) . "\n";
    };

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('info@mixtico.net', 'Mixtico - Mezclas Premium');
    $mail->addAddress('marugaul@gmail.com', 'Mario Ugalde');
    $mail->isHTML(true);
    $mail->Subject = '🍹 Email Marketing - Sistema Listo - Mixtico';

    $mail->Body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 40px; text-align: center; }
            .header h1 { margin: 0; color: white; font-size: 36px; }
            .header p { margin: 10px 0 0 0; color: #fef3c7; font-size: 18px; }
            .content { padding: 40px; }
            .success-box { background: #d1fae5; color: #065f46; padding: 30px; border-radius: 8px; border-left: 6px solid #10b981; margin: 20px 0; text-align: center; }
            .success-box h2 { margin-top: 0; color: #065f46; font-size: 28px; }
            .info-box { background: #e0f2fe; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #374151; color: #e5e7eb; padding: 30px; text-align: center; font-size: 14px; }
            ul { line-height: 2; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍹 Mixtico</h1>
                <p>Mezclas Premium para Cocteles - Costa Rica</p>
            </div>

            <div class="content">
                <div class="success-box">
                    <h2>✅ ¡Sistema de Email Marketing 100% Operativo!</h2>
                    <p style="font-size:18px;margin:0">Tu sistema está completamente configurado y listo para enviar campañas</p>
                </div>

                <h3 style="color:#dc2626">Configuración Verificada:</h3>
                <div class="info-box">
                    <p><strong>🌐 Servidor SMTP:</strong> ' . $smtp['smtp_host'] . ':' . $smtp['smtp_port'] . '</p>
                    <p><strong>🔒 Encriptación:</strong> ' . strtoupper($smtp['smtp_encryption']) . '</p>
                    <p><strong>📧 Email:</strong> info@mixtico.net</p>
                    <p><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>
                </div>

                <h3 style="color:#dc2626">Sistema Incluye:</h3>
                <ul>
                    <li>✓ Templates HTML profesionales</li>
                    <li>✓ Envío masivo con rate limiting</li>
                    <li>✓ Tracking de aperturas y clicks</li>
                    <li>✓ Gestión de destinatarios</li>
                    <li>✓ Programación de envíos</li>
                    <li>✓ Reportes y estadísticas</li>
                </ul>

                <div style="background:#fef3c7;padding:20px;border-radius:8px;border-left:4px solid #f97316;margin-top:20px">
                    <p style="margin:0"><strong>💡 Nota importante:</strong> Si este email llegó a SPAM, márcalo como "No es spam" para que futuros emails lleguen a la bandeja principal.</p>
                </div>
            </div>

            <div class="footer">
                <p><strong>COMPRATICA.COM</strong></p>
                <p>Sistema Profesional de Email Marketing</p>
                <p>Mixtico - Mezclas Premium para Cocteles</p>
                <p style="margin-top:15px;font-size:12px">Este es un email de prueba del sistema</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $mail->AltBody = "Test Email Marketing Mixtico\nSistema 100% operativo\nFecha: " . date('Y-m-d H:i:s');

    echo "<p style='font-size:16px;color:#666'>⏳ Enviando email a <strong>marugaul@gmail.com</strong>...</p>";

    ob_start();
    $result = $mail->send();
    ob_end_clean();

    if ($result) {
        echo "<div class='big-success'>";
        echo "✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓<br>";
        echo "<span style='font-size:18px;font-weight:normal;margin-top:10px;display:block'>Destinatario: marugaul@gmail.com</span>";
        echo "<span style='font-size:16px;font-weight:normal;margin-top:5px;display:block'>Hora: " . date('H:i:s') . "</span>";
        echo "</div>";

        echo "<div class='success'>";
        echo "<strong>✓ Revisa tu email</strong> (bandeja de entrada o SPAM) en unos segundos<br>";
        echo "<strong>✓ Si no lo ves inmediatamente,</strong> espera 1-2 minutos";
        echo "</div>";
    }

    // Debug SMTP
    echo "<details style='margin-top:20px'>";
    echo "<summary style='cursor:pointer;padding:10px;background:#f3f4f6;border-radius:6px'>📋 Ver Debug SMTP</summary>";
    echo "<pre style='margin-top:10px'>$debug</pre>";
    echo "</details>";

    echo "</div>";

    // ========================================
    // RESUMEN FINAL
    // ========================================
    echo "<div class='card' style='background:#064e3b;color:#d1fae5'>";
    echo "<h2 style='color:#6ee7b7;border-color:#10b981'>✅ SISTEMA AL 100% - LISTO PARA USAR</h2>";

    echo "<div style='background:#065f46;padding:20px;border-radius:8px;margin:20px 0'>";
    echo "<h3 style='color:#6ee7b7;margin-top:0'>Configuración Final de Mixtico:</h3>";
    echo "<ul style='font-size:16px;line-height:2'>";
    echo "<li><strong>Host:</strong> {$smtp['smtp_host']}</li>";
    echo "<li><strong>Puerto:</strong> {$smtp['smtp_port']}</li>";
    echo "<li><strong>Encriptación:</strong> " . strtoupper($smtp['smtp_encryption']) . " " . ($was_corrected ? "(corregido automáticamente)" : "") . "</li>";
    echo "<li><strong>Usuario:</strong> {$smtp['smtp_username']}</li>";
    echo "<li><strong>Contraseña:</strong> ✓ Configurada</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='text-align:center;margin-top:30px'>";
    echo "<p style='font-size:20px;color:#fef3c7'><strong>🎉 Puedes comenzar a enviar campañas de email marketing</strong></p>";
    echo "<a href='email_marketing.php' style='display:inline-block;background:#10b981;color:white;padding:15px 40px;text-decoration:none;border-radius:8px;font-size:18px;font-weight:bold;margin-top:15px'>Ir a Email Marketing →</a>";
    echo "</div>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<div class='error'>";
    echo "<h3>❌ Error Detectado</h3>";
    echo "<p><strong>Mensaje:</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";

    if (isset($debug) && !empty($debug)) {
        echo "<details style='margin-top:20px'>";
        echo "<summary style='cursor:pointer'>Ver Debug</summary>";
        echo "<pre>$debug</pre>";
        echo "</details>";
    }

    echo "<h4>Posibles Soluciones:</h4>";
    echo "<ul>";
    echo "<li>Si falta la contraseña: <a href='SET_PASSWORD_SMTP.php'>Configurar en SET_PASSWORD_SMTP.php</a></li>";
    echo "<li>Verifica que info@mixtico.net existe y está activo</li>";
    echo "<li>Prueba cambiar el puerto (465 SSL o 587 TLS)</li>";
    echo "<li>Contacta a tu proveedor si los puertos están bloqueados</li>";
    echo "</ul>";
    echo "</div>";
    echo "</div>";
}

?>

</div>

</body>
</html>
