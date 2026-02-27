<?php
/**
 * LIMPIAR CACHÉ DE PRODUCCIÓN
 * Ejecuta este archivo desde el navegador en tu servidor de producción
 */

// Limpiar OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpiado<br><br>";
} else {
    echo "⚠️ OPcache no disponible<br><br>";
}

// Limpiar caché de archivos
clearstatcache(true);
echo "✅ Cache de archivos limpiado<br><br>";

// Verificar base de datos
require_once __DIR__ . '/../includes/db.php';

echo "<h2>Diagnóstico de Base de Datos</h2>";

try {
    $pdo = db();

    // Ver ruta de BD
    echo "<strong>Base de datos:</strong> ";
    $result = $pdo->query("PRAGMA database_list")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $db) {
        if ($db['name'] == 'main') {
            echo htmlspecialchars($db['file']) . "<br><br>";
        }
    }

    // Verificar job_listings
    echo "<h3>Tabla job_listings:</h3>";
    $columns = $pdo->query("PRAGMA table_info(job_listings)")->fetchAll(PDO::FETCH_ASSOC);

    $hasPricingPlanId = false;
    echo "<ul>";
    foreach ($columns as $col) {
        if ($col['name'] === 'pricing_plan_id') {
            $hasPricingPlanId = true;
            echo "<li style='color:green;font-weight:bold'>{$col['name']} ({$col['type']})</li>";
        } else {
            echo "<li>{$col['name']} ({$col['type']})</li>";
        }
    }
    echo "</ul>";

    if ($hasPricingPlanId) {
        echo "<p style='color:green;font-size:18px'>✅ La columna pricing_plan_id EXISTE</p>";

        // Probar query
        echo "<h3>Prueba de Query:</h3>";
        $testQuery = $pdo->query("
            SELECT
                id,
                title,
                listing_type,
                pricing_plan_id
            FROM job_listings
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "<p style='color:green'>✅ Query ejecutado exitosamente</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Plan ID</th></tr>";
        foreach ($testQuery as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>" . htmlspecialchars(substr($row['title'], 0, 50)) . "</td>";
            echo "<td>{$row['listing_type']}</td>";
            echo "<td>" . ($row['pricing_plan_id'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";

        // Probar query problemático
        echo "<h3>Prueba del Query Problemático (línea 159):</h3>";
        $problematicQuery = $pdo->query("
            SELECT
                p.*,
                (SELECT COUNT(*) FROM job_listings WHERE listing_type='service' AND pricing_plan_id = p.id) as listings_count
            FROM service_pricing p
            ORDER BY display_order ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "<p style='color:green;font-size:20px'>✅ ¡QUERY FUNCIONA! El caché ha sido limpiado exitosamente</p>";
        echo "<p><strong>Planes encontrados:</strong> " . count($problematicQuery) . "</p>";

        echo "<hr>";
        echo "<h2 style='color:green'>✅ TODO RESUELTO</h2>";
        echo "<p>El caché ha sido limpiado. Ahora puedes usar:</p>";
        echo "<ul>";
        echo "<li><a href='servicios_config.php'>Configuración de Servicios</a></li>";
        echo "<li><a href='empleos_config.php'>Configuración de Empleos</a></li>";
        echo "<li><a href='bienes_raices_config.php'>Configuración de Bienes Raíces</a></li>";
        echo "</ul>";

    } else {
        echo "<p style='color:red;font-size:18px'>❌ La columna pricing_plan_id NO EXISTE</p>";
        echo "<p>Ejecuta: <code>ALTER TABLE job_listings ADD COLUMN pricing_plan_id INTEGER;</code></p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ERROR</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
