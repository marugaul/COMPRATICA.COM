-- =========================================================
-- Script SQL para actualizar fechas de espacios (sales)
-- =========================================================

-- IMPORTANTE: Reemplaza los valores según necesites
-- Format de fecha: 'YYYY-MM-DD HH:MM:SS'

-- EJEMPLO 1: Actualizar solo fecha de inicio de un espacio específico
-- UPDATE sales
-- SET start_at = '2025-11-20 08:00:00',
--     updated_at = datetime('now', 'localtime')
-- WHERE id = 5;

-- EJEMPLO 2: Actualizar solo fecha de fin de un espacio específico
-- UPDATE sales
-- SET end_at = '2025-11-27 18:00:00',
--     updated_at = datetime('now', 'localtime')
-- WHERE id = 5;

-- EJEMPLO 3: Actualizar ambas fechas de un espacio específico
-- UPDATE sales
-- SET start_at = '2025-11-20 08:00:00',
--     end_at = '2025-11-27 18:00:00',
--     updated_at = datetime('now', 'localtime')
-- WHERE id = 5;

-- EJEMPLO 4: Actualizar todas las fechas de un afiliado específico
-- UPDATE sales
-- SET start_at = '2025-11-20 08:00:00',
--     end_at = '2025-11-27 18:00:00',
--     updated_at = datetime('now', 'localtime')
-- WHERE affiliate_id = 3;

-- EJEMPLO 5: Extender la fecha de fin de todos los espacios activos por 7 días
-- UPDATE sales
-- SET end_at = datetime(end_at, '+7 days'),
--     updated_at = datetime('now', 'localtime')
-- WHERE is_active = 1;

-- EJEMPLO 6: Adelantar fecha de inicio de un espacio por 1 día
-- UPDATE sales
-- SET start_at = datetime(start_at, '+1 day'),
--     updated_at = datetime('now', 'localtime')
-- WHERE id = 5;

-- =========================================================
-- VERIFICACIÓN: Ver espacios antes de actualizar
-- =========================================================
-- Descomentar para ver los datos actuales:
-- SELECT id, title, start_at, end_at, is_active
-- FROM sales
-- ORDER BY id DESC;

-- =========================================================
-- PLANTILLA PARA TU USO
-- =========================================================
-- Copia y modifica según necesites:

-- Actualizar espacio ID X:
-- UPDATE sales
-- SET start_at = 'YYYY-MM-DD HH:MM:SS',
--     end_at = 'YYYY-MM-DD HH:MM:SS',
--     updated_at = datetime('now', 'localtime')
-- WHERE id = X;

-- Verificar cambio:
-- SELECT id, title, start_at, end_at
-- FROM sales
-- WHERE id = X;
