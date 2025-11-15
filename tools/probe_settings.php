<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

echo "== PROBE SETTINGS ==\n\n";

function table_has_column(PDO $pdo, $table, $col) {
  $st = $pdo->query("PRAGMA table_info($table)");
  $cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
  foreach ($cols as $c) if (strcasecmp($c['name'],$col)===0) return true;
  return false;
}

echo "DB file: ";
try {
  $stmt = $pdo->query("PRAGMA database_list");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo ($rows[0]['file'] ?? 'desconocido')."\n";
} catch (Throwable $e) { echo "err: ".$e->getMessage()."\n"; }

echo "\nSchema(settings):\n";
try {
  $st = $pdo->query("PRAGMA table_info(settings)");
  foreach ($st as $c) {
    echo " - {$c['name']} (type={$c['type']})\n";
  }
} catch (Throwable $e) { echo "err pragma: ".$e->getMessage()."\n"; }

echo "\nRows(settings):\n";
try {
  $st = $pdo->query("SELECT * FROM settings");
  $all = $st->fetchAll(PDO::FETCH_ASSOC);
  foreach ($all as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE)."\n";
  }
} catch (Throwable $e) { echo "err select: ".$e->getMessage()."\n"; }

$has_col = table_has_column($pdo, 'settings', 'sale_fee_crc');
echo "\nHas column sale_fee_crc? ".($has_col?'YES':'NO')."\n";

if ($has_col) {
  $val = $pdo->query("SELECT sale_fee_crc FROM settings WHERE sale_fee_crc IS NOT NULL ORDER BY id DESC LIMIT 1")->fetchColumn();
  echo "sale_fee_crc (by column) = ".var_export($val, true)."\n";
}

$has_kv = table_has_column($pdo, 'settings', 'key') && table_has_column($pdo, 'settings', 'val');
echo "Has key/val? ".($has_kv?'YES':'NO')."\n";
if ($has_kv) {
  $stmt = $pdo->prepare("SELECT val FROM settings WHERE key='sale_fee_crc' LIMIT 1");
  $stmt->execute();
  $kv = $stmt->fetchColumn();
  echo "sale_fee_crc (by key/val) = ".var_export($kv, true)."\n";
}

echo "\nDone.\n";
