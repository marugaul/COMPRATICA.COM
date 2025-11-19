<?php
/**
 * PASO 1: Crear tablas en MySQL para el sistema de lugares
 *
 * INSTRUCCIONES:
 * 1. Abre este archivo desde tu navegador: https://compratica.com/install_places_db.php
 * 2. Verás el resultado de la instalación
 * 3. Luego ejecuta seed_places.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_places.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalación BD Lugares - Compratica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2rem;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .step {
            margin: 1rem 0;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #5a67d8;
        }
        pre {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🗄️ Instalación Base de Datos de Lugares</h1>";

try {
    $pdo = db_places();

    echo "<div class='success'>✅ Conectado exitosamente a MySQL</div>";
    echo "<div class='info'>📊 Base de datos: <strong>comprati_marketplace</strong></div>";

    // Crear tabla places_cr
    echo "<div class='step'><strong>Paso 1:</strong> Creando tabla <code>places_cr</code>...</div>";

    $sqlPlaces = "
    CREATE TABLE IF NOT EXISTS places_cr (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        osm_id BIGINT UNIQUE,
        osm_type VARCHAR(50),
        name VARCHAR(255) NOT NULL,
        type VARCHAR(100),
        category VARCHAR(100),
        lat DECIMAL(10, 8),
        lng DECIMAL(11, 8),
        address TEXT,
        street VARCHAR(255),
        city VARCHAR(100),
        district VARCHAR(100),
        canton VARCHAR(100),
        province VARCHAR(100),
        postal_code VARCHAR(20),
        phone VARCHAR(50),
        website VARCHAR(255),
        tags JSON,
        priority INT DEFAULT 5,
        source VARCHAR(50) DEFAULT 'osm',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_name (name),
        INDEX idx_type (type),
        INDEX idx_category (category),
        INDEX idx_city (city),
        INDEX idx_province (province),
        INDEX idx_location (lat, lng),
        INDEX idx_priority (priority),
        INDEX idx_active (is_active),
        FULLTEXT INDEX idx_search (name, address, street, city)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sqlPlaces);
    echo "<div class='success'>✅ Tabla <code>places_cr</code> creada exitosamente</div>";

    // Crear tabla search_stats
    echo "<div class='step'><strong>Paso 2:</strong> Creando tabla <code>search_stats</code>...</div>";

    $sqlStats = "
    CREATE TABLE IF NOT EXISTS search_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        query VARCHAR(255),
        results_count INT,
        search_time_ms INT,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_query (query),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sqlStats);
    echo "<div class='success'>✅ Tabla <code>search_stats</code> creada exitosamente</div>";

    // Verificar tablas creadas
    echo "<div class='step'><strong>Paso 3:</strong> Verificando tablas creadas...</div>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'places_cr'");
    $placesExists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'search_stats'");
    $statsExists = $stmt->rowCount() > 0;

    if ($placesExists && $statsExists) {
        echo "<div class='success'>✅ Todas las tablas fueron creadas correctamente</div>";

        // Mostrar estructura
        echo "<div class='step'><strong>Estructura de la tabla places_cr:</strong></div>";
        echo "<pre>";
        $stmt = $pdo->query("DESCRIBE places_cr");
        $columns = $stmt->fetchAll();
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n",
                $col['Field'],
                $col['Type'],
                $col['Key'] ? "[$col[Key]]" : ''
            );
        }
        echo "</pre>";

        echo "<div class='success'>
            <h3>🎉 ¡Instalación Completada!</h3>
            <p>La base de datos está lista para recibir datos.</p>
            <p><strong>Siguiente paso:</strong> Ejecutar el script de poblar datos iniciales.</p>
        </div>";

        echo "<a href='seed_places.php' class='btn'>▶️ Continuar al Paso 2: Poblar Datos</a>";

    } else {
        echo "<div class='error'>❌ Error: No se pudieron crear todas las tablas</div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>
        <h3>❌ Error de Base de Datos</h3>
        <p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Código:</strong> " . $e->getCode() . "</p>
    </div>";

    echo "<div class='info'>
        <h4>💡 Posibles soluciones:</h4>
        <ul>
            <li>Verifica que el usuario <code>comprati_places_user</code> tenga permisos en la BD</li>
            <li>Verifica que la contraseña sea correcta</li>
            <li>Verifica que la BD <code>comprati_marketplace</code> exista</li>
            <li>Revisa los permisos en cPanel > MySQL Databases</li>
        </ul>
    </div>";
} catch (Exception $e) {
    echo "<div class='error'>
        <h3>❌ Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "
    </div>
</body>
</html>";
?>
