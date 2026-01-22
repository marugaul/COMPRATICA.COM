-- =============================================
-- ETAPA 2: ROLLBACK
-- =============================================
-- Este script revierte los cambios de etapa2-mejoras-venta-garaje.sql
--
-- Mismo caso que Etapa 1: las columnas quedarán con NULL
-- pero no afectan el funcionamiento
-- =============================================

-- OPCIÓN A: Dejar las columnas (seguro)
SELECT 'ROLLBACK: Las columnas nuevas quedaron pero con NULL' AS resultado;

-- OPCIÓN B: Eliminar columnas (si SQLite lo soporta)
-- Descomentar si necesitas:

-- ALTER TABLE sales DROP COLUMN latitude;
-- ALTER TABLE sales DROP COLUMN longitude;
-- ALTER TABLE sales DROP COLUMN show_in_map;
