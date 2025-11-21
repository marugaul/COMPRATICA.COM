<?php
/**
 * Test Email Super Simple
 * Sin dependencias complicadas - solo envía un email
 */

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar auth
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Login Requerido</title>";
    echo "<style>body{font-family:Arial;padding:40px;text-align:center;background:#fef3c7}";
    echo ".box{background:white;padding:30px;border-radius:12px;max-width:500px;margin:0 auto;box-shadow:0 4px 12px rgba(0,0,0,0.1)}";
    echo "h1{color:#dc2626}</style></head><body>";
    echo "<div class='box'><h1>🔒 Login Requerido</h1>";
    echo "<p>Debes estar logueado como admin</p>";
    echo "<p><a href='login.php' style='background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block'>Iniciar Sesión</a></p>";
    echo "</div></body></html>";
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// HTML Header
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Simple</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            border-left: 6px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 8px;
            border-left: 6px solid #ef4444;
        }
        .info {
            background: #e0f2fe;
            color: #075985;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        pre {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            overflow: auto;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px;
        }
        .btn:hover { background: #b91c1c; }
    </style>
</head>
<body>

<div class="card">
    <h1>📧 Test de Email Simple</h1>
    <p><strong>Destinatario:</strong> marugaul@gmail.com</p>
    <hr>

<?php

try {
    // PASO 1: Obtener config SMTP
    echo "<h3>PASO 1: Obtener Configuración SMTP</h3>";

    $stmt = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id DESC LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$smtp) {
        throw new Exception('No hay configuración SMTP. <a href="update_smtp_mixtico.php">Crear una aquí</a>');
    }

    echo "<div class='info'>";
    echo "<strong>Config ID:</strong> {$smtp['id']}<br>";
    echo "<strong>Nombre:</strong> {$smtp['config_name']}<br>";
    echo "<strong>Host:</strong> {$smtp['smtp_host']}<br>";
    echo "<strong>Puerto:</strong> {$smtp['smtp_port']}<br>";
    echo "<strong>Usuario:</strong> {$smtp['smtp_username']}<br>";
    echo "<strong>Encriptación:</strong> " . strtoupper($smtp['smtp_encryption']) . "<br>";

    if (empty($smtp['smtp_password'])) {
        echo "<strong style='color:#dc2626'>⚠️ CONTRASEÑA: NO CONFIGURADA</strong><br>";
        throw new Exception('Contraseña SMTP vacía. <a href="edit_smtp_config.php?id=' . $smtp['id'] . '">Agregar contraseña</a>');
    } else {
        echo "<strong style='color:#10b981'>✓ CONTRASEÑA: Configurada</strong>";
    }
    echo "</div>";

    // PASO 2: Cargar PHPMailer
    echo "<h3>PASO 2: Cargar PHPMailer</h3>";

    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        throw new Exception('PHPMailer no está instalado (vendor/autoload.php no existe)');
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception('Clase PHPMailer no disponible');
    }

    echo "<p style='color:#10b981'>✓ PHPMailer cargado correctamente</p>";

    // PASO 3: Configurar y enviar
    echo "<h3>PASO 3: Enviar Email</h3>";
    echo "<p>Configurando mail server...</p>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtp['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_username'];
    $mail->Password = $smtp['smtp_password'];
    $mail->Port = $smtp['smtp_port'];

    // Encryption
    if ($smtp['smtp_encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtp['smtp_encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    // Debug output
    $mail->SMTPDebug = 2;
    $debug = '';
    $mail->Debugoutput = function($str) use (&$debug) {
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

    // From
    $mail->setFrom($smtp['from_email'], $smtp['from_name']);

    // To
    $mail->addAddress('marugaul@gmail.com', 'Mario Ugalde');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - COMPRATICA Email Marketing';

    $mail->Body = '
    <html>
    <body style="font-family: Arial; padding: 20px; background: #f5f5f5;">
        <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px;">
            <h1 style="color: #dc2626;">✓ Email de Prueba Exitoso</h1>
            <hr>
            <p>Este email fue enviado desde el sistema de Email Marketing de COMPRATICA.COM</p>
            <h3>Configuración Utilizada:</h3>
            <ul>
                <li><strong>Servidor:</strong> ' . htmlspecialchars($smtp['smtp_host']) . ':' . $smtp['smtp_port'] . '</li>
                <li><strong>De:</strong> ' . htmlspecialchars($smtp['from_email']) . '</li>
                <li><strong>Fecha:</strong> ' . date('Y-m-d H:i:s') . '</li>
            </ul>
            <p style="background: #d1fae5; padding: 15px; border-radius: 8px; color: #065f46;">
                <strong>🎉 ¡El sistema funciona correctamente!</strong><br>
                Ya puedes enviar campañas de email marketing.
            </p>
        </div>
    </body>
    </html>
    ';

    $mail->AltBody = 'Test Email - El sistema funciona correctamente';

    echo "<p>Enviando...</p>";

    ob_start();
    $result = $mail->send();
    ob_end_clean();

    if ($result) {
        echo "<div class='success'>";
        echo "<h2 style='margin-top:0'>✓✓✓ EMAIL ENVIADO EXITOSAMENTE</h2>";
        echo "<p><strong>Destinatario:</strong> marugaul@gmail.com</p>";
        echo "<p>Revisa tu bandeja de entrada (o carpeta de SPAM) en unos segundos.</p>";
        echo "</div>";
    } else {
        throw new Exception('Error al enviar: ' . $mail->ErrorInfo);
    }

    // Mostrar debug
    echo "<h3>Debug SMTP:</h3>";
    echo "<pre>" . $debug . "</pre>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>✗ Error</h2>";
    echo "<p><strong>Mensaje:</strong></p>";
    echo "<p>" . $e->getMessage() . "</p>";

    if (isset($debug) && !empty($debug)) {
        echo "<h3>Debug Output:</h3>";
        echo "<pre>" . $debug . "</pre>";
    }

    echo "<hr>";
    echo "<p><strong>Posibles Soluciones:</strong></p>";
    echo "<ul>";
    echo "<li>Verifica la contraseña SMTP en <a href='edit_smtp_config.php'>edit_smtp_config.php</a></li>";
    echo "<li>Prueba cambiar el puerto (465 SSL o 587 TLS)</li>";
    echo "<li>Verifica que info@mixtico.net exista</li>";
    echo "</ul>";
    echo "</div>";
}

?>

    <hr>
    <p>
        <a href="email_marketing.php" class="btn">Email Marketing</a>
        <a href="edit_smtp_config.php" class="btn" style="background:#f59e0b">Editar SMTP</a>
        <a href="debug_email_send.php?campaign_id=1" class="btn" style="background:#0891b2">Debug Campaña</a>
    </p>

</div>

</body>
</html>
