-- ============================================
-- CREAR TABLA lugares_comerciales
-- Base de datos: comprati_marketplace
-- ============================================

-- Verificar si existe y eliminar (CUIDADO: solo si quieres recrear)
-- DROP TABLE IF EXISTS lugares_comerciales;

CREATE TABLE IF NOT EXISTS lugares_comerciales (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSTRUCCIONES:
-- ============================================
-- 1. Accede a phpMyAdmin o tu panel de hosting
-- 2. Selecciona la base de datos: comprati_marketplace
-- 3. Ve a la pestaña "SQL"
-- 4. Copia y pega este archivo completo
-- 5. Haz clic en "Ejecutar"
--
-- Luego podrás importar datos desde:
-- /public_html/importar_lugares_standalone.php
-- ============================================
