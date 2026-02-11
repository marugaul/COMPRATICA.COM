<?php
/**
 * Script de instalación del módulo de Bienes Raíces
 * Ejecuta este archivo una vez para crear las tablas y categorías necesarias
 */

require_once __DIR__ . '/includes/db.php';

echo "=== INSTALACIÓN MÓDULO DE BIENES RAÍCES ===\n\n";

try {
    $pdo = db();
    $pdo->beginTransaction();

    echo "1. Creando categorías de Bienes Raíces...\n";

    // Categorías de Bienes Raíces
    $categories = [
        ['BR: Casas en Venta', 'fa-home', 100],
        ['BR: Casas en Alquiler', 'fa-home', 101],
        ['BR: Apartamentos en Venta', 'fa-building', 102],
        ['BR: Apartamentos en Alquiler', 'fa-building', 103],
        ['BR: Locales Comerciales en Venta', 'fa-store', 104],
        ['BR: Locales Comerciales en Alquiler', 'fa-store', 105],
        ['BR: Oficinas en Venta', 'fa-briefcase', 106],
        ['BR: Oficinas en Alquiler', 'fa-briefcase', 107],
        ['BR: Terrenos en Venta', 'fa-map', 108],
        ['BR: Lotes en Venta', 'fa-map-marked-alt', 109],
        ['BR: Bodegas en Venta', 'fa-warehouse', 110],
        ['BR: Bodegas en Alquiler', 'fa-warehouse', 111],
        ['BR: Quintas en Venta', 'fa-tree', 112],
        ['BR: Fincas en Venta', 'fa-tractor', 113],
        ['BR: Condominios en Venta', 'fa-hotel', 114],
        ['BR: Condominios en Alquiler', 'fa-hotel', 115],
        ['BR: Habitaciones en Alquiler', 'fa-bed', 116],
        ['BR: Otros Bienes Raíces', 'fa-question-circle', 117]
    ];

    $stmtCat = $pdo->prepare("INSERT OR IGNORE INTO categories (name, icon, active, display_order) VALUES (?, ?, 1, ?)");
    foreach ($categories as $cat) {
        $stmtCat->execute($cat);
        echo "   ✓ Categoría creada: {$cat[0]}\n";
    }

    echo "\n2. Creando tabla de precios de publicaciones...\n";

    // Tabla de precios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS listing_pricing (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL,
          duration_days INTEGER NOT NULL,
          price_usd REAL NOT NULL,
          price_crc REAL NOT NULL,
          is_active INTEGER DEFAULT 1,
          is_featured INTEGER DEFAULT 0,
          description TEXT,
          display_order INTEGER DEFAULT 0,
          created_at TEXT DEFAULT (datetime('now')),
          updated_at TEXT DEFAULT (datetime('now'))
        )
    ");
    echo "   ✓ Tabla listing_pricing creada\n";

    echo "\n3. Insertando planes de precios...\n";

    // Planes de precios
    $prices = [
        ['Gratis 7 días', 7, 0.00, 0.00, 1, 0, 'Prueba gratis por 7 días', 1],
        ['Plan 30 días', 30, 1.00, 540.00, 1, 1, 'Publicación por 30 días', 2],
        ['Plan 90 días', 90, 2.00, 1080.00, 1, 1, 'Publicación por 90 días - Ahorrá 33%', 3]
    ];

    $stmtPrice = $pdo->prepare("INSERT OR IGNORE INTO listing_pricing (name, duration_days, price_usd, price_crc, is_active, is_featured, description, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($prices as $price) {
        $stmtPrice->execute($price);
        echo "   ✓ Plan creado: {$price[0]} - \${$price[2]} USD / ₡{$price[3]}\n";
    }

    echo "\n4. Creando tabla de publicaciones de bienes raíces...\n";

    // Tabla de publicaciones
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS real_estate_listings (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          category_id INTEGER NOT NULL,
          title TEXT NOT NULL,
          description TEXT,
          price REAL NOT NULL,
          currency TEXT DEFAULT 'CRC',
          location TEXT,
          province TEXT,
          canton TEXT,
          district TEXT,
          bedrooms INTEGER DEFAULT 0,
          bathrooms INTEGER DEFAULT 0,
          area_m2 REAL DEFAULT 0,
          parking_spaces INTEGER DEFAULT 0,
          features TEXT,
          images TEXT,
          contact_name TEXT,
          contact_phone TEXT,
          contact_email TEXT,
          contact_whatsapp TEXT,
          listing_type TEXT DEFAULT 'sale',
          pricing_plan_id INTEGER NOT NULL,
          is_active INTEGER DEFAULT 1,
          is_featured INTEGER DEFAULT 0,
          views_count INTEGER DEFAULT 0,
          start_date TEXT,
          end_date TEXT,
          payment_status TEXT DEFAULT 'pending',
          payment_id TEXT,
          created_at TEXT DEFAULT (datetime('now')),
          updated_at TEXT DEFAULT (datetime('now')),
          FOREIGN KEY (user_id) REFERENCES users(id),
          FOREIGN KEY (category_id) REFERENCES categories(id),
          FOREIGN KEY (pricing_plan_id) REFERENCES listing_pricing(id)
        )
    ");
    echo "   ✓ Tabla real_estate_listings creada\n";

    echo "\n5. Creando índices para mejorar el rendimiento...\n";

    // Índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_real_estate_user ON real_estate_listings(user_id)");
    echo "   ✓ Índice idx_real_estate_user creado\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_real_estate_category ON real_estate_listings(category_id)");
    echo "   ✓ Índice idx_real_estate_category creado\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_real_estate_active ON real_estate_listings(is_active)");
    echo "   ✓ Índice idx_real_estate_active creado\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_real_estate_dates ON real_estate_listings(start_date, end_date)");
    echo "   ✓ Índice idx_real_estate_dates creado\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_real_estate_location ON real_estate_listings(province, canton)");
    echo "   ✓ Índice idx_real_estate_location creado\n";

    $pdo->commit();

    echo "\n✅ INSTALACIÓN COMPLETADA EXITOSAMENTE\n\n";
    echo "El módulo de Bienes Raíces ha sido instalado correctamente.\n";
    echo "Puedes acceder a la página en: https://compratica.com/bienes-raices\n\n";
    echo "IMPORTANTE: Elimina este archivo (instalar-bienes-raices.php) después de ejecutarlo.\n\n";

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR EN LA INSTALACIÓN\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nPor favor, contacta al desarrollador.\n\n";
    exit(1);
}
