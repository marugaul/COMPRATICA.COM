-- ================================================
-- MIGRACIÓN COMPLETA: Sistema de Importación de Empleos
-- ================================================
-- Este script crea todas las estructuras necesarias para el sistema
-- de importación automática de empleos.
--
-- Ejecutar en cPanel → phpLiteAdmin o vía sqlite3:
-- sqlite3 data/compratica.db < migrations/setup_import_jobs_system.sql
-- ================================================

-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- 1. CREAR USUARIO BOT (si no existe)
-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- El usuario bot publica los empleos importados automáticamente

INSERT OR IGNORE INTO users (
    email,
    name,
    password_hash,
    role,
    is_active,
    created_at,
    updated_at
)
VALUES (
    'bot@compratica.com',
    'CompraTica Empleos',
    '$2y$10$abcdefghijklmnopqrstuv',  -- Hash dummy (el bot no hace login)
    'user',
    1,
    datetime('now'),
    datetime('now')
);

-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- 2. AGREGAR COLUMNAS A job_listings (si no existen)
-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- Estas columnas permiten rastrear de dónde vino cada empleo importado

-- Verificar si las columnas ya existen antes de agregarlas
-- SQLite no tiene IF NOT EXISTS para ALTER TABLE, pero no da error si ya existe

-- Columna: fuente de importación (ej: "arbeitnow", "remotive", "jobicy")
ALTER TABLE job_listings ADD COLUMN import_source TEXT DEFAULT NULL;

-- Columna: URL original del empleo en la fuente externa
ALTER TABLE job_listings ADD COLUMN source_url TEXT DEFAULT NULL;

-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- 3. CREAR TABLA job_import_log
-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- Registra cada ejecución de importación con estadísticas

CREATE TABLE IF NOT EXISTS job_import_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    -- Fuente de importación
    source TEXT NOT NULL,

    -- Timestamps
    started_at TEXT DEFAULT (datetime('now')),
    finished_at TEXT,

    -- Estadísticas
    inserted INTEGER DEFAULT 0,   -- Empleos nuevos insertados
    skipped INTEGER DEFAULT 0,    -- Empleos duplicados omitidos
    errors INTEGER DEFAULT 0,     -- Errores encontrados

    -- Mensaje adicional
    message TEXT
);

-- Índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_job_import_log_source
    ON job_import_log(source);

CREATE INDEX IF NOT EXISTS idx_job_import_log_started
    ON job_import_log(started_at DESC);

-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- 4. ÍNDICES ADICIONALES PARA job_listings
-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- Optimizan las búsquedas y filtros de empleos importados

CREATE INDEX IF NOT EXISTS idx_job_listings_import_source
    ON job_listings(import_source);

CREATE INDEX IF NOT EXISTS idx_job_listings_source_url
    ON job_listings(source_url);

-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
-- 5. VERIFICACIÓN: Consulta para confirmar que todo está creado
-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

-- Descomentar estas líneas si quieres verificar después de ejecutar:

/*
-- Verificar usuario bot
SELECT id, email, name, created_at
FROM users
WHERE email = 'bot@compratica.com';

-- Verificar columnas en job_listings
PRAGMA table_info(job_listings);

-- Verificar tabla job_import_log
SELECT name, sql
FROM sqlite_master
WHERE type='table' AND name='job_import_log';

-- Ver índices creados
SELECT name, tbl_name
FROM sqlite_master
WHERE type='index'
AND (tbl_name='job_listings' OR tbl_name='job_import_log')
ORDER BY tbl_name, name;
*/

-- ================================================
-- FIN DE LA MIGRACIÓN
-- ================================================
-- Después de ejecutar este script:
-- 1. Accede a https://compratica.com/admin/import_jobs.php
-- 2. Haz clic en "Importar ahora" para probar
-- 3. Revisa el log en tiempo real
-- ================================================
