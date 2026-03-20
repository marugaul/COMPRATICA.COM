-- Agregar campo enable_mooving a la tabla entrepreneur_shipping
-- Este campo permite a las emprendedoras habilitar la opción de envío con Mooving

-- Para SQLite:
ALTER TABLE entrepreneur_shipping ADD COLUMN enable_mooving INTEGER NOT NULL DEFAULT 0;

-- Comentarios sobre el campo:
-- enable_mooving: 0 = deshabilitado, 1 = habilitado
-- Esta opción es independiente de Uber y otras opciones de envío existentes
