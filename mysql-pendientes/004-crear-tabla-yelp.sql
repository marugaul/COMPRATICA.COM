-- Tabla para almacenar lugares de Yelp
-- Yelp Fusion API incluye teléfonos verificados

CREATE TABLE IF NOT EXISTS lugares_yelp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yelp_id VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    telefono VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    direccion TEXT,
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    pais VARCHAR(50) DEFAULT 'Costa Rica',
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    rating DECIMAL(2, 1),
    review_count INT DEFAULT 0,
    price VARCHAR(10),
    is_closed TINYINT(1) DEFAULT 0,
    yelp_url VARCHAR(500),
    image_url VARCHAR(500),
    data_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_ciudad (ciudad),
    INDEX idx_telefono (telefono),
    INDEX idx_email (email),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
