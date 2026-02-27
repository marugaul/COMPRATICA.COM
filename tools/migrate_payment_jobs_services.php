<?php
// tools/migrate_payment_jobs_services.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/db.php';

function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
  return (bool)$st->fetchColumn();
}

function has_col(PDO $pdo, string $table, string $col): bool {
  if (!table_exists($pdo, $table)) return false;
  $st = $pdo->query("PRAGMA table_info($table)");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if (isset($c['name']) && strtolower($c['name']) === strtolower($col)) return true;
  }
  return false;
}

header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

try {
  $pdo->exec("PRAGMA foreign_keys = OFF;");
  $pdo->beginTransaction();

  // ======================================
  // 1. Agregar columnas a job_listings
  // ======================================
  if (table_exists($pdo, 'job_listings')) {
    if (!has_col($pdo, 'job_listings', 'pricing_plan_id')) {
      $pdo->exec("ALTER TABLE job_listings ADD COLUMN pricing_plan_id INTEGER");
      echo "✓ job_listings.pricing_plan_id agregado\n";
    } else {
      echo "✓ job_listings.pricing_plan_id ya existe\n";
    }

    if (!has_col($pdo, 'job_listings', 'payment_status')) {
      $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_status TEXT DEFAULT 'pending'");
      echo "✓ job_listings.payment_status agregado\n";
    } else {
      echo "✓ job_listings.payment_status ya existe\n";
    }

    if (!has_col($pdo, 'job_listings', 'payment_id')) {
      $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_id TEXT");
      echo "✓ job_listings.payment_id agregado\n";
    } else {
      echo "✓ job_listings.payment_id ya existe\n";
    }

    if (!has_col($pdo, 'job_listings', 'payment_date')) {
      $pdo->exec("ALTER TABLE job_listings ADD COLUMN payment_date TEXT");
      echo "✓ job_listings.payment_date agregado\n";
    } else {
      echo "✓ job_listings.payment_date ya existe\n";
    }
  } else {
    echo "⚠ job_listings no existe\n";
  }

  // ======================================
  // 2. Crear tabla job_pricing
  // ======================================
  if (!table_exists($pdo, 'job_pricing')) {
    $pdo->exec("
      CREATE TABLE job_pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        duration_days INTEGER NOT NULL,
        price_usd REAL DEFAULT 0,
        price_crc REAL DEFAULT 0,
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
    echo "✓ Tabla job_pricing creada\n";

    // Insertar planes por defecto
    $pdo->exec("
      INSERT INTO job_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order)
      VALUES
        ('Gratis 7 días', 7, 0.00, 0, 3, 'sinpe,paypal', 1, 0, 'Publicación gratuita por 7 días con hasta 3 fotos', 1),
        ('Plan 30 días', 30, 1.00, 540, 5, 'sinpe,paypal', 1, 0, 'Publicación destacada por 30 días con hasta 5 fotos', 2),
        ('Plan 90 días', 90, 2.00, 1080, 8, 'sinpe,paypal', 1, 1, 'Máxima visibilidad por 90 días con hasta 8 fotos', 3)
    ");
    echo "✓ Planes de job_pricing insertados\n";
  } else {
    echo "✓ job_pricing ya existe\n";
  }

  // ======================================
  // 3. Crear tabla service_pricing
  // ======================================
  if (!table_exists($pdo, 'service_pricing')) {
    $pdo->exec("
      CREATE TABLE service_pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        duration_days INTEGER NOT NULL,
        price_usd REAL DEFAULT 0,
        price_crc REAL DEFAULT 0,
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
    echo "✓ Tabla service_pricing creada\n";

    // Insertar planes por defecto
    $pdo->exec("
      INSERT INTO service_pricing (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order)
      VALUES
        ('Gratis 7 días', 7, 0.00, 0, 3, 'sinpe,paypal', 1, 0, 'Publicación gratuita por 7 días con hasta 3 fotos', 1),
        ('Plan 30 días', 30, 1.00, 540, 5, 'sinpe,paypal', 1, 0, 'Publicación destacada por 30 días con hasta 5 fotos', 2),
        ('Plan 90 días', 90, 2.00, 1080, 8, 'sinpe,paypal', 1, 1, 'Máxima visibilidad por 90 días con hasta 8 fotos', 3)
    ");
    echo "✓ Planes de service_pricing insertados\n";
  } else {
    echo "✓ service_pricing ya existe\n";
  }

  // ======================================
  // 4. Crear índices
  // ======================================
  if (table_exists($pdo, 'job_listings')) {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_pricing_plan ON job_listings(pricing_plan_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_listings_payment_status ON job_listings(payment_status)");
    echo "✓ Índices de job_listings creados\n";
  }

  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_job_pricing_active ON job_pricing(is_active)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_service_pricing_active ON service_pricing(is_active)");
  echo "✓ Índices de pricing creados\n";

  $pdo->commit();
  $pdo->exec("PRAGMA foreign_keys = ON;");
  echo "\n✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $pdo->exec("PRAGMA foreign_keys = ON;");
  http_response_code(500);
  echo "\n❌ ERROR: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString() . "\n";
}
