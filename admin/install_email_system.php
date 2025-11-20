<?php
/**
 * Instalador Web - Sistema de Email Marketing
 * Ejecutar UNA SOLA VEZ desde el navegador
 */

session_start();
require_once __DIR__ . '/../includes/auth.php';

// Solo admin puede ejecutar
require_login();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

// Verificar si ya fue instalado
$lock_file = __DIR__ . '/.email_marketing_installed.lock';
if (file_exists($lock_file)) {
    die('
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><title>Ya Instalado</title><link rel="stylesheet" href="../assets/style.css"></head>
    <body style="padding: 40px; font-family: Arial;">
        <div class="card" style="max-width: 600px; margin: 0 auto; padding: 30px;">
            <h2>✅ Sistema Ya Instalado</h2>
            <p>El sistema de email marketing ya fue instalado previamente el <strong>' . date('Y-m-d H:i:s', filemtime($lock_file)) . '</strong></p>
            <p>Si necesitás reinstalar, eliminá el archivo: <code>admin/.email_marketing_installed.lock</code></p>
            <br>
            <a href="email_marketing.php" class="btn primary">Ir a Email Marketing</a>
            <a href="dashboard.php" class="btn">Volver al Dashboard</a>
        </div>
    </body>
    </html>
    ');
}

$log = [];
$errors = [];

// Función para logging
function add_log($msg, $is_error = false) {
    global $log, $errors;
    $log[] = ['msg' => $msg, 'error' => $is_error];
    if ($is_error) {
        $errors[] = $msg;
    }
}

// ============================================
// PASO 1: Verificar/Crear Directorios
// ============================================
add_log('PASO 1: Creando directorios necesarios...');

$upload_dir = __DIR__ . '/../uploads/email_attachments';
if (!is_dir($upload_dir)) {
    if (@mkdir($upload_dir, 0755, true)) {
        add_log("✓ Directorio creado: $upload_dir");
    } else {
        add_log("✗ Error creando directorio: $upload_dir", true);
    }
} else {
    add_log("✓ Directorio ya existe: $upload_dir");
}

// ============================================
// PASO 2: Verificar Composer y PHPMailer
// ============================================
add_log('PASO 2: Verificando dependencias de Composer...');

$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    add_log('✓ Composer vendor/autoload.php encontrado');
    require_once $vendor_autoload;

    // Verificar PHPMailer
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        add_log('✓ PHPMailer disponible');
    } else {
        add_log('⚠ PHPMailer no encontrado en vendor (ejecutar: composer install)', true);
    }
} else {
    add_log('⚠ Composer no instalado. Necesitás ejecutar: composer install', true);
    add_log('  Podés usar PHPMailer existente si ya lo tenés en otra ruta');
}

// ============================================
// PASO 3: Ejecutar SQL Schema
// ============================================
add_log('PASO 3: Creando tablas en MySQL...');

try {
    require_once __DIR__ . '/../config/database.php';
    $config = require __DIR__ . '/../config/database.php';

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    add_log("✓ Conexión a MySQL: {$config['database']}");

    // Leer y ejecutar SQL
    $sql_file = __DIR__ . '/setup_email_marketing.sql';
    if (!file_exists($sql_file)) {
        add_log("✗ No se encuentra: $sql_file", true);
    } else {
        $sql = file_get_contents($sql_file);

        // Ejecutar por statements separados
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $created = 0;

        foreach ($statements as $stmt) {
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            try {
                $pdo->exec($stmt);
                $created++;
            } catch (PDOException $e) {
                // Ignorar si tabla ya existe
                if (strpos($e->getMessage(), 'already exists') === false) {
                    add_log("⚠ SQL Warning: " . substr($e->getMessage(), 0, 100));
                }
            }
        }

        add_log("✓ SQL ejecutado: $created statements procesados");
    }

} catch (Exception $e) {
    add_log("✗ Error MySQL: " . $e->getMessage(), true);
}

// ============================================
// PASO 4: Insertar Plantillas HTML
// ============================================
add_log('PASO 4: Insertando plantillas de email...');

$templates = [
    [
        'name' => 'Mixtico - Transporte Privado',
        'company' => 'Mixtico',
        'subject' => 'Su Transporte Privado en Costa Rica 🚐',
        'file' => __DIR__ . '/email_templates/mixtico_template.html'
    ],
    [
        'name' => 'CRV-SOFT - Soluciones Tecnológicas',
        'company' => 'CRV-SOFT',
        'subject' => 'Transforme su Negocio con Tecnología 💻',
        'file' => __DIR__ . '/email_templates/crv_soft_template.html'
    ],
    [
        'name' => 'CompraTica - Marketplace Costa Rica',
        'company' => 'CompraTica',
        'subject' => '¡Descubre las Mejores Ofertas en CompraTica! 🇨🇷',
        'file' => __DIR__ . '/email_templates/compratica_template.html'
    ]
];

foreach ($templates as $tpl) {
    if (!file_exists($tpl['file'])) {
        add_log("⚠ No se encuentra: {$tpl['file']}");
        continue;
    }

    $html = file_get_contents($tpl['file']);

    try {
        // Verificar si ya existe
        $exists = $pdo->prepare("SELECT id FROM email_templates WHERE company = ? LIMIT 1");
        $exists->execute([$tpl['company']]);

        if ($exists->fetch()) {
            add_log("  - {$tpl['company']}: Ya existe (omitido)");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (name, company, subject_default, html_content, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tpl['name'], $tpl['company'], $tpl['subject'], $html]);
            add_log("  ✓ {$tpl['company']}: Plantilla insertada");
        }
    } catch (Exception $e) {
        add_log("  ✗ {$tpl['company']}: " . $e->getMessage(), true);
    }
}

// ============================================
// PASO 5: Insertar Configuraciones SMTP
// ============================================
add_log('PASO 5: Insertando configuraciones SMTP...');

$smtp_configs = [
    ['name' => 'Mixtico', 'email' => 'info@mixtico.net', 'from_name' => 'Mixtico Shuttle Service'],
    ['name' => 'CRV-SOFT', 'email' => 'contacto@crv-soft.com', 'from_name' => 'CRV-SOFT'],
    ['name' => 'Compratica', 'email' => 'info@compratica.com', 'from_name' => 'CompraTica Costa Rica']
];

foreach ($smtp_configs as $cfg) {
    try {
        // Verificar si ya existe
        $exists = $pdo->prepare("SELECT id FROM email_smtp_configs WHERE name = ? LIMIT 1");
        $exists->execute([$cfg['name']]);

        if ($exists->fetch()) {
            add_log("  - {$cfg['name']}: Ya existe (omitido)");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO email_smtp_configs
                (name, from_email, from_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, is_active, created_at)
                VALUES (?, ?, ?, 'smtp.gmail.com', 587, ?, '', 'tls', 1, NOW())
            ");
            $stmt->execute([$cfg['name'], $cfg['email'], $cfg['from_name'], $cfg['email']]);
            add_log("  ✓ {$cfg['name']}: Configuración creada (completar contraseña SMTP)");
        }
    } catch (Exception $e) {
        add_log("  ✗ {$cfg['name']}: " . $e->getMessage(), true);
    }
}

// ============================================
// FINALIZAR
// ============================================
if (empty($errors)) {
    // Crear archivo lock
    file_put_contents($lock_file, date('Y-m-d H:i:s'));
    add_log('');
    add_log('✅ INSTALACIÓN COMPLETADA EXITOSAMENTE');
    $success = true;
} else {
    add_log('');
    add_log('⚠ INSTALACIÓN COMPLETADA CON ADVERTENCIAS');
    $success = false;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalador Email Marketing</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 20px; font-family: Arial, sans-serif; }
.install-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 30px; }
.install-header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: center; }
.log-entry { padding: 8px 12px; margin: 4px 0; border-left: 3px solid #16a34a; background: #f0fdf4; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 14px; }
.log-entry.error { border-left-color: #ef4444; background: #fef2f2; color: #991b1b; }
.log-entry.warning { border-left-color: #f59e0b; background: #fffbeb; }
.actions { margin-top: 30px; text-align: center; }
.btn { display: inline-block; padding: 12px 24px; margin: 5px; border-radius: 6px; text-decoration: none; font-weight: bold; }
.btn.primary { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; }
.btn.secondary { background: #e5e7eb; color: #374151; }
</style>
</head>
<body>

<div class="install-container">
    <div class="install-header">
        <h1 style="margin: 0;">📧 Instalador Email Marketing</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">CompraTica.com</p>
    </div>

    <h2><?= $success ? '✅ Instalación Exitosa' : '⚠️ Instalación con Advertencias' ?></h2>

    <div style="background: #f9fafb; padding: 15px; border-radius: 8px; max-height: 400px; overflow-y: auto;">
        <?php foreach ($log as $entry): ?>
            <div class="log-entry<?= $entry['error'] ? ' error' : '' ?>">
                <?= htmlspecialchars($entry['msg']) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($success): ?>
        <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #16a34a;">Próximos Pasos:</h3>
            <ol style="margin: 10px 0;">
                <li><strong>Configurar SMTP:</strong> Ir a Email Marketing → Config. SMTP</li>
                <li><strong>Completar contraseñas SMTP</strong> para las 3 cuentas (Mixtico, CRV-SOFT, Compratica)</li>
                <li><strong>Configurar DNS:</strong> Agregar registros SPF/DKIM para evitar spam</li>
                <li><strong>Crear primera campaña:</strong> Probar con email de prueba</li>
            </ol>
        </div>
    <?php else: ?>
        <div style="background: #fffbeb; border: 1px solid #fcd34d; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #d97706;">Advertencias Encontradas:</h3>
            <ul style="margin: 10px 0;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Nota:</strong> El sistema puede funcionar parcialmente. Revisá los errores arriba.</p>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a href="email_marketing.php" class="btn primary">📧 Ir a Email Marketing</a>
        <a href="dashboard.php" class="btn secondary">← Volver al Dashboard</a>
    </div>

    <div style="margin-top: 30px; padding: 15px; background: #f3f4f6; border-radius: 8px; font-size: 13px; color: #6b7280;">
        <strong>ℹ️ Información:</strong><br>
        • Este instalador solo se puede ejecutar UNA VEZ<br>
        • Archivo lock creado: <code>.email_marketing_installed.lock</code><br>
        • Documentación completa: <code>admin/EMAIL_MARKETING_README.md</code><br>
        • Para reinstalar, eliminá el archivo lock
    </div>
</div>

</body>
</html>
