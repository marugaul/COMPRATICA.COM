-- Crear tabla de categorías para productos
-- Permite gestión centralizada de categorías del marketplace

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  icon TEXT DEFAULT NULL,
  active INTEGER DEFAULT 1,
  display_order INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now'))
);

-- Insertar categorías iniciales para venta de garaje
INSERT INTO categories (name, icon, display_order) VALUES
('Ropa y Accesorios', 'fa-tshirt', 1),
('Electrónica', 'fa-laptop', 2),
('Hogar y Decoración', 'fa-couch', 3),
('Libros y Revistas', 'fa-book', 4),
('Juguetes y Juegos', 'fa-gamepad', 5),
('Deportes y Fitness', 'fa-dumbbell', 6),
('Muebles', 'fa-chair', 7),
('Electrodomésticos', 'fa-blender', 8),
('Herramientas', 'fa-wrench', 9),
('Bebés y Niños', 'fa-baby-carriage', 10),
('Belleza y Salud', 'fa-spa', 11),
('Vehículos y Accesorios', 'fa-car', 12),
('Arte y Manualidades', 'fa-palette', 13),
('Música e Instrumentos', 'fa-music', 14),
('Otros', 'fa-box', 15);
