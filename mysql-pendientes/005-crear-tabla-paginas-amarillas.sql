-- Tabla para almacenar lugares de Páginas Amarillas Costa Rica
-- Datos obtenidos mediante web scraping

CREATE TABLE IF NOT EXISTS lugares_paginas_amarillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pa_id VARCHAR(100) UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    subcategoria VARCHAR(100),
    telefono VARCHAR(50),
    telefono2 VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    direccion TEXT,
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(20),
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    horario TEXT,
    descripcion TEXT,
    data_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_ciudad (ciudad),
    INDEX idx_telefono (telefono),
    INDEX idx_email (email),
    INDEX idx_provincia (provincia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
