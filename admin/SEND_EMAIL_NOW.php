<?php
// ============================================
// TEST EMAIL DIRECTO - SIN COMPLICACIONES
// Envía email a marugaul@gmail.com
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bypass temporal de auth para testing (QUITAR DESPUÉS)
// Comentar estas 2 líneas después de probar:
$_SESSION['is_admin'] = true;
$_SESSION['admin_user'] = 'test';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .step { margin: 20px 0; padding: 15px; background: #111; border-left: 4px solid #0f0; }
        .error { color: #f00; border-left-color: #f00; }
        .ok { color: #0f0; font-weight: bold; }
        .warn { color: #ff0; }
        pre { background: #222; padding: 10px; overflow: auto; }
    </style>
</head>
<body>

<h1>EMAIL TEST - EJECUTANDO...</h1>

<?php

try {
    echo "<div class='step'>";
    echo "<h3>PASO 1: Conectar Base de Datos</h3>";

    $config = require __DIR__ . '/../config/database.php';

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<p class='ok'>✓ Conectado a: {$config['database']}</p>";
    echo "</div>";

    // PASO 2: Config SMTP
    echo "<div class='step'>";
    echo "<h3>PASO 2: Obtener Config SMTP</h3>";

    $stmt = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id DESC LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtp) {
        throw new Exception("No hay config SMTP");
    }

    echo "<p class='ok'>✓ Config: {$smtp['config_name']}</p>";
    echo "<p>Host: {$smtp['smtp_host']}:{$smtp['smtp_port']}</p>";
    echo "<p>Usuario: {$smtp['smtp_username']}</p>";
    echo "<p>Encriptación: {$smtp['smtp_encryption']}</p>";

    if (empty($smtp['smtp_password'])) {
        echo "<p class='warn'>⚠ CONTRASEÑA VACÍA - NO SE PUEDE ENVIAR</p>";
        throw new Exception("Contraseña SMTP no configurada. Ve a: https://compratica.com/admin/edit_smtp_config.php");
    }

    echo "<p class='ok'>✓ Contraseña configurada</p>";
    echo "</div>";

    // PASO 3: PHPMailer
    echo "<div class='step'>";
    echo "<h3>PASO 3: Cargar PHPMailer</h3>";

    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($autoload)) {
        throw new Exception("PHPMailer no instalado: $autoload no existe");
    }

    require_once $autoload;

    echo "<p class='ok'>✓ Autoloader cargado</p>";

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception("Clase PHPMailer no disponible");
    }

    echo "<p class='ok'>✓ Clase PHPMailer disponible</p>";
    echo "</div>";

    // PASO 4: ENVIAR
    echo "<div class='step'>";
    echo "<h3>PASO 4: ENVIAR EMAIL</h3>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Configuración
    $mail->isSMTP();
    $mail->Host = $smtp['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_username'];
    $mail->Password = $smtp['smtp_password'];
    $mail->Port = (int)$smtp['smtp_port'];

    // Encriptación
    if ($smtp['smtp_encryption'] === 'ssl') {
        $mail->SMTPSecure = 'ssl';
    } elseif ($smtp['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = 'tls';
    }

    // Debug
    $mail->SMTPDebug = 2;
    $debug = '';
    $mail->Debugoutput = function($str, $level) use (&$debug) {
        $debug .= htmlspecialchars($str) . "\n";
    };

    // SSL Options
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
    $mail->addAddress('marugaul@gmail.com', 'Mario Ugalde');

    $mail->isHTML(true);
    $mail->Subject = 'TEST - Email Marketing COMPRATICA';
    $mail->Body = '
    <html>
    <body style="font-family: Arial; padding: 20px;">
        <h1 style="color: #10b981;">✓ Email Enviado Correctamente</h1>
        <p>Este email fue enviado desde <strong>COMPRATICA.COM</strong></p>
        <p>Fecha: ' . date('Y-m-d H:i:s') . '</p>
        <hr>
        <p><strong>Servidor SMTP:</strong> ' . $smtp['smtp_host'] . ':' . $smtp['smtp_port'] . '</p>
        <p><strong>De:</strong> ' . $smtp['from_email'] . '</p>
        <hr>
        <h2 style="color: #10b981;">🎉 ¡El sistema funciona!</h2>
        <p>Ya puedes enviar campañas de email marketing.</p>
    </body>
    </html>
    ';

    $mail->AltBody = 'TEST - Email Marketing COMPRATICA - El sistema funciona correctamente';

    echo "<p class='warn'>⏳ Enviando...</p>";

    ob_start();
    $result = $mail->send();
    ob_end_clean();

    if ($result) {
        echo "<div style='background: #0a5; color: #fff; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h2>✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓</h2>";
        echo "<p><strong>Destinatario:</strong> marugaul@gmail.com</p>";
        echo "<p><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
        echo "<p>Revisa tu bandeja de entrada o SPAM en unos segundos.</p>";
        echo "</div>";
    } else {
        throw new Exception("Error al enviar: " . $mail->ErrorInfo);
    }

    echo "</div>";

    // Debug output
    echo "<div class='step'>";
    echo "<h3>DEBUG SMTP:</h3>";
    echo "<pre>$debug</pre>";
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
    echo "<h3>SOLUCIONES:</h3>";
    echo "<ul>";
    echo "<li>Agregar contraseña SMTP: <a href='edit_smtp_config.php' style='color:#0ff'>edit_smtp_config.php</a></li>";
    echo "<li>Verificar que info@mixtico.net existe</li>";
    echo "<li>Probar puerto 587 con TLS en lugar de 465 SSL</li>";
    echo "<li>Contactar hosting si puertos están bloqueados</li>";
    echo "</ul>";
    echo "</div>";
}

?>

<hr>
<p>
    <a href="edit_smtp_config.php" style="color:#0ff">Editar SMTP Config</a> |
    <a href="email_marketing.php" style="color:#0ff">Email Marketing</a>
</p>

<p style="color:#666;font-size:11px">
    NOTA: Este script tiene bypass de autenticación para testing.<br>
    Después de verificar que funciona, edita el archivo y comenta las líneas del bypass.
</p>

</body>
</html>
