-- Tabla de configuración de precios para publicaciones
-- Permite parametrizar los costos de publicación según duración
-- Para el módulo de Bienes Raíces

CREATE TABLE IF NOT EXISTS listing_pricing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,           -- Nombre del plan (ej: "Gratis 7 días")
  duration_days INTEGER NOT NULL, -- Duración en días
  price_usd REAL NOT NULL,       -- Precio en dólares
  price_crc REAL NOT NULL,       -- Precio en colones
  is_active INTEGER DEFAULT 1,   -- Si está activo o no
  is_featured INTEGER DEFAULT 0, -- Si es un plan destacado
  description TEXT,              -- Descripción del plan
  display_order INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

-- Insertar los planes de precios iniciales
INSERT INTO listing_pricing (name, duration_days, price_usd, price_crc, is_active, is_featured, description, display_order) VALUES
('Gratis 7 días', 7, 0.00, 0.00, 1, 0, 'Prueba gratis por 7 días', 1),
('Plan 30 días', 30, 1.00, 540.00, 1, 1, 'Publicación por 30 días', 2),
('Plan 90 días', 90, 2.00, 1080.00, 1, 1, 'Publicación por 90 días - Ahorrá 33%', 3);

-- Tabla para registrar las publicaciones de bienes raíces
CREATE TABLE IF NOT EXISTS real_estate_listings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,              -- ID del usuario que publica
  category_id INTEGER NOT NULL,          -- ID de la categoría (de la tabla categories)
  title TEXT NOT NULL,                   -- Título de la publicación
  description TEXT,                      -- Descripción detallada
  price REAL NOT NULL,                   -- Precio de la propiedad
  currency TEXT DEFAULT 'CRC',           -- Moneda (CRC o USD)
  location TEXT,                         -- Ubicación de la propiedad
  province TEXT,                         -- Provincia
  canton TEXT,                           -- Cantón
  district TEXT,                         -- Distrito
  bedrooms INTEGER DEFAULT 0,            -- Número de habitaciones
  bathrooms INTEGER DEFAULT 0,           -- Número de baños
  area_m2 REAL DEFAULT 0,               -- Área en metros cuadrados
  parking_spaces INTEGER DEFAULT 0,      -- Espacios de parqueo
  features TEXT,                         -- JSON con características (piscina, jardín, etc.)
  images TEXT,                           -- JSON con URLs de imágenes
  contact_name TEXT,                     -- Nombre de contacto
  contact_phone TEXT,                    -- Teléfono de contacto
  contact_email TEXT,                    -- Email de contacto
  contact_whatsapp TEXT,                 -- WhatsApp de contacto
  listing_type TEXT DEFAULT 'sale',      -- 'sale' o 'rent'
  pricing_plan_id INTEGER NOT NULL,      -- ID del plan de precios seleccionado
  is_active INTEGER DEFAULT 1,           -- Si está activa
  is_featured INTEGER DEFAULT 0,         -- Si es destacada
  views_count INTEGER DEFAULT 0,         -- Contador de vistas
  start_date TEXT,                       -- Fecha de inicio de publicación
  end_date TEXT,                         -- Fecha de fin de publicación
  payment_status TEXT DEFAULT 'pending', -- 'pending', 'paid', 'free'
  payment_id TEXT,                       -- ID del pago (si aplica)
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (pricing_plan_id) REFERENCES listing_pricing(id)
);

-- Índices para mejorar el rendimiento
CREATE INDEX IF NOT EXISTS idx_real_estate_user ON real_estate_listings(user_id);
CREATE INDEX IF NOT EXISTS idx_real_estate_category ON real_estate_listings(category_id);
CREATE INDEX IF NOT EXISTS idx_real_estate_active ON real_estate_listings(is_active);
CREATE INDEX IF NOT EXISTS idx_real_estate_dates ON real_estate_listings(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_real_estate_location ON real_estate_listings(province, canton);
