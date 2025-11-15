<?php
// UTF-8 sin BOM
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

try {
  echo "== MIGRACION orders.updated_at ==\n";
  // ¿ya existe?
  $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
  $has = false;
  foreach ($cols as $c) {
    if (strcasecmp($c['name'], 'updated_at') === 0) { $has = true; break; }
  }

  if ($has) {
    echo "OK: orders.updated_at ya existia.\n";
  } else {
    // agregar columna (TEXT NULL) y rellenar con la fecha actual para registros existentes
    $pdo->exec("ALTER TABLE orders ADD COLUMN updated_at TEXT NULL;");
    $pdo->exec("UPDATE orders SET updated_at = datetime('now') WHERE updated_at IS NULL;");
    echo "OK: Columna agregada y poblada.\n";
  }
  echo "LISTO.\n";
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
