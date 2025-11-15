<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS affiliate_payment_methods (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  affiliate_id INTEGER NOT NULL,
  sinpe_phone TEXT,
  paypal_email TEXT,
  active_sinpe INTEGER DEFAULT 0,
  active_paypal INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now','localtime')),
  updated_at TEXT DEFAULT (datetime('now','localtime'))
);
");

echo "OK: Tabla affiliate_payment_methods creada o ya existía\n";
