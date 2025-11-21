<?php
// ============================================
// TEST EMAIL MIXTICO - Usando PHPMailer existente
// Usa PHPMailer de /includes/PHPMailer/
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email Mixtico</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 30px; margin: 20px 0; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
        .success { background: #d1fae5; color: #065f46; padding: 25px; border-radius: 8px; border-left: 6px solid #10b981; margin: 20px 0; }
        .error { background: #fee2e2; color: #991b1b; padding: 25px; border-radius: 8px; border-left: 6px solid #ef4444; margin: 20px 0; }
        .warning { background: #fef3c7; color: #92400e; padding: 20px; border-radius: 8px; border-left: 6px solid #f59e0b; }
        .info { background: #fed7aa; color: #7c2d12; padding: 20px; border-radius: 8px; margin: 20px 0; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 8px; overflow: auto; font-size: 12px; border: 1px solid #ddd; }
        h1 { color: #dc2626; text-align: center; margin: 0; }
        h2 { color: #f97316; border-bottom: 2px solid #fed7aa; padding-bottom: 10px; }
        .big-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 50px; border-radius: 12px; text-align: center; font-size: 28px; font-weight: bold; margin: 30px 0; box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3); }
        .step-number { background: #f97316; color: white; border-radius: 50%; width: 45px; height: 45px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 20px; margin-right: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card" style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);">
        <h1 style="color: #7c2d12; font-size: 36px;">🍹 TEST EMAIL MIXTICO</h1>
        <p style="text-align:center;font-size:20px;color:#7c2d12;font-weight:bold;margin:10px 0 0 0">Sistema de Email Marketing</p>
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
    // PASO 1: VERIFICAR CONFIG SMTP
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>1</span>Configuración SMTP de Mixtico</h2>";

    $smtp = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'")->fetch(PDO::FETCH_ASSOC);

    if (!$smtp) {
        throw new Exception("❌ No se encontró configuración para info@mixtico.net");
    }

    echo "<div class='info'>";
    echo "<h4 style='margin-top:0'>Configuración Actual:</h4>";
    echo "<table style='width:100%;border-collapse:collapse'>";
    echo "<tr><td style='padding:8px;border:1px solid #ddd'><strong>Host</strong></td><td style='padding:8px;border:1px solid #ddd'>{$smtp['smtp_host']}</td></tr>";
    echo "<tr><td style='padding:8px;border:1px solid #ddd'><strong>Puerto</strong></td><td style='padding:8px;border:1px solid #ddd'>{$smtp['smtp_port']}</td></tr>";
    echo "<tr><td style='padding:8px;border:1px solid #ddd'><strong>Usuario</strong></td><td style='padding:8px;border:1px solid #ddd'>{$smtp['smtp_username']}</td></tr>";
    echo "<tr><td style='padding:8px;border:1px solid #ddd'><strong>Encriptación</strong></td><td style='padding:8px;border:1px solid #ddd'>" . strtoupper($smtp['smtp_encryption']) . "</td></tr>";
    echo "<tr><td style='padding:8px;border:1px solid #ddd'><strong>Contraseña</strong></td><td style='padding:8px;border:1px solid #ddd'>" . (empty($smtp['smtp_password']) ? '<span style="color:#dc2626">❌ NO configurada</span>' : '<span style="color:#10b981">✓ Configurada</span>') . "</td></tr>";
    echo "</table>";
    echo "</div>";

    if (empty($smtp['smtp_password'])) {
        throw new Exception("❌ Contraseña SMTP no configurada");
    }

    // Corregir encriptación si es necesario
    if ($smtp['smtp_port'] == 465 && $smtp['smtp_encryption'] != 'ssl') {
        echo "<div class='warning'>";
        echo "⚠️ Corrigiendo encriptación: Puerto 465 requiere SSL<br>";
        echo "Actualizando...";
        echo "</div>";
        $pdo->exec("UPDATE email_smtp_configs SET smtp_encryption = 'ssl' WHERE id = {$smtp['id']}");
        $smtp['smtp_encryption'] = 'ssl';
        echo "<div class='success'>✓ Encriptación corregida a SSL</div>";
    } else {
        echo "<div class='success'>✓ Configuración correcta</div>";
    }

    echo "</div>";

    // ========================================
    // PASO 2: CARGAR PHPMAILER
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>2</span>Cargar PHPMailer</h2>";

    // Intentar cargar desde /includes/PHPMailer/
    $phpmailer_path = __DIR__ . '/../includes/PHPMailer/PHPMailer.php';

    if (!file_exists($phpmailer_path)) {
        throw new Exception("❌ PHPMailer no encontrado en: $phpmailer_path");
    }

    require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../includes/PHPMailer/Exception.php';

    echo "<div class='success'>";
    echo "✓ PHPMailer cargado desde /includes/PHPMailer/<br>";
    echo "✓ Archivos: PHPMailer.php, SMTP.php, Exception.php";
    echo "</div>";

    echo "</div>";

    // ========================================
    // PASO 3: ENVIAR EMAIL DE PRUEBA
    // ========================================
    echo "<div class='card'>";
    echo "<h2><span class='step-number'>3</span>Enviar Email de Prueba</h2>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configuración SMTP
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
    $mail->Subject = '🍹 Sistema Email Marketing Listo - Mixtico';

    $mail->Body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #fef3c7; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
            .header { background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 50px 30px; text-align: center; }
            .header h1 { margin: 0; color: white; font-size: 42px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
            .header p { margin: 15px 0 0 0; color: #fef3c7; font-size: 20px; }
            .content { padding: 40px 30px; }
            .success-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 30px; border-radius: 12px; text-align: center; margin: 25px 0; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
            .success-box h2 { margin: 0; color: #065f46; font-size: 28px; }
            .info-box { background: #fed7aa; padding: 25px; border-radius: 12px; margin: 25px 0; border-left: 6px solid #f97316; }
            .footer { background: #7c2d12; color: #fed7aa; padding: 30px; text-align: center; }
            ul { line-height: 2.2; font-size: 16px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍹 Mixtico</h1>
                <p>Mezclas Premium para Cocteles</p>
            </div>

            <div class="content">
                <div class="success-box">
                    <h2>✅ ¡Sistema 100% Operativo!</h2>
                    <p style="font-size:18px;margin:10px 0 0 0">Email Marketing completamente configurado y listo</p>
                </div>

                <h3 style="color:#dc2626;font-size:22px">Configuración Verificada:</h3>
                <div class="info-box">
                    <p style="margin:8px 0"><strong>🌐 Servidor:</strong> ' . $smtp['smtp_host'] . ':' . $smtp['smtp_port'] . '</p>
                    <p style="margin:8px 0"><strong>🔒 Seguridad:</strong> ' . strtoupper($smtp['smtp_encryption']) . '</p>
                    <p style="margin:8px 0"><strong>📧 Email:</strong> info@mixtico.net</p>
                    <p style="margin:8px 0"><strong>📅 Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>
                </div>

                <h3 style="color:#dc2626;font-size:22px">Funcionalidades Incluidas:</h3>
                <ul>
                    <li>✓ Templates HTML profesionales personalizados</li>
                    <li>✓ Envío masivo con control de velocidad</li>
                    <li>✓ Tracking de aperturas y clicks en tiempo real</li>
                    <li>✓ Gestión avanzada de destinatarios</li>
                    <li>✓ Programación de campañas</li>
                    <li>✓ Reportes y estadísticas detalladas</li>
                    <li>✓ Sistema anti-spam integrado</li>
                </ul>

                <div style="background:#fef3c7;padding:25px;border-radius:12px;border-left:6px solid #f97316;margin-top:25px">
                    <p style="margin:0;font-size:16px"><strong>💡 Importante:</strong> Si este email llegó a tu carpeta de SPAM, márcalo como "No es spam" para asegurar que futuros emails de campañas lleguen directamente a la bandeja principal.</p>
                </div>
            </div>

            <div class="footer">
                <p style="font-size:18px;margin:0"><strong>COMPRATICA.COM</strong></p>
                <p style="margin:10px 0;font-size:16px">Sistema Profesional de Email Marketing</p>
                <p style="margin:10px 0;font-size:16px"><strong>Mixtico - Mezclas Premium para Cocteles</strong></p>
                <p style="margin:20px 0 0 0;font-size:13px;color:#fdba74">Email de verificación del sistema • ' . date('Y') . '</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $mail->AltBody = "Test Email Marketing Mixtico\nSistema 100% operativo\nFecha: " . date('Y-m-d H:i:s');

    echo "<p style='font-size:18px;color:#666'>⏳ Enviando email a <strong style='color:#dc2626'>marugaul@gmail.com</strong>...</p>";

    ob_start();
    $result = $mail->send();
    ob_end_clean();

    if ($result) {
        echo "<div class='big-success'>";
        echo "✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓<br>";
        echo "<div style='font-size:20px;font-weight:normal;margin-top:15px'>Destinatario: marugaul@gmail.com</div>";
        echo "<div style='font-size:18px;font-weight:normal;margin-top:8px'>Hora: " . date('H:i:s d/m/Y') . "</div>";
        echo "</div>";

        echo "<div class='success'>";
        echo "<h3 style='margin-top:0'>✓ Próximos Pasos:</h3>";
        echo "<ol style='line-height:2;font-size:16px'>";
        echo "<li>Revisa tu bandeja de entrada de <strong>marugaul@gmail.com</strong></li>";
        echo "<li>Si no lo ves, revisa la carpeta de <strong>SPAM</strong></li>";
        echo "<li>Espera 1-2 minutos si aún no aparece</li>";
        echo "<li>Una vez confirmado, ¡puedes empezar a crear campañas!</li>";
        echo "</ol>";
        echo "</div>";
    }

    echo "<details style='margin-top:25px'>";
    echo "<summary style='cursor:pointer;padding:12px;background:#f3f4f6;border-radius:8px;font-weight:bold'>📋 Ver Debug SMTP Completo</summary>";
    echo "<pre style='margin-top:15px'>$debug</pre>";
    echo "</details>";

    echo "</div>";

    // ========================================
    // RESUMEN FINAL
    // ========================================
    echo "<div class='card' style='background:linear-gradient(135deg, #064e3b 0%, #065f46 100%);color:#d1fae5'>";
    echo "<h2 style='color:#6ee7b7;border-color:#10b981'>🎉 SISTEMA AL 100% - COMPLETAMENTE FUNCIONAL</h2>";

    echo "<div style='background:#065f46;padding:25px;border-radius:12px;margin:20px 0'>";
    echo "<h3 style='color:#6ee7b7;margin-top:0;font-size:22px'>Configuración Final:</h3>";
    echo "<table style='width:100%;color:#d1fae5'>";
    echo "<tr><td style='padding:10px;font-size:16px'><strong>Host:</strong></td><td style='padding:10px;font-size:16px'>{$smtp['smtp_host']}</td></tr>";
    echo "<tr><td style='padding:10px;font-size:16px'><strong>Puerto:</strong></td><td style='padding:10px;font-size:16px'>{$smtp['smtp_port']}</td></tr>";
    echo "<tr><td style='padding:10px;font-size:16px'><strong>Encriptación:</strong></td><td style='padding:10px;font-size:16px'>" . strtoupper($smtp['smtp_encryption']) . "</td></tr>";
    echo "<tr><td style='padding:10px;font-size:16px'><strong>Usuario:</strong></td><td style='padding:10px;font-size:16px'>{$smtp['smtp_username']}</td></tr>";
    echo "<tr><td style='padding:10px;font-size:16px'><strong>Estado:</strong></td><td style='padding:10px;font-size:16px'><span style='color:#6ee7b7'>✓ Operativo</span></td></tr>";
    echo "</table>";
    echo "</div>";

    echo "<div style='text-align:center;margin-top:35px'>";
    echo "<p style='font-size:24px;color:#fef3c7;font-weight:bold;margin-bottom:20px'>🎯 ¡Listo para Email Marketing!</p>";
    echo "<a href='email_marketing.php' style='display:inline-block;background:#10b981;color:white;padding:18px 45px;text-decoration:none;border-radius:10px;font-size:20px;font-weight:bold;box-shadow:0 4px 12px rgba(16,185,129,0.3)'>Ir a Email Marketing →</a>";
    echo "</div>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<div class='error'>";
    echo "<h3 style='margin-top:0'>❌ Error Detectado</h3>";
    echo "<p style='font-size:16px'><strong>Mensaje:</strong></p>";
    echo "<p style='font-size:16px'>" . htmlspecialchars($e->getMessage()) . "</p>";

    if (isset($debug) && !empty($debug)) {
        echo "<details style='margin-top:20px'>";
        echo "<summary style='cursor:pointer'>Ver Debug</summary>";
        echo "<pre>$debug</pre>";
        echo "</details>";
    }

    echo "<h4>Posibles Soluciones:</h4>";
    echo "<ul style='line-height:2'>";
    echo "<li>Verifica que la contraseña de info@mixtico.net sea correcta</li>";
    echo "<li>Confirma que el email info@mixtico.net existe y está activo</li>";
    echo "<li>Prueba cambiar entre puerto 465 (SSL) y 587 (TLS)</li>";
    echo "<li>Contacta a tu proveedor de hosting si los puertos están bloqueados</li>";
    echo "</ul>";
    echo "</div>";
    echo "</div>";
}

?>

</div>

</body>
</html>
