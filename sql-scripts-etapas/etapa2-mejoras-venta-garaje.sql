-- =============================================
-- ETAPA 2: MEJORAS AVANZADAS VENTA DE GARAJE
-- =============================================
-- Este script agrega campos necesarios para:
-- 1. Mapa de ubicaciones (Google Maps)
-- 2. Barra de progreso visual
--
-- ROLLBACK: Ver archivo etapa2-rollback.sql
-- =============================================

-- Agregar coordenadas para Google Maps
ALTER TABLE sales ADD COLUMN latitude REAL DEFAULT NULL;
ALTER TABLE sales ADD COLUMN longitude REAL DEFAULT NULL;

-- Agregar campo para mostrar/ocultar en mapa
ALTER TABLE sales ADD COLUMN show_in_map INTEGER DEFAULT 1;
