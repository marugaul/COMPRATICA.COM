-- =============================================
-- ETAPA 1: MEJORAS VENTA DE GARAJE (ACTUALIZADO)
-- =============================================
-- Este script agrega campos necesarios para:
-- 1. Mostrar ubicación de la venta (YA EXISTE EN TU BD - no se toca)
-- 2. Segunda imagen de portada
-- 3. Descripción corta para cards
-- 4. Tags/categorías
--
-- NOTA: La columna 'location' ya existe en tu BD, por lo que
--       solo se agregarán las 3 columnas restantes
--
-- ROLLBACK: Ver archivo etapa1-rollback.sql
-- =============================================

-- Agregar segunda imagen de portada
ALTER TABLE sales ADD COLUMN cover_image2 TEXT DEFAULT NULL;

-- Agregar descripción corta para mostrar en cards
ALTER TABLE sales ADD COLUMN description TEXT DEFAULT NULL;

-- Agregar tags/categorías (formato JSON: ["Ropa", "Electrónica"])
ALTER TABLE sales ADD COLUMN tags TEXT DEFAULT NULL;

-- Verificar que se agregaron correctamente
SELECT 'Columnas agregadas correctamente. Ejecuta: PRAGMA table_info(sales) para verificar' AS resultado;
