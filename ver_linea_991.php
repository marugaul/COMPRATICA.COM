<?php
/**
 * VER LÍNEA 991 - Muestra líneas alrededor del error
 */

$secret = 'LINE991';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');

$checkout = '/home/comprati/public_html/checkout.php';

echo "==========================================\n";
echo "LÍNEAS 980-1010 DEL ARCHIVO CHECKOUT.PHP\n";
echo "==========================================\n\n";

$lines = file($checkout);

for ($i = 979; $i < min(1010, count($lines)); $i++) {
    $lineNum = $i + 1;
    $mark = ($lineNum == 991) ? ' <<<< ERROR AQUÍ' : '';
    echo sprintf("%4d: %s%s", $lineNum, $lines[$i], $mark);
}

echo "\n\n";
echo "==========================================\n";
echo "BLOQUES <script> EN EL ARCHIVO\n";
echo "==========================================\n\n";

$content = file_get_contents($checkout);
$scriptStart = strpos($content, '<script>');
$scriptEnd = strpos($content, '</script>');

if ($scriptStart !== false && $scriptEnd !== false) {
    $scriptContent = substr($content, $scriptStart, $scriptEnd - $scriptStart + 9);

    // Contar líneas antes del script
    $beforeScript = substr($content, 0, $scriptStart);
    $linesBefore = substr_count($beforeScript, "\n");

    echo "Script comienza en línea aproximadamente: " . ($linesBefore + 1) . "\n";
    echo "Primeras 100 líneas del script:\n";
    echo "----------------------------------------\n";

    $scriptLines = explode("\n", $scriptContent);
    for ($i = 0; $i < min(100, count($scriptLines)); $i++) {
        echo sprintf("%4d: %s\n", $linesBefore + $i + 1, $scriptLines[$i]);
    }
}
?>
