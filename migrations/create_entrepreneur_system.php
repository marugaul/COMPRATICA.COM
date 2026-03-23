<?php
/**
 * Migración: Sistema de Emprendedoras
 * Crea tablas para productos, planes y categorías para emprendedoras
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Tabla de Planes de Suscripción para Emprendedoras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price_monthly REAL NOT NULL DEFAULT 0,
            price_annual REAL NOT NULL DEFAULT 0,
            max_products INTEGER DEFAULT -1,
            commission_rate REAL DEFAULT 0,
            features TEXT,
            is_active INTEGER DEFAULT 1,
            display_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        )
    ");

    // 2. Tabla de Suscripciones de Emprendedoras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            plan_id INTEGER NOT NULL,
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'pending', 'expired', 'cancelled')),
            payment_method TEXT CHECK(payment_method IN ('sinpe', 'paypal', 'card', NULL)),
            payment_id TEXT,
            payment_date TEXT,
            start_date TEXT NOT NULL,
            end_date TEXT,
            auto_renew INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES entrepreneur_plans(id)
        )
    ");

    // 3. Tabla de Categorías de Productos para Emprendedoras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            description TEXT,
            icon TEXT,
            image TEXT,
            parent_id INTEGER,
            display_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (parent_id) REFERENCES entrepreneur_categories(id)
        )
    ");

    // 4. Tabla de Productos de Emprendedoras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER,
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'CRC',
            stock INTEGER DEFAULT 0,
            sku TEXT,

            -- Imágenes
            image_1 TEXT,
            image_2 TEXT,
            image_3 TEXT,
            image_4 TEXT,
            image_5 TEXT,

            -- Dimensiones y peso
            weight_kg REAL,
            size_cm_length REAL,
            size_cm_width REAL,
            size_cm_height REAL,

            -- Métodos de pago aceptados
            accepts_sinpe INTEGER DEFAULT 1,
            accepts_paypal INTEGER DEFAULT 1,
            sinpe_phone TEXT,
            paypal_email TEXT,

            -- Información adicional
            featured INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            views_count INTEGER DEFAULT 0,
            sales_count INTEGER DEFAULT 0,

            -- Entrega
            shipping_available INTEGER DEFAULT 1,
            pickup_available INTEGER DEFAULT 1,
            pickup_location TEXT,

            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES entrepreneur_categories(id)
        )
    ");

    // 5. Tabla de Pedidos de Productos de Emprendedoras
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            buyer_user_id INTEGER,
            seller_user_id INTEGER NOT NULL,

            -- Información del comprador
            buyer_name TEXT NOT NULL,
            buyer_email TEXT NOT NULL,
            buyer_phone TEXT,

            -- Detalles del pedido
            quantity INTEGER NOT NULL DEFAULT 1,
            unit_price REAL NOT NULL,
            total_price REAL NOT NULL,
            currency TEXT NOT NULL DEFAULT 'CRC',

            -- Pago
            payment_method TEXT CHECK(payment_method IN ('sinpe', 'paypal')),
            payment_status TEXT DEFAULT 'pending' CHECK(payment_status IN ('pending', 'confirmed', 'failed', 'refunded')),
            payment_id TEXT,
            payment_date TEXT,
            payment_proof_image TEXT,

            -- Entrega
            delivery_method TEXT CHECK(delivery_method IN ('pickup', 'shipping')),
            delivery_address TEXT,
            delivery_status TEXT DEFAULT 'pending' CHECK(delivery_status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')),
            tracking_number TEXT,

            -- Estado general
            status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'confirmed', 'processing', 'completed', 'cancelled', 'refunded')),

            -- Notas
            buyer_notes TEXT,
            seller_notes TEXT,

            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),

            FOREIGN KEY (product_id) REFERENCES entrepreneur_products(id),
            FOREIGN KEY (buyer_user_id) REFERENCES users(id),
            FOREIGN KEY (seller_user_id) REFERENCES users(id)
        )
    ");

    // 6. Tabla de Reseñas de Productos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entrepreneur_product_reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            order_id INTEGER,
            user_id INTEGER,
            rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
            comment TEXT,
            is_verified_purchase INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (product_id) REFERENCES entrepreneur_products(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES entrepreneur_orders(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Crear índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_subscriptions_user ON entrepreneur_subscriptions(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_subscriptions_plan ON entrepreneur_subscriptions(plan_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_subscriptions_status ON entrepreneur_subscriptions(status)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_products_user ON entrepreneur_products(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_products_category ON entrepreneur_products(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_products_active ON entrepreneur_products(is_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_products_featured ON entrepreneur_products(featured)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_orders_product ON entrepreneur_orders(product_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_orders_buyer ON entrepreneur_orders(buyer_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_orders_seller ON entrepreneur_orders(seller_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_orders_status ON entrepreneur_orders(status)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_reviews_product ON entrepreneur_product_reviews(product_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_entrepreneur_reviews_user ON entrepreneur_product_reviews(user_id)");

    // Insertar planes por defecto
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO entrepreneur_plans (name, description, price_monthly, price_annual, max_products, commission_rate, features, display_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $plans = [
        [
            'Plan Gratuito',
            'Perfecto para comenzar',
            0,
            0,
            5,
            0.10,
            json_encode(['Hasta 5 productos', 'Comisión 10%', 'Pagos SINPE y PayPal', 'Soporte básico']),
            1
        ],
        [
            'Plan Básico',
            'Para emprendedoras en crecimiento',
            5000,
            50000,
            20,
            0.05,
            json_encode(['Hasta 20 productos', 'Comisión 5%', 'Pagos SINPE y PayPal', 'Productos destacados', 'Soporte prioritario']),
            2
        ],
        [
            'Plan Profesional',
            'Sin límites para tu negocio',
            10000,
            100000,
            -1,
            0.03,
            json_encode(['Productos ilimitados', 'Comisión 3%', 'Pagos SINPE y PayPal', 'Destacados ilimitados', 'Soporte VIP', 'Estadísticas avanzadas']),
            3
        ]
    ];

    foreach ($plans as $plan) {
        $stmt->execute($plan);
    }

    // Insertar categorías por defecto
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO entrepreneur_categories (name, slug, description, icon, display_order)
        VALUES (?, ?, ?, ?, ?)
    ");

    $categories = [
        ['Café de Costa Rica', 'cafe-costa-rica', 'Café costarricense premium', 'fa-coffee', 1],
        ['Joyería', 'joyeria', 'Accesorios y joyería artesanal', 'fa-gem', 2],
        ['Artesanías', 'artesanias', 'Productos artesanales hechos a mano', 'fa-hands', 3],
        ['Ropa y Moda', 'ropa-moda', 'Ropa, accesorios y moda', 'fa-tshirt', 4],
        ['Alimentos y Bebidas', 'alimentos-bebidas', 'Productos comestibles artesanales', 'fa-utensils', 5],
        ['Belleza y Cuidado Personal', 'belleza-cuidado', 'Productos de belleza naturales', 'fa-spa', 6],
        ['Hogar y Decoración', 'hogar-decoracion', 'Decoración y artículos para el hogar', 'fa-home', 7],
        ['Arte', 'arte', 'Pinturas, ilustraciones y arte', 'fa-palette', 8],
        ['Libros y Papelería', 'libros-papeleria', 'Libros, cuadernos y papelería', 'fa-book', 9],
        ['Tecnología', 'tecnologia', 'Accesorios tecnológicos', 'fa-laptop', 10],
        ['Juguetes', 'juguetes', 'Juguetes educativos y artesanales', 'fa-puzzle-piece', 11],
        ['Plantas', 'plantas', 'Plantas y productos de jardinería', 'fa-leaf', 12],
        ['Mascotas', 'mascotas', 'Productos para mascotas', 'fa-paw', 13],
        ['Deportes', 'deportes', 'Artículos deportivos', 'fa-running', 14],
        ['Otros', 'otros', 'Otros productos', 'fa-box', 15]
    ];

    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }

    $pdo->commit();

    echo "✅ Sistema de Emprendedoras creado exitosamente!\n\n";
    echo "📊 Tablas creadas:\n";
    echo "  - entrepreneur_plans (planes de suscripción)\n";
    echo "  - entrepreneur_subscriptions (suscripciones)\n";
    echo "  - entrepreneur_categories (categorías de productos)\n";
    echo "  - entrepreneur_products (productos)\n";
    echo "  - entrepreneur_orders (pedidos)\n";
    echo "  - entrepreneur_product_reviews (reseñas)\n\n";

    // Mostrar planes creados
    echo "📦 Planes creados:\n";
    $plans = $pdo->query("SELECT * FROM entrepreneur_plans ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($plans as $plan) {
        echo "  - {$plan['name']}: ₡" . number_format($plan['price_monthly']) . "/mes\n";
    }

    echo "\n📂 Categorías creadas:\n";
    $cats = $pdo->query("SELECT * FROM entrepreneur_categories ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as $cat) {
        echo "  - {$cat['name']}\n";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
