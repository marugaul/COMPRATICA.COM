-- ================================================
-- UNIFICACIÓN DE USUARIOS
-- Crea tabla 'users' unificada y migra datos de:
-- - real_estate_agents
-- - jobs_employers
-- - affiliates
-- ================================================

-- Crear tabla unificada de usuarios
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,

  -- Campos comunes
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  phone TEXT,
  password_hash TEXT NOT NULL,

  -- Campos de empresa/negocio
  company_name TEXT,
  company_description TEXT,
  company_logo TEXT,
  website TEXT,

  -- Campos específicos de bienes raíces
  license_number TEXT,
  specialization TEXT,
  bio TEXT,
  profile_image TEXT,

  -- Redes sociales
  facebook TEXT,
  instagram TEXT,
  whatsapp TEXT,

  -- Campos específicos de afiliados
  slug TEXT UNIQUE,
  avatar TEXT,
  fee_pct REAL DEFAULT 0.10,
  offers_products INTEGER DEFAULT 0,
  offers_services INTEGER DEFAULT 0,
  business_description TEXT,

  -- Control de cuenta
  is_active INTEGER DEFAULT 1,

  -- Timestamps
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_slug ON users(slug);
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);

-- ================================================
-- MIGRAR DATOS EXISTENTES
-- ================================================

-- Migrar desde real_estate_agents
INSERT OR IGNORE INTO users (
  name, email, phone, password_hash,
  company_name, company_description, company_logo, website,
  license_number, specialization, bio, profile_image,
  facebook, instagram, whatsapp,
  is_active, created_at, updated_at
)
SELECT
  name, email, phone, password_hash,
  company_name, company_description, company_logo, website,
  license_number, specialization, bio, profile_image,
  facebook, instagram, whatsapp,
  is_active, created_at, updated_at
FROM real_estate_agents
WHERE email NOT IN (SELECT email FROM users);

-- Migrar desde jobs_employers
INSERT OR IGNORE INTO users (
  name, email, phone, password_hash,
  company_name, company_description, company_logo, website,
  is_active, created_at, updated_at
)
SELECT
  name, email, phone, password_hash,
  company_name, company_description, company_logo, website,
  is_active, created_at, updated_at
FROM jobs_employers
WHERE email NOT IN (SELECT email FROM users);

-- Migrar desde affiliates
INSERT OR IGNORE INTO users (
  name, email, phone, password_hash,
  slug, avatar, fee_pct, offers_products, offers_services, business_description,
  is_active, created_at, updated_at
)
SELECT
  name,
  COALESCE(email, slug || '@temp.local'), -- Si no hay email, crear uno temporal
  phone,
  COALESCE(password_hash, pass_hash), -- Usar password_hash o pass_hash
  slug, avatar, fee_pct, offers_products, offers_services, business_description,
  is_active, created_at, updated_at
FROM affiliates
WHERE COALESCE(email, slug || '@temp.local') NOT IN (SELECT email FROM users);
