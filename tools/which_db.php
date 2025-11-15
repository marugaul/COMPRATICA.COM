<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

echo "== WHICH DB ==\n\n";

try {
  $stmt = $pdo->query("PRAGMA database_list");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    echo "DB[".$r['seq']."] name=".$r['name']." file=".$r['file']."\n";
  }
} catch (Throwable $e) {
  echo "ERROR PRAGMA database_list: ".$e->getMessage()."\n";
}

echo "\nTables:\n";
try {
  $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
  foreach ($stmt as $t) echo " - ".$t['name']."\n";
} catch (Throwable $e) {
  echo "ERROR tables: ".$e->getMessage()."\n";
}
