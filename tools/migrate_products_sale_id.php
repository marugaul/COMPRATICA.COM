<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

try {
  // ¿La columna ya existe?
  $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
  $has = false;
  foreach ($cols as $c) if ($c['name']==='sale_id') $has=true;

  if (!$has) {
    $pdo->exec("ALTER TABLE products ADD COLUMN sale_id INTEGER NULL");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_sale_id ON products(sale_id)");
    echo "OK: products.sale_id agregado\n";
  } else {
    echo "OK: products.sale_id ya existía\n";
  }

  // Backfill: mejor dejar NULL para que NO aparezcan en ningún espacio hasta asignarlos
  $pdo->exec("UPDATE products SET sale_id=NULL WHERE sale_id IS NULL");

  echo "Listo.\n";
} catch (Exception $e) {
  http_response_code(500);
  echo "ERROR: ".$e->getMessage()."\n";
}
