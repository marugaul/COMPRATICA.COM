<?php
// tools/fix_affiliates_add_columns.php
// Arregla la tabla affiliates agregando columnas faltantes (phone, pass_hash, created_at, updated_at)

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
$pdo = db();

function hasColumn(PDO $pdo, $table, $col) {
  $st = $pdo->query("PRAGMA table_info($table)");
  $cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
  foreach ($cols as $c) {
    if (isset($c['name']) && strtolower($c['name']) === strtolower($col)) return true;
  }
  return false;
}

try {
  echo "== FIX affiliates (add missing columns) ==\n";
  $pdo->exec("PRAGMA foreign_keys = OFF;");

  $missing = [];
  foreach (['phone','pass_hash','created_at','updated_at'] as $col) {
    if (!hasColumn($pdo, 'affiliates', $col)) $missing[] = $col;
  }

  if (empty($missing)) {
    echo "OK: affiliates ya tiene todas las columnas necesarias.\n";
  } else {
    foreach ($missing as $col) {
      switch ($col) {
        case 'phone':
          $pdo->exec("ALTER TABLE affiliates ADD COLUMN phone TEXT NULL");
          echo "ADD: phone\n";
          break;
        case 'pass_hash':
          $pdo->exec("ALTER TABLE affiliates ADD COLUMN pass_hash TEXT NULL");
          echo "ADD: pass_hash\n";
          break;
        case 'created_at':
          $pdo->exec("ALTER TABLE affiliates ADD COLUMN created_at TEXT NULL");
          echo "ADD: created_at\n";
          break;
        case 'updated_at':
          $pdo->exec("ALTER TABLE affiliates ADD COLUMN updated_at TEXT NULL");
          echo "ADD: updated_at\n";
          break;
      }
    }
    echo "Listo: columnas agregadas.\n";
  }

  // Inicializa created_at/updated_at si quedaron NULL
  $pdo->exec("UPDATE affiliates SET created_at = COALESCE(created_at, datetime('now')), updated_at = COALESCE(updated_at, datetime('now'))");
  echo "OK: timestamps inicializados.\n";

  $pdo->exec("PRAGMA foreign_keys = ON;");
  echo "FIN.\n";
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: " . $e->getMessage() . "\n";
}
