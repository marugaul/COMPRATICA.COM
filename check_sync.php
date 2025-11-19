<?php
/**
 * Verifica si los archivos están sincronizados con la última versión
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Estado de Sincronización</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .ok { color: green; } .old { color: red; }</style>";

$files = [
    'import_osm_progress.php' => 'auto-resume',
    'api/start_import.php' => 'cURL',
    'api/import_functions.php' => 'cURL',
    'api/reset_import.php' => 'reset'
];

echo "<ul>";
foreach ($files as $file => $feature) {
    $path = __DIR__ . '/' . $file;

    if (file_exists($path)) {
        $modTime = filemtime($path);
        $modDate = date('Y-m-d H:i:s', $modTime);
        $age = time() - $modTime;

        $class = $age < 120 ? 'ok' : 'old'; // Verde si es de hace menos de 2 minutos

        echo "<li class='$class'>";
        echo "<strong>$file</strong> ($feature)<br>";
        echo "Última modificación: $modDate<br>";
        echo "Hace: " . round($age / 60, 1) . " minutos";
        echo "</li><br>";
    } else {
        echo "<li style='color: red;'><strong>$file</strong> - NO EXISTE</li><br>";
    }
}
echo "</ul>";

echo "<hr>";
echo "<p>Fecha actual del servidor: " . date('Y-m-d H:i:s') . "</p>";

// Verificar si auto-resume está en el archivo
$progressFile = __DIR__ . '/import_osm_progress.php';
if (file_exists($progressFile)) {
    $content = file_get_contents($progressFile);
    if (strpos($content, 'Reanudando importación en curso') !== false) {
        echo "<p class='ok'>✅ Auto-resume feature está presente</p>";
    } else {
        echo "<p class='old'>❌ Auto-resume feature NO está presente (archivo antiguo)</p>";
    }
}

echo "<hr>";
echo "<p><a href='import_osm_progress.php'>Ir a página de importación</a></p>";
?>
