-- ============================================================================
-- Fix: Crear tabla entrepreneur_shipping para opciones de envío
-- Fecha: 2026-03-15
-- Descripción: Tabla para gestionar opciones de envío de emprendedoras
-- ============================================================================

-- Crear tabla si no existe
CREATE TABLE IF NOT EXISTS entrepreneur_shipping (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    enable_free_shipping INTEGER NOT NULL DEFAULT 0,
    enable_pickup        INTEGER NOT NULL DEFAULT 0,
    enable_express       INTEGER NOT NULL DEFAULT 0,
    free_shipping_min    INTEGER NOT NULL DEFAULT 0,
    pickup_instructions  TEXT    NOT NULL DEFAULT '',
    express_zones        TEXT    NOT NULL DEFAULT '[]',
    updated_at           TEXT    DEFAULT (datetime('now','localtime'))
);

-- Verificar que se creó correctamente
SELECT
    'Tabla entrepreneur_shipping creada correctamente' as resultado,
    COUNT(*) as total_registros
FROM entrepreneur_shipping;

-- Mostrar estructura
PRAGMA table_info(entrepreneur_shipping);

-- ============================================================================
-- DESCRIPCIÓN DE CAMPOS:
-- ============================================================================
--
-- id                      : ID autoincremental (PRIMARY KEY)
-- user_id                 : ID del usuario/emprendedora (UNIQUE)
-- enable_free_shipping    : 1 = Envío gratis activado, 0 = desactivado
-- enable_pickup           : 1 = Retiro en local activado, 0 = desactivado
-- enable_express          : 1 = Envío express activado, 0 = desactivado
-- free_shipping_min       : Monto mínimo en colones para envío gratis
-- pickup_instructions     : Texto con instrucciones de retiro en local
-- express_zones           : JSON array con zonas de envío express
--                           Formato: [{"name":"Zona","price":1500}, ...]
-- updated_at              : Fecha/hora de última actualización
--
-- ============================================================================
-- EJEMPLO DE DATOS:
-- ============================================================================
--
-- INSERT INTO entrepreneur_shipping (
--     user_id,
--     enable_free_shipping,
--     enable_pickup,
--     enable_express,
--     free_shipping_min,
--     pickup_instructions,
--     express_zones
-- ) VALUES (
--     1,                                                    -- user_id
--     1,                                                    -- envío gratis: Sí
--     1,                                                    -- retiro en local: Sí
--     1,                                                    -- envío express: Sí
--     10000,                                                -- mínimo: ₡10,000
--     'Av. Central, San José. Lunes a sábado 9am-6pm.',   -- instrucciones
--     '[{"name":"San José Centro","price":1500},{"name":"Alajuela","price":2000}]'
-- );
--
-- ============================================================================
-- VERIFICACIÓN:
-- ============================================================================

-- Ver todos los registros
SELECT
    user_id,
    CASE enable_free_shipping WHEN 1 THEN 'Sí' ELSE 'No' END as envio_gratis,
    CASE enable_pickup WHEN 1 THEN 'Sí' ELSE 'No' END as retiro_local,
    CASE enable_express WHEN 1 THEN 'Sí' ELSE 'No' END as envio_express,
    free_shipping_min as minimo_envio_gratis,
    pickup_instructions as instrucciones,
    express_zones as zonas_json,
    updated_at as actualizado
FROM entrepreneur_shipping;

-- ============================================================================
-- ROLLBACK (si necesitas deshacer):
-- ============================================================================
-- DROP TABLE IF EXISTS entrepreneur_shipping;
-- ============================================================================
