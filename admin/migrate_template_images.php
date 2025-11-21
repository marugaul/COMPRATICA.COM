<?php
/**
 * Migración: Agregar soporte de imágenes a plantillas
 * Ejecutar solo UNA vez
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migración Imágenes en Plantillas</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:900px;margin:0 auto;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:20px;margin:15px 0;border-radius:4px;}
.error{background:#fee;border-left:4px solid #dc2626;padding:20px;margin:15px 0;border-radius:4px;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:20px;margin:15px 0;border-radius:4px;}
h1{color:#16a34a;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
code{background:#e5e7eb;padding:2px 6px;border-radius:3px;font-family:monospace;}
</style></head><body>";

echo "<h1>🖼️ Migración: Soporte de Imágenes en Plantillas</h1>";

try {
    $changes = [];

    // Verificar y agregar columna image_path
    echo "<div class='info'><strong>1. Verificando columna image_path...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates LIKE 'image_path'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE email_templates
            ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER html_content
        ");
        $changes[] = "✓ Columna <code>image_path</code> agregada";
        echo "<div class='success'><strong>✓ Columna image_path agregada</strong></div>";
    } else {
        echo "<div class='info'>✓ Columna image_path ya existe</div>";
    }

    // Verificar y agregar columna image_display
    echo "<div class='info'><strong>2. Verificando columna image_display...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates LIKE 'image_display'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE email_templates
            ADD COLUMN image_display ENUM('inline', 'attachment', 'none') DEFAULT 'none' AFTER image_path
        ");
        $changes[] = "✓ Columna <code>image_display</code> agregada";
        echo "<div class='success'><strong>✓ Columna image_display agregada</strong></div>";
    } else {
        echo "<div class='info'>✓ Columna image_display ya existe</div>";
    }

    // Verificar y agregar columna image_cid (Content-ID para inline images)
    echo "<div class='info'><strong>3. Verificando columna image_cid...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates LIKE 'image_cid'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            ALTER TABLE email_templates
            ADD COLUMN image_cid VARCHAR(100) DEFAULT NULL AFTER image_display
        ");
        $changes[] = "✓ Columna <code>image_cid</code> agregada";
        echo "<div class='success'><strong>✓ Columna image_cid agregada</strong></div>";
    } else {
        echo "<div class='info'>✓ Columna image_cid ya existe</div>";
    }

    // Crear directorio para imágenes de plantillas si no existe
    echo "<div class='info'><strong>4. Verificando directorio de imágenes...</strong></div>";

    $imageDir = __DIR__ . '/../uploads/template_images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
        $changes[] = "✓ Directorio <code>/uploads/template_images/</code> creado";
        echo "<div class='success'><strong>✓ Directorio creado: /uploads/template_images/</strong></div>";
    } else {
        echo "<div class='info'>✓ Directorio ya existe</div>";
    }

    // Mostrar estructura actualizada
    echo "<div class='info'><strong>5. Estructura actualizada de email_templates:</strong></div>";

    $columns = $pdo->query("SHOW COLUMNS FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table style='width:100%;border-collapse:collapse;background:white;'>";
    echo "<tr style='background:#0891b2;color:white;'>";
    echo "<th style='padding:10px;text-align:left;'>Campo</th>";
    echo "<th style='padding:10px;text-align:left;'>Tipo</th>";
    echo "<th style='padding:10px;text-align:left;'>Null</th>";
    echo "<th style='padding:10px;text-align:left;'>Default</th>";
    echo "</tr>";

    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], ['image_path', 'image_display', 'image_cid']) ? 'background:#fef3c7;font-weight:bold;' : '';
        echo "<tr style='border-bottom:1px solid #ddd;{$highlight}'>";
        echo "<td style='padding:10px;'><code>{$col['Field']}</code></td>";
        echo "<td style='padding:10px;'>{$col['Type']}</td>";
        echo "<td style='padding:10px;'>{$col['Null']}</td>";
        echo "<td style='padding:10px;'>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Resumen
    if (count($changes) > 0) {
        echo "<div class='success'>";
        echo "<h3>✅ Migración Completada</h3>";
        echo "<p><strong>Cambios realizados:</strong></p>";
        echo "<ul>";
        foreach ($changes as $change) {
            echo "<li>{$change}</li>";
        }
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<h3>ℹ️ Sin Cambios</h3>";
        echo "<p>La base de datos ya tiene todas las columnas necesarias.</p>";
        echo "</div>";
    }

    echo "<div class='success'>";
    echo "<h3>🎯 Funcionalidades Habilitadas:</h3>";
    echo "<ul>";
    echo "<li><strong>image_path</strong>: Ruta de la imagen en el servidor</li>";
    echo "<li><strong>image_display</strong>: Modo de visualización:";
    echo "<ul>";
    echo "<li><code>inline</code> - Imagen dentro del cuerpo del email (cid:)</li>";
    echo "<li><code>attachment</code> - Imagen como adjunto</li>";
    echo "<li><code>none</code> - Sin imagen</li>";
    echo "</ul></li>";
    echo "<li><strong>image_cid</strong>: Content-ID único para imágenes inline</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>📝 Uso en HTML:</h3>";
    echo "<p><strong>Para imagen inline</strong> (dentro del cuerpo):</p>";
    echo "<pre style='background:#1f2937;color:#f3f4f6;padding:15px;border-radius:5px;overflow-x:auto;'>";
    echo htmlspecialchars('<img src="{template_image}" alt="Imagen" style="max-width:100%;">');
    echo "</pre>";
    echo "<p class='text-muted'>La variable <code>{template_image}</code> se reemplazará automáticamente</p>";
    echo "</div>";

    echo "<p style='text-align:center;'>";
    echo "<a href='email_marketing.php?page=templates' class='btn'>📝 Gestionar Plantillas</a> ";
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
