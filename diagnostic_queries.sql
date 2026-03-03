-- CONSULTAS DE DIAGNÓSTICO PARA VANESSA CASTRO
-- Ejecuta estas queries y comparte los resultados

-- 1. Buscar el afiliado Vanessa Castro
SELECT '=== 1. AFILIADO VANESSA CASTRO ===' as query;
SELECT id, name, email, created_at
FROM affiliates
WHERE email = 'vanecastro@gmail.com';

-- 2. Buscar todos los espacios de Vanessa (por email)
SELECT '=== 2. ESPACIOS DE VANESSA CASTRO ===' as query;
SELECT s.id, s.affiliate_id, s.title, s.is_active, s.start_at, s.end_at, s.created_at
FROM sales s
JOIN affiliates a ON a.id = s.affiliate_id
WHERE a.email = 'vanecastro@gmail.com'
ORDER BY s.id DESC;

-- 3. Buscar espacio con título similar a "garage" y "mujer"
SELECT '=== 3. ESPACIOS CON TÍTULO SIMILAR ===' as query;
SELECT s.id, s.affiliate_id, s.title, s.is_active, a.name as affiliate_name, a.email
FROM sales s
LEFT JOIN affiliates a ON a.id = s.affiliate_id
WHERE s.title LIKE '%garage%' AND s.title LIKE '%mujer%';

-- 4. Buscar espacio con ID 21 (el que aparece en la URL de la captura)
SELECT '=== 4. ESPACIO ID 21 ===' as query;
SELECT s.*, a.name as affiliate_name, a.email as affiliate_email
FROM sales s
LEFT JOIN affiliates a ON a.id = s.affiliate_id
WHERE s.id = 21;

-- 5. Productos del espacio 21 (si existe)
SELECT '=== 5. PRODUCTOS DEL ESPACIO 21 ===' as query;
SELECT p.id, p.affiliate_id, p.sale_id, p.name, p.price, p.currency, p.stock, p.active
FROM products p
WHERE p.sale_id = 21;

-- 6. Buscar productos con nombres similares a los de la captura
SELECT '=== 6. PRODUCTOS CON NOMBRE SIMILAR ===' as query;
SELECT p.id, p.sale_id, p.affiliate_id, p.name, p.price, p.currency, p.active,
       s.title as space_title, a.name as affiliate_name
FROM products p
LEFT JOIN sales s ON s.id = p.sale_id
LEFT JOIN affiliates a ON a.id = p.affiliate_id
WHERE p.name LIKE '%palazzo%' OR p.name LIKE '%blusa%' OR p.name LIKE '%rayada%';

-- 7. Todos los espacios activos con productos
SELECT '=== 7. ESPACIOS ACTIVOS CON PRODUCTOS ===' as query;
SELECT s.id, s.title, s.affiliate_id, a.name as affiliate_name, a.email,
       COUNT(p.id) as product_count
FROM sales s
LEFT JOIN affiliates a ON a.id = s.affiliate_id
LEFT JOIN products p ON p.sale_id = s.id
WHERE s.is_active = 1
GROUP BY s.id
HAVING product_count > 0
ORDER BY s.id DESC;

-- 8. Últimos 5 espacios creados
SELECT '=== 8. ÚLTIMOS 5 ESPACIOS CREADOS ===' as query;
SELECT s.id, s.affiliate_id, s.title, s.is_active, a.name as affiliate_name, a.email,
       s.created_at, s.start_at, s.end_at
FROM sales s
LEFT JOIN affiliates a ON a.id = s.affiliate_id
ORDER BY s.created_at DESC
LIMIT 5;
