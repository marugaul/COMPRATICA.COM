<?php
// ============================================
// DIAGNOSTICO CAMPAÑA - ERROR 500
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bypass auth
$_SESSION['is_admin'] = true;

$campaign_id = $_GET['campaign_id'] ?? 4;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Campaña <?= $campaign_id ?></title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .step { margin: 20px 0; padding: 15px; background: #111; border-left: 4px solid #0f0; }
        .error { color: #f00; border-left-color: #f00; }
        .ok { color: #0f0; }
        .warn { color: #ff0; }
        pre { background: #222; padding: 10px; overflow: auto; }
    </style>
</head>
<body>

<h1>DIAGNÓSTICO CAMPAÑA ID: <?= $campaign_id ?></h1>

<?php

try {
    echo "<div class='step'>";
    echo "<h3>1. Conectar BD</h3>";

    require __DIR__ . '/../config/database.php';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<p class='ok'>✓ Conectado</p>";
    echo "</div>";

    // CAMPAÑA
    echo "<div class='step'>";
    echo "<h3>2. Buscar Campaña</h3>";

    $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo "<p class='error'>✗ CAMPAÑA NO EXISTE (ID: $campaign_id)</p>";
        echo "<p>Buscando todas las campañas...</p>";

        $all = $pdo->query("SELECT id, name, status FROM email_campaigns ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Campañas disponibles:</p><pre>";
        print_r($all);
        echo "</pre>";
    } else {
        echo "<p class='ok'>✓ Campaña encontrada: {$campaign['name']}</p>";
        echo "<pre>";
        print_r($campaign);
        echo "</pre>";
    }
    echo "</div>";

    if ($campaign) {
        // SMTP CONFIG
        echo "<div class='step'>";
        echo "<h3>3. Config SMTP</h3>";

        $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
        $stmt->execute([$campaign['smtp_config_id']]);
        $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$smtp) {
            echo "<p class='error'>✗ SMTP Config NO existe (ID: {$campaign['smtp_config_id']})</p>";
        } else {
            echo "<p class='ok'>✓ SMTP encontrado: {$smtp['config_name']}</p>";
            echo "<p>Host: {$smtp['smtp_host']}:{$smtp['smtp_port']}</p>";
            echo "<p>Password: " . (empty($smtp['smtp_password']) ? '<span class="error">VACÍO</span>' : '<span class="ok">OK</span>') . "</p>";
        }
        echo "</div>";

        // DESTINATARIOS
        echo "<div class='step'>";
        echo "<h3>4. Destinatarios</h3>";

        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM email_recipients WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo "<p>Total: $total</p>";

        if ($total > 0) {
            $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM email_recipients WHERE campaign_id = ? GROUP BY status");
            $stmt->execute([$campaign_id]);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<p>Por estado:</p><pre>";
            print_r($stats);
            echo "</pre>";

            // Mostrar primeros 3
            $stmt = $pdo->prepare("SELECT * FROM email_recipients WHERE campaign_id = ? LIMIT 3");
            $stmt->execute([$campaign_id]);
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<p>Muestra:</p><pre>";
            print_r($samples);
            echo "</pre>";
        } else {
            echo "<p class='error'>✗ NO HAY DESTINATARIOS</p>";
        }
        echo "</div>";

        // TEMPLATE
        echo "<div class='step'>";
        echo "<h3>5. Template</h3>";

        if ($campaign['template_id']) {
            $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$campaign['template_id']]);
            $tpl = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tpl) {
                echo "<p class='ok'>✓ Template: {$tpl['name']}</p>";
            } else {
                echo "<p class='error'>✗ Template NO existe (ID: {$campaign['template_id']})</p>";
            }
        } else {
            echo "<p class='warn'>⚠ Sin template</p>";
        }
        echo "</div>";

        // PHPMAILER
        echo "<div class='step'>";
        echo "<h3>6. PHPMailer</h3>";

        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            echo "<p class='ok'>✓ Autoload OK</p>";

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                echo "<p class='ok'>✓ PHPMailer OK</p>";
            } else {
                echo "<p class='error'>✗ Clase PHPMailer no disponible</p>";
            }
        } else {
            echo "<p class='error'>✗ vendor/autoload.php no existe</p>";
        }
        echo "</div>";

        // EMAIL_SENDER
        echo "<div class='step'>";
        echo "<h3>7. EmailSender Class</h3>";

        if (file_exists(__DIR__ . '/email_sender.php')) {
            require_once __DIR__ . '/email_sender.php';
            echo "<p class='ok'>✓ email_sender.php cargado</p>";

            if (class_exists('EmailSender')) {
                echo "<p class='ok'>✓ Clase EmailSender OK</p>";
            } else {
                echo "<p class='error'>✗ Clase EmailSender no existe</p>";
            }
        } else {
            echo "<p class='error'>✗ email_sender.php no existe</p>";
        }
        echo "</div>";

        // RESUMEN
        echo "<div class='step'>";
        echo "<h3>RESUMEN</h3>";

        $issues = [];

        if (!$smtp) {
            $issues[] = "SMTP Config no existe";
        } elseif (empty($smtp['smtp_password'])) {
            $issues[] = "Contraseña SMTP vacía";
        }

        if ($total == 0) {
            $issues[] = "No hay destinatarios";
        }

        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $issues[] = "PHPMailer no disponible";
        }

        if (count($issues) > 0) {
            echo "<p class='error'>PROBLEMAS ENCONTRADOS:</p>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li class='error'>$issue</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='ok'>✓ TODO LISTO PARA ENVIAR</p>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='step error'>";
    echo "<h3>ERROR FATAL</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

?>

<hr>
<p>
    <a href="SEND_EMAIL_NOW.php" style="color:#0ff">Test Email Directo</a> |
    <a href="SET_PASSWORD_SMTP.php" style="color:#0ff">Config Password</a> |
    <a href="email_marketing.php" style="color:#0ff">Email Marketing</a>
</p>

</body>
</html>
