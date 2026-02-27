-- FIX: Agregar columna payment_methods a listing_pricing
-- Fecha: 2026-02-27
-- Propósito: Solucionar error "SQLSTATE[HY000]: General error: 1 no such column: payment_methods"

-- Paso 1: Agregar la columna payment_methods
ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';

-- Paso 2: Actualizar planes existentes con valores por defecto
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 1;  -- Plan Gratis
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 2;  -- Plan 30 días
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 3;  -- Plan 90 días

-- Paso 3: Asegurarse de que todos los planes tengan métodos de pago
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE payment_methods IS NULL OR payment_methods = '';

-- Verificar los cambios
SELECT id, name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active
FROM listing_pricing
ORDER BY display_order;
