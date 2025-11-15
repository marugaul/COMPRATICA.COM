<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();

header('Content-Type: text/plain; charset=utf-8');

try {
  // Completar affiliate_id de orders a partir de products
  $sql = "UPDATE orders
          SET affiliate_id = (
            SELECT p.affiliate_id FROM products p WHERE p.id = orders.product_id
          )
          WHERE (affiliate_id IS NULL OR affiliate_id = 0)";
  $aff_updated = $pdo->exec($sql);
  echo "Backfill OK. Orders con affiliate_id completado: {$aff_updated}\n";
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: " . $e->getMessage() . "\n";
}
