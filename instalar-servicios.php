<?php
/**
 * Script de instalación del módulo de Servicios Profesionales
 * Ejecutar una vez para crear las tablas y categorías necesarias.
 * Comparte autenticación con real_estate_agents (bienes raíces).
 */

require_once __DIR__ . '/includes/db.php';

echo "=== INSTALACIÓN MÓDULO DE SERVICIOS PROFESIONALES ===\n\n";

try {
    $pdo = db();
    $pdo->beginTransaction();

    echo "1. Creando categorías de Servicios...\n";

    $categories = [
        ['SERV: Abogados y Servicios Legales',  'fa-gavel',              200],
        ['SERV: Contabilidad y Finanzas',        'fa-calculator',         201],
        ['SERV: Mantenimiento del Hogar',        'fa-tools',              202],
        ['SERV: Plomería y Electricidad',        'fa-plug',               203],
        ['SERV: Limpieza del Hogar',             'fa-broom',              204],
        ['SERV: Shuttle y Transporte',           'fa-shuttle-van',        205],
        ['SERV: Fletes y Mudanzas',              'fa-truck',              206],
        ['SERV: Tutorías y Clases',              'fa-chalkboard-teacher', 207],
        ['SERV: Diseño y Creatividad',           'fa-paint-brush',        208],
        ['SERV: Tecnología y Sistemas',          'fa-laptop-code',        209],
        ['SERV: Salud y Bienestar',              'fa-heartbeat',          210],
        ['SERV: Fotografía y Video',             'fa-camera',             211],
        ['SERV: Eventos y Catering',             'fa-glass-cheers',       212],
        ['SERV: Jardinería y Zonas Verdes',      'fa-leaf',               213],
        ['SERV: Seguridad y Vigilancia',         'fa-shield-alt',         214],
        ['SERV: Otros Servicios',                'fa-concierge-bell',     215],
    ];

    $stmtCat = $pdo->prepare("INSERT OR IGNORE INTO categories (name, icon, active, display_order) VALUES (?, ?, 1, ?)");
    foreach ($categories as $cat) {
        $stmtCat->execute($cat);
        echo "   OK Categoria: {$cat[0]}\n";
    }

    echo "\n2. Verificando tabla listing_pricing (compartida)...\n";
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

    $count = $pdo->query("SELECT COUNT(*) FROM listing_pricing")->fetchColumn();
    if ((int)$count === 0) {
        $pdo->exec("
            INSERT INTO listing_pricing (name, duration_days, price_usd, price_crc, is_active, is_featured, description, display_order) VALUES
            ('Gratis 7 días', 7, 0.00, 0.00, 1, 0, 'Prueba gratis por 7 días', 1),
            ('Plan 30 días', 30, 1.00, 540.00, 1, 1, 'Publicación por 30 días', 2),
            ('Plan 90 días', 90, 2.00, 1080.00, 1, 1, 'Publicación por 90 días - Ahorrá 33%', 3)
        ");
        echo "   OK Planes de precios insertados.\n";
    } else {
        echo "   OK Planes de precios ya existen ({$count}).\n";
    }

    echo "\n3. Creando tabla service_listings...\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_listings (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          agent_id INTEGER NOT NULL,
          category_id INTEGER NOT NULL,
          title TEXT NOT NULL,
          description TEXT,
          service_type TEXT DEFAULT 'presencial',
          price_from REAL DEFAULT 0,
          price_to REAL DEFAULT 0,
          price_type TEXT DEFAULT 'hora',
          currency TEXT DEFAULT 'CRC',
          province TEXT,
          canton TEXT,
          district TEXT,
          location_description TEXT,
          experience_years INTEGER DEFAULT 0,
          skills TEXT,
          availability TEXT,
          images TEXT,
          contact_name TEXT,
          contact_phone TEXT,
          contact_email TEXT,
          contact_whatsapp TEXT,
          website TEXT,
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
          FOREIGN KEY (agent_id) REFERENCES real_estate_agents(id),
          FOREIGN KEY (category_id) REFERENCES categories(id),
          FOREIGN KEY (pricing_plan_id) REFERENCES listing_pricing(id)
        )
    ");
    echo "   OK Tabla service_listings creada.\n";

    echo "\n4. Creando índices...\n";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_agent    ON service_listings(agent_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_category ON service_listings(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_active   ON service_listings(is_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_dates    ON service_listings(start_date, end_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_province ON service_listings(province)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_payment  ON service_listings(payment_status)");
    echo "   OK Índices creados.\n";

    $pdo->commit();
    echo "\n=== INSTALACION COMPLETADA EXITOSAMENTE ===\n";
    echo "Ya podés acceder al dashboard en: /services/login.php\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
