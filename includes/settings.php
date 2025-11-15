<?php
require_once __DIR__ . '/db.php';

function table_has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->query("PRAGMA table_info($table)");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
      if (isset($c['name']) && strtolower($c['name']) === strtolower($col)) return true;
    }
  } catch (Throwable $e) {}
  return false;
}

function table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}

/**
 * get_setting('SALE_FEE_CRC', 2000)
 * Soporta:
 *  - Tabla KV: settings(key TEXT PRIMARY KEY, val TEXT)
 *  - Tabla “id=1”: settings(id INTEGER CHECK(id=1), sale_fee_crc REAL, exchange_rate REAL, ...)
 */
function get_setting($key, $default=null){
  $pdo = db();
  $key_lc = strtolower(trim((string)$key));

  // 1) Intentar esquema KV (key/val)
  if (table_exists($pdo, 'settings') && table_has_column($pdo, 'settings', 'key') && table_has_column($pdo, 'settings', 'val')) {
    try {
      $st = $pdo->prepare("SELECT val FROM settings WHERE key=? LIMIT 1");
      $st->execute([$key]);
      $v = $st->fetchColumn();
      if ($v !== false && $v !== null) return is_numeric($v) ? 0 + $v : $v;
    } catch (Throwable $e) { /* continúa al 2) */ }
  }

  // 2) Intentar esquema “id=1” con columna homónima
  //    Mapear 'SALE_FEE_CRC' -> 'sale_fee_crc', etc.
  $col = preg_replace('/[^a-z0-9_]+/', '_', strtolower($key));
  if (table_exists($pdo, 'settings') && table_has_column($pdo, 'settings', $col) && table_has_column($pdo, 'settings', 'id')) {
    try {
      $st = $pdo->query("SELECT $col AS v FROM settings WHERE id=1");
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row && $row['v'] !== null) return is_numeric($row['v']) ? 0 + $row['v'] : $row['v'];
    } catch (Throwable $e) { /* fallback */ }
  }

  return $default;
}

function set_setting($key, $val){
  $pdo = db();
  // Preferir esquema KV si existe
  if (table_exists($pdo, 'settings') && table_has_column($pdo, 'settings', 'key') && table_has_column($pdo, 'settings', 'val')) {
    $st = $pdo->prepare("INSERT INTO settings(key,val) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET val=excluded.val");
    return $st->execute([$key, $val]);
  }
  // Si no, intentar esquema id=1 con columna homónima
  $col = preg_replace('/[^a-z0-9_]+/', '_', strtolower($key));
  if (table_exists($pdo, 'settings') && table_has_column($pdo, 'settings', $col) && table_has_column($pdo, 'settings', 'id')) {
    $st = $pdo->prepare("UPDATE settings SET $col=? WHERE id=1");
    return $st->execute([$val]);
  }
  // Si no existe ninguno, crear esquema KV minimal y guardar ahí
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, val TEXT)");
    $st = $pdo->prepare("INSERT INTO settings(key,val) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET val=excluded.val");
    return $st->execute([$key, $val]);
  } catch (Throwable $e) { return false; }
}
