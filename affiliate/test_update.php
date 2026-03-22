<?php
// PAGINA DE PRUEBA - BORRAR DESPUES
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$aff_id = (int)($_SESSION['aff_id'] ?? 0);
$result = [];

// Si viene POST, ejecutar el UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id  = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');

    // Paso 1: buscar sin filtro
    $st = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.affiliate_id, o.product_id, p.sale_id, s.affiliate_id AS sale_aff FROM orders o JOIN products p ON p.id=o.product_id LEFT JOIN sales s ON s.id=p.sale_id WHERE o.id=? LIMIT 1");
    $st->execute([$order_id]);
    $raw = $st->fetch(PDO::FETCH_ASSOC);
    $result['1_raw_order'] = $raw ?: 'NO ENCONTRADO';

    // Paso 2: buscar CON filtro de afiliado
    $st2 = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.affiliate_id, s.affiliate_id AS sale_aff FROM orders o JOIN products p ON p.id=o.product_id LEFT JOIN sales s ON s.id=p.sale_id WHERE o.id=? AND (s.affiliate_id=? OR o.affiliate_id=?) LIMIT 1");
    $st2->execute([$order_id, $aff_id, $aff_id]);
    $filtered = $st2->fetch(PDO::FETCH_ASSOC);
    $result['2_con_filtro_aff'] = $filtered ?: 'NO ENCONTRADO (por eso falla)';

    // Paso 3: si se encontró, hacer el UPDATE
    if ($filtered && $new_status) {
        $upd = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $upd->execute([$new_status, $order_id]);
        $result['3_update_rows'] = $upd->rowCount();
        $result['3_update_ok'] = $upd->rowCount() > 0 ? 'ACTUALIZADO ✓' : 'CERO FILAS - no cambió';
    }
}

// Listar primeras 5 ordenes del afiliado
$list = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.affiliate_id, s.affiliate_id AS sale_aff FROM orders o JOIN products p ON p.id=o.product_id LEFT JOIN sales s ON s.id=p.sale_id WHERE (s.affiliate_id=? OR o.affiliate_id=?) ORDER BY o.id DESC LIMIT 5");
$list->execute([$aff_id, $aff_id]);
$orders = $list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Test Update</title>
<style>body{font-family:monospace;padding:20px;background:#f5f5f5} pre{background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd} .ok{color:green;font-weight:bold} .err{color:red;font-weight:bold} form{background:#fff;padding:15px;border-radius:6px;margin:10px 0;border:1px solid #ddd}</style>
</head><body>
<h2>🔧 Test Update de Estado</h2>

<pre><b>SESSION aff_id = <?= $aff_id ?></b> <?= $aff_id > 0 ? '<span class="ok">OK</span>' : '<span class="err">⚠ NO HAY SESIÓN</span>' ?></pre>

<?php if ($result): ?>
<h3>Resultado del POST:</h3>
<pre><?= json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
<?php endif; ?>

<h3>Tus últimas 5 órdenes (como aparecen en el listado):</h3>
<pre><?= json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>

<h3>Prueba manual:</h3>
<form method="post">
    <label>Order ID: <input name="order_id" value="208" style="padding:4px;margin:0 8px"></label>
    <label>Nuevo estado:
        <select name="status" style="padding:4px;margin:0 8px">
            <option>Pendiente</option>
            <option>Pagado</option>
            <option>Empacado</option>
            <option>En camino</option>
            <option>Entregado</option>
            <option>Cancelado</option>
        </select>
    </label>
    <button type="submit" style="padding:6px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer">Actualizar</button>
</form>

<p style="color:#999;font-size:0.8rem">⚠️ Borrar este archivo cuando termines la prueba</p>
</body></html>
