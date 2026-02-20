-- Tabla de publicaciones de servicios profesionales
-- Comparte usuarios con real_estate_agents (mismo sistema de autenticación)
-- Mismos planes de precios: listing_pricing (7 días gratis, 30 días $1, 90 días $2)

-- Tabla principal de servicios
CREATE TABLE IF NOT EXISTS service_listings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  agent_id INTEGER NOT NULL,             -- ID del proveedor (real_estate_agents.id)
  category_id INTEGER NOT NULL,          -- ID de la categoría SERV:
  title TEXT NOT NULL,                   -- Título del servicio
  description TEXT,                      -- Descripción detallada
  service_type TEXT DEFAULT 'presencial', -- 'presencial', 'virtual', 'ambos'
  price_from REAL DEFAULT 0,             -- Precio desde
  price_to REAL DEFAULT 0,               -- Precio hasta (0 = no aplica)
  price_type TEXT DEFAULT 'hora',        -- 'hora', 'proyecto', 'mensual', 'consulta', 'negociable'
  currency TEXT DEFAULT 'CRC',           -- 'CRC' o 'USD'
  province TEXT,                         -- Provincia
  canton TEXT,                           -- Cantón
  district TEXT,                         -- Distrito
  location_description TEXT,             -- Descripción del área de cobertura
  experience_years INTEGER DEFAULT 0,    -- Años de experiencia
  skills TEXT,                           -- JSON con habilidades/herramientas
  availability TEXT,                     -- JSON con disponibilidad (días/horarios)
  images TEXT,                           -- JSON con URLs de imágenes
  contact_name TEXT,                     -- Nombre de contacto
  contact_phone TEXT,                    -- Teléfono de contacto
  contact_email TEXT,                    -- Email de contacto
  contact_whatsapp TEXT,                 -- WhatsApp de contacto
  website TEXT,                          -- Sitio web (opcional)
  pricing_plan_id INTEGER NOT NULL,      -- ID del plan (listing_pricing)
  is_active INTEGER DEFAULT 1,           -- Si está activo
  is_featured INTEGER DEFAULT 0,         -- Si es destacado
  views_count INTEGER DEFAULT 0,         -- Contador de vistas
  start_date TEXT,                       -- Fecha de inicio de publicación
  end_date TEXT,                         -- Fecha de fin de publicación
  payment_status TEXT DEFAULT 'pending', -- 'pending', 'paid', 'free'
  payment_id TEXT,                       -- ID del pago (si aplica)
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (agent_id) REFERENCES real_estate_agents(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (pricing_plan_id) REFERENCES listing_pricing(id)
);

-- Índices para rendimiento
CREATE INDEX IF NOT EXISTS idx_service_agent ON service_listings(agent_id);
CREATE INDEX IF NOT EXISTS idx_service_category ON service_listings(category_id);
CREATE INDEX IF NOT EXISTS idx_service_active ON service_listings(is_active);
CREATE INDEX IF NOT EXISTS idx_service_dates ON service_listings(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_service_province ON service_listings(province);
CREATE INDEX IF NOT EXISTS idx_service_payment ON service_listings(payment_status);

-- Categorías de servicios profesionales (prefijo SERV:)
-- Se insertan en la tabla 'categories' existente
INSERT OR IGNORE INTO categories (name, icon, active, display_order) VALUES
('SERV: Abogados y Servicios Legales', 'fa-gavel', 1, 200),
('SERV: Contabilidad y Finanzas', 'fa-calculator', 1, 201),
('SERV: Mantenimiento del Hogar', 'fa-tools', 1, 202),
('SERV: Plomería y Electricidad', 'fa-plug', 1, 203),
('SERV: Limpieza del Hogar', 'fa-broom', 1, 204),
('SERV: Shuttle y Transporte', 'fa-shuttle-van', 1, 205),
('SERV: Fletes y Mudanzas', 'fa-truck', 1, 206),
('SERV: Tutorías y Clases', 'fa-chalkboard-teacher', 1, 207),
('SERV: Diseño y Creatividad', 'fa-paint-brush', 1, 208),
('SERV: Tecnología y Sistemas', 'fa-laptop-code', 1, 209),
('SERV: Salud y Bienestar', 'fa-heartbeat', 1, 210),
('SERV: Fotografía y Video', 'fa-camera', 1, 211),
('SERV: Eventos y Catering', 'fa-glass-cheers', 1, 212),
('SERV: Jardinería y Zonas Verdes', 'fa-leaf', 1, 213),
('SERV: Seguridad y Vigilancia', 'fa-shield-alt', 1, 214),
('SERV: Otros Servicios', 'fa-concierge-bell', 1, 215);
