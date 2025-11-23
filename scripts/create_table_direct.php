<?php
/**
 * Script para crear tabla lugares_comerciales
 */

$config = [
    'host' => 'localhost',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Conexión exitosa a la base de datos\n\n";

    // Verificar si la tabla ya existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

    if ($check) {
        echo "⚠️  La tabla 'lugares_comerciales' ya existe\n";
        echo "Mostrando estructura:\n\n";

        $structure = $pdo->query("DESCRIBE lugares_comerciales")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($structure as $col) {
            echo sprintf("%-25s %-20s %s\n", $col['Field'], $col['Type'], $col['Key']);
        }
        exit(0);
    }

    echo "📋 Creando tabla 'lugares_comerciales'...\n";

    $sql = "CREATE TABLE lugares_comerciales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        tipo VARCHAR(100),
        categoria VARCHAR(100),
        subtipo VARCHAR(100),
        descripcion TEXT,
        direccion VARCHAR(500),
        ciudad VARCHAR(100),
        provincia VARCHAR(100),
        codigo_postal VARCHAR(20),
        telefono VARCHAR(50),
        email VARCHAR(255),
        website VARCHAR(500),
        facebook VARCHAR(255),
        instagram VARCHAR(255),
        horario TEXT,
        latitud DECIMAL(10, 8),
        longitud DECIMAL(11, 8),
        osm_id BIGINT,
        osm_type VARCHAR(10),
        capacidad INT,
        estrellas TINYINT,
        wifi BOOLEAN DEFAULT FALSE,
        parking BOOLEAN DEFAULT FALSE,
        discapacidad_acceso BOOLEAN DEFAULT FALSE,
        tarjetas_credito BOOLEAN DEFAULT FALSE,
        delivery BOOLEAN DEFAULT FALSE,
        takeaway BOOLEAN DEFAULT FALSE,
        tags_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_categoria (categoria),
        INDEX idx_ciudad (ciudad),
        INDEX idx_provincia (provincia),
        INDEX idx_email (email),
        INDEX idx_osm_id (osm_id),
        FULLTEXT idx_nombre (nombre, descripcion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);

    echo "✅ Tabla 'lugares_comerciales' creada exitosamente!\n\n";

    echo "📊 Estructura de la tabla:\n\n";
    $structure = $pdo->query("DESCRIBE lugares_comerciales")->fetchAll(PDO::FETCH_ASSOC);

    echo sprintf("%-25s %-20s %-10s\n", "Campo", "Tipo", "Index");
    echo str_repeat("-", 60) . "\n";

    foreach ($structure as $col) {
        echo sprintf("%-25s %-20s %-10s\n",
            $col['Field'],
            $col['Type'],
            $col['Key'] ?: '-'
        );
    }

    echo "\n✅ Todo listo! Ahora puedes:\n";
    echo "   1. Acceder a: /admin/email_marketing.php?page=lugares-comerciales\n";
    echo "   2. Importar datos desde el importador web\n\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
