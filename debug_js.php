<?php
/**
 * DEBUG JAVASCRIPT - Encuentra errores de sintaxis en checkout.php
 */

$secret = 'DEBUG2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

header('Content-Type: text/plain');

$checkout = file_get_contents('/home/comprati/public_html/checkout.php');

// Extraer todo el contenido entre <script> y </script>
preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $checkout, $matches);

echo "========================================\n";
echo "BLOQUES DE JAVASCRIPT ENCONTRADOS\n";
echo "========================================\n\n";

foreach ($matches[1] as $index => $scriptContent) {
    echo "BLOQUE #" . ($index + 1) . ":\n";
    echo "----------------------------------------\n";

    $lines = explode("\n", $scriptContent);

    // Buscar líneas sospechosas
    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);

        // Buscar problemas comunes
        if (preg_match('/\bstyle\s+[^\.]/', $trimmed)) {
            echo "⚠️  LÍNEA " . ($lineNum + 1) . " - 'style' sin punto: $trimmed\n";
        }

        if (preg_match('/[^\'"]style[^\'"]/', $trimmed) && !preg_match('/\.style\./', $trimmed)) {
            echo "⚠️  LÍNEA " . ($lineNum + 1) . " - 'style' mal formateado: $trimmed\n";
        }

        // Buscar comillas no cerradas
        $singleQuotes = substr_count($trimmed, "'") - substr_count($trimmed, "\\'");
        $doubleQuotes = substr_count($trimmed, '"') - substr_count($trimmed, '\\"');

        if ($singleQuotes % 2 !== 0) {
            echo "❌ LÍNEA " . ($lineNum + 1) . " - Comillas simples no cerradas: $trimmed\n";
        }

        if ($doubleQuotes % 2 !== 0) {
            echo "❌ LÍNEA " . ($lineNum + 1) . " - Comillas dobles no cerradas: $trimmed\n";
        }
    }

    // Mostrar primeras 50 líneas del script
    echo "\nPRIMERAS 50 LÍNEAS:\n";
    echo "----------------------------------------\n";
    for ($i = 0; $i < min(50, count($lines)); $i++) {
        echo sprintf("%4d: %s\n", $i + 1, $lines[$i]);
    }

    if (count($lines) > 50) {
        echo "\n... [" . (count($lines) - 50) . " líneas más] ...\n";
    }

    echo "\n\n";
}

// Buscar inline style en HTML que pueda causar problemas
echo "========================================\n";
echo "BUSCAR 'style' EN HTML (líneas 980-1000)\n";
echo "========================================\n\n";

$lines = explode("\n", $checkout);
for ($i = 979; $i < min(1000, count($lines)); $i++) {
    if (stripos($lines[$i], 'style') !== false) {
        echo sprintf("%4d: %s\n", $i + 1, $lines[$i]);
    }
}
?>
