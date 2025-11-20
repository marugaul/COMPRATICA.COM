#!/usr/bin/php
<?php
/**
 * Instalador CLI - Sistema de Email Marketing
 * Ejecutar desde línea de comandos o CRON
 */

echo "========================================\n";
echo "Instalador Email Marketing - CompraTica\n";
echo "========================================\n\n";

// Verificar si ya fue instalado
$lock_file = __DIR__ . '/.email_marketing_installed.lock';
if (file_exists($lock_file)) {
    echo "✅ Sistema ya instalado el " . date('Y-m-d H:i:s', filemtime($lock_file)) . "\n";
    echo "Para reinstalar, eliminar: admin/.email_marketing_installed.lock\n";
    exit(0);
}

$errors = 0;

// ============================================
// PASO 1: Crear Directorios
// ============================================
echo "PASO 1: Creando directorios...\n";

$upload_dir = __DIR__ . '/../uploads/email_attachments';
if (!is_dir($upload_dir)) {
    if (@mkdir($upload_dir, 0755, true)) {
        echo "  ✓ Directorio creado: $upload_dir\n";
    } else {
        echo "  ✗ Error creando directorio: $upload_dir\n";
        $errors++;
    }
} else {
    echo "  ✓ Directorio ya existe\n";
}

// ============================================
// PASO 2: Verificar Composer/PHPMailer
// ============================================
echo "\nPASO 2: Verificando dependencias...\n";

$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    echo "  ✓ Composer vendor/autoload.php encontrado\n";
    require_once $vendor_autoload;

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "  ✓ PHPMailer disponible\n";
    } else {
        echo "  ⚠ PHPMailer no encontrado (ejecutar: composer install)\n";
    }
} else {
    echo "  ⚠ Composer no instalado. Ejecutar: composer install\n";
    echo "     O usar PHPMailer existente en otra ruta\n";
}

// ============================================
// PASO 3: Ejecutar SQL Schema
// ============================================
echo "\nPASO 3: Creando tablas MySQL...\n";

try {
    $config_file = __DIR__ . '/../config/database.php';
    if (!file_exists($config_file)) {
        throw new Exception("No se encuentra config/database.php");
    }

    $config = require $config_file;

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "  ✓ Conexión MySQL: {$config['database']}\n";

    // Leer SQL
    $sql_file = __DIR__ . '/setup_email_marketing.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("No se encuentra: $sql_file");
    }

    $sql = file_get_contents($sql_file);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $created = 0;

    foreach ($statements as $stmt) {
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        try {
            $pdo->exec($stmt);
            $created++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "    ⚠ SQL: " . substr($e->getMessage(), 0, 80) . "...\n";
            }
        }
    }

    echo "  ✓ SQL ejecutado: $created statements\n";

} catch (Exception $e) {
    echo "  ✗ Error MySQL: " . $e->getMessage() . "\n";
    $errors++;
    exit(1);
}

// ============================================
// PASO 4: Insertar Plantillas
// ============================================
echo "\nPASO 4: Insertando plantillas HTML...\n";

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
        echo "  ⚠ No encontrado: " . basename($tpl['file']) . "\n";
        continue;
    }

    $html = file_get_contents($tpl['file']);

    try {
        $exists = $pdo->prepare("SELECT id FROM email_templates WHERE company = ? LIMIT 1");
        $exists->execute([$tpl['company']]);

        if ($exists->fetch()) {
            echo "  - {$tpl['company']}: Ya existe (omitido)\n";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (name, company, subject_default, html_content, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tpl['name'], $tpl['company'], $tpl['subject'], $html]);
            echo "  ✓ {$tpl['company']}: Plantilla insertada\n";
        }
    } catch (Exception $e) {
        echo "  ✗ {$tpl['company']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// ============================================
// PASO 5: Configuraciones SMTP
// ============================================
echo "\nPASO 5: Insertando configs SMTP...\n";

$smtp_configs = [
    ['name' => 'Mixtico', 'email' => 'info@mixtico.net', 'from_name' => 'Mixtico Shuttle Service'],
    ['name' => 'CRV-SOFT', 'email' => 'contacto@crv-soft.com', 'from_name' => 'CRV-SOFT'],
    ['name' => 'Compratica', 'email' => 'info@compratica.com', 'from_name' => 'CompraTica Costa Rica']
];

foreach ($smtp_configs as $cfg) {
    try {
        $exists = $pdo->prepare("SELECT id FROM email_smtp_configs WHERE name = ? LIMIT 1");
        $exists->execute([$cfg['name']]);

        if ($exists->fetch()) {
            echo "  - {$cfg['name']}: Ya existe (omitido)\n";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO email_smtp_configs
                (name, from_email, from_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, is_active, created_at)
                VALUES (?, ?, ?, 'smtp.gmail.com', 587, ?, '', 'tls', 1, NOW())
            ");
            $stmt->execute([$cfg['name'], $cfg['email'], $cfg['from_name'], $cfg['email']]);
            echo "  ✓ {$cfg['name']}: Config creada (completar password SMTP)\n";
        }
    } catch (Exception $e) {
        echo "  ✗ {$cfg['name']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// ============================================
// FINALIZAR
// ============================================
echo "\n========================================\n";

if ($errors === 0) {
    file_put_contents($lock_file, date('Y-m-d H:i:s'));
    echo "✅ INSTALACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "========================================\n\n";
    echo "Próximos pasos:\n";
    echo "1. Configurar SMTP en: admin/email_marketing.php?page=smtp-config\n";
    echo "2. Completar contraseñas SMTP para 3 cuentas\n";
    echo "3. Configurar DNS (SPF/DKIM)\n";
    echo "4. Crear primera campaña de prueba\n\n";
    echo "Acceder a: https://compratica.com/admin/email_marketing.php\n\n";
    exit(0);
} else {
    echo "⚠ INSTALACIÓN COMPLETADA CON $errors ERROR(ES)\n";
    echo "========================================\n";
    echo "Revisar logs arriba y corregir errores.\n\n";
    exit(1);
}
