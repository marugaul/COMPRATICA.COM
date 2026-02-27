-- Migración: Agregar campos configurables a listing_pricing
-- Fecha: 2026-02-27
-- Descripción: Permite al administrador configurar fotos máximas y métodos de pago por plan

-- Agregar columna para número máximo de fotos permitidas
ALTER TABLE listing_pricing ADD COLUMN max_photos INTEGER DEFAULT 3;

-- Agregar columna para métodos de pago permitidos (sinpe, paypal, ambos)
-- Formato: 'sinpe', 'paypal', 'sinpe,paypal'
ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';

-- Actualizar los planes existentes con valores por defecto
UPDATE listing_pricing SET max_photos = 3 WHERE id = 1;  -- Plan Gratis: 3 fotos
UPDATE listing_pricing SET max_photos = 5 WHERE id = 2;  -- Plan 30 días: 5 fotos
UPDATE listing_pricing SET max_photos = 8 WHERE id = 3;  -- Plan 90 días: 8 fotos

-- Asegurarse de que todos los planes tengan métodos de pago configurados
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE payment_methods IS NULL OR payment_methods = '';

-- Verificar los cambios
SELECT id, name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active FROM listing_pricing;
