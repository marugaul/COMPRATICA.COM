<?php
/**
 * Verificar estructura de tablas de Email Marketing
 * Este script muestra si las migraciones se han ejecutado correctamente
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

// Conectar a BD MySQL
$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Verificación de Tablas - Email Marketing</title>\n";
echo "<style>\n";
echo "body { font-family: Arial; padding: 40px; background: #f5f5f5; max-width: 1200px; margin: 0 auto; }\n";
echo ".success { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo ".error { background: #fee; border-left: 4px solid #dc2626; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo ".warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo ".info { background: #eff6ff; border-left: 4px solid #0891b2; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo "table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }\n";
echo "th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }\n";
echo "th { background: #3b82f6; color: white; }\n";
echo "h1 { color: #3b82f6; }\n";
echo "code { background: #1f2937; color: #10b981; padding: 2px 6px; border-radius: 3px; }\n";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🔍 Verificación de Estructura de Email Marketing</h1>\n";

try {
    // Verificar tabla email_templates
    echo "<div class='info'>";
    echo "<h3>📊 Tabla: email_templates</h3>";

    $stmt = $pdo->query("DESCRIBE email_templates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";

    $requiredColumns = ['is_default', 'image_path', 'image_display', 'image_cid'];
    $foundColumns = [];

    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";

        $foundColumns[] = $col['Field'];
    }
    echo "</table>";
    echo "</div>";

    // Verificar columnas requeridas
    $missing = array_diff($requiredColumns, $foundColumns);

    if (empty($missing)) {
        echo "<div class='success'>";
        echo "<h3>✅ Todas las migraciones han sido aplicadas correctamente</h3>";
        echo "<p>Las siguientes columnas existen:</p>";
        echo "<ul>";
        foreach ($requiredColumns as $col) {
            echo "<li><code>$col</code> ✓</li>";
        }
        echo "</ul>";
        echo "<p><strong>Puedes subir plantillas con imágenes sin problemas.</strong></p>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<h3>❌ Faltan Migraciones</h3>";
        echo "<p>Las siguientes columnas NO existen y deben ser creadas:</p>";
        echo "<ul>";
        foreach ($missing as $col) {
            echo "<li><code>$col</code> ✗</li>";
        }
        echo "</ul>";
        echo "<p><strong>⚠️ DEBES ejecutar las migraciones antes de usar el sistema de plantillas:</strong></p>";
        echo "<ol>";

        if (in_array('is_default', $missing)) {
            echo "<li><a href='/admin/migrate_templates_default.php' target='_blank'>Migración 1: Agregar columna is_default</a></li>";
        }

        if (in_array('image_path', $missing) || in_array('image_display', $missing) || in_array('image_cid', $missing)) {
            echo "<li><a href='/admin/migrate_template_images.php' target='_blank'>Migración 2: Agregar columnas de imágenes</a></li>";
        }

        echo "</ol>";
        echo "</div>";
    }

    // Verificar tabla email_smtp_configs
    echo "<div class='info'>";
    echo "<h3>📧 Configuraciones SMTP</h3>";

    $stmt = $pdo->query("SELECT name, config_name, smtp_host, smtp_port, is_active FROM email_smtp_configs ORDER BY id");
    $smtpConfigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($smtpConfigs) > 0) {
        echo "<table>";
        echo "<tr><th>Nombre</th><th>Identificador</th><th>Host</th><th>Puerto</th><th>Estado</th></tr>";
        foreach ($smtpConfigs as $config) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($config['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($config['config_name']) . "</td>";
            echo "<td>" . htmlspecialchars($config['smtp_host']) . "</td>";
            echo "<td>" . htmlspecialchars($config['smtp_port']) . "</td>";
            echo "<td>" . ($config['is_active'] ? '✓ Activo' : '✗ Inactivo') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        $configNames = array_column($smtpConfigs, 'config_name');
        if (!in_array('otro-correo', $configNames)) {
            echo "<div class='warning'>";
            echo "<p>⚠️ No existe la configuración SMTP <strong>'Otro Correo'</strong></p>";
            echo "<p><a href='/admin/add_otro_correo_smtp.php' target='_blank'>Clic aquí para agregar la 4ta configuración SMTP</a></p>";
            echo "</div>";
        }
    } else {
        echo "<p>No hay configuraciones SMTP. <a href='/admin/email_marketing/smtp_config.php'>Agregar configuración</a></p>";
    }
    echo "</div>";

    // Verificar plantillas
    echo "<div class='info'>";
    echo "<h3>📄 Plantillas de Email</h3>";

    $stmt = $pdo->query("SELECT name, company, is_active, is_default FROM email_templates ORDER BY id");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($templates) > 0) {
        echo "<table>";
        echo "<tr><th>Nombre</th><th>Identificador</th><th>Estado</th><th>Default</th></tr>";
        foreach ($templates as $tpl) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($tpl['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($tpl['company']) . "</td>";
            echo "<td>" . ($tpl['is_active'] ? '✓ Activa' : '✗ Inactiva') . "</td>";
            echo "<td>" . ($tpl['is_default'] ? '⭐ Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay plantillas creadas aún.</p>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine();
    echo "</div>";
}

echo "<p style='text-align:center;margin-top:30px;'>";
echo "<a href='/admin/email_marketing.php' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;'>← Volver a Email Marketing</a>";
echo "</p>";

echo "</body></html>";
?>
