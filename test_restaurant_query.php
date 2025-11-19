<?php
/**
 * Test query específica para restaurantes
 */

header('Content-Type: text/html; charset=utf-8');

$query = '[out:json][timeout:90][bbox:8.0,-86.0,11.2,-82.5];
(
  node["amenity"~"restaurant|cafe|fast_food|bar|food_court"];
  way["amenity"~"restaurant|cafe|fast_food|bar|food_court"];
);
out body;
>;
out skel qt;';

echo "<h1>Test: Restaurantes y Cafés</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } .ok { color: green; } .error { color: red; }</style>";

$url = 'https://overpass-api.de/api/interpreter';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'data=' . urlencode($query),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_USERAGENT => 'CompraTica/1.0 (shuttle service)',
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true
]);

echo "<p>Consultando Overpass API...</p>";
$startTime = microtime(true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$elapsed = round((microtime(true) - $startTime) * 1000);

if ($response === false || $httpCode !== 200) {
    echo "<p class='error'>❌ Error HTTP {$httpCode}: {$error}</p>";
    exit;
}

echo "<p class='ok'>✅ Respuesta recibida en {$elapsed}ms</p>";

$data = json_decode($response, true);

if (!isset($data['elements'])) {
    echo "<p class='error'>❌ No elements in response</p>";
    exit;
}

$total = count($data['elements']);
$withName = 0;
$withCoords = 0;

foreach ($data['elements'] as $el) {
    if (!empty($el['tags']['name'])) $withName++;
    if (isset($el['lat']) && isset($el['lon'])) $withCoords++;
    elseif (isset($el['center'])) $withCoords++;
}

echo "<p class='ok'>✅ Total elementos: <strong>{$total}</strong></p>";
echo "<p>Con nombre: <strong>{$withName}</strong></p>";
echo "<p>Con coordenadas: <strong>{$withCoords}</strong></p>";
echo "<p><strong>Importables: {$withName}</strong></p>";

// Mostrar algunos ejemplos
echo "<h3>Primeros 10 restaurantes:</h3><ul>";
$count = 0;
foreach ($data['elements'] as $el) {
    if ($count >= 10) break;
    if (empty($el['tags']['name'])) continue;
    echo "<li>" . htmlspecialchars($el['tags']['name']) . " (" . ($el['tags']['amenity'] ?? 'N/A') . ")</li>";
    $count++;
}
echo "</ul>";
?>
