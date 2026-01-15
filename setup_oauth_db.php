<?php
/**
 * Script para verificar y configurar la tabla users para OAuth
 * Compatible con SQLite
 */

require_once __DIR__ . '/includes/db.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Setup OAuth DB</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}";
echo ".box{background:white;padding:20px;margin:15px 0;border-radius:8px;}";
echo ".success{background:#f0fdf4;border-left:4px solid #16a34a;}";
echo ".error{background:#fee;border-left:4px solid #dc2626;}";
echo ".warning{background:#fef3c7;border-left:4px solid #f59e0b;}";
echo "code{background:#1f2937;color:#10b981;padding:2px 6px;border-radius:3px;font-family:monospace;}";
echo "pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:8px;overflow-x:auto;}";
echo "</style></head><body>";

echo "<h1>🔧 Configuración OAuth - Base de Datos</h1>";

try {
    $pdo = db();

    echo "<div class='box'>";
    echo "<h2>1. Verificando estructura de tabla users</h2>";

    // Verificar si la tabla users existe (compatible con SQLite)
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p class='warning'>⚠️ La tabla <code>users</code> no existe. Se creará automáticamente.</p>";

        // Crear tabla users con columnas OAuth
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE,
                name TEXT,
                phone TEXT,
                password_hash TEXT,
                status TEXT DEFAULT 'active',
                created_at TEXT DEFAULT (datetime('now')),
                oauth_provider TEXT DEFAULT NULL,
                oauth_id TEXT DEFAULT NULL
            )
        ");

        echo "<p class='success'>✅ Tabla <code>users</code> creada con columnas OAuth</p>";
    } else {
        echo "<p class='success'>✅ Tabla <code>users</code> existe</p>";

        // Obtener columnas existentes (compatible con SQLite)
        $stmt = $pdo->query("PRAGMA table_info(users)");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['name'];
        }

        echo "<p>Columnas existentes: " . count($existingColumns) . "</p>";
        echo "<details><summary>Ver todas las columnas</summary><ul>";
        foreach ($existingColumns as $col) {
            echo "<li><code>$col</code></li>";
        }
        echo "</ul></details>";
    }
    echo "</div>";

    // Verificar y agregar columnas OAuth
    echo "<div class='box'>";
    echo "<h2>2. Verificando columnas OAuth</h2>";

    // Volver a obtener las columnas actuales
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $currentColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentColumns[] = $row['name'];
    }

    $columnsToAdd = [];
    if (!in_array('oauth_provider', $currentColumns)) {
        $columnsToAdd[] = ['name' => 'oauth_provider', 'definition' => 'TEXT DEFAULT NULL'];
    }
    if (!in_array('oauth_id', $currentColumns)) {
        $columnsToAdd[] = ['name' => 'oauth_id', 'definition' => 'TEXT DEFAULT NULL'];
    }

    if (!empty($columnsToAdd)) {
        echo "<h3>Agregando columnas faltantes</h3>";

        foreach ($columnsToAdd as $column) {
            try {
                $sql = "ALTER TABLE users ADD COLUMN {$column['name']} {$column['definition']}";
                $pdo->exec($sql);
                echo "<p class='success'>✅ Columna <code>{$column['name']}</code> agregada exitosamente</p>";
                echo "<pre>$sql</pre>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error al agregar <code>{$column['name']}</code>: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    } else {
        echo "<p class='success'>✅ Todas las columnas OAuth ya existen</p>";
        if (in_array('oauth_provider', $currentColumns)) {
            echo "<p class='success'>✅ Columna <code>oauth_provider</code> existe</p>";
        }
        if (in_array('oauth_id', $currentColumns)) {
            echo "<p class='success'>✅ Columna <code>oauth_id</code> existe</p>";
        }
    }
    echo "</div>";

    // Verificar índices (SQLite maneja índices de manera diferente)
    echo "<div class='box'>";
    echo "<h2>3. Verificando índices</h2>";

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='users'");
    $indexes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $indexes[] = $row['name'];
    }

    // Crear índice para oauth_provider + oauth_id si no existe
    if (!in_array('idx_oauth', $indexes)) {
        try {
            $pdo->exec("CREATE INDEX idx_oauth ON users(oauth_provider, oauth_id)");
            echo "<p class='success'>✅ Índice <code>idx_oauth</code> creado</p>";
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ No se pudo crear índice: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p class='success'>✅ Índice <code>idx_oauth</code> ya existe</p>";
    }
    echo "</div>";

    // Mostrar usuarios OAuth existentes
    echo "<div class='box'>";
    echo "<h2>4. Usuarios OAuth existentes</h2>";

    // Verificar nuevamente que las columnas existan antes de consultar
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $finalColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $finalColumns[] = $row['name'];
    }

    if (in_array('oauth_provider', $finalColumns)) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE oauth_provider IS NOT NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $oauthUsers = $result['total'];

        echo "<p>Total de usuarios con OAuth: <strong>$oauthUsers</strong></p>";

        if ($oauthUsers > 0) {
            $stmt = $pdo->query("SELECT oauth_provider, COUNT(*) as count FROM users WHERE oauth_provider IS NOT NULL GROUP BY oauth_provider");
            echo "<ul>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<li>" . htmlspecialchars($row['oauth_provider']) . ": " . $row['count'] . " usuarios</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p class='warning'>⚠️ Columna oauth_provider no disponible aún</p>";
    }
    echo "</div>";

    echo "<div class='box success'>";
    echo "<h2>✅ Configuración completada</h2>";
    echo "<p>La base de datos está lista para usar OAuth con Google y Facebook.</p>";
    echo "<p><a href='/login.php' style='display:inline-block;padding:12px 24px;background:#4285f4;color:white;text-decoration:none;border-radius:6px;margin:5px;'>Ir a Login</a>";
    echo "<a href='/test_google_oauth.php' style='display:inline-block;padding:12px 24px;background:#667eea;color:white;text-decoration:none;border-radius:6px;margin:5px;'>Test OAuth</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
