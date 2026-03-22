<?php
// Archivo de diagnóstico temporal - BORRAR DESPUÉS
ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
$pdo = db();
$aff_id = (int)($_SESSION['aff_id'] ?? 0);

// Tomar el primer pedido del afiliado
$ord = $pdo->prepare("
  SELECT o.id, o.order_number, o.status, o.affiliate_id, o.product_id,
         p.sale_id, s.affiliate_id AS sale_aff_id
  FROM orders o
  JOIN products p ON p.id = o.product_id
  LEFT JOIN sales s ON s.id = p.sale_id
  WHERE (s.affiliate_id = ? OR o.affiliate_id = ?)
  ORDER BY o.id DESC LIMIT 1
");
$ord->execute([$aff_id, $aff_id]);
$row = $ord->fetch(PDO::FETCH_ASSOC);

// Intentar update en ese pedido
$update_result = null;
$update_error  = null;
if ($row) {
    try {
        $test_status = ($row['status'] === 'Pendiente') ? 'Empacado' : 'Pendiente';
        $upd = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $upd->execute([$test_status, $row['id']]);
        $affected = $upd->rowCount();
        // Revertir
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$row['status'], $row['id']]);
        $update_result = "OK - rows_affected={$affected} - test_status={$test_status}";
    } catch (Throwable $e) {
        $update_error = $e->getMessage();
    }
}

echo json_encode([
    'session_aff_id' => $aff_id,
    'order_found'    => $row ?: null,
    'update_test'    => $update_result,
    'update_error'   => $update_error,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
