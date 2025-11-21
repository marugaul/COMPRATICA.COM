<?php
/**
 * Actualizar Configuración SMTP para Mixtico
 * Configura el servidor de correo mail.mixtico.net con SSL
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Actualizar SMTP Mixtico</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;max-width:800px;margin:0 auto}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.info{color:#0891b2;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #f97316}
pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;font-size:12px}
table{width:100%;border-collapse:collapse;margin:10px 0}
table th{background:#fed7aa;text-align:left;padding:8px}
table td{border:1px solid #ddd;padding:8px}
</style></head><body>";

echo "<h1>🍹 Configuración SMTP - Mixtico.net</h1>";

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<div class='step'>";
    echo "<h3>1. Conectando a Base de Datos...</h3>";
    echo "<p><span class='ok'>✓ Conexión exitosa</span></p>";
    echo "</div>";

    // Datos de configuración SMTP de Mixtico
    $smtp_data = [
        'config_name' => 'Mixtico - Mail Server (SSL)',
        'smtp_host' => 'mail.mixtico.net',
        'smtp_port' => 465,
        'smtp_username' => 'info@mixtico.net',
        'smtp_password' => '', // Dejar vacío por seguridad - el usuario lo llenará manualmente
        'smtp_encryption' => 'ssl',
        'from_email' => 'info@mixtico.net',
        'from_name' => 'Mixtico - Mezclas Premium'
    ];

    echo "<div class='step'>";
    echo "<h3>2. Configuración a Insertar/Actualizar</h3>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    foreach ($smtp_data as $key => $value) {
        $display_value = ($key === 'smtp_password' && empty($value)) ? '<em>(dejar vacío - actualizar manualmente)</em>' : htmlspecialchars($value);
        echo "<tr><td><strong>$key</strong></td><td>$display_value</td></tr>";
    }
    echo "</table>";
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>3. Verificando Configuraciones Existentes...</h3>";

    // Buscar si ya existe una configuración para Mixtico
    $stmt = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_host = 'mail.mixtico.net' OR config_name LIKE '%Mixtico%'");
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($existing) > 0) {
        echo "<p><span class='info'>⚠ Encontradas " . count($existing) . " configuración(es) existente(s) para Mixtico</span></p>";

        foreach ($existing as $conf) {
            echo "<p>ID: {$conf['id']} - {$conf['config_name']} - Puerto: {$conf['smtp_port']}</p>";
        }

        // Actualizar la primera encontrada
        $update_id = $existing[0]['id'];

        $stmt = $pdo->prepare("
            UPDATE email_smtp_configs SET
                config_name = ?,
                smtp_host = ?,
                smtp_port = ?,
                smtp_username = ?,
                smtp_encryption = ?,
                from_email = ?,
                from_name = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $smtp_data['config_name'],
            $smtp_data['smtp_host'],
            $smtp_data['smtp_port'],
            $smtp_data['smtp_username'],
            $smtp_data['smtp_encryption'],
            $smtp_data['from_email'],
            $smtp_data['from_name'],
            $update_id
        ]);

        echo "<p><span class='ok'>✓ Configuración actualizada (ID: $update_id)</span></p>";
        echo "<p><strong>NOTA:</strong> La contraseña NO fue actualizada. Debes ingresarla manualmente en el panel de Email Marketing.</p>";

    } else {
        echo "<p><span class='info'>⚠ No se encontró configuración existente. Creando nueva...</span></p>";

        $stmt = $pdo->prepare("
            INSERT INTO email_smtp_configs
            (config_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $smtp_data['config_name'],
            $smtp_data['smtp_host'],
            $smtp_data['smtp_port'],
            $smtp_data['smtp_username'],
            $smtp_data['smtp_password'],
            $smtp_data['smtp_encryption'],
            $smtp_data['from_email'],
            $smtp_data['from_name']
        ]);

        $new_id = $pdo->lastInsertId();
        echo "<p><span class='ok'>✓ Nueva configuración creada (ID: $new_id)</span></p>";
        echo "<p><strong>IMPORTANTE:</strong> Debes agregar la contraseña manualmente en el panel de Email Marketing.</p>";
    }

    echo "</div>";

    // Mostrar todas las configuraciones actuales
    echo "<div class='step'>";
    echo "<h3>4. Configuraciones SMTP Actuales</h3>";
    $stmt = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id");
    $all_configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($all_configs) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Host</th><th>Puerto</th><th>Usuario</th><th>Encriptación</th><th>Password Set</th></tr>";
        foreach ($all_configs as $conf) {
            $has_password = !empty($conf['smtp_password']) ? '<span class="ok">✓ Sí</span>' : '<span class="error">✗ No</span>';
            echo "<tr>";
            echo "<td>{$conf['id']}</td>";
            echo "<td>" . htmlspecialchars($conf['config_name']) . "</td>";
            echo "<td>{$conf['smtp_host']}</td>";
            echo "<td>{$conf['smtp_port']}</td>";
            echo "<td>{$conf['smtp_username']}</td>";
            echo "<td>{$conf['smtp_encryption']}</td>";
            echo "<td>$has_password</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Instrucciones finales
    echo "<div class='step' style='background:#fef3c7;border-left-color:#f97316'>";
    echo "<h3>📋 Pasos Siguientes:</h3>";
    echo "<ol>";
    echo "<li><strong>Agrega la contraseña:</strong> Ve al panel de Email Marketing y edita la configuración SMTP para agregar la contraseña de info@mixtico.net</li>";
    echo "<li><strong>Prueba el envío:</strong> Crea una campaña de prueba con 1 destinatario</li>";
    echo "<li><strong>Configuración Alternativa:</strong> Si el puerto 465 (SSL) falla, puedes cambiar a puerto 587 (TLS) manualmente</li>";
    echo "</ol>";
    echo "<p><strong>Configuración Aplicada (Recomendada):</strong></p>";
    echo "<ul>";
    echo "<li>🌐 <strong>Host:</strong> mail.mixtico.net</li>";
    echo "<li>🔌 <strong>Puerto:</strong> 465</li>";
    echo "<li>🔒 <strong>Encriptación:</strong> SSL</li>";
    echo "<li>👤 <strong>Usuario:</strong> info@mixtico.net</li>";
    echo "<li>🔑 <strong>Contraseña:</strong> [Agregar manualmente]</li>";
    echo "</ul>";
    echo "</div>";

    echo "<hr>";
    echo "<p>";
    echo "<a href='email_marketing.php?page=smtp-configs' style='display:inline-block;background:#f97316;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;margin-right:10px'>Ver Configuraciones SMTP</a>";
    echo "<a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px'>Ir a Email Marketing</a>";
    echo "</p>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color:red'>";
    echo "<h3><span class='error'>✗ Error</span></h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>
