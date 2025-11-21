<?php
/**
 * Test de Conexión SMTP
 * Prueba la conexión al servidor SMTP y opcionalmente envía un email de prueba
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación - si no está logueado, mostrar error amigable
$is_authenticated = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$is_authenticated) {
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

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Obtener configuración SMTP
$smtp_id = $_GET['id'] ?? 0;
if ($smtp_id) {
    $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
    $stmt->execute([$smtp_id]);
    $smtp_config = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $smtp_config = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Procesar envío de email de prueba
$test_result = '';
$test_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;

        $test_email = $_POST['test_email'];

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

        // Debug
        $mail->SMTPDebug = 2;
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
        $mail->addAddress($test_email);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = '🧪 Test de Configuración SMTP - ' . $smtp_config['config_name'];
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px;'>
                <h2 style='color: #dc2626;'>✓ Email de Prueba Enviado Correctamente</h2>
                <hr>
                <p>Este es un email de prueba del sistema de Email Marketing de COMPRATICA.COM</p>
                <p><strong>Configuración SMTP utilizada:</strong></p>
                <ul>
                    <li><strong>Nombre:</strong> {$smtp_config['config_name']}</li>
                    <li><strong>Host:</strong> {$smtp_config['smtp_host']}</li>
                    <li><strong>Puerto:</strong> {$smtp_config['smtp_port']}</li>
                    <li><strong>Encriptación:</strong> {$smtp_config['smtp_encryption']}</li>
                    <li><strong>Usuario:</strong> {$smtp_config['smtp_username']}</li>
                </ul>
                <hr>
                <p style='color: #16a34a; font-weight: bold;'>Si recibiste este email, la configuración SMTP está funcionando correctamente! 🎉</p>
                <p style='color: #6b7280; font-size: 12px; margin-top: 20px;'>
                    Enviado desde: " . $_SERVER['HTTP_HOST'] . "<br>
                    Fecha: " . date('Y-m-d H:i:s') . "
                </p>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Test de Email Marketing - Si ves este mensaje, la configuración SMTP funciona correctamente.";

        // Intentar enviar
        ob_start();
        $send_result = $mail->send();
        $debug_output .= ob_get_clean();

        if ($send_result) {
            $test_result = "✓ Email de prueba enviado correctamente a: " . $test_email;
        }

    } catch (Exception $e) {
        $test_error = "Error al enviar: " . $mail->ErrorInfo . "<br><br>Excepción: " . $e->getMessage();
        $debug_output .= "<br><strong>Error Completo:</strong><br>" . $e->getTraceAsString();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Conexión SMTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            padding: 30px 15px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }
        .debug-output {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        .config-table th {
            background: #f0fdf4;
            font-weight: 600;
        }
        .config-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 900px;">

        <?php if (!$smtp_config): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> No se encontró configuración SMTP.
                <a href="update_smtp_mixtico.php" class="btn btn-sm btn-primary ms-2">Crear Configuración</a>
            </div>
        <?php else: ?>

        <!-- Información de Configuración -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-server"></i> Test de Conexión SMTP</h3>
            </div>
            <div class="card-body">
                <h5>Configuración Actual:</h5>
                <table class="table config-table">
                    <tr>
                        <th width="200">Nombre</th>
                        <td><?= htmlspecialchars($smtp_config['config_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Host SMTP</th>
                        <td><?= htmlspecialchars($smtp_config['smtp_host']) ?></td>
                    </tr>
                    <tr>
                        <th>Puerto</th>
                        <td><?= $smtp_config['smtp_port'] ?></td>
                    </tr>
                    <tr>
                        <th>Usuario</th>
                        <td><?= htmlspecialchars($smtp_config['smtp_username']) ?></td>
                    </tr>
                    <tr>
                        <th>Encriptación</th>
                        <td><?= strtoupper($smtp_config['smtp_encryption']) ?></td>
                    </tr>
                    <tr>
                        <th>Contraseña</th>
                        <td>
                            <?php if (!empty($smtp_config['smtp_password'])): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> Configurada (••••••••)</span>
                            <?php else: ?>
                                <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> NO configurada</span>
                                <a href="edit_smtp_config.php?id=<?= $smtp_config['id'] ?>" class="btn btn-sm btn-warning ms-2">Agregar Contraseña</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Email Remitente</th>
                        <td><?= htmlspecialchars($smtp_config['from_email']) ?></td>
                    </tr>
                    <tr>
                        <th>Nombre Remitente</th>
                        <td><?= htmlspecialchars($smtp_config['from_name']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Formulario de Test -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);">
                <h4 class="mb-0"><i class="fas fa-paper-plane"></i> Enviar Email de Prueba</h4>
            </div>
            <div class="card-body">
                <?php if ($test_result): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $test_result ?>
                    </div>
                <?php endif; ?>

                <?php if ($test_error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= $test_error ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($smtp_config['smtp_password'])): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email de Destino (para recibir el test):</label>
                            <input type="email" name="test_email" class="form-control"
                                   placeholder="tucorreo@ejemplo.com" required>
                            <small class="text-muted">Ingresa un email válido donde recibirás el mensaje de prueba</small>
                        </div>
                        <button type="submit" name="send_test" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Enviar Email de Prueba
                        </button>
                        <a href="edit_smtp_config.php?id=<?= $smtp_config['id'] ?>" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Editar Configuración
                        </a>
                        <a href="email_marketing.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Debes configurar la contraseña SMTP antes de poder enviar emails de prueba.
                        <br><br>
                        <a href="edit_smtp_config.php?id=<?= $smtp_config['id'] ?>" class="btn btn-warning">
                            <i class="fas fa-cog"></i> Configurar Contraseña Ahora
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Debug Output -->
        <?php if (isset($debug_output) && !empty($debug_output)): ?>
        <div class="card">
            <div class="card-header" style="background: #1e293b;">
                <h5 class="mb-0 text-white"><i class="fas fa-terminal"></i> Salida de Debug SMTP</h5>
            </div>
            <div class="card-body">
                <div class="debug-output">
                    <?= $debug_output ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</body>
</html>
