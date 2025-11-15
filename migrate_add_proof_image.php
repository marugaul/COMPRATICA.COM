<?php
// migrate_add_proof_image.php  (ejecútalo 1 sola vez)
// Añade orders.proof_image (y updated_at si falta) en SQLite.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
$pdo = db();

function has_col(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->query("PRAGMA table_info($table)");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if (isset($c['name']) && strtolower($c['name']) === strtolower($col)) return true;
  }
  return false;
}

$done = [];

try {
  $pdo->beginTransaction();

  if (!has_col($pdo, 'orders', 'proof_image')) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN proof_image TEXT");
    $done[] = "orders.proof_image agregado";
  } else {
    $done[] = "orders.proof_image ya existía";
  }

  if (!has_col($pdo, 'orders', 'updated_at')) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN updated_at TEXT");
    $done[] = "orders.updated_at agregado";
  } else {
    $done[] = "orders.updated_at ya existía";
  }

  $pdo->commit();
  echo "OK:\n- " . implode("\n- ", $done);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo "ERROR: " . $e->getMessage();
}
