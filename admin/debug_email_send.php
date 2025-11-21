<?php
/**
 * Debug Completo del Proceso de Envío
 * Muestra paso a paso qué ocurre al intentar enviar
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

$campaign_id = $_GET['campaign_id'] ?? 1;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Envío Email</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#1e293b;color:#e2e8f0;max-width:1200px;margin:0 auto}
.step{background:#334155;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #0891b2}
.ok{color:#10b981;font-weight:bold}.error{color:#ef4444;font-weight:bold}.warning{color:#f59e0b;font-weight:bold}
pre{background:#0f172a;padding:10px;border-radius:4px;overflow:auto;font-size:11px;border:1px solid #475569}
table{width:100%;border-collapse:collapse;margin:10px 0;background:#1e293b}
table th{background:#475569;text-align:left;padding:8px;color:white}
table td{border:1px solid #475569;padding:8px;color:#e2e8f0}
h1{color:#38bdf8}h2{color:#7dd3fc}h3{color:#bae6fd}
</style></head><body>";

echo "<h1>🔍 Debug Completo - Campaña ID: $campaign_id</h1>";

try {
    // PASO 1: Base de datos
    echo "<div class='step'><h3>PASO 1: Conectar a Base de Datos</h3>";
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p><span class='ok'>✓ Conectado a: {$config['database']}</span></p></div>";

    // PASO 2: Campaña
    echo "<div class='step'><h3>PASO 2: Obtener Campaña</h3>";
    $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($campaign) {
        echo "<p><span class='ok'>✓ Campaña encontrada</span></p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        foreach ($campaign as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='error'>✗ Campaña NO encontrada</span></p>";
        die("</div></body></html>");
    }
    echo "</div>";

    // PASO 3: SMTP Config
    echo "<div class='step'><h3>PASO 3: Obtener Configuración SMTP</h3>";
    $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
    $stmt->execute([$campaign['smtp_config_id']]);
    $smtp_config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp_config) {
        echo "<p><span class='ok'>✓ SMTP Config encontrada</span></p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        foreach ($smtp_config as $key => $value) {
            if ($key === 'smtp_password') {
                $display = empty($value) ? '<span class="error">✗ VACÍA</span>' : '<span class="ok">✓ Configurada (••••••••)</span>';
            } else {
                $display = htmlspecialchars($value);
            }
            echo "<tr><td><strong>$key</strong></td><td>$display</td></tr>";
        }
        echo "</table>";

        if (empty($smtp_config['smtp_password'])) {
            echo "<p><span class='error'>⚠️ ALERTA: La contraseña SMTP está VACÍA - Los emails NO se enviarán</span></p>";
        }
    } else {
        echo "<p><span class='error'>✗ SMTP Config NO encontrada (ID: {$campaign['smtp_config_id']})</span></p>";
        die("</div></body></html>");
    }
    echo "</div>";

    // PASO 4: Template
    echo "<div class='step'><h3>PASO 4: Obtener Template</h3>";
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$campaign['template_id']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($template) {
        echo "<p><span class='ok'>✓ Template encontrado: {$template['name']}</span></p>";
        echo "<p><strong>Asunto:</strong> " . htmlspecialchars($template['subject_default']) . "</p>";
        echo "<p><strong>Longitud HTML:</strong> " . strlen($template['html_content']) . " caracteres</p>";
    } else {
        echo "<p><span class='error'>✗ Template NO encontrado (ID: {$campaign['template_id']})</span></p>";
        die("</div></body></html>");
    }
    echo "</div>";

    // PASO 5: Destinatarios
    echo "<div class='step'><h3>PASO 5: Verificar Destinatarios</h3>";
    $stmt = $pdo->prepare("SELECT * FROM email_recipients WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Total destinatarios:</strong> " . count($recipients) . "</p>";

    if (count($recipients) > 0) {
        // Contar por estado
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM email_recipients WHERE campaign_id = ? GROUP BY status");
        $stmt->execute([$campaign_id]);
        $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<p><strong>Por Estado:</strong></p><ul>";
        foreach ($status_counts as $stat) {
            echo "<li>{$stat['status']}: {$stat['count']}</li>";
        }
        echo "</ul>";

        // Mostrar primeros 5 destinatarios
        echo "<p><strong>Primeros 5 destinatarios:</strong></p>";
        echo "<table><tr><th>ID</th><th>Email</th><th>Nombre</th><th>Estado</th><th>Enviado</th><th>Error</th></tr>";
        foreach (array_slice($recipients, 0, 5) as $r) {
            echo "<tr>";
            echo "<td>{$r['id']}</td>";
            echo "<td>{$r['email']}</td>";
            echo "<td>{$r['name']}</td>";
            echo "<td>{$r['status']}</td>";
            echo "<td>" . ($r['sent_at'] ?? '-') . "</td>";
            echo "<td>" . ($r['error_message'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='error'>✗ NO HAY DESTINATARIOS en esta campaña!</span></p>";
        echo "<p><span class='warning'>⚠️ Debes agregar destinatarios antes de enviar</span></p>";
    }
    echo "</div>";

    // PASO 6: PHPMailer
    echo "<div class='step'><h3>PASO 6: Verificar PHPMailer</h3>";
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        echo "<p><span class='ok'>✓ vendor/autoload.php existe</span></p>";
        require_once __DIR__ . '/../vendor/autoload.php';

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "<p><span class='ok'>✓ Clase PHPMailer disponible</span></p>";
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            echo "<p><span class='ok'>✓ PHPMailer instanciado - Versión: {$mail::VERSION}</span></p>";
        } else {
            echo "<p><span class='error'>✗ Clase PHPMailer NO disponible</span></p>";
        }
    } else {
        echo "<p><span class='error'>✗ vendor/autoload.php NO existe</span></p>";
    }
    echo "</div>";

    // PASO 7: EmailSender
    echo "<div class='step'><h3>PASO 7: Verificar EmailSender</h3>";
    if (file_exists(__DIR__ . '/email_sender.php')) {
        echo "<p><span class='ok'>✓ email_sender.php existe</span></p>";
        require_once __DIR__ . '/email_sender.php';

        if (class_exists('EmailSender')) {
            echo "<p><span class='ok'>✓ Clase EmailSender disponible</span></p>";

            $sender = new EmailSender($smtp_config, $pdo);
            echo "<p><span class='ok'>✓ EmailSender instanciado</span></p>";

            $methods = get_class_methods($sender);
            echo "<p><strong>Métodos disponibles:</strong></p><ul>";
            foreach (['send', 'sendCampaign'] as $method) {
                if (in_array($method, $methods)) {
                    echo "<li><span class='ok'>✓ $method()</span></li>";
                } else {
                    echo "<li><span class='error'>✗ $method()</span></li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p><span class='error'>✗ Clase EmailSender NO disponible</span></p>";
        }
    } else {
        echo "<p><span class='error'>✗ email_sender.php NO existe</span></p>";
    }
    echo "</div>";

    // PASO 8: Test de envío REAL (solo si hay destinatarios pendientes)
    $stmt = $pdo->prepare("SELECT * FROM email_recipients WHERE campaign_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$campaign_id]);
    $test_recipient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($test_recipient && !empty($smtp_config['smtp_password'])) {
        echo "<div class='step'><h3>PASO 8: TEST DE ENVÍO REAL</h3>";
        echo "<p><span class='warning'>⚠️ Intentando enviar a: {$test_recipient['email']}</span></p>";

        try {
            $result = $sender->send(
                [
                    'email' => $test_recipient['email'],
                    'name' => $test_recipient['name'],
                    'phone' => $test_recipient['phone'] ?? '',
                    'custom_data' => json_decode($test_recipient['custom_data'] ?? '{}', true)
                ],
                $template['html_content'],
                $campaign['subject'],
                $test_recipient['tracking_code']
            );

            echo "<p><strong>Resultado del envío:</strong></p>";
            echo "<pre>" . print_r($result, true) . "</pre>";

            if ($result['success']) {
                echo "<p><span class='ok'>✓✓✓ EMAIL ENVIADO EXITOSAMENTE!</span></p>";
            } else {
                echo "<p><span class='error'>✗✗✗ ERROR AL ENVIAR</span></p>";
                echo "<p><strong>Mensaje de error:</strong> {$result['message']}</p>";
            }

        } catch (Exception $e) {
            echo "<p><span class='error'>✗ Excepción al enviar:</span></p>";
            echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }

        echo "</div>";
    } else {
        echo "<div class='step'><h3>PASO 8: TEST DE ENVÍO - OMITIDO</h3>";
        if (empty($smtp_config['smtp_password'])) {
            echo "<p><span class='warning'>⚠️ No se puede hacer test - contraseña SMTP no configurada</span></p>";
        } elseif (!$test_recipient) {
            echo "<p><span class='warning'>⚠️ No hay destinatarios pendientes para probar</span></p>";
        }
        echo "</div>";
    }

    // PASO 9: Logs de envío previos
    echo "<div class='step'><h3>PASO 9: Logs de Envío Previos</h3>";
    $stmt = $pdo->prepare("SELECT * FROM email_send_logs WHERE campaign_id = ? ORDER BY sent_at DESC LIMIT 10");
    $stmt->execute([$campaign_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) > 0) {
        echo "<p><span class='ok'>✓ Encontrados " . count($logs) . " logs</span></p>";
        echo "<table><tr><th>ID</th><th>Email</th><th>Estado</th><th>Error</th><th>SMTP Response</th><th>Fecha</th></tr>";
        foreach ($logs as $log) {
            $status_class = $log['status'] === 'success' ? 'ok' : 'error';
            echo "<tr>";
            echo "<td>{$log['id']}</td>";
            echo "<td>{$log['email']}</td>";
            echo "<td><span class='$status_class'>{$log['status']}</span></td>";
            echo "<td>" . htmlspecialchars($log['error_message'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars(substr($log['smtp_response'] ?? '-', 0, 100)) . "</td>";
            echo "<td>{$log['sent_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='warning'>⚠️ No hay logs de envío - nunca se ha intentado enviar emails</span></p>";
    }
    echo "</div>";

    // RESUMEN FINAL
    echo "<div class='step' style='border-left-color:#10b981;background:#064e3b'>";
    echo "<h2 style='color:#6ee7b7'>📊 RESUMEN DE DIAGNÓSTICO</h2>";
    echo "<ul style='font-size:16px;line-height:1.8'>";

    // Check password
    if (empty($smtp_config['smtp_password'])) {
        echo "<li><span class='error'>❌ CONTRASEÑA SMTP VACÍA - BLOQUEA ENVÍO</span></li>";
        echo "<li><strong>Solución:</strong> <a href='edit_smtp_config.php?id={$smtp_config['id']}' style='color:#38bdf8'>Agregar contraseña aquí</a></li>";
    } else {
        echo "<li><span class='ok'>✓ Contraseña SMTP configurada</span></li>";
    }

    // Check recipients
    if (count($recipients) === 0) {
        echo "<li><span class='error'>❌ NO HAY DESTINATARIOS</span></li>";
        echo "<li><strong>Solución:</strong> Agregar destinatarios a la campaña</li>";
    } else {
        $pending_count = 0;
        foreach ($status_counts as $stat) {
            if ($stat['status'] === 'pending') $pending_count = $stat['count'];
        }
        if ($pending_count > 0) {
            echo "<li><span class='ok'>✓ Hay $pending_count destinatarios pendientes</span></li>";
        } else {
            echo "<li><span class='warning'>⚠️ No hay destinatarios PENDIENTES (ya fueron enviados o fallaron)</span></li>";
        }
    }

    // Check PHPMailer
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<li><span class='ok'>✓ PHPMailer instalado y funcional</span></li>";
    } else {
        echo "<li><span class='error'>❌ PHPMailer NO disponible</span></li>";
    }

    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color:#ef4444'>";
    echo "<h3><span class='error'>✗ ERROR CRÍTICO</span></h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr><p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Email Marketing</a>";
echo "<a href='edit_smtp_config.php' style='display:inline-block;background:#f59e0b;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Editar SMTP</a>";
echo "<a href='test_smtp_connection.php' style='display:inline-block;background:#10b981;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin:5px'>Test SMTP</a></p>";

echo "</body></html>";
?>
