-- ================================================
-- MIGRACIÓN: Agregar soporte de pagos y planes de precios
-- a Empleos y Servicios (igual que Bienes Raíces)
-- ================================================

-- ======================================
-- 1. AGREGAR COLUMNAS A job_listings
-- ======================================
ALTER TABLE job_listings ADD COLUMN pricing_plan_id INTEGER;
ALTER TABLE job_listings ADD COLUMN payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending', 'confirmed', 'free'));
ALTER TABLE job_listings ADD COLUMN payment_id TEXT;
ALTER TABLE job_listings ADD COLUMN payment_date TEXT;

-- ======================================
-- 2. CREAR TABLA job_pricing (Planes para Empleos)
-- ======================================
CREATE TABLE IF NOT EXISTS job_pricing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,                    -- Nombre del plan (ej: "Gratis 7 días")
  duration_days INTEGER NOT NULL,        -- Duración en días
  price_usd REAL DEFAULT 0,              -- Precio en dólares
  price_crc REAL DEFAULT 0,              -- Precio en colones
  max_photos INTEGER DEFAULT 3,          -- Máximo de fotos permitidas
  payment_methods TEXT DEFAULT 'sinpe,paypal', -- Métodos de pago: "sinpe,paypal"
  is_active INTEGER DEFAULT 1,           -- Si el plan está activo
  is_featured INTEGER DEFAULT 0,         -- Si el plan es destacado
  description TEXT,                      -- Descripción del plan
  display_order INTEGER DEFAULT 0,       -- Orden de visualización
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

-- Insertar planes por defecto para Empleos
INSERT INTO job_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order)
VALUES
  ('Gratis 7 días', 7, 0.00, 0, 3, 'sinpe,paypal', 1, 0, 'Publicación gratuita por 7 días con hasta 3 fotos', 1),
  ('Plan 30 días', 30, 1.00, 540, 5, 'sinpe,paypal', 1, 0, 'Publicación destacada por 30 días con hasta 5 fotos', 2),
  ('Plan 90 días', 90, 2.00, 1080, 8, 'sinpe,paypal', 1, 1, 'Máxima visibilidad por 90 días con hasta 8 fotos', 3);

-- ======================================
-- 3. CREAR TABLA service_pricing (Planes para Servicios)
-- ======================================
CREATE TABLE IF NOT EXISTS service_pricing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,                    -- Nombre del plan
  duration_days INTEGER NOT NULL,        -- Duración en días
  price_usd REAL DEFAULT 0,              -- Precio en dólares
  price_crc REAL DEFAULT 0,              -- Precio en colones
  max_photos INTEGER DEFAULT 3,          -- Máximo de fotos permitidas
  payment_methods TEXT DEFAULT 'sinpe,paypal', -- Métodos de pago
  is_active INTEGER DEFAULT 1,           -- Si el plan está activo
  is_featured INTEGER DEFAULT 0,         -- Si el plan es destacado
  description TEXT,                      -- Descripción del plan
  display_order INTEGER DEFAULT 0,       -- Orden de visualización
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

-- Insertar planes por defecto para Servicios
INSERT INTO service_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order)
VALUES
  ('Gratis 7 días', 7, 0.00, 0, 3, 'sinpe,paypal', 1, 0, 'Publicación gratuita por 7 días con hasta 3 fotos', 1),
  ('Plan 30 días', 30, 1.00, 540, 5, 'sinpe,paypal', 1, 0, 'Publicación destacada por 30 días con hasta 5 fotos', 2),
  ('Plan 90 días', 90, 2.00, 1080, 8, 'sinpe,paypal', 1, 1, 'Máxima visibilidad por 90 días con hasta 8 fotos', 3);

-- ======================================
-- 4. CREAR ÍNDICES
-- ======================================
CREATE INDEX IF NOT EXISTS idx_job_listings_pricing_plan ON job_listings(pricing_plan_id);
CREATE INDEX IF NOT EXISTS idx_job_listings_payment_status ON job_listings(payment_status);
CREATE INDEX IF NOT EXISTS idx_job_pricing_active ON job_pricing(is_active);
CREATE INDEX IF NOT EXISTS idx_service_pricing_active ON service_pricing(is_active);

-- ======================================
-- NOTA: Esta migración hace que Empleos y Servicios
-- funcionen exactamente igual que Bienes Raíces:
-- - Planes configurables por el admin
-- - Opciones de pago configurables (SINPE, PayPal)
-- - Precios en USD y CRC
-- - Planes gratuitos y de pago
-- ======================================
