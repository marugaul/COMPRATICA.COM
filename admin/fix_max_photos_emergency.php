<?php
/**
 * SCRIPT DE EMERGENCIA - Reparar max_photos
 * Ejecuta esto desde el navegador cuando tengas el error
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔧 Reparación de Emergencia - max_photos</h1>";
echo "<hr>";

// Paso 1: Limpiar TODO el caché
echo "<h2>Paso 1: Limpiando caché...</h2>";

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpiado<br>";
}

if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__DIR__ . '/../includes/db.php', true);
    echo "✅ db.php invalidado<br>";
}

clearstatcache(true);
echo "✅ Cache de archivos limpiado<br><br>";

// Paso 2: Conectar sin usar la función db() cacheada
echo "<h2>Paso 2: Conectando a base de datos (nueva conexión)...</h2>";

$dbFile = __DIR__ . '/../data.sqlite';
echo "<strong>Archivo BD:</strong> " . realpath($dbFile) . "<br>";
echo "<strong>Existe:</strong> " . (file_exists($dbFile) ? "✅ Sí" : "❌ No") . "<br>";
echo "<strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($dbFile)), -4) . "<br>";
echo "<strong>Tamaño:</strong> " . number_format(filesize($dbFile)) . " bytes<br><br>";

try {
    // NUEVA conexión PDO (no usar la función db() cacheada)
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Conexión establecida<br><br>";

    // Paso 3: Verificar columnas
    echo "<h2>Paso 3: Verificando estructura de listing_pricing...</h2>";

    $columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);

    $hasMaxPhotos = false;
    $hasPaymentMethods = false;

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Estado</th></tr>";

    foreach ($columns as $col) {
        if ($col['name'] === 'max_photos') $hasMaxPhotos = true;
        if ($col['name'] === 'payment_methods') $hasPaymentMethods = true;

        $status = "";
        if ($col['name'] === 'max_photos' || $col['name'] === 'payment_methods') {
            $status = "<span style='color:green;font-weight:bold'>✅ OBJETIVO</span>";
        }

        echo "<tr>";
        echo "<td>{$col['name']}</td>";
        echo "<td>{$col['type']}</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    // Paso 4: Agregar columnas si faltan
    if (!$hasMaxPhotos || !$hasPaymentMethods) {
        echo "<h2>Paso 4: ❌ COLUMNAS FALTANTES - Agregando ahora...</h2>";

        $pdo->beginTransaction();

        try {
            if (!$hasMaxPhotos) {
                echo "➕ Agregando max_photos...<br>";
                $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN max_photos INTEGER DEFAULT 3");
                echo "✅ max_photos agregada<br>";

                // Actualizar planes existentes
                $pdo->exec("UPDATE listing_pricing SET max_photos = 3 WHERE id = 1");
                $pdo->exec("UPDATE listing_pricing SET max_photos = 5 WHERE id = 2");
                $pdo->exec("UPDATE listing_pricing SET max_photos = 8 WHERE id = 3");
                echo "✅ Valores actualizados<br>";
            }

            if (!$hasPaymentMethods) {
                echo "➕ Agregando payment_methods...<br>";
                $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal'");
                echo "✅ payment_methods agregada<br>";

                // Actualizar planes existentes
                $pdo->exec("UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE payment_methods IS NULL OR payment_methods = ''");
                echo "✅ Valores actualizados<br>";
            }

            $pdo->commit();
            echo "<p style='color:green;font-size:20px'>✅ COLUMNAS AGREGADAS EXITOSAMENTE</p>";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p style='color:red'>❌ Error al agregar columnas: " . htmlspecialchars($e->getMessage()) . "</p>";
            throw $e;
        }

    } else {
        echo "<h2>Paso 4: ✅ Columnas ya existen</h2>";
    }

    // Paso 5: Probar SELECT
    echo "<h2>Paso 5: Probando SELECT...</h2>";
    $plans = $pdo->query("SELECT id, name, max_photos, payment_methods FROM listing_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Max Fotos</th><th>Métodos Pago</th></tr>";
    foreach ($plans as $plan) {
        echo "<tr>";
        echo "<td>{$plan['id']}</td>";
        echo "<td>" . htmlspecialchars($plan['name']) . "</td>";
        echo "<td><strong>{$plan['max_photos']}</strong></td>";
        echo "<td>" . htmlspecialchars($plan['payment_methods']) . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    echo "<p style='color:green;font-size:18px'>✅ SELECT funciona correctamente</p>";

    // Paso 6: Probar UPDATE
    echo "<h2>Paso 6: Probando UPDATE...</h2>";

    $stmt = $pdo->prepare("UPDATE listing_pricing SET max_photos = ?, payment_methods = ?, updated_at = datetime('now') WHERE id = 1");
    $stmt->execute([3, 'sinpe,paypal']);

    echo "<p style='color:green;font-size:18px'>✅ UPDATE funciona correctamente</p>";

    // Paso 7: Limpiar caché de nuevo
    echo "<h2>Paso 7: Limpiando caché final...</h2>";
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "✅ OPcache limpiado nuevamente<br>";
    }

    echo "<hr>";
    echo "<h1 style='color:green'>✅ ¡REPARACIÓN COMPLETADA!</h1>";
    echo "<p><strong>Próximo paso:</strong> Intenta acceder a <a href='bienes_raices_config.php'>bienes_raices_config.php</a></p>";
    echo "<p>Si el error persiste, presiona <strong>Ctrl+Shift+R</strong> para refrescar sin caché del navegador.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ERROR CRÍTICO</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre style='background:#f5f5f5;padding:10px;overflow:auto'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";

    echo "<hr>";
    echo "<h3>🔍 Información de Debug:</h3>";
    echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
    echo "<p><strong>PDO Drivers:</strong> " . implode(', ', PDO::getAvailableDrivers()) . "</p>";
    echo "<p><strong>Archivo DB:</strong> $dbFile</p>";
    echo "<p><strong>DB existe:</strong> " . (file_exists($dbFile) ? 'Sí' : 'No') . "</p>";
}
?>
