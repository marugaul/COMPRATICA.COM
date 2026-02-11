<?php
/**
 * Script de instalación para el módulo de Agentes de Bienes Raíces
 * Crea tabla separada de agentes (real_estate_agents)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();

    echo "<h1>Instalando módulo de Agentes de Bienes Raíces...</h1>";

    // Ejecutar las consultas directamente
    $queries = [
        // Tabla de agentes de bienes raíces
        "CREATE TABLE IF NOT EXISTS real_estate_agents (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL,
          email TEXT NOT NULL UNIQUE,
          phone TEXT,
          password_hash TEXT NOT NULL,
          company_name TEXT,
          company_description TEXT,
          company_logo TEXT,
          website TEXT,
          license_number TEXT,
          specialization TEXT,
          bio TEXT,
          profile_image TEXT,
          facebook TEXT,
          instagram TEXT,
          whatsapp TEXT,
          is_active INTEGER DEFAULT 1,
          created_at TEXT DEFAULT (datetime('now')),
          updated_at TEXT DEFAULT (datetime('now'))
        )",

        // Agregar campo agent_id a real_estate_listings si no existe
        "ALTER TABLE real_estate_listings ADD COLUMN agent_id INTEGER",

        // Índice para agent_id
        "CREATE INDEX IF NOT EXISTS idx_real_estate_listings_agent ON real_estate_listings(agent_id)",
    ];

    $executed = 0;
    $errors = 0;

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
            $executed++;
            echo "<p style='color: green;'>✓ Ejecutado: " . substr($query, 0, 60) . "...</p>";
        } catch (Exception $e) {
            // Si el error es porque la columna ya existe, no es un error grave
            if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                echo "<p style='color: orange;'>⚠ Columna ya existe: " . substr($query, 0, 60) . "...</p>";
            } else {
                $errors++;
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<hr>";
    echo "<h2>Resumen:</h2>";
    echo "<p><strong>Consultas ejecutadas:</strong> $executed</p>";
    echo "<p><strong>Errores:</strong> $errors</p>";

    // Verificar que las tablas se crearon
    echo "<hr>";
    echo "<h2>Verificación de tablas:</h2>";

    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM real_estate_agents");
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p style='color: green;'>✓ Tabla 'real_estate_agents': $count registros</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Tabla 'real_estate_agents': Error - " . $e->getMessage() . "</p>";
    }

    // Verificar columnas de real_estate_listings
    echo "<hr>";
    echo "<h2>Verificación de columnas en real_estate_listings:</h2>";

    $columns = $pdo->query("PRAGMA table_info(real_estate_listings)")->fetchAll(PDO::FETCH_ASSOC);
    $hasAgentId = false;

    foreach ($columns as $col) {
        if ($col['name'] === 'agent_id') {
            $hasAgentId = true;
            echo "<p style='color: green;'>✓ Columna 'agent_id' existe en real_estate_listings</p>";
            break;
        }
    }

    if (!$hasAgentId) {
        echo "<p style='color: red;'>✗ Columna 'agent_id' NO existe en real_estate_listings</p>";
    }

    echo "<hr>";
    echo "<h2 style='color: green;'>✓ Instalación completada exitosamente</h2>";
    echo "<p><strong>Nota:</strong> La tabla 'real_estate_listings' ahora puede usar 'agent_id' para agentes separados del sistema de afiliados.</p>";
    echo "<p><a href='/real-estate/register.php'>Ir a registro de Agentes de Bienes Raíces</a></p>";
    echo "<p><a href='/'>Volver al inicio</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error durante la instalación:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
