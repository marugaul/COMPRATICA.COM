<?php
/**
 * Migración: Agregar columna is_default a email_templates
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

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migración Plantillas</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:900px;margin:0 auto;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:20px;margin:15px 0;border-radius:4px;}
.error{background:#fee;border-left:4px solid #dc2626;padding:20px;margin:15px 0;border-radius:4px;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:20px;margin:15px 0;border-radius:4px;}
h1{color:#16a34a;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
code{background:#e5e7eb;padding:2px 6px;border-radius:3px;font-family:monospace;}
</style></head><body>";

echo "<h1>🔧 Migración: Sistema de Plantilla por Defecto</h1>";

try {
    // Verificar si la columna ya existe
    echo "<div class='info'><strong>1. Verificando estructura de tabla...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates LIKE 'is_default'");
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        echo "<div class='info'>";
        echo "<strong>ℹ️ La columna <code>is_default</code> ya existe</strong><br>";
        echo "No es necesario ejecutar la migración.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>➕ Agregando columna <code>is_default</code>...</strong>";
        echo "</div>";

        // Agregar columna
        $pdo->exec("
            ALTER TABLE email_templates
            ADD COLUMN is_default TINYINT(1) DEFAULT 0 AFTER is_active
        ");

        echo "<div class='success'>";
        echo "<strong>✅ Columna agregada exitosamente</strong>";
        echo "</div>";

        // Marcar primera plantilla como default si no hay ninguna
        echo "<div class='info'>";
        echo "<strong>2. Configurando plantilla por defecto...</strong>";
        echo "</div>";

        $defaultCount = $pdo->query("SELECT COUNT(*) FROM email_templates WHERE is_default = 1")->fetchColumn();

        if ($defaultCount == 0) {
            // Marcar la primera plantilla activa como default
            $firstTemplate = $pdo->query("SELECT id, name FROM email_templates WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

            if ($firstTemplate) {
                $pdo->prepare("UPDATE email_templates SET is_default = 1 WHERE id = ?")->execute([$firstTemplate['id']]);
                echo "<div class='success'>";
                echo "<strong>✅ Plantilla por defecto configurada:</strong><br>";
                echo "ID: {$firstTemplate['id']}<br>";
                echo "Nombre: " . htmlspecialchars($firstTemplate['name']);
                echo "</div>";
            }
        }
    }

    // Mostrar estado actual
    echo "<div class='info'><strong>3. Estado actual de plantillas:</strong></div>";

    $templates = $pdo->query("
        SELECT id, name, company, is_active, is_default
        FROM email_templates
        ORDER BY is_default DESC, company
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table style='width:100%;border-collapse:collapse;background:white;'>";
    echo "<tr style='background:#0891b2;color:white;'>";
    echo "<th style='padding:10px;text-align:left;'>ID</th>";
    echo "<th style='padding:10px;text-align:left;'>Nombre</th>";
    echo "<th style='padding:10px;text-align:left;'>Company</th>";
    echo "<th style='padding:10px;text-align:center;'>Activa</th>";
    echo "<th style='padding:10px;text-align:center;'>Por Defecto</th>";
    echo "</tr>";

    foreach ($templates as $tpl) {
        $rowStyle = $tpl['is_default'] ? 'background:#fef3c7;font-weight:bold;' : '';
        echo "<tr style='border-bottom:1px solid #ddd;{$rowStyle}'>";
        echo "<td style='padding:10px;'>{$tpl['id']}</td>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($tpl['name']) . "</td>";
        echo "<td style='padding:10px;'><code>{$tpl['company']}</code></td>";
        echo "<td style='padding:10px;text-align:center;'>" . ($tpl['is_active'] ? '✓' : '✗') . "</td>";
        echo "<td style='padding:10px;text-align:center;'>" . ($tpl['is_default'] ? '★' : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='success'>";
    echo "<h3>✅ Migración Completada Exitosamente</h3>";
    echo "<p>El sistema de plantillas ahora soporta:</p>";
    echo "<ul>";
    echo "<li>✓ Marcar plantillas como predeterminadas</li>";
    echo "<li>✓ Solo una plantilla puede ser default a la vez</li>";
    echo "<li>✓ Vista resaltada en el dashboard con ★</li>";
    echo "<li>✓ Borde dorado alrededor de la plantilla default</li>";
    echo "</ul>";
    echo "</div>";

    echo "<p style='text-align:center;'>";
    echo "<a href='email_marketing.php?page=templates' class='btn'>📝 Ver Plantillas</a> ";
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
