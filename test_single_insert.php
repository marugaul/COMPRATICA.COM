<?php
/**
 * Test de inserción simple para diagnosticar el problema
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/db_places.php';
require_once __DIR__ . '/api/import_functions.php';

try {
    $pdo = db_places();

    // Obtener UN lugar de Overpass para probar
    $categories = getImportCategories();
    $testCat = $categories[0]; // Hoteles

    echo "🔍 Testeando categoría: {$testCat['name']}\n\n";

    $query = buildOverpassQuery($testCat['query']);
    $places = fetchFromOverpass($query);

    if (!$places) {
        die("❌ No se pudieron obtener datos de Overpass\n");
    }

    echo "✅ Recibidos: " . count($places) . " elementos\n\n";

    // Encontrar el primer lugar válido con nombre y coordenadas
    $testPlace = null;
    foreach ($places as $place) {
        if (isset($place['tags']['name']) && !empty($place['tags']['name'])) {
            if (isset($place['lat']) && isset($place['lon'])) {
                $testPlace = $place;
                break;
            }
        }
    }

    if (!$testPlace) {
        die("❌ No se encontró un lugar válido para testear\n");
    }

    echo "📍 Lugar de prueba:\n";
    echo "   Nombre: " . $testPlace['tags']['name'] . "\n";
    echo "   Lat: " . $testPlace['lat'] . "\n";
    echo "   Lon: " . $testPlace['lon'] . "\n";
    echo "   OSM ID: " . $testPlace['id'] . "\n";
    echo "   OSM Type: " . $testPlace['type'] . "\n\n";

    // Intentar insertar
    echo "🔨 Intentando insertar...\n";

    $stmt = $pdo->prepare("
        INSERT INTO places_cr
        (name, type, category, latitude, longitude, city, address, phone, website, description, priority, osm_id, osm_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            type = VALUES(type),
            category = VALUES(category),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            priority = VALUES(priority)
    ");

    $result = $stmt->execute([
        $testPlace['tags']['name'],
        $testCat['type'],
        $testCat['category'],
        $testPlace['lat'],
        $testPlace['lon'],
        $testPlace['tags']['addr:city'] ?? null,
        $testPlace['tags']['addr:street'] ?? null,
        $testPlace['tags']['phone'] ?? null,
        $testPlace['tags']['website'] ?? null,
        $testPlace['tags']['description'] ?? null,
        $testCat['priority'],
        $testPlace['id'],
        $testPlace['type']
    ]);

    if ($result) {
        echo "✅ INSERCIÓN EXITOSA!\n";
        echo "   Rows affected: " . $stmt->rowCount() . "\n\n";

        // Verificar
        $check = $pdo->query("SELECT COUNT(*) as c FROM places_cr")->fetch();
        echo "📊 Total en BD: " . $check['c'] . " lugares\n";
    } else {
        echo "❌ FALLO EN INSERCIÓN\n";
        print_r($stmt->errorInfo());
    }

} catch (PDOException $e) {
    echo "❌ ERROR PDO: " . $e->getMessage() . "\n";
    echo "   Code: " . $e->getCode() . "\n";
    echo "   SQL State: " . $e->errorInfo[0] . "\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
