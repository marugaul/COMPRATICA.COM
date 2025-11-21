<?php
// ============================================
// INSTALAR PHPMAILER - Descarga Directa
// Descarga y extrae PHPMailer desde GitHub
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutos

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Instalar PHPMailer</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%); padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 30px; margin: 20px 0; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success { background: #d1fae5; color: #065f46; padding: 20px; border-radius: 8px; border-left: 6px solid #10b981; }
        .error { background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; border-left: 6px solid #ef4444; }
        .warning { background: #fef3c7; color: #92400e; padding: 20px; border-radius: 8px; border-left: 6px solid #f59e0b; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 8px; overflow: auto; font-size: 12px; }
        h1 { color: #0891b2; text-align: center; }
        h2 { color: #0e7490; }
        .btn { display: inline-block; background: #0891b2; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; font-size: 16px; }
        .btn:hover { background: #0e7490; }
        .big-success { background: #10b981; color: white; padding: 40px; border-radius: 12px; text-align: center; font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>📦 Instalador de PHPMailer</h1>
        <p style="text-align:center;font-size:18px;color:#666">Descarga e instalación directa desde GitHub</p>
    </div>

<?php

if (isset($_GET['install'])) {

    echo "<div class='card'>";
    echo "<h2>Instalando PHPMailer...</h2>";

    $vendor_dir = __DIR__ . '/../vendor';
    $phpmailer_dir = $vendor_dir . '/phpmailer/phpmailer';

    try {
        // Paso 1: Crear directorios
        echo "<p>📁 <strong>Paso 1:</strong> Crear directorios...</p>";

        if (!is_dir($vendor_dir)) {
            mkdir($vendor_dir, 0755, true);
            echo "<p class='success'>✓ Creado: $vendor_dir</p>";
        } else {
            echo "<p class='success'>✓ Ya existe: $vendor_dir</p>";
        }

        if (!is_dir($phpmailer_dir)) {
            mkdir($phpmailer_dir, 0755, true);
            echo "<p class='success'>✓ Creado: $phpmailer_dir</p>";
        }

        // Paso 2: Descargar PHPMailer
        echo "<p>⬇️ <strong>Paso 2:</strong> Descargar PHPMailer desde GitHub...</p>";

        $zip_url = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
        $zip_file = $vendor_dir . '/phpmailer.zip';

        $ch = curl_init($zip_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $zip_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$zip_content) {
            throw new Exception("Error al descargar PHPMailer (HTTP $http_code)");
        }

        file_put_contents($zip_file, $zip_content);
        $zip_size = filesize($zip_file);
        echo "<p class='success'>✓ Descargado: " . round($zip_size / 1024) . " KB</p>";

        // Paso 3: Extraer ZIP
        echo "<p>📦 <strong>Paso 3:</strong> Extraer archivos...</p>";

        $zip = new ZipArchive();
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($vendor_dir);
            $zip->close();
            echo "<p class='success'>✓ Archivos extraídos</p>";
        } else {
            throw new Exception("Error al abrir el archivo ZIP");
        }

        // Paso 4: Mover archivos a la ubicación correcta
        echo "<p>📂 <strong>Paso 4:</strong> Organizar archivos...</p>";

        $extracted_dir = $vendor_dir . '/PHPMailer-6.9.1';
        $src_dir = $extracted_dir . '/src';

        if (is_dir($src_dir)) {
            // Copiar archivos del src a phpmailer/phpmailer/src
            $dest_src_dir = $phpmailer_dir . '/src';
            if (!is_dir($dest_src_dir)) {
                mkdir($dest_src_dir, 0755, true);
            }

            $files = scandir($src_dir);
            $copied = 0;
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    copy($src_dir . '/' . $file, $dest_src_dir . '/' . $file);
                    $copied++;
                }
            }
            echo "<p class='success'>✓ Copiados $copied archivos a src/</p>";
        }

        // Limpiar
        unlink($zip_file);
        echo "<p class='success'>✓ Archivo ZIP eliminado</p>";

        // Paso 5: Crear autoloader simple
        echo "<p>⚙️ <strong>Paso 5:</strong> Crear autoloader...</p>";

        $autoload_content = "<?php
// Autoloader simple para PHPMailer
spl_autoload_register(function(\$class) {
    \$prefix = 'PHPMailer\\\\PHPMailer\\\\';
    \$base_dir = __DIR__ . '/phpmailer/phpmailer/src/';

    \$len = strlen(\$prefix);
    if (strncmp(\$prefix, \$class, \$len) !== 0) {
        return;
    }

    \$relative_class = substr(\$class, \$len);
    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';

    if (file_exists(\$file)) {
        require \$file;
    }
});
";

        file_put_contents($vendor_dir . '/autoload.php', $autoload_content);
        echo "<p class='success'>✓ Autoloader creado</p>";

        // Paso 6: Verificar instalación
        echo "<p>✅ <strong>Paso 6:</strong> Verificar instalación...</p>";

        require_once $vendor_dir . '/autoload.php';

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $version = $mail::VERSION;

            echo "<div class='big-success'>";
            echo "✓✓✓ PHPMAILER INSTALADO EXITOSAMENTE ✓✓✓<br>";
            echo "<span style='font-size:18px;margin-top:10px;display:block'>Versión: $version</span>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<h3>Archivos Instalados:</h3>";
            echo "<ul>";
            echo "<li>✓ PHPMailer.php</li>";
            echo "<li>✓ SMTP.php</li>";
            echo "<li>✓ Exception.php</li>";
            echo "<li>✓ OAuth.php</li>";
            echo "<li>✓ Autoloader</li>";
            echo "</ul>";
            echo "</div>";

            echo "<div class='card' style='background:#e0f2fe;text-align:center'>";
            echo "<h3 style='color:#075985'>🎉 Todo Listo!</h3>";
            echo "<p style='font-size:16px;color:#0c4a6e'>Ahora puedes enviar emails con el sistema de email marketing</p>";
            echo "<p style='margin-top:20px'>";
            echo "<a href='TEST_COMPLETE.php' class='btn'>Enviar Email de Prueba →</a>";
            echo "</p>";
            echo "</div>";

        } else {
            throw new Exception("PHPMailer instalado pero clase no disponible");
        }

        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Error Durante la Instalación</h3>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
        echo "</div>";
    }

} else {
    // Mostrar información y botón de instalación

    $vendor_exists = file_exists(__DIR__ . '/../vendor/autoload.php');

    echo "<div class='card'>";
    echo "<h2>Estado Actual</h2>";

    if ($vendor_exists) {
        echo "<div class='success'>";
        echo "<strong>✓ PHPMailer ya está instalado</strong>";
        echo "</div>";

        echo "<p style='text-align:center;margin-top:20px'>";
        echo "<a href='TEST_COMPLETE.php' class='btn'>Ir al Test de Email →</a>";
        echo "</p>";

    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ PHPMailer NO está instalado</strong>";
        echo "</div>";

        echo "<h3>Este instalador hará:</h3>";
        echo "<ol>";
        echo "<li>Descargar PHPMailer v6.9.1 desde GitHub</li>";
        echo "<li>Extraer los archivos en vendor/phpmailer/phpmailer/</li>";
        echo "<li>Crear un autoloader funcional</li>";
        echo "<li>Verificar que todo funciona correctamente</li>";
        echo "</ol>";

        echo "<div style='background:#e0f2fe;padding:20px;border-radius:8px;margin:20px 0'>";
        echo "<p><strong>Nota:</strong> El proceso toma aproximadamente 10-15 segundos</p>";
        echo "</div>";

        echo "<p style='text-align:center;margin-top:30px'>";
        echo "<a href='?install=1' class='btn'>📦 INSTALAR PHPMAILER AHORA</a>";
        echo "</p>";
    }

    echo "</div>";
}

?>

</div>

</body>
</html>
