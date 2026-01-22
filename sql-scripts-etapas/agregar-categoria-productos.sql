-- Agregar columna de categoría a la tabla products
-- Para permitir clasificación de productos

ALTER TABLE products ADD COLUMN category TEXT DEFAULT NULL;
