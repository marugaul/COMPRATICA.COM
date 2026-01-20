<?php
/**
 * Script para verificar y arreglar permisos de la base de datos SQLite
 */

echo "<h1>🔧 Diagnóstico y Reparación de Permisos</h1>";

$dbFile = __DIR__ . '/data.sqlite';
$dbDir = __DIR__;

echo "<h2>1. Estado actual</h2>";
echo "<p><strong>Archivo:</strong> " . htmlspecialchars($dbFile) . "</p>";
echo "<p><strong>Existe:</strong> " . (file_exists($dbFile) ? '✅ Sí' : '❌ No') . "</p>";

if (file_exists($dbFile)) {
    $perms = substr(sprintf('%o', fileperms($dbFile)), -4);
    $isWritable = is_writable($dbFile);
    $owner = posix_getpwuid(fileowner($dbFile));
    $group = posix_getgrgid(filegroup($dbFile));

    echo "<p><strong>Permisos actuales:</strong> " . $perms . "</p>";
    echo "<p><strong>¿Es escribible?:</strong> " . ($isWritable ? '✅ Sí' : '❌ No') . "</p>";
    echo "<p><strong>Owner:</strong> " . htmlspecialchars($owner['name']) . "</p>";
    echo "<p><strong>Group:</strong> " . htmlspecialchars($group['name']) . "</p>";

    $currentUser = posix_getpwuid(posix_geteuid());
    echo "<p><strong>Usuario del servidor web:</strong> " . htmlspecialchars($currentUser['name']) . "</p>";

    echo "<hr>";
    echo "<h2>2. Intentando arreglar permisos</h2>";

    if (!$isWritable) {
        echo "<p style='color:orange;'>⚠️ El archivo no es escribible, intentando cambiar permisos...</p>";

        // Intentar cambiar permisos a 0666 (lectura/escritura para todos)
        if (@chmod($dbFile, 0666)) {
            echo "<p style='color:green;'>✅ Permisos cambiados a 0666</p>";
            $isWritable = is_writable($dbFile);
        } else {
            echo "<p style='color:red;'>❌ No se pudieron cambiar los permisos automáticamente</p>";
            echo "<p><strong>Ejecuta manualmente vía SSH:</strong></p>";
            echo "<pre>chmod 666 " . htmlspecialchars($dbFile) . "</pre>";
        }
    }

    echo "<hr>";
    echo "<h2>3. Intentando crear la tabla</h2>";

    if ($isWritable) {
        try {
            require_once __DIR__ . '/includes/db.php';
            $pdo = db();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Verificar si existe
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();

            if ($exists) {
                echo "<p style='color:green;'>✅ La tabla YA EXISTE</p>";
            } else {
                echo "<p style='color:orange;'>⚠️ La tabla no existe, creándola...</p>";

                $sql = "CREATE TABLE affiliate_shipping_options (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    affiliate_id INTEGER NOT NULL,
                    enable_pickup INTEGER DEFAULT 1,
                    enable_free_shipping INTEGER DEFAULT 0,
                    enable_uber INTEGER DEFAULT 0,
                    pickup_instructions TEXT DEFAULT NULL,
                    free_shipping_min_amount REAL DEFAULT 0,
                    created_at TEXT DEFAULT (datetime('now','localtime')),
                    updated_at TEXT DEFAULT (datetime('now','localtime'))
                )";

                $pdo->exec($sql);
                echo "<p style='color:green;'>✅ Tabla creada</p>";

                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_aff_shipping_options ON affiliate_shipping_options(affiliate_id)");
                echo "<p style='color:green;'>✅ Índice creado</p>";

                // Verificar de nuevo
                $exists2 = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();
                if ($exists2) {
                    echo "<p style='color:green;font-weight:bold;font-size:20px;'>🎉 ¡ÉXITO! La tabla se creó y guardó correctamente</p>";
                } else {
                    echo "<p style='color:red;'>❌ La tabla se creó pero no se guardó (problema de permisos)</p>";
                }
            }

        } catch (Exception $e) {
            echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ No se puede crear la tabla porque el archivo no es escribible</p>";
        echo "<p><strong>Solución: Ejecuta esto vía SSH:</strong></p>";
        echo "<pre>chmod 666 " . htmlspecialchars($dbFile) . "\n# O mejor aún:\nchmod 664 " . htmlspecialchars($dbFile) . "\nchown comprati:comprati " . htmlspecialchars($dbFile) . "</pre>";
    }
}

echo "<hr>";
echo "<p><a href='verify_table.php'>→ Verificar tabla</a></p>";
echo "<p><a href='affiliate/dashboard.php'>→ Dashboard de Afiliado</a></p>";
?>
