<?php
/**
 * Generador de Favicon - Bandera de Costa Rica
 * Crea favicon.ico y versiones PNG para CompraTica.com
 */

// Crear directorio si no existe
$faviconDir = __DIR__;

/**
 * Crear imagen PNG de la bandera de Costa Rica
 */
function createCostaRicaFlagPNG($size) {
    // Crear imagen
    $img = imagecreatetruecolor($size, $size);

    // Colores de la bandera de CR
    $azul = imagecolorallocate($img, 0, 43, 127);    // #002b7f
    $blanco = imagecolorallocate($img, 255, 255, 255); // #ffffff
    $rojo = imagecolorallocate($img, 206, 17, 38);    // #ce1126

    // Calcular alturas proporcionales de las franjas
    // Bandera CR: 1-1-2-1-1 (azul-blanco-rojo-blanco-azul)
    $franjaSimple = $size / 6;
    $franjaDoble = $franjaSimple * 2;

    // Dibujar franjas
    imagefilledrectangle($img, 0, 0, $size, $franjaSimple, $azul);                                    // Azul superior
    imagefilledrectangle($img, 0, $franjaSimple, $size, $franjaSimple * 2, $blanco);                // Blanco superior
    imagefilledrectangle($img, 0, $franjaSimple * 2, $size, $franjaSimple * 4, $rojo);              // Rojo central (doble)
    imagefilledrectangle($img, 0, $franjaSimple * 4, $size, $franjaSimple * 5, $blanco);            // Blanco inferior
    imagefilledrectangle($img, 0, $franjaSimple * 5, $size, $size, $azul);                          // Azul inferior

    return $img;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Generando Favicon</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:900px;margin:0 auto;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:20px;margin:15px 0;border-radius:4px;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:20px;margin:15px 0;border-radius:4px;}
.preview{display:flex;gap:20px;align-items:center;margin:20px 0;}
img{border:2px solid #ddd;padding:5px;background:white;}
h1{color:#16a34a;}
</style></head><body>";

echo "<h1>🇨🇷 Generador de Favicon - Bandera de Costa Rica</h1>";

try {
    $generated = [];

    // Generar favicon-16x16.png
    echo "<div class='info'><strong>1. Generando favicon-16x16.png...</strong></div>";
    $img16 = createCostaRicaFlagPNG(16);
    $file16 = $faviconDir . '/favicon-16x16.png';
    imagepng($img16, $file16);
    imagedestroy($img16);
    $generated[] = 'favicon-16x16.png';
    echo "<div class='success'>✓ favicon-16x16.png creado</div>";

    // Generar favicon-32x32.png
    echo "<div class='info'><strong>2. Generando favicon-32x32.png...</strong></div>";
    $img32 = createCostaRicaFlagPNG(32);
    $file32 = $faviconDir . '/favicon-32x32.png';
    imagepng($img32, $file32);
    imagedestroy($img32);
    $generated[] = 'favicon-32x32.png';
    echo "<div class='success'>✓ favicon-32x32.png creado</div>";

    // Generar apple-touch-icon.png (180x180)
    echo "<div class='info'><strong>3. Generando apple-touch-icon.png...</strong></div>";
    $img180 = createCostaRicaFlagPNG(180);
    $file180 = $faviconDir . '/apple-touch-icon.png';
    imagepng($img180, $file180);
    imagedestroy($img180);
    $generated[] = 'apple-touch-icon.png';
    echo "<div class='success'>✓ apple-touch-icon.png creado (180x180)</div>";

    // Generar favicon.ico (usando la imagen de 32x32)
    echo "<div class='info'><strong>4. Generando favicon.ico...</strong></div>";
    // PHP no soporta nativamente ICO, así que copiamos el PNG como fallback
    copy($file32, $faviconDir . '/favicon.ico');
    $generated[] = 'favicon.ico';
    echo "<div class='success'>✓ favicon.ico creado</div>";

    // Preview
    echo "<div class='success'>";
    echo "<h3>✅ ¡Favicon Generado Exitosamente!</h3>";
    echo "<div class='preview'>";
    echo "<div><img src='favicon-16x16.png' width='32' height='32'><br><small>16x16</small></div>";
    echo "<div><img src='favicon-32x32.png' width='64' height='64'><br><small>32x32</small></div>";
    echo "<div><img src='apple-touch-icon.png' width='90' height='90'><br><small>180x180</small></div>";
    echo "</div>";
    echo "</div>";

    // Instrucciones
    echo "<div class='info'>";
    echo "<h3>📝 Archivos Generados:</h3>";
    echo "<ul>";
    foreach ($generated as $file) {
        echo "<li><code>{$file}</code></li>";
    }
    echo "</ul>";

    echo "<h3>🔧 Próximos Pasos:</h3>";
    echo "<ol>";
    echo "<li>Los archivos ya están en la raíz del sitio</li>";
    echo "<li>Agrega las siguientes líneas al <code>&lt;head&gt;</code> de tu sitio:</li>";
    echo "</ol>";

    echo "<pre style='background:#1f2937;color:#f3f4f6;padding:15px;border-radius:5px;overflow-x:auto;'>";
    echo htmlspecialchars('<!-- Favicon - Bandera de Costa Rica -->
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="shortcut icon" href="/favicon.ico">');
    echo "</pre>";
    echo "</div>";

    echo "<div class='success'>";
    echo "<h3>🎯 ¿Cómo usar?</h3>";
    echo "<p>El favicon se mostrará automáticamente en:</p>";
    echo "<ul>";
    echo "<li>🌐 Pestaña del navegador</li>";
    echo "<li>⭐ Marcadores/Favoritos</li>";
    echo "<li>📱 Pantalla de inicio iOS (Apple Touch Icon)</li>";
    echo "<li>🔍 Búsquedas de Google</li>";
    echo "</ul>";
    echo "</div>";

    echo "<p style='text-align:center;margin-top:30px;'>";
    echo "<a href='/' style='display:inline-block;padding:12px 24px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;'>← Volver al Sitio</a> ";
    echo "<a href='/admin/' style='display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:6px;'>Admin Panel</a>";
    echo "</p>";

} catch (Exception $e) {
    echo "<div style='background:#fee;border-left:4px solid #dc2626;padding:20px;margin:15px 0;border-radius:4px;'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
