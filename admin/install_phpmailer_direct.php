<?php
/**
 * Instalador Directo de PHPMailer (sin Composer)
 * Descarga PHPMailer desde GitHub
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

set_time_limit(300);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Instalador PHPMailer</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;max-width:800px;margin:0 auto}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;font-size:12px}
.spinner{border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:20px auto}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
</style></head><body>";

echo "<h1>🔧 Instalador Directo de PHPMailer</h1>";

$base_dir = dirname(__DIR__);
$vendor_dir = $base_dir . '/vendor';
$phpmailer_dir = $vendor_dir . '/phpmailer/phpmailer';

// Paso 1: Verificar estado
echo "<div class='step'><h3>1. Verificando estado actual</h3>";
if (file_exists($phpmailer_dir . '/src/PHPMailer.php')) {
    echo "<p><span class='ok'>✓ PHPMailer ya está instalado</span></p></div>";
    echo "<div class='step' style='background:#d1fae5'>";
    echo "<h3>✓ Sistema Listo</h3>";
    echo "<p><a href='email_marketing.php' style='background:#dc2626;color:white;padding:12px 24px;text-decoration:none;border-radius:6px'>Ir a Email Marketing</a></p>";
    echo "</div></body></html>";
    exit;
}
echo "<p><span class='error'>⚠ PHPMailer no instalado</span></p></div>";

// Paso 2: Crear directorios
echo "<div class='step'><h3>2. Creando estructura de directorios</h3>";
@mkdir($vendor_dir, 0755, true);
@mkdir($vendor_dir . '/phpmailer', 0755, true);
@mkdir($phpmailer_dir, 0755, true);
echo "<p><span class='ok'>✓ Directorios creados</span></p></div>";

// Paso 3: Descargar PHPMailer usando cURL
echo "<div class='step'><h3>3. Descargando PHPMailer v6.9.1</h3>";
echo "<div class='spinner'></div>";
flush();

$zip_url = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
$zip_file = $base_dir . '/phpmailer.zip';

// Intentar con cURL primero
$ch = curl_init($zip_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$zip_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $zip_content !== false) {
    file_put_contents($zip_file, $zip_content);
    echo "<p><span class='ok'>✓ Descarga completada (" . number_format(strlen($zip_content)) . " bytes)</span></p>";
} else {
    echo "<p><span class='error'>✗ Error al descargar (HTTP: $http_code)</span></p>";
    die("</div></body></html>");
}
echo "</div>";

// Paso 4: Descomprimir
echo "<div class='step'><h3>4. Descomprimiendo archivo</h3>";

$zip = new ZipArchive;
if ($zip->open($zip_file) === TRUE) {
    $zip->extractTo($vendor_dir . '/phpmailer');
    $zip->close();
    
    // Renombrar directorio extraído
    $extracted_dir = $vendor_dir . '/phpmailer/PHPMailer-6.9.1';
    if (file_exists($extracted_dir)) {
        rename($extracted_dir, $phpmailer_dir);
    }
    
    unlink($zip_file);
    echo "<p><span class='ok'>✓ Archivos descomprimidos</span></p>";
} else {
    echo "<p><span class='error'>✗ Error al descomprimir</span></p>";
    die("</div></body></html>");
}
echo "</div>";

// Paso 5: Crear autoload.php
echo "<div class='step'><h3>5. Configurando autoloader</h3>";

$autoload_content = <<<'AUTOLOAD'
<?php
// Autoloader simple para PHPMailer
spl_autoload_register(function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
AUTOLOAD;

file_put_contents($vendor_dir . '/autoload.php', $autoload_content);
echo "<p><span class='ok'>✓ Autoloader creado</span></p></div>";

// Paso 6: Verificar
echo "<div class='step'><h3>6. Verificación Final</h3>";

$required_files = [
    'src/PHPMailer.php',
    'src/SMTP.php',
    'src/Exception.php'
];

$all_ok = true;
foreach ($required_files as $file) {
    $full_path = $phpmailer_dir . '/' . $file;
    if (file_exists($full_path)) {
        echo "<p><span class='ok'>✓ $file</span></p>";
    } else {
        echo "<p><span class='error'>✗ $file NO encontrado</span></p>";
        $all_ok = false;
    }
}
echo "</div>";

if ($all_ok) {
    echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
    echo "<h3 style='color:#065f46'>✓ ¡Instalación Completada!</h3>";
    echo "<p>PHPMailer está instalado y listo para usar.</p>";
    echo "<p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;margin:5px'>Ir a Email Marketing</a>";
    echo "<a href='email_marketing.php?page=new-campaign' style='display:inline-block;background:#16a34a;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;margin:5px'>Nueva Campaña</a></p>";
    echo "</div>";
} else {
    echo "<div class='step' style='background:#fee2e2'>";
    echo "<h3>⚠ Error en la instalación</h3>";
    echo "<p>Algunos archivos no se instalaron correctamente.</p>";
    echo "</div>";
}

echo "</body></html>";
?>
