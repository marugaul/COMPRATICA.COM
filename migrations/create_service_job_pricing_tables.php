<?php
// Script para crear tablas de planes separadas para SERVICIOS y EMPLEOS
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

try {
    echo "Creando tablas de planes para SERVICIOS y EMPLEOS...\n\n";

    // Verificar si las tablas ya existen
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

    // =====================
    // Tabla para SERVICIOS
    // =====================
    if (!in_array('service_pricing', $tables)) {
        echo "➕ Creando tabla service_pricing...\n";

        $pdo->exec("
            CREATE TABLE service_pricing (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              duration_days INTEGER NOT NULL,
              price_usd REAL NOT NULL,
              price_crc REAL NOT NULL,
              max_photos INTEGER DEFAULT 3,
              payment_methods TEXT DEFAULT 'sinpe,paypal',
              is_active INTEGER DEFAULT 1,
              is_featured INTEGER DEFAULT 0,
              description TEXT,
              display_order INTEGER DEFAULT 0,
              created_at TEXT DEFAULT (datetime('now')),
              updated_at TEXT DEFAULT (datetime('now'))
            )
        ");

        // Insertar planes por defecto para SERVICIOS
        $pdo->exec("
            INSERT INTO service_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order) VALUES
            ('Gratis 7 días', 7, 0.00, 0.00, 2, 'sinpe,paypal', 1, 0, 'Prueba gratis por 7 días', 1),
            ('Plan 30 días', 30, 0.75, 405.00, 4, 'sinpe,paypal', 1, 1, 'Publicación por 30 días', 2),
            ('Plan 60 días', 60, 1.25, 675.00, 6, 'sinpe,paypal', 1, 1, 'Publicación por 60 días - Ahorrá 20%', 3)
        ");

        echo "✅ Tabla service_pricing creada con 3 planes por defecto\n\n";
    } else {
        echo "ℹ️  La tabla service_pricing ya existe\n\n";
    }

    // ===================
    // Tabla para EMPLEOS
    // ===================
    if (!in_array('job_pricing', $tables)) {
        echo "➕ Creando tabla job_pricing...\n";

        $pdo->exec("
            CREATE TABLE job_pricing (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              name TEXT NOT NULL,
              duration_days INTEGER NOT NULL,
              price_usd REAL NOT NULL,
              price_crc REAL NOT NULL,
              max_photos INTEGER DEFAULT 3,
              payment_methods TEXT DEFAULT 'sinpe,paypal',
              is_active INTEGER DEFAULT 1,
              is_featured INTEGER DEFAULT 0,
              description TEXT,
              display_order INTEGER DEFAULT 0,
              created_at TEXT DEFAULT (datetime('now')),
              updated_at TEXT DEFAULT (datetime('now'))
            )
        ");

        // Insertar planes por defecto para EMPLEOS
        $pdo->exec("
            INSERT INTO job_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order) VALUES
            ('Gratis 14 días', 14, 0.00, 0.00, 2, 'sinpe,paypal', 1, 0, 'Prueba gratis por 14 días', 1),
            ('Plan 30 días', 30, 0.50, 270.00, 3, 'sinpe,paypal', 1, 1, 'Publicación por 30 días', 2),
            ('Plan 60 días', 60, 0.80, 432.00, 4, 'sinpe,paypal', 1, 1, 'Publicación por 60 días - Ahorrá 20%', 3)
        ");

        echo "✅ Tabla job_pricing creada con 3 planes por defecto\n\n";
    } else {
        echo "ℹ️  La tabla job_pricing ya existe\n\n";
    }

    echo "📊 Resumen de planes:\n\n";

    // Mostrar planes de SERVICIOS
    echo "=== SERVICIOS ===\n";
    $servicePlans = $pdo->query("SELECT id, name, duration_days, max_photos, price_usd, payment_methods FROM service_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($servicePlans as $plan) {
        printf("  • %s: %d días, %d fotos, $%.2f, métodos: %s\n",
            $plan['name'],
            $plan['duration_days'],
            $plan['max_photos'],
            $plan['price_usd'],
            $plan['payment_methods']
        );
    }

    echo "\n=== EMPLEOS ===\n";
    $jobPlans = $pdo->query("SELECT id, name, duration_days, max_photos, price_usd, payment_methods FROM job_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jobPlans as $plan) {
        printf("  • %s: %d días, %d fotos, $%.2f, métodos: %s\n",
            $plan['name'],
            $plan['duration_days'],
            $plan['max_photos'],
            $plan['price_usd'],
            $plan['payment_methods']
        );
    }

    echo "\n✅ Migración completada exitosamente\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
