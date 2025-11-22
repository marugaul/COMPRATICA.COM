<?php
/**
 * Conexión a Base de Datos MySQL
 * Actualizado de SQLite a MySQL
 */

require_once __DIR__ . '/config.php';

/**
 * Retorna conexión PDO a MySQL
 */
function db() {
    static $pdo = null;

    if ($pdo === null) {
        // Cargar configuración de MySQL
        $config = require __DIR__ . '/../config/database.php';

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            // Verificar y crear tablas si no existen
            initializeTables($pdo);

        } catch (PDOException $e) {
            error_log("Error de conexión MySQL en db(): " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }

    return $pdo;
}

/**
 * Inicializar tablas básicas si no existen
 */
function initializeTables($pdo) {
    try {
        // Verificar si la tabla products existe
        $tables = $pdo->query("SHOW TABLES LIKE 'products'")->fetchAll();

        if (empty($tables)) {
            // Crear tabla products
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    stock INT NOT NULL DEFAULT 0,
                    image VARCHAR(255),
                    image2 VARCHAR(255),
                    currency VARCHAR(3) NOT NULL DEFAULT 'CRC',
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    sale_id INT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_active (active),
                    INDEX idx_sale_id (sale_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Verificar tabla orders
        $tables = $pdo->query("SHOW TABLES LIKE 'orders'")->fetchAll();

        if (empty($tables)) {
            // Crear tabla orders
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_id INT NOT NULL,
                    qty INT NOT NULL DEFAULT 1,
                    buyer_email VARCHAR(255),
                    buyer_phone VARCHAR(50),
                    residency VARCHAR(255),
                    note TEXT,
                    status VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
                    paypal_txn_id VARCHAR(255),
                    paypal_amount DECIMAL(10,2),
                    paypal_currency VARCHAR(3),
                    proof_image VARCHAR(255),
                    exrate_used DECIMAL(10,2),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_product_id (product_id),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at),
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        // Verificar tabla settings
        $tables = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchAll();

        if (empty($tables)) {
            // Crear tabla settings
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT PRIMARY KEY DEFAULT 1,
                    exchange_rate DECIMAL(10,2) NOT NULL DEFAULT 540.00
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Insertar configuración inicial
            $pdo->exec("INSERT IGNORE INTO settings (id, exchange_rate) VALUES (1, 540.00)");
        }

        // Verificar y agregar columna image2 si no existe en products
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'image2'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN image2 VARCHAR(255) AFTER image");
        }

        // Verificar y agregar columna sale_id si no existe en products
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'sale_id'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN sale_id INT DEFAULT NULL AFTER image2");
            $pdo->exec("ALTER TABLE products ADD INDEX idx_sale_id (sale_id)");
        }

    } catch (PDOException $e) {
        error_log("Error al inicializar tablas: " . $e->getMessage());
        // No lanzar excepción para permitir que el sistema funcione
    }
}

/**
 * Retorna fecha/hora actual en formato ISO
 */
function now_iso() {
    return date('Y-m-d H:i:s');
}

/**
 * Obtiene el tipo de cambio de la configuración
 */
function get_exchange_rate() {
    try {
        $pdo = db();
        $row = $pdo->query("SELECT exchange_rate FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        return (float)($row['exchange_rate'] ?? 540.00);
    } catch (Exception $e) {
        error_log("Error al obtener tipo de cambio: " . $e->getMessage());
        return 540.00; // Valor por defecto
    }
}
