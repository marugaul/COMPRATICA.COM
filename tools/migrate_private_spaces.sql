-- =========================================================
-- MIGRACIÓN: Agregar funcionalidad de espacios privados
-- Base de datos: SQLite (data.sqlite)
-- Fecha: 2025-11-17
-- =========================================================

-- IMPORTANTE: Este script es seguro ejecutar múltiples veces
-- Si las columnas ya existen, SQLite dará error pero no afectará datos

-- 1. Agregar columna is_private (0 = público, 1 = privado)
ALTER TABLE sales ADD COLUMN is_private INTEGER NOT NULL DEFAULT 0;

-- 2. Agregar columna access_code (código de 6 dígitos)
ALTER TABLE sales ADD COLUMN access_code TEXT;

-- =========================================================
-- VERIFICACIÓN (ejecutar después para confirmar)
-- =========================================================
-- Descomentar estas líneas para verificar:
-- PRAGMA table_info(sales);
-- SELECT id, title, is_private, access_code FROM sales LIMIT 5;
