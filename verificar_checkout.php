<?php
/**
 * VERIFICAR CHECKOUT - Ver si tiene el código de Uber
 */

$secret = 'CHECKCHK2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "DIAGNÓSTICO DE CHECKOUT.PHP\n";
echo "==============================================\n\n";

$checkout_public = '/home/comprati/public_html/checkout.php';

// 1. Verificar que existe
echo "[1] Archivo checkout.php:\n";
if (file_exists($checkout_public)) {
    $size = filesize($checkout_public);
    $mtime = filemtime($checkout_public);
    echo "  ✓ Existe\n";
    echo "  Tamaño: " . number_format($size) . " bytes\n";
    echo "  Fecha: " . date('Y-m-d H:i:s', $mtime) . "\n\n";
} else {
    echo "  ❌ NO EXISTE\n";
    exit;
}

// 2. Buscar código específico de Uber
$content = file_get_contents($checkout_public);

echo "[2] Búsqueda de código Uber:\n";

$searches = [
    'geolocate-btn' => 'Botón de geolocalización',
    'Mi Ubicación' => 'Texto del botón',
    'delivery_lat' => 'Campo hidden de latitud',
    'delivery_lng' => 'Campo hidden de longitud',
    'navigator.geolocation' => 'API de geolocalización',
    'delivery-address-section' => 'Sección de dirección de entrega',
    'uber-option' => 'Opción de Uber',
    'Envío por Uber' => 'Texto de envío Uber'
];

foreach ($searches as $search => $description) {
    $found = strpos($content, $search) !== false;
    $status = $found ? '✓' : '❌';
    echo "  $status $description ($search)\n";

    if ($found) {
        // Mostrar el contexto (línea donde aparece)
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (strpos($line, $search) !== false) {
                echo "      Línea " . ($num + 1) . ": " . trim(substr($line, 0, 60)) . "...\n";
                break;
            }
        }
    }
}

// 3. Verificar si hay CSS que oculte la sección
echo "\n[3] Búsqueda de CSS que oculte elementos:\n";

$css_searches = [
    'display: none' => 'display none en delivery-address-section',
    'display:none' => 'display none (sin espacio)'
];

$found_hidden = false;
foreach ($css_searches as $css => $desc) {
    // Buscar si delivery-address-section tiene display:none
    if (preg_match('/#delivery-address-section[^{]*{[^}]*display\s*:\s*none/i', $content)) {
        echo "  ⚠️  ENCONTRADO: delivery-address-section tiene display:none\n";
        $found_hidden = true;
    }
}

if (!$found_hidden) {
    echo "  ✓ No hay CSS que oculte delivery-address-section\n";
}

// 4. Verificar JavaScript de mostrar/ocultar
echo "\n[4] JavaScript de control de visibilidad:\n";

if (preg_match('/getElementById\([\'"]delivery-address-section[\'"]\)\.style\.display\s*=\s*[\'"]([^\'"]*)[\'"]/i', $content, $matches)) {
    echo "  Encontrado código que cambia display a: " . $matches[1] . "\n";
} else {
    echo "  ⚠️  No se encontró código que controle la visibilidad\n";
}

// 5. Contar líneas del archivo
$lines = explode("\n", $content);
echo "\n[5] Información del archivo:\n";
echo "  Total líneas: " . count($lines) . "\n";

// Buscar líneas específicas
$geolocate_line = 0;
foreach ($lines as $num => $line) {
    if (strpos($line, 'geolocate-btn') !== false) {
        $geolocate_line = $num + 1;
        break;
    }
}
echo "  Botón geolocalización en línea: " . ($geolocate_line > 0 ? $geolocate_line : "NO ENCONTRADO") . "\n";

// 6. Ver si el archivo tiene la estructura esperada
echo "\n[6] Estructura esperada:\n";

$expected_lines = [
    940 => 'geolocate-btn',
    1143 => 'addEventListener.*geolocate'
];

foreach ($expected_lines as $line_num => $pattern) {
    if (isset($lines[$line_num - 1])) {
        $line = $lines[$line_num - 1];
        $matches_pattern = preg_match('/' . $pattern . '/i', $line);
        $status = $matches_pattern ? '✓' : '❌';
        echo "  $status Línea $line_num: " . ($matches_pattern ? "Correcto" : "Diferente") . "\n";
        if (!$matches_pattern) {
            echo "      Contenido real: " . trim(substr($line, 0, 80)) . "\n";
        }
    } else {
        echo "  ❌ Línea $line_num: No existe (archivo más corto)\n";
    }
}

echo "\n==============================================\n";
echo "RESUMEN\n";
echo "==============================================\n";

$has_all_code = strpos($content, 'geolocate-btn') !== false &&
                strpos($content, 'delivery_lat') !== false &&
                strpos($content, 'navigator.geolocation') !== false;

if ($has_all_code) {
    echo "✅ El archivo checkout.php TIENE todo el código de Uber\n";
    echo "\nPosibles causas del problema:\n";
    echo "1. Caché del navegador - Presiona Ctrl+Shift+R para recargar\n";
    echo "2. La sección está oculta por CSS\n";
    echo "3. JavaScript no se está ejecutando\n";
    echo "4. Estás viendo un checkout diferente\n";
} else {
    echo "❌ El archivo checkout.php NO TIENE el código de Uber completo\n";
    echo "\nNecesita sincronizarse de nuevo desde GitHub\n";
}

echo "\n";
echo "Para probar, ve a:\n";
echo "https://compratica.com/store.php?sale_id=18\n";
echo "Agrega un producto al carrito y ve al checkout\n";

echo "</pre>";
?>
