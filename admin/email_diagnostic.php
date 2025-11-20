<?php
/**
 * Diagnóstico del Sistema de Email Marketing
 * Verifica todas las dependencias y configuraciones
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico Email Marketing</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;max-width:900px;margin:0 auto}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.warning{color:orange;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;font-size:12px}
table{width:100%;border-collapse:collapse;margin:10px 0}
table th{background:#e0f2fe;text-align:left;padding:8px}
table td{border:1px solid #ddd;padding:8px}
</style></head><body>";

echo "<h1>🔍 Diagnóstico del Sistema de Email Marketing</h1>";

// Test 1: PHPMailer
echo "<div class='step'><h3>1. PHPMailer</h3>";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "<p><span class='ok'>✓ Autoloader cargado correctamente</span></p>";

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<p><span class='ok'>✓ Clase PHPMailer disponible</span></p>";

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $version = $mail::VERSION;
        echo "<p><span class='ok'>✓ PHPMailer versión: $version</span></p>";
    } else {
        echo "<p><span class='error'>✗ Clase PHPMailer NO encontrada</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error al cargar PHPMailer: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

// Test 2: Database Connection
echo "<div class='step'><h3>2. Conexión a Base de Datos</h3>";
try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p><span class='ok'>✓ Conexión exitosa a: {$config['database']}</span></p>";
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error de conexión: " . $e->getMessage() . "</span></p>";
    die("</div></body></html>");
}
echo "</div>";

// Test 3: Email Tables
echo "<div class='step'><h3>3. Tablas de Email Marketing</h3>";
$required_tables = [
    'email_campaigns',
    'email_templates',
    'email_smtp_configs',
    'email_recipients',
    'email_send_logs'
];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><span class='ok'>✓ $table</span> - $count registros</p>";
    } catch (Exception $e) {
        echo "<p><span class='error'>✗ $table - NO existe o error</span></p>";
    }
}
echo "</div>";

// Test 4: SMTP Configs
echo "<div class='step'><h3>4. Configuraciones SMTP</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM email_smtp_configs LIMIT 5");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($configs) > 0) {
        echo "<p><span class='ok'>✓ " . count($configs) . " configuración(es) SMTP encontrada(s)</span></p>";
        echo "<table><tr><th>ID</th><th>Nombre</th><th>Host</th><th>Puerto</th><th>Usuario</th><th>Encriptación</th></tr>";
        foreach ($configs as $config) {
            echo "<tr>";
            echo "<td>{$config['id']}</td>";
            echo "<td>{$config['config_name']}</td>";
            echo "<td>{$config['smtp_host']}</td>";
            echo "<td>{$config['smtp_port']}</td>";
            echo "<td>{$config['smtp_username']}</td>";
            echo "<td>{$config['smtp_encryption']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='warning'>⚠ No hay configuraciones SMTP</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

// Test 5: Email Templates
echo "<div class='step'><h3>5. Plantillas de Email</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM email_templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($templates) > 0) {
        echo "<p><span class='ok'>✓ " . count($templates) . " plantilla(s) encontrada(s)</span></p>";
        echo "<table><tr><th>ID</th><th>Nombre</th><th>Empresa</th><th>Asunto</th></tr>";
        foreach ($templates as $tpl) {
            echo "<tr>";
            echo "<td>{$tpl['id']}</td>";
            echo "<td>{$tpl['name']}</td>";
            echo "<td>{$tpl['company']}</td>";
            echo "<td>" . htmlspecialchars($tpl['subject_default']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='warning'>⚠ No hay plantillas cargadas</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

// Test 6: Campañas
echo "<div class='step'><h3>6. Campañas de Email</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM email_campaigns ORDER BY created_at DESC LIMIT 5");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($campaigns) > 0) {
        echo "<p><span class='ok'>✓ " . count($campaigns) . " campaña(s) reciente(s)</span></p>";
        echo "<table><tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Destinatarios</th><th>Enviados</th><th>Abiertos</th><th>Fecha</th></tr>";
        foreach ($campaigns as $camp) {
            $status_color = [
                'draft' => 'gray',
                'scheduled' => 'blue',
                'sending' => 'orange',
                'sent' => 'green',
                'failed' => 'red'
            ][$camp['status']] ?? 'black';

            echo "<tr>";
            echo "<td>{$camp['id']}</td>";
            echo "<td>" . htmlspecialchars($camp['name']) . "</td>";
            echo "<td style='color:$status_color;font-weight:bold'>{$camp['status']}</td>";
            echo "<td>{$camp['total_recipients']}</td>";
            echo "<td>{$camp['sent_count']}</td>";
            echo "<td>{$camp['opened_count']}</td>";
            echo "<td>{$camp['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p><span class='warning'>⚠ No hay campañas creadas</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

// Test 7: EmailSender Class
echo "<div class='step'><h3>7. Clase EmailSender</h3>";
try {
    require_once __DIR__ . '/email_sender.php';
    echo "<p><span class='ok'>✓ email_sender.php cargado correctamente</span></p>";

    if (class_exists('EmailSender')) {
        echo "<p><span class='ok'>✓ Clase EmailSender disponible</span></p>";

        // Test con configuración dummy
        $dummy_config = [
            'smtp_host' => 'smtp.test.com',
            'smtp_port' => 587,
            'smtp_username' => 'test@test.com',
            'smtp_password' => 'test',
            'smtp_encryption' => 'tls'
        ];

        $sender = new EmailSender($dummy_config, $pdo);
        echo "<p><span class='ok'>✓ EmailSender instanciado correctamente</span></p>";

        if (method_exists($sender, 'sendCampaign')) {
            echo "<p><span class='ok'>✓ Método sendCampaign() existe</span></p>";
        } else {
            echo "<p><span class='error'>✗ Método sendCampaign() NO encontrado</span></p>";
        }
    } else {
        echo "<p><span class='error'>✗ Clase EmailSender NO encontrada</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
}
echo "</div>";

// Test 8: File Permissions
echo "<div class='step'><h3>8. Permisos de Archivos Críticos</h3>";
$files_to_check = [
    __DIR__ . '/email_sender.php',
    __DIR__ . '/email_marketing_send.php',
    __DIR__ . '/email_track.php',
    __DIR__ . '/../vendor/autoload.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "<p><span class='ok'>✓ " . basename($file) . "</span> - Permisos: $perms</p>";
    } else {
        echo "<p><span class='error'>✗ " . basename($file) . " - NO existe</span></p>";
    }
}
echo "</div>";

// Summary
echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
echo "<h3 style='color:#065f46'>📊 Resumen</h3>";
echo "<p>Diagnóstico completado. Si todos los checks están en verde (✓), el sistema está listo para enviar emails.</p>";
echo "<p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin-right:10px'>Ir a Email Marketing</a>";
echo "<a href='email_marketing.php?page=new-campaign' style='display:inline-block;background:#16a34a;color:white;padding:10px 20px;text-decoration:none;border-radius:6px'>Nueva Campaña</a></p>";
echo "</div>";

echo "</body></html>";
?>
