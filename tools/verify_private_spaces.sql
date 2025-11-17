-- =========================================================
-- VERIFICACIÓN: Estructura de tabla sales
-- Ejecutar DESPUÉS de la migración para confirmar
-- =========================================================

-- Ver estructura completa de la tabla sales
PRAGMA table_info(sales);

-- Ver datos de ejemplo (primeros 5 registros)
SELECT id, title, is_private, access_code, is_active
FROM sales
LIMIT 5;
