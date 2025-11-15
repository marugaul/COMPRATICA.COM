<?php
// admin/dashboard.php (FINAL - UTF-8 sin BOM)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

$msg = '';
$action = $_POST['action'] ?? null;

/* ----------------------- Configuración ----------------------- */
if ($action === 'save_settings') {
    $rate = (float)($_POST['exchange_rate'] ?? 540.00);
    $pdo->prepare("UPDATE settings SET exchange_rate=? WHERE id=1")->execute([$rate]);
    $msg = "Tipo de cambio actualizado.";
}

/* ----------------------- Productos: crear/actualizar ----------------------- */
if ($action === 'create' || $action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $currency = strtoupper(trim($_POST['currency'] ?? 'CRC'));
    if (!in_array($currency, ['CRC','USD'])) $currency = 'CRC';
    $active = isset($_POST['active']) ? 1 : 0;

    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $imageName = uniqid('img_') . '.' . $ext;
            @move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $imageName);
        }
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image, currency, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name,$desc,$price,$stock,$imageName,$currency,$active,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
        $msg = "Producto creado.";
    } else { // update
        if ($imageName) {
            $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, image=?, currency=?, active=?, updated_at=? WHERE id=?");
            $stmt->execute([$name,$desc,$price,$stock,$imageName,$currency,$active,date('Y-m-d H:i:s'),$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, currency=?, active=?, updated_at=? WHERE id=?");
            $stmt->execute([$name,$desc,$price,$stock,$currency,$active,date('Y-m-d H:i:s'),$id]);
        }
        $msg = "Producto actualizado.";
    }
}

/* ----------------------- Productos: eliminar ----------------------- */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    $msg = "Producto eliminado.";
}

/* ----------------------- Pedidos: cambiar estado ----------------------- */
if ($action === 'update_order_status') {
    $oid = (int)($_POST['order_id'] ?? 0);
    $st = $_POST['status'] ?? 'Pendiente';
    $allowed = ['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'];
    if (!in_array($st, $allowed)) $st = 'Pendiente';
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$st,$oid]);
    $msg = "Estado de pedido actualizado.";
}

/* ----------------------- Cargas para vista ----------------------- */
/* Productos + info de espacio/afiliado/fee/activo */
$products = $pdo->query("
SELECT
  p.*,
  s.id     AS sale_id,
  s.title  AS sale_title,
  s.is_active AS sale_active,
  a.id     AS aff_id,
  a.email  AS aff_email,
  (
    SELECT status
    FROM sale_fees f
    WHERE f.sale_id = s.id
    ORDER BY f.id DESC
    LIMIT 1
  ) AS fee_status
FROM products p
LEFT JOIN sales s      ON s.id = p.sale_id
LEFT JOIN affiliates a ON a.id = s.affiliate_id
ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* Pedidos (igual que lo tenías) */
$orders = $pdo->query("SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id=o.product_id ORDER BY o.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

/* Settings */
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$ex = (float)($settings['exchange_rate'] ?? 540.00);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Backoffice - <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="header">
  <div class="logo">⚙️ Backoffice</div>
  <nav>
      
      <a class="btn" href="../index.php">Ver tienda</a>
      
      <!-- CAMBIO DE NUEVOS ENLACES PARA ADMIN -->
     
  
  <a class="btn" href="dashboard.php">Dashboard</a>
  <a class="btn" href="dashboard_ext.php">Productos (Extendido)</a>
  <a class="btn" href="sales_admin.php">Espacios</a>
  <a class="btn" href="affiliates.php">Afiliados</a>
  <a class="btn" href="settings_fee.php">Costo por espacio</a>
  <a class="btn" href="logout.php">Salir</a>
<!-- CAMBIO DE NUEVOS ENLACES PARA ADMIN -->

  
  
  </nav>
</header>
<div class="container">
<?php if(!empty($msg)): ?><div class="success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<h2>Configuración</h2>
<form class="form" method="post">
  <input type="hidden" name="action" value="save_settings">
  <label>Tipo de cambio (CRC por 1 USD)
    <input class="input" type="number" name="exchange_rate" step="0.01" min="100" value="<?php echo htmlspecialchars(number_format($ex,2,'.','')); ?>">
  </label>
  <div class="actions"><button class="btn primary">Guardar</button></div>
</form>

<h2>Crear / Editar producto</h2>
<form class="form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="id" id="id">
  <label>Nombre <input class="input" type="text" name="name" id="name" required></label>
  <label>Precio <input class="input" type="number" name="price" id="price" step="0.01" min="0" required></label>
  <label>Moneda
    <select class="input" name="currency" id="currency">
      <option value="CRC">CRC (₡)</option>
      <option value="USD">USD ($)</option>
    </select>
  </label>
  <label>Inventario <input class="input" type="number" name="stock" id="stock" min="0" required></label>
  <label>Descripción <textarea class="input" name="description" id="description" rows="3"></textarea></label>
  <label>Imagen <input class="input" type="file" name="image" accept="image/*"></label>
  <label><input type="checkbox" name="active" id="active" checked> Activo</label>
  <div class="actions">
    <button class="btn primary" name="action" value="create" type="submit">Crear</button>
    <button class="btn" name="action" value="update" type="submit">Actualizar</button>
  </div>
</form>

<h2>Productos</h2>
<table class="table">
  <thead>
    <tr>
      <th>ID</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Moneda</th><th>Stock</th><th>Activo</th>
      <th>Espacio</th><th>Afiliado</th><th>Fee</th><th>Activo (Esp.)</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($products as $p):
      $cur = strtoupper(trim($p['currency'] ?? 'CRC'));
      $sym = ($cur === 'USD') ? '$' : '₡';
    ?>
    <tr>
      <td><?php echo (int)$p['id']; ?></td>
      <td><?php if(!empty($p['image'])): ?><img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" class="thumb"><?php endif; ?></td>
      <td><?php echo htmlspecialchars($p['name']); ?></td>
      <td><?php echo $sym; ?><?php echo number_format((float)$p['price'], $sym==='$'?2:0, ',', '.'); ?></td>
      <td><?php echo htmlspecialchars($cur); ?></td>
      <td><?php echo (int)$p['stock']; ?></td>
      <td><?php echo !empty($p['active'])?'Sí':'No'; ?></td>

      <!-- NUEVAS COLUMNAS -->
      <td>
        <?php
          if (!empty($p['sale_title'])) {
            echo htmlspecialchars($p['sale_title']);
            if (!empty($p['sale_id'])) echo " (#".(int)$p['sale_id'].")";
          } else {
            echo "—";
          }
        ?>
      </td>
      <td>
        <?php
          if (!empty($p['aff_email'])) {
            echo htmlspecialchars($p['aff_email']);
            if (!empty($p['aff_id'])) echo " (#".(int)$p['aff_id'].")";
          } else {
            echo "—";
          }
        ?>
      </td>
      <td><?php echo htmlspecialchars($p['fee_status'] ?: '—'); ?></td>
      <td><?php echo !empty($p['sale_active']) ? 'Sí' : 'No'; ?></td>
      <!-- FIN NUEVAS COLUMNAS -->

      <td class="actions">
        <button class="btn" onclick='fillForm(<?php echo json_encode($p); ?>)'>Editar</button>
        <form method="post" onsubmit="return confirm("¿Eliminar producto?");" style="display:inline">
          <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
          <button class="btn" name="action" value="delete">Eliminar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Pedidos recientes</h2>
<table class="table">
  <thead><tr><th>ID</th><th>Fecha</th><th>Producto</th><th>Cant</th><th>Cliente</th><th>Residencia</th><th>Estado</th><th>Comprobante</th><th>Acción</th></tr></thead>
  <tbody>
    <?php foreach($orders as $o): ?>
    <tr>
      <td><?php echo (int)$o['id']; ?></td>
      <td><?php echo htmlspecialchars($o['created_at']); ?></td>
      <td><?php echo htmlspecialchars($o['product_name']); ?></td>
      <td><?php echo (int)$o['qty']; ?></td>
      <td><?php echo htmlspecialchars($o['buyer_email']." / ".$o['buyer_phone']); ?></td>
      <td><?php echo htmlspecialchars($o['residency']); ?></td>
      <td><?php echo htmlspecialchars($o['status']); ?></td>
      <td>
        <?php if(!empty($o['proof_image'])): ?>
          <a href="../uploads/payments/<?php echo htmlspecialchars($o['proof_image']); ?>" target="_blank"><img class="thumb" src="../uploads/payments/<?php echo htmlspecialchars($o['proof_image']); ?>"></a>
        <?php else: ?>
          <span class="small">Sin comprobante</span>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" style="display:flex; gap:6px; align-items:center">
          <input type="hidden" name="action" value="update_order_status">
          <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
          <select name="status" class="input" style="padding:6px">
            <?php foreach(['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'] as $st): ?>
              <option value="<?php echo $st; ?>" <?php echo (($o['status'])===$st?'selected':''); ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn">Guardar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<script>
function fillForm(p){
  document.getElementById('id').value = p.id || '';
  document.getElementById('name').value = p.name || '';
  document.getElementById('price').value = p.price || 0;
  document.getElementById('currency').value = (p.currency || 'CRC').toUpperCase();
  document.getElementById('stock').value = p.stock || 0;
  document.getElementById('description').value = p.description || '';
  document.getElementById('active').checked = p.active == 1 || p.active === '1';
  window.scrollTo({top:0, behavior:'smooth'});
}
</script>
</body></html>
