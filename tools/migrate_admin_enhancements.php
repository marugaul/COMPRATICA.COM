<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

try {
  // Evitar sorpresas con claves foráneas en alter
  $pdo->exec("PRAGMA foreign_keys = OFF;");

  echo "== MIGRACION ADMIN ENHANCEMENTS v2 (compat)==\n";

  // 1) SETTINGS: agregar columna sale_fee_crc si no existe (sin insertar filas nuevas)
  $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
  $hasSaleFee = false;
  foreach ($cols as $c) {
    if ($c['name']==='sale_fee_crc') { $hasSaleFee = true; break; }
  }
  if (!$hasSaleFee) {
    $pdo->exec("ALTER TABLE settings ADD COLUMN sale_fee_crc INTEGER NULL");
    echo "OK: settings.sale_fee_crc agregado\n";
  } else {
    echo "OK: settings.sale_fee_crc ya existía\n";
  }

  // Inicializa si está NULL (no insertamos filas nuevas)
  $pdo->exec("UPDATE settings SET sale_fee_crc=2000 WHERE sale_fee_crc IS NULL");
  echo "OK: settings.sale_fee_crc inicializado (2000) cuando estaba NULL\n";

  // 2) Índices útiles (idempotentes)
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_sale_id ON products(sale_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_aff ON sales(affiliate_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sale_fees_sale ON sale_fees(sale_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_aff_sale ON orders(affiliate_id, sale_id)");
  echo "OK: Índices creados/ya existentes\n";

  // 3) Afiliados (por si no existiera)
  $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliates'")->fetchColumn();
  if (!$exists) {
    $pdo->exec("CREATE TABLE affiliates (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT, email TEXT UNIQUE, phone TEXT, pass_hash TEXT,
      created_at TEXT, updated_at TEXT
    )");
    echo "OK: tabla affiliates creada\n";
  } else {
    echo "OK: tabla affiliates ya existía\n";
  }

  // 4) Activar claves foráneas de nuevo
  $pdo->exec("PRAGMA foreign_keys = ON;");

  echo "LISTO.\n";
} catch (Exception $e) {
  http_response_code(500);
  echo "ERROR: ".$e->getMessage()."\n";
}
