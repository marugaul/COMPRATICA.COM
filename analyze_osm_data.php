<?php
/**
 * Análisis de datos disponibles en OSM para Costa Rica
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/api/import_functions.php';

$categories = getImportCategories();
$hotelCat = $categories[0]; // Hoteles

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║        ANÁLISIS DE DATOS OSM - HOTELES EN COSTA RICA            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

$query = buildOverpassQuery($hotelCat['query']);
$places = fetchFromOverpass($query);

if (!$places) {
    die("❌ No se pudieron obtener datos\n");
}

echo "📊 Total de elementos recibidos: " . count($places) . "\n\n";

// Estadísticas
$stats = [
    'total' => count($places),
    'con_nombre' => 0,
    'con_telefono' => 0,
    'con_email' => 0,
    'con_website' => 0,
    'con_operador' => 0,
    'con_ciudad' => 0,
    'con_calle' => 0,
    'con_provincia' => 0,
    'con_canton' => 0,
    'con_distrito' => 0,
    'con_postal' => 0,
    'con_coords' => 0
];

foreach ($places as $place) {
    if (!empty($place['tags']['name'])) $stats['con_nombre']++;
    if (!empty($place['tags']['phone']) || !empty($place['tags']['contact:phone'])) $stats['con_telefono']++;
    if (!empty($place['tags']['email']) || !empty($place['tags']['contact:email'])) $stats['con_email']++;
    if (!empty($place['tags']['website']) || !empty($place['tags']['contact:website'])) $stats['con_website']++;
    if (!empty($place['tags']['operator']) || !empty($place['tags']['brand'])) $stats['con_operador']++;
    if (!empty($place['tags']['addr:city'])) $stats['con_ciudad']++;
    if (!empty($place['tags']['addr:street'])) $stats['con_calle']++;
    if (!empty($place['tags']['addr:province'])) $stats['con_provincia']++;
    if (!empty($place['tags']['addr:canton'])) $stats['con_canton']++;
    if (!empty($place['tags']['addr:district'])) $stats['con_distrito']++;
    if (!empty($place['tags']['addr:postcode'])) $stats['con_postal']++;
    if (isset($place['lat']) && isset($place['lon'])) $stats['con_coords']++;
}

echo "DATOS DISPONIBLES:\n";
echo str_repeat("=", 70) . "\n";
printf("%-30s %10s %10s\n", "CAMPO", "CANTIDAD", "PORCENTAJE");
echo str_repeat("=", 70) . "\n";

$pct = function($n) use ($stats) {
    return round(($n / $stats['total']) * 100, 1) . "%";
};

printf("%-30s %10d %10s\n", "Nombre", $stats['con_nombre'], $pct($stats['con_nombre']));
printf("%-30s %10d %10s\n", "Teléfono ☎️", $stats['con_telefono'], $pct($stats['con_telefono']));
printf("%-30s %10d %10s\n", "Email 📧", $stats['con_email'], $pct($stats['con_email']));
printf("%-30s %10d %10s\n", "Website 🌐", $stats['con_website'], $pct($stats['con_website']));
printf("%-30s %10d %10s\n", "Operador/Dueño 👤", $stats['con_operador'], $pct($stats['con_operador']));
printf("%-30s %10d %10s\n", "Coordenadas 📍", $stats['con_coords'], $pct($stats['con_coords']));
printf("%-30s %10d %10s\n", "Ciudad", $stats['con_ciudad'], $pct($stats['con_ciudad']));
printf("%-30s %10d %10s\n", "Calle", $stats['con_calle'], $pct($stats['con_calle']));
printf("%-30s %10d %10s\n", "Provincia", $stats['con_provincia'], $pct($stats['con_provincia']));
printf("%-30s %10d %10s\n", "Cantón", $stats['con_canton'], $pct($stats['con_canton']));
printf("%-30s %10d %10s\n", "Distrito", $stats['con_distrito'], $pct($stats['con_distrito']));
printf("%-30s %10d %10s\n", "Código Postal", $stats['con_postal'], $pct($stats['con_postal']));

echo "\n\n📌 EJEMPLOS DE HOTELES CON MÁS DATOS:\n";
echo str_repeat("=", 70) . "\n";

$count = 0;
foreach ($places as $place) {
    if ($count >= 5) break;
    if (empty($place['tags']['name'])) continue;

    $tags = $place['tags'];

    echo "\n🏨 " . $tags['name'] . "\n";
    if (!empty($tags['phone']) || !empty($tags['contact:phone'])) {
        echo "   ☎️  Teléfono: " . ($tags['phone'] ?? $tags['contact:phone']) . "\n";
    }
    if (!empty($tags['email']) || !empty($tags['contact:email'])) {
        echo "   📧 Email: " . ($tags['email'] ?? $tags['contact:email']) . "\n";
    }
    if (!empty($tags['website']) || !empty($tags['contact:website'])) {
        echo "   🌐 Website: " . ($tags['website'] ?? $tags['contact:website']) . "\n";
    }
    if (!empty($tags['operator'])) echo "   👤 Operador: " . $tags['operator'] . "\n";
    if (!empty($tags['brand'])) echo "   🏷️  Marca: " . $tags['brand'] . "\n";
    if (!empty($tags['addr:city'])) echo "   🏙️  Ciudad: " . $tags['addr:city'] . "\n";
    if (!empty($tags['addr:street'])) echo "   📍 Calle: " . $tags['addr:street'] . "\n";
    if (!empty($tags['addr:province'])) echo "   🗺️  Provincia: " . $tags['addr:province'] . "\n";
    if (!empty($tags['addr:canton'])) echo "   📌 Cantón: " . $tags['addr:canton'] . "\n";
    if (!empty($tags['addr:district'])) echo "   📌 Distrito: " . $tags['addr:district'] . "\n";

    $count++;
}

echo "\n\n";
?>
