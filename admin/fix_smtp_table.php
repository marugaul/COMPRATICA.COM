<?php
// ============================================
// AGREGAR COLUMNA config_name SI NO EXISTE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix SMTP Table</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .step { margin: 20px 0; padding: 15px; background: #111; border-left: 4px solid #0f0; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        pre { background: #222; padding: 10px; }
    </style>
</head>
<body>

<h1>REPARAR TABLA email_smtp_configs</h1>

<?php

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<div class='step'>";
    echo "<h3>1. Ver estructura actual</h3>";

    $stmt = $pdo->query("DESCRIBE email_smtp_configs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_config_name = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'config_name') {
            $has_config_name = true;
            break;
        }
    }

    echo "<p>Columnas actuales: " . count($columns) . "</p>";
    echo "<p>¿Tiene config_name? " . ($has_config_name ? '<span class="ok">SÍ</span>' : '<span class="error">NO</span>') . "</p>";
    echo "</div>";

    if (!$has_config_name) {
        echo "<div class='step'>";
        echo "<h3>2. Agregar columna config_name</h3>";

        $pdo->exec("ALTER TABLE email_smtp_configs ADD COLUMN config_name VARCHAR(255) NULL AFTER id");

        echo "<p class='ok'>✓ Columna config_name agregada</p>";
        echo "</div>";

        echo "<div class='step'>";
        echo "<h3>3. Actualizar nombres de configs existentes</h3>";

        // Obtener configs y generar nombres
        $configs = $pdo->query("SELECT * FROM email_smtp_configs")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($configs as $cfg) {
            $name = "Config SMTP - " . $cfg['smtp_username'];
            $pdo->prepare("UPDATE email_smtp_configs SET config_name = ? WHERE id = ?")->execute([$name, $cfg['id']]);
            echo "<p>✓ Config ID {$cfg['id']}: $name</p>";
        }

        echo "</div>";
    } else {
        echo "<div class='step'>";
        echo "<h3>✓ Tabla ya tiene la columna config_name</h3>";
        echo "</div>";
    }

    echo "<div class='step'>";
    echo "<h3>Estado Final</h3>";

    $configs = $pdo->query("SELECT id, config_name, smtp_host, smtp_port, smtp_username FROM email_smtp_configs")->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($configs as $cfg) {
        echo "ID: {$cfg['id']}\n";
        echo "Nombre: {$cfg['config_name']}\n";
        echo "Host: {$cfg['smtp_host']}:{$cfg['smtp_port']}\n";
        echo "Usuario: {$cfg['smtp_username']}\n";
        echo "---\n";
    }
    echo "</pre>";
    echo "</div>";

    echo "<div class='step' style='border-left-color:#0a0'>";
    echo "<h3>✓ REPARACIÓN COMPLETADA</h3>";
    echo "<p>Ahora accede a:</p>";
    echo "<p><a href='SET_PASSWORD_SMTP.php' style='color:#0ff'>→ SET_PASSWORD_SMTP.php</a></p>";
    echo "<p><a href='SEND_EMAIL_NOW.php' style='color:#0ff'>→ SEND_EMAIL_NOW.php</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color:#f00'>";
    echo "<h3>ERROR</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

?>

</body>
</html>
