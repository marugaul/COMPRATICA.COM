<?php
/**
 * Script CRON para crear tabla lugares_comerciales
 * Ejecutar UNA SOLA VEZ mediante cron
 */

// Configuración
$config = [
    'host' => '127.0.0.1',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
];

$log_file = __DIR__ . '/../logs/lugares_setup.log';

// Función para logging
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

try {
    log_message("=== Inicio de instalación de tabla lugares_comerciales ===");

    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    log_message("✓ Conexión a base de datos exitosa");

    // Verificar si ya existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

    if ($check) {
        $count = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        log_message("ℹ La tabla ya existe con $count registros");
        log_message("✓ No es necesario crear la tabla");
        exit(0);
    }

    log_message("→ Creando tabla lugares_comerciales...");

    // Crear tabla
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

    log_message("✓✓✓ TABLA CREADA EXITOSAMENTE ✓✓✓");
    log_message("→ Tabla 'lugares_comerciales' lista para usar");

    // Verificar estructura
    $structure = $pdo->query("DESCRIBE lugares_comerciales")->fetchAll(PDO::FETCH_ASSOC);
    log_message("→ Tabla tiene " . count($structure) . " campos");

    log_message("=== Instalación completada exitosamente ===");
    exit(0);

} catch (PDOException $e) {
    log_message("✗✗✗ ERROR DE BASE DE DATOS ✗✗✗");
    log_message("Error: " . $e->getMessage());
    log_message("Archivo: " . $e->getFile() . ":" . $e->getLine());
    exit(1);

} catch (Exception $e) {
    log_message("✗✗✗ ERROR GENERAL ✗✗✗");
    log_message("Error: " . $e->getMessage());
    exit(1);
}
