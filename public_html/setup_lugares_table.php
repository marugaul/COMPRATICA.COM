<?php
/**
 * Script de instalación única para tabla lugares_comerciales
 * Ejecutar una sola vez visitando: https://compratica.com/public_html/setup_lugares_table.php
 * O configurar como cron job
 */

// Configuración de base de datos
$config = [
    'host' => '127.0.0.1',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación Lugares Comerciales</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #bee5eb;
            margin: 20px 0;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Instalación de Tabla lugares_comerciales</h1>

<?php
try {
    // Intentar conexión
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo '<div class="success">✅ Conexión a base de datos exitosa</div>';

    // Verificar si la tabla ya existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

    if ($check) {
        echo '<div class="info">';
        echo '<strong>ℹ️ La tabla ya existe</strong><br>';

        $count = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != '' AND email IS NOT NULL")->fetchColumn();

        echo "📊 Registros totales: <strong>" . number_format($count) . "</strong><br>";
        echo "📧 Con email: <strong>" . number_format($with_email) . "</strong><br>";
        echo '</div>';

        echo '<h2>✅ Instalación completa</h2>';
        echo '<p>La tabla <code>lugares_comerciales</code> ya está lista para usar.</p>';
        echo '<p><strong>Puedes eliminar este archivo ahora por seguridad:</strong> <code>public_html/setup_lugares_table.php</code></p>';

    } else {
        // Crear la tabla
        echo '<div class="info">📋 Creando tabla lugares_comerciales...</div>';

        $sql = "CREATE TABLE lugares_comerciales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            tipo VARCHAR(100),
            categoria VARCHAR(100),
            subtipo VARCHAR(100),
            descripcion TEXT,
            direccion VARCHAR(500),
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            codigo_postal VARCHAR(20),
            telefono VARCHAR(50),
            email VARCHAR(255),
            website VARCHAR(500),
            facebook VARCHAR(255),
            instagram VARCHAR(255),
            horario TEXT,
            latitud DECIMAL(10, 8),
            longitud DECIMAL(11, 8),
            osm_id BIGINT,
            osm_type VARCHAR(10),
            capacidad INT,
            estrellas TINYINT,
            wifi BOOLEAN DEFAULT FALSE,
            parking BOOLEAN DEFAULT FALSE,
            discapacidad_acceso BOOLEAN DEFAULT FALSE,
            tarjetas_credito BOOLEAN DEFAULT FALSE,
            delivery BOOLEAN DEFAULT FALSE,
            takeaway BOOLEAN DEFAULT FALSE,
            tags_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tipo (tipo),
            INDEX idx_categoria (categoria),
            INDEX idx_ciudad (ciudad),
            INDEX idx_provincia (provincia),
            INDEX idx_email (email),
            INDEX idx_osm_id (osm_id),
            FULLTEXT idx_nombre (nombre, descripcion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);

        echo '<div class="success">';
        echo '<strong>✅ TABLA CREADA EXITOSAMENTE</strong><br><br>';
        echo '🎉 La tabla <code>lugares_comerciales</code> está lista para usar!';
        echo '</div>';

        echo '<h2>📥 Próximos pasos</h2>';
        echo '<ol>';
        echo '<li>Importar datos desde: <a href="/public_html/importar_lugares_standalone.php" target="_blank">Importador OpenStreetMap</a></li>';
        echo '<li>Usar en campañas: <a href="/admin/email_marketing.php?page=new-campaign">Nueva Campaña</a></li>';
        echo '<li><strong>Eliminar este archivo por seguridad:</strong> <code>public_html/setup_lugares_table.php</code></li>';
        echo '</ol>';

        // Mostrar estructura de la tabla
        echo '<h2>📊 Estructura de la tabla</h2>';
        $structure = $pdo->query("DESCRIBE lugares_comerciales")->fetchAll(PDO::FETCH_ASSOC);

        echo '<table style="width:100%; border-collapse: collapse; margin-top: 10px;">';
        echo '<tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
        echo '<th style="padding: 10px; text-align: left;">Campo</th>';
        echo '<th style="padding: 10px; text-align: left;">Tipo</th>';
        echo '<th style="padding: 10px; text-align: left;">Index</th>';
        echo '</tr>';

        foreach ($structure as $col) {
            echo '<tr style="border-bottom: 1px solid #dee2e6;">';
            echo '<td style="padding: 8px;"><code>' . htmlspecialchars($col['Field']) . '</code></td>';
            echo '<td style="padding: 8px;">' . htmlspecialchars($col['Type']) . '</td>';
            echo '<td style="padding: 8px;">' . ($col['Key'] ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error de conexión MySQL:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';

    echo '<h2>🔧 Soluciones posibles:</h2>';
    echo '<ol>';
    echo '<li><strong>Verificar credenciales:</strong> Usuario y contraseña correctos</li>';
    echo '<li><strong>Verificar host:</strong> Cambiar <code>127.0.0.1</code> por <code>localhost</code> o viceversa</li>';
    echo '<li><strong>Verificar socket MySQL:</strong> El servidor puede estar usando socket Unix en lugar de TCP</li>';
    echo '<li><strong>Alternativa manual:</strong> Ejecutar el SQL desde phpMyAdmin usando el archivo <code>CREATE_LUGARES_TABLE.sql</code></li>';
    echo '</ol>';

} catch (Exception $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>

        <hr style="margin: 40px 0; border: none; border-top: 1px solid #dee2e6;">

        <h2>⚙️ Configurar como Cron Job (opcional)</h2>
        <p>Si quieres automatizar la creación, añade esto a tu crontab:</p>
        <code style="display: block; padding: 10px; background: #f8f9fa; margin: 10px 0;">
        0 3 * * * /usr/bin/php /home/user/COMPRATICA/public_html/setup_lugares_table.php > /dev/null 2>&1
        </code>
        <p><small>Esto ejecutará el script diariamente a las 3 AM (pero solo creará la tabla si no existe)</small></p>

    </div>
</body>
</html>
