<?php
/**
 * Test de Overpass API - Diagnóstico simple
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Overpass API</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .success { color: green; } .error { color: red; } pre { background: #f5f5f5; padding: 10px; overflow: auto; }</style>";

// Test 1: Query simple
echo "<h2>Test 1: Query de Hoteles en Costa Rica</h2>";

$query = '[out:json][timeout:90][bbox:8.0,-86.0,11.2,-82.5];
(
  node["tourism"~"hotel|hostel|guest_house|motel|apartment"];
  way["tourism"~"hotel|hostel|guest_house|motel|apartment"];
);
out body;
>;
out skel qt;';

echo "<strong>Query:</strong><pre>" . htmlspecialchars($query) . "</pre>";

$url = 'https://overpass-api.de/api/interpreter';

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                   "User-Agent: CompraTica/1.0 (shuttle service)\r\n",
        'content' => 'data=' . urlencode($query),
        'timeout' => 120
    ]
]);

echo "<p>Ejecutando query...</p>";
$startTime = microtime(true);

$response = @file_get_contents($url, false, $context);
$elapsed = round((microtime(true) - $startTime) * 1000);

if ($response === false) {
    echo "<p class='error'>❌ Error: No se pudo conectar a Overpass API</p>";
    $error = error_get_last();
    echo "<pre>" . print_r($error, true) . "</pre>";
    exit;
}

echo "<p class='success'>✅ Respuesta recibida en {$elapsed}ms</p>";

$data = json_decode($response, true);

if (!$data) {
    echo "<p class='error'>❌ Error: No se pudo decodificar JSON</p>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "...</pre>";
    exit;
}

echo "<p class='success'>✅ JSON decodificado correctamente</p>";

if (!isset($data['elements'])) {
    echo "<p class='error'>❌ Error: No hay 'elements' en la respuesta</p>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    exit;
}

$total = count($data['elements']);
echo "<p class='success'>✅ Total elementos: <strong>{$total}</strong></p>";

// Analizar elementos
$withName = 0;
$withCoords = 0;
$nodes = 0;
$ways = 0;

foreach ($data['elements'] as $el) {
    if ($el['type'] === 'node') $nodes++;
    if ($el['type'] === 'way') $ways++;

    if (!empty($el['tags']['name'])) {
        $withName++;
    }

    if (isset($el['lat']) && isset($el['lon'])) {
        $withCoords++;
    } elseif (isset($el['center'])) {
        $withCoords++;
    }
}

echo "<h3>Análisis:</h3>";
echo "<ul>";
echo "<li>Nodes: {$nodes}</li>";
echo "<li>Ways: {$ways}</li>";
echo "<li>Con nombre: {$withName}</li>";
echo "<li>Con coordenadas: {$withCoords}</li>";
echo "<li><strong>Importables: " . ($withName) . "</strong></li>";
echo "</ul>";

// Mostrar algunos ejemplos
echo "<h3>Ejemplos (primeros 10 con nombre):</h3>";
echo "<ol>";
$count = 0;
foreach ($data['elements'] as $el) {
    if ($count >= 10) break;
    if (empty($el['tags']['name'])) continue;

    $lat = $el['lat'] ?? ($el['center']['lat'] ?? 'N/A');
    $lng = $el['lon'] ?? ($el['center']['lon'] ?? 'N/A');

    echo "<li>";
    echo "<strong>" . htmlspecialchars($el['tags']['name']) . "</strong><br>";
    echo "Tipo: {$el['type']}, ID: {$el['id']}<br>";
    echo "Coords: {$lat}, {$lng}<br>";
    if (!empty($el['tags']['addr:city'])) {
        echo "Ciudad: " . htmlspecialchars($el['tags']['addr:city']) . "<br>";
    }
    echo "</li>";
    $count++;
}
echo "</ol>";

echo "<hr>";
echo "<h2>Conclusión:</h2>";
if ($withName > 0) {
    echo "<p class='success'>✅ Overpass API funciona correctamente. Hay {$withName} lugares importables.</p>";
} else {
    echo "<p class='error'>❌ Overpass API responde pero no hay lugares con nombre.</p>";
}
?>
