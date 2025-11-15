<?php
// tools/migrate_add_proof_image_sale_fees.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/db.php';

function has_col(PDO $pdo, string $table, string $col): bool {
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

  // Verificar tabla sale_fees
  $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sale_fees'")->fetchColumn();
  if (!$exists) throw new RuntimeException("La tabla 'sale_fees' no existe en esta DB.");

  if (!has_col($pdo,'sale_fees','proof_image')) {
    $pdo->exec("ALTER TABLE sale_fees ADD COLUMN proof_image TEXT");
    echo "OK: sale_fees.proof_image agregado\n";
  } else {
    echo "OK: sale_fees.proof_image ya existía\n";
  }

  if (!has_col($pdo,'sale_fees','updated_at')) {
    $pdo->exec("ALTER TABLE sale_fees ADD COLUMN updated_at TEXT");
    echo "OK: sale_fees.updated_at agregado\n";
  } else {
    echo "OK: sale_fees.updated_at ya existía\n";
  }

  $pdo->commit();
  $pdo->exec("PRAGMA foreign_keys = ON;");
  echo "LISTO.\n";
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $pdo->exec("PRAGMA foreign_keys = ON;");
  http_response_code(500);
  echo "ERROR: ".$e->getMessage()."\n";
}
