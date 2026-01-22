-- =============================================
-- ETAPA 1: MEJORAS VENTA DE GARAJE
-- =============================================
-- Este script agrega campos necesarios para:
-- 1. Mostrar ubicación de la venta
-- 2. Segunda imagen de portada (si no existe)
-- 3. Descripción corta para cards
-- 4. Contador de productos
--
-- ROLLBACK: Ver archivo etapa1-rollback.sql
-- =============================================

-- Agregar campo de ubicación/dirección
ALTER TABLE sales ADD COLUMN location TEXT DEFAULT NULL;

-- Agregar segunda imagen de portada (si no existe)
ALTER TABLE sales ADD COLUMN cover_image2 TEXT DEFAULT NULL;

-- Agregar descripción corta para mostrar en cards
ALTER TABLE sales ADD COLUMN description TEXT DEFAULT NULL;

-- Agregar tags/categorías (formato JSON: ["Ropa", "Electrónica"])
ALTER TABLE sales ADD COLUMN tags TEXT DEFAULT NULL;
