-- Tabla para lugares desde Foursquare Places API
CREATE TABLE IF NOT EXISTS lugares_foursquare (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Información básica
    foursquare_id VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    categorias_json TEXT,

    -- Contacto
    telefono VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    facebook VARCHAR(255),
    twitter VARCHAR(255),
    instagram VARCHAR(255),

    -- Dirección
    direccion VARCHAR(500),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(20),
    pais VARCHAR(50) DEFAULT 'Costa Rica',

    -- Ubicación
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),

    -- Información adicional
    rating DECIMAL(2, 1),
    precio_nivel TINYINT,
    verificado BOOLEAN DEFAULT FALSE,
    descripcion TEXT,
    horario TEXT,

    -- Foursquare específico
    popularidad INT,
    checkins INT,
    tips_count INT,
    fotos_count INT,

    -- Metadata
    data_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_categoria (categoria),
    INDEX idx_ciudad (ciudad),
    INDEX idx_provincia (provincia),
    INDEX idx_email (email),
    INDEX idx_telefono (telefono),
    INDEX idx_foursquare_id (foursquare_id),
    FULLTEXT idx_nombre (nombre, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
