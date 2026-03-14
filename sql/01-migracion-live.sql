-- =====================================================================
-- SCRIPT 1: Migración columnas "En Vivo" en tabla users
-- Ejecutar primero. Si da error "duplicate column", ignorar y continuar.
-- =====================================================================

ALTER TABLE users ADD COLUMN is_live INTEGER DEFAULT 0;
ALTER TABLE users ADD COLUMN live_title TEXT;
ALTER TABLE users ADD COLUMN live_link TEXT;
ALTER TABLE users ADD COLUMN live_started_at TEXT;
