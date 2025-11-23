<?php
/**
 * CREAR TABLA lugares_comerciales DIRECTAMENTE
 */

// Usar la config existente
require_once __DIR__ . '/../includes/config.php';
$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Conexión exitosa\n\n";

    // Verificar si existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

    if ($check) {
        echo "⚠️ La tabla ya existe!\n";
        $count = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        echo "Registros actuales: " . number_format($count) . "\n";
        exit(0);
    }

    echo "📋 Creando tabla lugares_comerciales...\n";

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

    echo "✅ TABLA CREADA EXITOSAMENTE!\n\n";
    echo "Ya puedes usar 'lugares_comerciales' en tus campañas\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
