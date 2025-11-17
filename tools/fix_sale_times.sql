-- Script SQL para actualizar horas de ventas de garaje
-- Cambia 00:00:00 a horarios razonables (8:00 AM - 6:00 PM)
--
-- INSTRUCCIONES:
-- 1. Abre tu base de datos SQLite en cPanel (File Manager o phpLiteAdmin)
-- 2. Copia y pega este SQL completo
-- 3. Ejecuta
--
-- NOTA: Este script es seguro y solo actualiza ventas con hora 00:00:00

-- Ver qué ventas se van a actualizar (opcional - solo para revisar)
-- SELECT id, title, start_at, end_at
-- FROM sales
-- WHERE start_at LIKE '%00:00:00' OR end_at LIKE '%00:00:00';

-- Actualizar fechas de inicio: cambiar 00:00:00 a 08:00:00 (8:00 AM)
UPDATE sales
SET start_at = REPLACE(start_at, ' 00:00:00', ' 08:00:00'),
    updated_at = datetime('now', 'localtime')
WHERE start_at LIKE '%00:00:00';

-- Actualizar fechas de fin: cambiar 00:00:00 a 18:00:00 (6:00 PM)
UPDATE sales
SET end_at = REPLACE(end_at, ' 00:00:00', ' 18:00:00'),
    updated_at = datetime('now', 'localtime')
WHERE end_at LIKE '%00:00:00';

-- Ver resultado final (opcional - para verificar)
-- SELECT id, title, start_at, end_at
-- FROM sales
-- ORDER BY start_at DESC;
