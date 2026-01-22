-- =============================================
-- ETAPA 1: ROLLBACK (ACTUALIZADO)
-- =============================================
-- Este script revierte los cambios de etapa1-mejoras-venta-garaje.sql
--
-- NOTA: La columna 'location' ya existía antes, por lo que NO se toca
--       Solo se revierten: cover_image2, description, tags
--
-- IMPORTANTE: SQLite no soporta DROP COLUMN directamente
-- en versiones antiguas. Si falla, usar este método:
--
-- 1. Crear tabla temporal sin las columnas nuevas
-- 2. Copiar datos
-- 3. Eliminar tabla original
-- 4. Renombrar temporal
--
-- Por ahora, las columnas quedarán pero con NULL
-- No afecta funcionamiento de la app anterior
-- =============================================

-- OPCIÓN A: Dejar las columnas (seguro, no rompe nada)
-- No hacer nada, las columnas nuevas simplemente quedarán con NULL
-- y no afectarán el funcionamiento previo

-- OPCIÓN B: Eliminar columnas (si SQLite lo soporta)
-- Descomentar si tu versión de SQLite soporta DROP COLUMN:

-- ALTER TABLE sales DROP COLUMN cover_image2;
-- ALTER TABLE sales DROP COLUMN description;
-- ALTER TABLE sales DROP COLUMN tags;
-- NOTA: NO tocar location, ya existía antes

-- OPCIÓN C: Recrear tabla sin las columnas nuevas
-- Solo usar si OPCIÓN B falla y necesitas eliminar las columnas:

/*
BEGIN TRANSACTION;

-- Crear tabla temporal con estructura original + location (que ya existía)
CREATE TABLE sales_backup (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    cover_image TEXT,
    start_at TEXT NOT NULL,
    end_at TEXT NOT NULL,
    is_active INTEGER DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    is_private INTEGER DEFAULT 0,
    access_code TEXT,
    location TEXT,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id)
);

-- Copiar datos (todas las columnas originales)
INSERT INTO sales_backup (id, affiliate_id, title, cover_image, start_at, end_at, is_active, created_at, updated_at, is_private, access_code, location)
SELECT id, affiliate_id, title, cover_image, start_at, end_at, is_active, created_at, updated_at, is_private, access_code, location
FROM sales;

-- Eliminar tabla original
DROP TABLE sales;

-- Renombrar backup a sales
ALTER TABLE sales_backup RENAME TO sales;

COMMIT;
*/

SELECT 'ROLLBACK: Las columnas nuevas quedaron pero con NULL (no afecta funcionamiento anterior)' AS resultado;
