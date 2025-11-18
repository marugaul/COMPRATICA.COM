<?php
/**
 * CAPTURAR HTML RENDERIZADO - Guardar el HTML final que genera checkout.php
 */

// Simular sesión y variables necesarias
session_start();

// Configurar variables mínimas necesarias
$_SESSION['user_id'] = 1; // Usuario de prueba
$_GET['sale_id'] = 18; // Espacio de prueba que tiene pickup

// Capturar el output
ob_start();

try {
    // Suprimir errores para capturar el HTML
    error_reporting(0);

    // Incluir checkout.php
    include '/home/comprati/public_html/checkout.php';

    // Obtener el HTML generado
    $html = ob_get_clean();

    // Guardar en archivo
    $filename = '/home/comprati/public_html/checkout_rendered_' . time() . '.html';
    file_put_contents($filename, $html);

    // Mostrar resultado
    header('Content-Type: text/plain; charset=utf-8');

    echo "==========================================\n";
    echo "HTML RENDERIZADO CAPTURADO\n";
    echo "==========================================\n\n";
    echo "Archivo guardado en:\n";
    echo basename($filename) . "\n\n";
    echo "Líneas totales: " . substr_count($html, "\n") . "\n\n";

    // Buscar la línea 980
    $lines = explode("\n", $html);

    echo "==========================================\n";
    echo "LÍNEAS 975-990 DEL HTML RENDERIZADO\n";
    echo "==========================================\n\n";

    for ($i = 974; $i < min(990, count($lines)); $i++) {
        $lineNum = $i + 1;
        $mark = ($lineNum == 980) ? ' <<<<< ERROR AQUÍ' : '';
        echo sprintf("%4d: %s%s\n", $lineNum, substr($lines[$i], 0, 120), $mark);
    }

    echo "\n\n";
    echo "==========================================\n";
    echo "BUSCAR 'style' CERCA DE LÍNEA 980\n";
    echo "==========================================\n\n";

    for ($i = 970; $i < min(1000, count($lines)); $i++) {
        if (stripos($lines[$i], 'style') !== false) {
            echo sprintf("%4d: %s\n", $i + 1, substr($lines[$i], 0, 120));
        }
    }

    echo "\n\n";
    echo "Puedes ver el archivo completo en:\n";
    echo "https://compratica.com/" . basename($filename) . "\n";

} catch (Exception $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage();
}
?>
