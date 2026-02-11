-- Crear categorías de Bienes Raíces para el marketplace
-- Categorías inspiradas en encuentra24.com
-- Se diferencian con el prefijo "BR:" para identificarlas como Bienes Raíces

INSERT INTO categories (name, icon, active, display_order) VALUES
-- CASAS
('BR: Casas en Venta', 'fa-home', 1, 100),
('BR: Casas en Alquiler', 'fa-home', 1, 101),

-- APARTAMENTOS
('BR: Apartamentos en Venta', 'fa-building', 1, 102),
('BR: Apartamentos en Alquiler', 'fa-building', 1, 103),

-- LOCALES COMERCIALES
('BR: Locales Comerciales en Venta', 'fa-store', 1, 104),
('BR: Locales Comerciales en Alquiler', 'fa-store', 1, 105),

-- OFICINAS
('BR: Oficinas en Venta', 'fa-briefcase', 1, 106),
('BR: Oficinas en Alquiler', 'fa-briefcase', 1, 107),

-- TERRENOS
('BR: Terrenos en Venta', 'fa-map', 1, 108),
('BR: Lotes en Venta', 'fa-map-marked-alt', 1, 109),

-- BODEGAS
('BR: Bodegas en Venta', 'fa-warehouse', 1, 110),
('BR: Bodegas en Alquiler', 'fa-warehouse', 1, 111),

-- QUINTAS Y FINCAS
('BR: Quintas en Venta', 'fa-tree', 1, 112),
('BR: Fincas en Venta', 'fa-tractor', 1, 113),

-- CONDOMINIOS
('BR: Condominios en Venta', 'fa-hotel', 1, 114),
('BR: Condominios en Alquiler', 'fa-hotel', 1, 115),

-- HABITACIONES (ALQUILERES COMPARTIDOS)
('BR: Habitaciones en Alquiler', 'fa-bed', 1, 116),

-- OTROS
('BR: Otros Bienes Raíces', 'fa-question-circle', 1, 117)
ON CONFLICT(name) DO NOTHING;
