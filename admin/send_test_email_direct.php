<?php
/**
 * Envío Directo de Email de Prueba
 * Script simple para enviar un email de prueba a marugaul@gmail.com
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Acceso Denegado</title>";
    echo "<style>body{font-family:Arial;padding:40px;text-align:center;background:#fef3c7}";
    echo ".error-box{background:white;padding:30px;border-radius:12px;max-width:500px;margin:0 auto;box-shadow:0 4px 12px rgba(0,0,0,0.1)}";
    echo "h1{color:#dc2626}</style></head><body>";
    echo "<div class='error-box'>";
    echo "<h1>🔒 Acceso Denegado</h1>";
    echo "<p>Debes iniciar sesión como administrador para acceder a esta página.</p>";
    echo "<p><a href='login.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin-top:10px'>Iniciar Sesión</a></p>";
    echo "</div></body></html>";
    exit;
}

require_once __DIR__ . '/../includes/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Email a marugaul@gmail.com</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#1e293b;color:#e2e8f0;max-width:900px;margin:0 auto}
.step{background:#334155;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #10b981}
.ok{color:#10b981;font-weight:bold}.error{color:#ef4444;font-weight:bold}.warning{color:#f59e0b;font-weight:bold}
pre{background:#0f172a;padding:10px;border-radius:4px;overflow:auto;font-size:11px;border:1px solid #475569}
h1{color:#10b981}h2{color:#6ee7b7}
</style></head><body>";

echo "<h1>📧 Envío de Email de Prueba</h1>";
echo "<h2>Destinatario: marugaul@gmail.com</h2>";

try {
    // PASO 1: Obtener configuración SMTP
    echo "<div class='step'><h3>PASO 1: Obtener Configuración SMTP</h3>";
    $stmt = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id DESC LIMIT 1");
    $smtp_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtp_config) {
        throw new Exception('No hay configuración SMTP disponible');
    }

    echo "<p><span class='ok'>✓ Configuración encontrada: {$smtp_config['config_name']}</span></p>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> {$smtp_config['smtp_host']}</li>";
    echo "<li><strong>Puerto:</strong> {$smtp_config['smtp_port']}</li>";
    echo "<li><strong>Usuario:</strong> {$smtp_config['smtp_username']}</li>";
    echo "<li><strong>Encriptación:</strong> {$smtp_config['smtp_encryption']}</li>";

    if (empty($smtp_config['smtp_password'])) {
        echo "<li><span class='error'>⚠️ CONTRASEÑA NO CONFIGURADA</span></li>";
        echo "</ul>";
        throw new Exception('La contraseña SMTP no está configurada. Ve a edit_smtp_config.php');
    } else {
        echo "<li><strong>Contraseña:</strong> <span class='ok'>✓ Configurada</span></li>";
    }
    echo "</ul>";
    echo "</div>";

    // PASO 2: Cargar PHPMailer
    echo "<div class='step'><h3>PASO 2: Cargar PHPMailer</h3>";
    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception('PHPMailer no está disponible');
    }

    echo "<p><span class='ok'>✓ PHPMailer cargado correctamente</span></p>";
    echo "</div>";

    // PASO 3: Configurar email
    echo "<div class='step'><h3>PASO 3: Configurar y Enviar Email</h3>";

    $mail = new PHPMailer(true);

    // Configuración del servidor
    $mail->isSMTP();
    $mail->Host = $smtp_config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_config['smtp_username'];
    $mail->Password = $smtp_config['smtp_password'];
    $mail->Port = $smtp_config['smtp_port'];

    // Encriptación
    if ($smtp_config['smtp_encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtp_config['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    // Debug nivel 2
    $mail->SMTPDebug = 2;
    $debug_output = '';
    $mail->Debugoutput = function($str, $level) use (&$debug_output) {
        $debug_output .= htmlspecialchars($str) . "<br>";
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
    $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);

    // Destinatario
    $mail->addAddress('marugaul@gmail.com', 'Mario Ugalde');

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = '🧪 Email de Prueba - Sistema Email Marketing COMPRATICA';

    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; }
            .header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
            .content { padding: 30px; background: #f9fafb; }
            .success { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981; }
            .info-box { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { background: #374151; color: #e5e7eb; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;font-size:28px'>✓ Email de Prueba</h1>
                <p style='margin:10px 0 0 0'>Sistema de Email Marketing</p>
            </div>

            <div class='content'>
                <div class='success'>
                    <h2 style='margin-top:0'>🎉 ¡El Sistema Funciona Correctamente!</h2>
                    <p>Si estás leyendo este email, significa que la configuración SMTP está funcionando perfectamente.</p>
                </div>

                <h3>Información del Envío:</h3>
                <div class='info-box'>
                    <p><strong>🌐 Servidor SMTP:</strong> {$smtp_config['smtp_host']}:{$smtp_config['smtp_port']}</p>
                    <p><strong>🔒 Encriptación:</strong> " . strtoupper($smtp_config['smtp_encryption']) . "</p>
                    <p><strong>📧 Remitente:</strong> {$smtp_config['from_email']}</p>
                    <p><strong>👤 Nombre:</strong> {$smtp_config['from_name']}</p>
                    <p><strong>📅 Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><strong>🖥️ Servidor:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'compratica.com') . "</p>
                </div>

                <h3>✅ Próximos Pasos:</h3>
                <ol>
                    <li><strong>Crear Campañas:</strong> Ya puedes crear campañas de email marketing</li>
                    <li><strong>Agregar Destinatarios:</strong> Importa listas de contactos</li>
                    <li><strong>Usar Templates:</strong> Mixtico, CRV-SOFT, CompraTica ya están listos</li>
                    <li><strong>Enviar Masivamente:</strong> El sistema soporta envío por lotes</li>
                </ol>

                <p style='background:#fef3c7;padding:15px;border-radius:8px;border-left:4px solid #f59e0b'>
                    <strong>💡 Tip:</strong> Recuerda verificar tu bandeja de SPAM si no ves los emails en la bandeja principal.
                </p>
            </div>

            <div class='footer'>
                <p><strong>COMPRATICA.COM</strong></p>
                <p>Sistema de Email Marketing Profesional</p>
                <p>Este es un email automático de prueba del sistema.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->AltBody = "Email de Prueba - Sistema Email Marketing COMPRATICA.COM\n\n"
        . "Si ves este mensaje, el sistema está funcionando correctamente.\n\n"
        . "Servidor: {$smtp_config['smtp_host']}:{$smtp_config['smtp_port']}\n"
        . "Fecha: " . date('Y-m-d H:i:s');

    echo "<p><span class='warning'>⏳ Enviando email...</span></p>";

    // Intentar enviar
    ob_start();
    $send_result = $mail->send();
    $output = ob_get_clean();

    if ($send_result) {
        echo "<div style='background:#d1fae5;color:#065f46;padding:20px;border-radius:8px;margin:20px 0;border-left:6px solid #10b981'>";
        echo "<h2 style='margin-top:0'>✓✓✓ EMAIL ENVIADO EXITOSAMENTE!</h2>";
        echo "<p><strong>Destinatario:</strong> marugaul@gmail.com</p>";
        echo "<p><strong>Asunto:</strong> 🧪 Email de Prueba - Sistema Email Marketing COMPRATICA</p>";
        echo "<p>Revisa tu bandeja de entrada (o spam) en unos segundos.</p>";
        echo "</div>";
    }

    echo "</div>";

    // PASO 4: Mostrar debug
    echo "<div class='step'><h3>PASO 4: Debug SMTP</h3>";
    echo "<div style='background:#0f172a;padding:15px;border-radius:8px;overflow:auto;max-height:400px'>";
    echo $debug_output;
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color:#ef4444'>";
    echo "<h3><span class='error'>✗ ERROR AL ENVIAR</span></h3>";
    echo "<p><strong>Mensaje:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";

    if (isset($debug_output) && !empty($debug_output)) {
        echo "<p><strong>Debug Output:</strong></p>";
        echo "<div style='background:#0f172a;padding:15px;border-radius:8px;overflow:auto;max-height:400px'>";
        echo $debug_output;
        echo "</div>";
    }

    echo "<hr>";
    echo "<p><strong>Posibles Soluciones:</strong></p>";
    echo "<ul>";
    echo "<li>Verifica que la contraseña SMTP sea correcta: <a href='edit_smtp_config.php' style='color:#38bdf8'>Editar Config</a></li>";
    echo "<li>Intenta cambiar el puerto (465 SSL o 587 TLS)</li>";
    echo "<li>Verifica que info@mixtico.net exista y esté activo</li>";
    echo "<li>Contacta a tu proveedor de hosting si el puerto está bloqueado</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Email Marketing</a>";
echo "<a href='edit_smtp_config.php' style='display:inline-block;background:#f59e0b;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Editar SMTP</a>";
echo "<a href='debug_email_send.php?campaign_id=1' style='display:inline-block;background:#0891b2;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Debug Campaña</a></p>";

echo "</body></html>";
?>
