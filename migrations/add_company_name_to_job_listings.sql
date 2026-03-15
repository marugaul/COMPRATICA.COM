-- ================================================
-- MIGRACIÓN: Agregar company_name a job_listings
-- ================================================
-- Esta migración agrega un campo company_name directamente
-- en la tabla job_listings para almacenar el nombre de la
-- empresa real (especialmente importante para empleos importados)
-- ================================================

-- Agregar columna company_name si no existe
ALTER TABLE job_listings ADD COLUMN company_name TEXT DEFAULT NULL;

-- Crear índice para búsquedas por empresa
CREATE INDEX IF NOT EXISTS idx_job_listings_company_name
    ON job_listings(company_name);

-- ================================================
-- NOTAS:
-- ================================================
-- Después de ejecutar esta migración, actualizar:
-- 1. publicacion-detalle.php - para usar jl.company_name con prioridad
-- 2. empleos.php - para usar jl.company_name con prioridad
-- 3. Scripts de importación - para guardar company_name en jl.company_name
-- ================================================
