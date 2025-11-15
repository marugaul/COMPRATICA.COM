<?php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../includes/db.php';
$ref = __DIR__ . '/../data.sqlite'; // ruta que usa includes/db.php
$pdo = db();
$real = realpath($ref);
echo "<h1>Probe DB</h1>";
echo "<p><strong>data.sqlite (según includes/db.php):</strong> $ref</p>";
echo "<p><strong>realpath:</strong> $real</p>";

try {
  $c = $pdo->query("SELECT COUNT(*) c FROM products")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
  echo "<p><strong>products COUNT:</strong> $c</p>";
  $rows = $pdo->query("SELECT id,name,image,currency,active,created_at FROM products ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
  echo "<pre>"; var_dump($rows); echo "</pre>";
} catch(Throwable $e){
  echo "<p style='color:red'>ERROR: ".$e->getMessage()."</p>";
}