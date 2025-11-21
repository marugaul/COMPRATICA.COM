<?php
/**
 * Agregar configuración SMTP "Otro Correo"
 * Ejecutar solo UNA vez para crear la 4ta opción personalizable
 */

require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Agregar Otro Correo SMTP</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:900px;margin:0 auto;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:20px;margin:15px 0;border-radius:4px;}
.error{background:#fee;border-left:4px solid #dc2626;padding:20px;margin:15px 0;border-radius:4px;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:20px;margin:15px 0;border-radius:4px;}
h1{color:#16a34a;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
</style></head><body>";

echo "<h1>✉️ Agregar Configuración SMTP: Otro Correo</h1>";

try {
    // Verificar si ya existe
    $exists = $pdo->query("SELECT id FROM email_smtp_configs WHERE name = 'Otro Correo'")->fetch();

    if ($exists) {
        echo "<div class='info'>";
        echo "<strong>ℹ️ La configuración 'Otro Correo' ya existe</strong><br>";
        echo "ID: {$exists['id']}<br>";
        echo "Puedes editarla desde la página de Configuración SMTP.";
        echo "</div>";

        $config = $pdo->query("SELECT * FROM email_smtp_configs WHERE name = 'Otro Correo'")->fetch(PDO::FETCH_ASSOC);
        echo "<div class='info'>";
        echo "<h3>Configuración Actual:</h3>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> " . htmlspecialchars($config['from_email']) . "</li>";
        echo "<li><strong>Nombre:</strong> " . htmlspecialchars($config['from_name']) . "</li>";
        echo "<li><strong>Host:</strong> " . htmlspecialchars($config['smtp_host']) . "</li>";
        echo "<li><strong>Puerto:</strong> " . htmlspecialchars($config['smtp_port']) . "</li>";
        echo "<li><strong>Usuario:</strong> " . htmlspecialchars($config['smtp_username']) . "</li>";
        echo "<li><strong>Encriptación:</strong> " . htmlspecialchars($config['smtp_encryption']) . "</li>";
        echo "<li><strong>Estado:</strong> " . ($config['is_active'] ? '✓ Activo' : '✗ Inactivo') . "</li>";
        echo "</ul>";
        echo "</div>";

    } else {
        echo "<div class='info'>";
        echo "<strong>📋 Creando configuración SMTP 'Otro Correo'...</strong>";
        echo "</div>";

        // Crear nueva configuración con valores por defecto que el usuario puede editar
        $stmt = $pdo->prepare("
            INSERT INTO email_smtp_configs (
                name,
                from_email,
                from_name,
                smtp_host,
                smtp_port,
                smtp_username,
                smtp_password,
                smtp_encryption,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            'Otro Correo',
            'usuario@tudominio.com',                 // Email por defecto
            'Nombre del Remitente',                   // Nombre por defecto
            'smtp.tudominio.com',                     // Host por defecto
            587,                                       // Puerto por defecto (TLS)
            'usuario@tudominio.com',                  // Usuario por defecto
            '',                                        // Sin contraseña inicial
            'tls',                                     // TLS por defecto
            0                                          // Inactivo por defecto
        ]);

        $newId = $pdo->lastInsertId();

        echo "<div class='success'>";
        echo "<strong>✅ Configuración 'Otro Correo' creada exitosamente!</strong><br><br>";
        echo "<strong>ID:</strong> {$newId}<br>";
        echo "<strong>Estado:</strong> Inactivo (configurar primero antes de activar)<br><br>";
        echo "<strong>Valores por defecto creados:</strong>";
        echo "<ul>";
        echo "<li><strong>Email:</strong> usuario@tudominio.com</li>";
        echo "<li><strong>Nombre:</strong> Nombre del Remitente</li>";
        echo "<li><strong>Host:</strong> smtp.tudominio.com</li>";
        echo "<li><strong>Puerto:</strong> 587 (TLS)</li>";
        echo "<li><strong>Usuario:</strong> usuario@tudominio.com</li>";
        echo "<li><strong>Contraseña:</strong> (vacía - debe configurar)</li>";
        echo "<li><strong>Encriptación:</strong> TLS</li>";
        echo "</ul>";
        echo "</div>";

        echo "<div class='info'>";
        echo "<h3>📝 Próximos Pasos:</h3>";
        echo "<ol>";
        echo "<li>Ve a <strong>Configuración SMTP</strong> en el panel de administración</li>";
        echo "<li>Edita todos los campos de <strong>'Otro Correo'</strong> con tus datos reales</li>";
        echo "<li>Configura el servidor SMTP, puerto, usuario y contraseña</li>";
        echo "<li>Haz clic en <strong>Probar</strong> para verificar la conexión</li>";
        echo "<li>Una vez confirmado, activa la configuración</li>";
        echo "</ol>";
        echo "</div>";
    }

    echo "<div class='info'>";
    echo "<h3>🔧 Configuraciones SMTP Disponibles:</h3>";
    $allConfigs = $pdo->query("SELECT name, from_email, is_active FROM email_smtp_configs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#e5e7eb;'>";
    echo "<th style='padding:10px;text-align:left;'>Nombre</th>";
    echo "<th style='padding:10px;text-align:left;'>Email</th>";
    echo "<th style='padding:10px;text-align:left;'>Estado</th>";
    echo "</tr>";
    foreach ($allConfigs as $cfg) {
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:10px;'><strong>" . htmlspecialchars($cfg['name']) . "</strong></td>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($cfg['from_email']) . "</td>";
        echo "<td style='padding:10px;'>" . ($cfg['is_active'] ? '✓ Activo' : '✗ Inactivo') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    echo "<p style='text-align:center;'>";
    echo "<a href='email_marketing.php?page=smtp_config' class='btn'>⚙️ Ir a Configuración SMTP</a> ";
    echo "<a href='email_marketing.php?page=dashboard' class='btn'>🏠 Dashboard</a>";
    echo "</p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "<br><br><strong>Archivo:</strong> {$e->getFile()}<br>";
    echo "<strong>Línea:</strong> {$e->getLine()}";
    echo "</div>";
}

echo "</body></html>";
