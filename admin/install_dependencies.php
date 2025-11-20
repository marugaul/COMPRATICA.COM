<?php
/**
 * Instalador de Dependencias PHP (Composer)
 * Ejecutar desde navegador: https://compratica.com/admin/install_dependencies.php
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado - Debes estar logueado como admin');
}

set_time_limit(300); // 5 minutos

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Instalador de Dependencias</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;max-width:800px;margin:0 auto}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;font-size:12px}
.spinner{border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:20px auto}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
</style></head><body>";

echo "<h1>🔧 Instalador de Dependencias - PHPMailer</h1>";

$base_dir = dirname(__DIR__);
$vendor_dir = $base_dir . '/vendor';
$composer_phar = $base_dir . '/composer.phar';

echo "<div class='step'><h3>1. Verificando estado actual</h3>";

// Verificar si vendor existe
if (file_exists($vendor_dir) && file_exists($vendor_dir . '/autoload.php')) {
    echo "<p><span class='ok'>✓ Directorio vendor/ ya existe</span></p>";
    
    if (file_exists($vendor_dir . '/phpmailer/phpmailer/src/PHPMailer.php')) {
        echo "<p><span class='ok'>✓ PHPMailer ya está instalado</span></p>";
        echo "<p>No es necesario instalar nada.</p>";
        echo "</div>";
        echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
        echo "<h3 style='color:#065f46'>✓ Sistema Listo</h3>";
        echo "<p>Las dependencias ya están instaladas.</p>";
        echo "<p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px'>Ir a Email Marketing</a></p>";
        echo "</div></body></html>";
        exit;
    }
}

echo "<p><span class='error'>⚠ Dependencias no instaladas</span></p>";
echo "</div>";

// Paso 2: Descargar Composer
echo "<div class='step'><h3>2. Descargando Composer</h3>";

if (!file_exists($composer_phar)) {
    echo "<p>Descargando composer.phar...</p>";
    echo "<div class='spinner'></div>";
    flush();
    
    $composer_installer = file_get_contents('https://getcomposer.org/installer');
    if ($composer_installer === false) {
        echo "<p><span class='error'>✗ Error al descargar el instalador de Composer</span></p>";
        die("</div></body></html>");
    }
    
    file_put_contents($base_dir . '/composer-setup.php', $composer_installer);
    
    // Ejecutar instalador
    ob_start();
    chdir($base_dir);
    include $base_dir . '/composer-setup.php';
    $output = ob_get_clean();
    
    unlink($base_dir . '/composer-setup.php');
    
    if (file_exists($composer_phar)) {
        echo "<p><span class='ok'>✓ Composer descargado exitosamente</span></p>";
    } else {
        echo "<p><span class='error'>✗ Error al instalar Composer</span></p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        die("</div></body></html>");
    }
} else {
    echo "<p><span class='ok'>✓ Composer ya existe</span></p>";
}
echo "</div>";

// Paso 3: Instalar dependencias
echo "<div class='step'><h3>3. Instalando Dependencias (PHPMailer)</h3>";
echo "<p>Esto puede tomar 30-60 segundos...</p>";
echo "<div class='spinner'></div>";
flush();

// Ejecutar composer install
chdir($base_dir);

$cmd = "php -d allow_url_fopen=1 " . escapeshellarg($composer_phar) . " install --no-dev --optimize-autoloader --no-interaction 2>&1";

ob_start();
passthru($cmd, $return_code);
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";

if ($return_code === 0 && file_exists($vendor_dir . '/autoload.php')) {
    echo "<p><span class='ok'>✓ Dependencias instaladas exitosamente</span></p>";
} else {
    echo "<p><span class='error'>✗ Error al instalar dependencias (código: $return_code)</span></p>";
}
echo "</div>";

// Paso 4: Verificar instalación
echo "<div class='step'><h3>4. Verificación Final</h3>";

$checks = [
    'vendor/autoload.php' => 'Autoloader de Composer',
    'vendor/phpmailer/phpmailer/src/PHPMailer.php' => 'PHPMailer',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php' => 'PHPSpreadsheet (Excel)'
];

$all_ok = true;
foreach ($checks as $file => $name) {
    if (file_exists($base_dir . '/' . $file)) {
        echo "<p><span class='ok'>✓ $name</span></p>";
    } else {
        echo "<p><span class='error'>✗ $name NO encontrado</span></p>";
        $all_ok = false;
    }
}
echo "</div>";

if ($all_ok) {
    echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
    echo "<h3 style='color:#065f46'>✓ ¡Instalación Completada!</h3>";
    echo "<p>Todas las dependencias están instaladas correctamente.</p>";
    echo "<p><strong>Ya puedes usar el sistema de Email Marketing.</strong></p>";
    echo "<p>";
    echo "<a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;margin:5px'>Ir a Email Marketing</a>";
    echo "<a href='email_marketing.php?page=new-campaign' style='display:inline-block;background:#16a34a;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;margin:5px'>Nueva Campaña</a>";
    echo "</p>";
    echo "</div>";
} else {
    echo "<div class='step' style='background:#fee2e2;border-left-color:#dc2626'>";
    echo "<h3 style='color:#991b1b'>⚠ Instalación Incompleta</h3>";
    echo "<p>Algunos componentes no se instalaron correctamente.</p>";
    echo "<p>Revisa los errores arriba o contacta soporte.</p>";
    echo "</div>";
}

echo "</body></html>";
?>
