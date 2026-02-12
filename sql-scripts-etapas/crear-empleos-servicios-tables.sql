-- ================================================
-- TABLAS PARA EMPLEOS Y SERVICIOS
-- Sistema completamente separado de afiliados
-- ================================================

-- Tabla de empleadores/proveedores de servicios
CREATE TABLE IF NOT EXISTS jobs_employers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  phone TEXT,
  password_hash TEXT NOT NULL,
  company_name TEXT,
  company_description TEXT,
  company_logo TEXT,
  website TEXT,
  is_active INTEGER DEFAULT 1,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

-- Tabla de publicaciones de empleos y servicios
CREATE TABLE IF NOT EXISTS job_listings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employer_id INTEGER NOT NULL,
  listing_type TEXT NOT NULL CHECK(listing_type IN ('job', 'service')),

  -- Información básica
  title TEXT NOT NULL,
  description TEXT NOT NULL,
  category TEXT,

  -- Detalles específicos para empleos
  job_type TEXT CHECK(job_type IN ('full-time', 'part-time', 'freelance', 'contract', 'internship', NULL)),
  salary_min REAL,
  salary_max REAL,
  salary_currency TEXT DEFAULT 'CRC',
  salary_period TEXT CHECK(salary_period IN ('hour', 'day', 'week', 'month', 'year', 'project', NULL)),

  -- Detalles específicos para servicios
  service_price REAL,
  service_price_type TEXT CHECK(service_price_type IN ('fixed', 'hourly', 'daily', 'negotiable', NULL)),

  -- Ubicación
  location TEXT,
  province TEXT,
  canton TEXT,
  distrito TEXT,
  remote_allowed INTEGER DEFAULT 0,

  -- Requisitos y beneficios
  requirements TEXT,
  benefits TEXT,

  -- Información de contacto
  contact_name TEXT,
  contact_email TEXT,
  contact_phone TEXT,
  contact_whatsapp TEXT,
  application_url TEXT,

  -- Imágenes
  image_1 TEXT,
  image_2 TEXT,
  image_3 TEXT,
  image_4 TEXT,
  image_5 TEXT,

  -- Control de publicación
  is_active INTEGER DEFAULT 1,
  is_featured INTEGER DEFAULT 0,
  start_date TEXT,
  end_date TEXT,
  views_count INTEGER DEFAULT 0,
  applications_count INTEGER DEFAULT 0,

  -- Timestamps
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),

  FOREIGN KEY (employer_id) REFERENCES jobs_employers(id) ON DELETE CASCADE
);

-- Índices para mejorar el rendimiento
CREATE INDEX IF NOT EXISTS idx_job_listings_employer ON job_listings(employer_id);
CREATE INDEX IF NOT EXISTS idx_job_listings_type ON job_listings(listing_type);
CREATE INDEX IF NOT EXISTS idx_job_listings_category ON job_listings(category);
CREATE INDEX IF NOT EXISTS idx_job_listings_active ON job_listings(is_active);
CREATE INDEX IF NOT EXISTS idx_job_listings_province ON job_listings(province);
CREATE INDEX IF NOT EXISTS idx_job_listings_dates ON job_listings(start_date, end_date);

-- Tabla de categorías para empleos y servicios
CREATE TABLE IF NOT EXISTS job_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  icon TEXT,
  parent_category TEXT,
  display_order INTEGER DEFAULT 0,
  active INTEGER DEFAULT 1
);

-- Insertar categorías por defecto
INSERT OR IGNORE INTO job_categories (name, icon, parent_category, display_order) VALUES
-- Categorías de Empleos
('EMP: Tecnología e Informática', 'fa-laptop-code', 'Empleos', 1),
('EMP: Administración y Finanzas', 'fa-calculator', 'Empleos', 2),
('EMP: Ventas y Marketing', 'fa-chart-line', 'Empleos', 3),
('EMP: Salud y Medicina', 'fa-heartbeat', 'Empleos', 4),
('EMP: Educación', 'fa-graduation-cap', 'Empleos', 5),
('EMP: Construcción', 'fa-hard-hat', 'Empleos', 6),
('EMP: Hostelería y Turismo', 'fa-hotel', 'Empleos', 7),
('EMP: Transporte y Logística', 'fa-truck', 'Empleos', 8),
('EMP: Servicio al Cliente', 'fa-headset', 'Empleos', 9),
('EMP: Legal y Jurídico', 'fa-gavel', 'Empleos', 10),

-- Categorías de Servicios
('SERV: Desarrollo Web y Apps', 'fa-code', 'Servicios', 20),
('SERV: Diseño Gráfico', 'fa-palette', 'Servicios', 21),
('SERV: Marketing Digital', 'fa-bullhorn', 'Servicios', 22),
('SERV: Fotografía y Video', 'fa-camera', 'Servicios', 23),
('SERV: Consultoría', 'fa-user-tie', 'Servicios', 24),
('SERV: Reparaciones', 'fa-tools', 'Servicios', 25),
('SERV: Limpieza', 'fa-broom', 'Servicios', 26),
('SERV: Belleza y Estética', 'fa-cut', 'Servicios', 27),
('SERV: Eventos', 'fa-calendar-alt', 'Servicios', 28),
('SERV: Clases Particulares', 'fa-chalkboard-teacher', 'Servicios', 29),
('SERV: Traducción', 'fa-language', 'Servicios', 30),
('SERV: Legal', 'fa-balance-scale', 'Servicios', 31);

-- Tabla de aplicaciones (para empleos)
CREATE TABLE IF NOT EXISTS job_applications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  listing_id INTEGER NOT NULL,
  applicant_name TEXT NOT NULL,
  applicant_email TEXT NOT NULL,
  applicant_phone TEXT,
  resume_url TEXT,
  cover_letter TEXT,
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'reviewed', 'interview', 'rejected', 'accepted')),
  notes TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),

  FOREIGN KEY (listing_id) REFERENCES job_listings(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_applications_listing ON job_applications(listing_id);
CREATE INDEX IF NOT EXISTS idx_applications_status ON job_applications(status);
