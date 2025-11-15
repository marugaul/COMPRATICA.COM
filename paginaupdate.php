<?php
require_once __DIR__.'/includes/db.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
$st = $pdo->prepare("INSERT OR REPLACE INTO settings (k,v) VALUES ('shipping_cost_crc', ?)");
$st->execute(['2500']); // <-- pon aquí el valor en colones
echo "OK";
