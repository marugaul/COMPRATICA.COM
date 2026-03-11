-- =====================================================================
-- SCRIPT 3: Productos de prueba (15 por vendedora)
-- Ejecutar DESPUÉS del script 02.
-- =====================================================================

-- Borrar productos de prueba si ya existen
DELETE FROM entrepreneur_products
WHERE user_id IN (
    SELECT id FROM users WHERE email IN ('marisol.test@compratica.com', 'cafe.test@compratica.com')
);

-- ─────────────────────────────────────────────────────────────────────
-- MARISOL ARTESANÍAS CR — 15 productos (artesanías, hogar, joyería)
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Collar de Piedras Volcánicas', 'Collar artesanal hecho con piedras volcánicas del Irazú, diseño único y exclusivo.', 15500, 'CRC', 8, 'https://picsum.photos/seed/aw-col1/400/400', 1, 1, 142, 18, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Aretes de Madera Tropical', 'Aretes livianos de madera de guanacaste, barnizados y pintados a mano.', 9800, 'CRC', 12, 'https://picsum.photos/seed/aw-col2/400/400', 0, 1, 87, 11, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Tapiz de Fique Trenzado', 'Tapiz decorativo trenzado en fique natural, colores neutros para sala o comedor.', 22000, 'CRC', 5, 'https://picsum.photos/seed/aw-col3/400/400', 1, 1, 210, 25, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Pulsera de Semillas Silvestres', 'Pulsera con semillas silvestres costarricenses: ojoche, jobillo y guácimo.', 7500, 'CRC', 20, 'https://picsum.photos/seed/aw-col4/400/400', 0, 1, 64, 8, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 8, 'Cuadro en Técnica Óxido', 'Obra artística en técnica de óxido sobre tela, 40x40 cm, enmarcado a mano.', 45000, 'CRC', 3, 'https://picsum.photos/seed/aw-col5/400/400', 1, 1, 178, 5, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Canasta de Palma Real', 'Canasta tejida a mano en palma real, resistente y decorativa.', 18500, 'CRC', 7, 'https://picsum.photos/seed/aw-col6/400/400', 0, 1, 93, 14, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Anillo de Tagua', 'Anillo de tagua (marfil vegetal) tallado a mano, acabado natural.', 6500, 'CRC', 15, 'https://picsum.photos/seed/aw-col7/400/400', 0, 1, 55, 9, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Portavelas de Cerámica', 'Portavelas artesanal en cerámica, diseño tropical con motivos de mariposas.', 12000, 'CRC', 10, 'https://picsum.photos/seed/aw-col8/400/400', 1, 1, 119, 16, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Dije Tortuga de Plata', 'Dije en plata .925 con forma de tortuga baula, símbolo de Costa Rica.', 28000, 'CRC', 4, 'https://picsum.photos/seed/aw-col9/400/400', 0, 1, 231, 7, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Móvil de Bambú', 'Móvil decorativo de bambú con conchas y semillas, sonido relajante.', 14500, 'CRC', 6, 'https://picsum.photos/seed/aw-col10/400/400', 0, 1, 76, 12, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 8, 'Maceta Pintada a Mano', 'Maceta de barro pintada con diseños de flora costarricense, tamaño mediano.', 11000, 'CRC', 9, 'https://picsum.photos/seed/aw-col11/400/400', 0, 1, 44, 6, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 3, 'Set Aretes Tropicales x3', 'Set de 3 pares de aretes: tucán, mariposa y bromelia, pintados a mano.', 18000, 'CRC', 8, 'https://picsum.photos/seed/aw-col12/400/400', 1, 1, 167, 20, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Cojín de Algodón Pintado', 'Cojín 45x45 cm de algodón puro con pintura textil de quetzal.', 25000, 'CRC', 5, 'https://picsum.photos/seed/aw-col13/400/400', 0, 1, 88, 10, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 8, 'Marcapáginas Artesanales x5', 'Set de 5 marcapáginas ilustrados con flora y fauna de CR, laminados.', 4500, 'CRC', 25, 'https://picsum.photos/seed/aw-col14/400/400', 0, 1, 39, 15, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 7, 'Espejo con Marco de Bambú', 'Espejo redondo 30 cm con marco artesanal de bambú trenzado, listo para colgar.', 38000, 'CRC', 3, 'https://picsum.photos/seed/aw-col15/400/400', 1, 1, 195, 8, 1, 1, '88001234', 'marisol@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'marisol.test@compratica.com';

-- ─────────────────────────────────────────────────────────────────────
-- CAFÉ TICO PREMIUM — 15 productos (café, alimentos)
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Café Tarrazú Grano 250g', 'Café de altura 1800 msnm, perfil floral con notas de chocolate y caramelo.', 6500, 'CRC', 50, 'https://picsum.photos/seed/ct-caf1/400/400', 1, 1, 284, 45, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Café Naranjo Molido 500g', 'Café molido medio, tostado artesanal, ideal para cafetera de filtro.', 11000, 'CRC', 30, 'https://picsum.photos/seed/ct-caf2/400/400', 0, 1, 156, 28, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Mermelada de Cas 240g', 'Mermelada artesanal de cas sin conservantes, sabor intenso y natural.', 4800, 'CRC', 40, 'https://picsum.photos/seed/ct-caf3/400/400', 1, 1, 122, 33, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Café Tres Ríos Grano 500g', 'Denominación de origen, notas cítricas y acidez brillante.', 13500, 'CRC', 20, 'https://picsum.photos/seed/ct-caf4/400/400', 1, 1, 198, 31, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Granola Tropical 300g', 'Granola con avena, coco, piña deshidratada y macadamia, sin azúcar añadida.', 7200, 'CRC', 35, 'https://picsum.photos/seed/ct-caf5/400/400', 0, 1, 99, 22, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Cold Brew Concentrado 500ml', 'Cold brew de Tarrazú preparado en frío 18 horas, concentrado 1:4.', 8500, 'CRC', 15, 'https://picsum.photos/seed/ct-caf6/400/400', 1, 1, 244, 19, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Miel de Abeja Silvestre 350g', 'Miel pura de abeja silvestre recolectada en montañas de Turrialba.', 9800, 'CRC', 22, 'https://picsum.photos/seed/ct-caf7/400/400', 0, 1, 113, 17, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Chocolate 70% Cacao CR 100g', 'Tableta de chocolate oscuro elaborada con cacao costarricense, sin lecitina.', 5500, 'CRC', 45, 'https://picsum.photos/seed/ct-caf8/400/400', 1, 1, 176, 38, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Kit Degustación 4 Cafés', '4 muestras de 60g: Tarrazú, Naranjo, Tres Ríos y Orosi. El regalo perfecto.', 19000, 'CRC', 10, 'https://picsum.photos/seed/ct-caf9/400/400', 1, 1, 321, 14, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Vinagre de Piña 250ml', 'Vinagre natural fermentado de piña, excelente para ensaladas y marinadas.', 4200, 'CRC', 28, 'https://picsum.photos/seed/ct-caf10/400/400', 0, 1, 67, 11, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Café Volcán Poás Grano 250g', 'Cultivado a 1600 m en las faldas del Poás, perfil suave y dulce.', 7000, 'CRC', 18, 'https://picsum.photos/seed/ct-caf11/400/400', 0, 1, 88, 13, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Galletas de Café y Canela x12', 'Galletas artesanales con café Tarrazú y canela de Ceilán, caja de 12 unidades.', 5800, 'CRC', 30, 'https://picsum.photos/seed/ct-caf12/400/400', 0, 1, 54, 16, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Pasta de Maní y Café 250g', 'Mantequilla de maní con café tostado, sin azúcar, proteína natural.', 6200, 'CRC', 25, 'https://picsum.photos/seed/ct-caf13/400/400', 1, 1, 142, 24, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 1, 'Café Descafeinado Grano 250g', 'Proceso Swiss Water, sin químicos, todo el sabor sin cafeína.', 7800, 'CRC', 12, 'https://picsum.photos/seed/ct-caf14/400/400', 0, 1, 71, 9, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

INSERT INTO entrepreneur_products (user_id, category_id, name, description, price, currency, stock, image_1, featured, is_active, views_count, sales_count, accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email, shipping_available, created_at, updated_at)
SELECT u.id, 5, 'Cascara – Té de Hoja de Café 50g', 'Infusión tropical con notas de tamarindo e hibisco. Sin cafeína.', 3800, 'CRC', 40, 'https://picsum.photos/seed/ct-caf15/400/400', 0, 1, 49, 18, 1, 1, '88009876', 'cafético@paypal.com', 1, datetime('now'), datetime('now')
FROM users u WHERE u.email = 'cafe.test@compratica.com';

-- ── Verificación final ──────────────────────────────────────────────
SELECT u.name AS vendedora, COUNT(p.id) AS productos
FROM entrepreneur_products p
JOIN users u ON u.id = p.user_id
WHERE u.email IN ('marisol.test@compratica.com', 'cafe.test@compratica.com')
GROUP BY u.id;
