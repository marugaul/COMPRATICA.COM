-- Script para agregar funcionalidad de espacios privados
-- Agrega campos is_private y access_code a la tabla sales

-- Agregar campo para marcar un espacio como privado (0 = público, 1 = privado)
ALTER TABLE sales ADD COLUMN is_private INTEGER NOT NULL DEFAULT 0;

-- Agregar campo para el código de acceso (6 dígitos)
ALTER TABLE sales ADD COLUMN access_code TEXT;

-- Verificar los cambios
-- Para ejecutar: sqlite3 data.sqlite < tools/add_private_spaces.sql
