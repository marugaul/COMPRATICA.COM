-- =====================================================================
-- SCRIPT 2: Usuarios emprendedoras de prueba
-- Password de ambas: Compratica2024!
-- =====================================================================

-- Borrar si ya existen (para evitar duplicados)
DELETE FROM users WHERE email IN ('marisol.test@compratica.com', 'cafe.test@compratica.com');

-- Vendedora 1: Marisol Artesanías CR (EN VIVO activado)
INSERT INTO users (name, email, password_hash, status, is_active, is_live, live_title, live_link, live_started_at, bio, created_at)
VALUES (
    'Marisol Artesanías CR',
    'marisol.test@compratica.com',
    '$2y$12$2JTtMZ60bTquPy3u6kxJIe1ExOlkLApfyniAOwnYI7PPYM0fgGBAu',
    'active',
    1,
    1,
    'EN VIVO – Nueva colección verano 🎨',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    datetime('now'),
    'Artesana costarricense con 10 años de experiencia. Tejidos, cerámica y joyería hecha a mano.',
    datetime('now')
);

-- Vendedora 2: Café Tico Premium
INSERT INTO users (name, email, password_hash, status, is_active, is_live, bio, created_at)
VALUES (
    'Café Tico Premium',
    'cafe.test@compratica.com',
    '$2y$12$2JTtMZ60bTquPy3u6kxJIe1ExOlkLApfyniAOwnYI7PPYM0fgGBAu',
    'active',
    1,
    0,
    'Productores de café de altura de Tarrazú, Naranjo y Tres Ríos. 100% costarricense.',
    datetime('now')
);

-- Suscripciones activas (necesario para acceder al dashboard)
-- Toma el primer plan disponible
INSERT INTO entrepreneur_subscriptions (user_id, plan_id, status, created_at)
SELECT id, (SELECT id FROM entrepreneur_plans ORDER BY id LIMIT 1), 'active', datetime('now')
FROM users WHERE email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_subscriptions (user_id, plan_id, status, created_at)
SELECT id, (SELECT id FROM entrepreneur_plans ORDER BY id LIMIT 1), 'active', datetime('now')
FROM users WHERE email = 'cafe.test@compratica.com';

-- Verificar
SELECT id, name, email, is_live, status FROM users
WHERE email IN ('marisol.test@compratica.com', 'cafe.test@compratica.com');
