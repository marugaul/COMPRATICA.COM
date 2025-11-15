<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

if (!function_exists('get_shipping_cost')) {
  function get_shipping_cost() {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $st = $pdo->prepare("SELECT v FROM settings WHERE k='shipping_cost_crc' LIMIT 1");
    $st->execute();
    $v = $st->fetchColumn();
    if ($v === false) {
      $v = '2000';
      $pdo->prepare("INSERT OR REPLACE INTO settings (k,v) VALUES ('shipping_cost_crc', ?)")->execute([$v]);
    }
    return (int)$v;
  }
}
?>