<?php
// admin/dashboard.php (FINAL - UTF-8 sin BOM)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// Define APP_URL si no existe
if (!defined('APP_URL')) {
    if (function_exists('app_base_url')) {
        define('APP_URL', rtrim(app_base_url(), '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('APP_URL', $scheme . $host);
    }
}

require_login();
$pdo = db();

/**
 * Helper seguro para escapar HTML sin warnings por valores null.
 * Siempre devolverá string, usando ENT_QUOTES y UTF-8.
 */
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

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

    // NUEVO: Imagen 2 opcional
    $imageName2 = null;
    if (!empty($_FILES['image2']['name'])) {
        @mkdir(__DIR__ . '/../uploads', 0775, true);
        $ext2 = strtolower(pathinfo($_FILES['image2']['name'], PATHINFO_EXTENSION));
        if (in_array($ext2, ['jpg','jpeg','png','gif','webp'])) {
            $imageName2 = uniqid('img2_') . '.' . $ext2;
            @move_uploaded_file($_FILES['image2']['tmp_name'], __DIR__ . '/../uploads/' . $imageName2);
        }
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image, currency, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name,$desc,$price,$stock,$imageName,$currency,$active,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);

        // ⬅️ NUEVO: si hay segunda imagen, guardarla
        if ($imageName2 !== null) {
            try { $pdo->prepare("UPDATE products SET image2=? WHERE id=?")->execute([$imageName2, (int)$pdo->lastInsertId()]); }
            catch (Throwable $e) { error_log("[admin/dashboard] image2 INSERT update: ".$e->getMessage()); }
        }

        $msg = "Producto creado.";
    } else {
        if ($imageName) {
            $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, image=?, currency=?, active=?, updated_at=? WHERE id=?");
            $stmt->execute([$name,$desc,$price,$stock,$imageName,$currency,$active,date('Y-m-d H:i:s'),$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, currency=?, active=?, updated_at=? WHERE id=?");
            $stmt->execute([$name,$desc,$price,$stock,$currency,$active,date('Y-m-d H:i:s'),$id]);
        }

        // ⬅️ NUEVO: si hay segunda imagen al editar, guardarla
        if ($imageName2 !== null) {
            try { $pdo->prepare("UPDATE products SET image2=? WHERE id=?")->execute([$imageName2, $id]); }
            catch (Throwable $e) { error_log("[admin/dashboard] image2 UPDATE: ".$e->getMessage()); }
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

/* ----------------------- Afiliados: activar/desactivar + correo ----------------------- */
if (isset($_POST['toggle_affiliate'])) {
    $aff_id    = (int)($_POST['aff_id'] ?? 0);
    $new_state = (int)($_POST['new_state'] ?? 0);
    if ($aff_id > 0) {
        $pdo->prepare("UPDATE affiliates SET is_active=? WHERE id=?")->execute([$new_state, $aff_id]);
        $msg = "Afiliado #{$aff_id} " . ($new_state === 1 ? "activado" : "desactivado") . ".";

        $st = $pdo->prepare("SELECT name, email, phone FROM affiliates WHERE id=?");
        $st->execute([$aff_id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);

        // Notificar SIEMPRE (activar o desactivar)
        if ($a) {
            $affName  = (string)($a['name']  ?? '');
            $affEmail = (string)($a['email'] ?? '');
            $affPhone = (string)($a['phone'] ?? '');
            $admin    = defined('ADMIN_EMAIL') ? (string)ADMIN_EMAIL : '';

            $loginUrl = APP_URL . "/affiliate/login.php";

            if ($new_state === 1) {
                // ACTIVACIÓN
                $subjectAff = "✅ Tu cuenta de afiliado ha sido activada";
                $bodyAff = "
                <p>Hola <strong>" . h($affName) . "</strong>,</p>
                <p>¡Tu cuenta de afiliado en <strong>" . h(APP_NAME) . "</strong> ha sido <strong>activada</strong>!</p>
                <p>Ya podés iniciar sesión y publicar tus espacios de venta:</p>
                <p><a href='" . h($loginUrl) . "'
                      style='background:#2563eb;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block'>Iniciar sesión</a></p>
                <p>Si tenés dudas, escribinos a <a href='mailto:" . h($admin) . "'>" . h($admin) . "</a>.</p>
                <br><p>— El equipo de " . h(APP_NAME) . "</p>";

                $subjectAdm = "📢 Afiliado ACTIVADO: {$affName}";
                $bodyAdm = "
                <p>Se activó el afiliado <strong>" . h($affName) . "</strong>
                (<a href='mailto:" . h($affEmail) . "'>" . h($affEmail) . "</a>)</p>
                <p>Teléfono: <strong>" . h($affPhone) . "</strong></p>";
            } else {
                // DESACTIVACIÓN
                $subjectAff = "⛔ Tu cuenta de afiliado fue desactivada";
                $bodyAff = "
                <p>Hola <strong>" . h($affName) . "</strong>,</p>
                <p>Tu cuenta de afiliado en <strong>" . h(APP_NAME) . "</strong> ha sido <strong>desactivada</strong>.</p>
                <p>Si creés que se trata de un error, escribinos a
                <a href='mailto:" . h($admin) . "'>" . h($admin) . "</a>.</p>
                <br><p>— El equipo de " . h(APP_NAME) . "</p>";

                $subjectAdm = "📢 Afiliado DESACTIVADO: {$affName}";
                $bodyAdm = "
                <p>Se desactivó el afiliado <strong>" . h($affName) . "</strong>
                (<a href='mailto:" . h($affEmail) . "'>" . h($affEmail) . "</a>)</p>
                <p>Teléfono: <strong>" . h($affPhone) . "</strong></p>";
            }

            // Envíos con send_email()
            try {
                if ($affEmail !== '') {
                    @send_email($affEmail, $subjectAff, $bodyAff);
                } else {
                    error_log("[admin/dashboard] Afiliado #{$aff_id} sin email; no se envía notificación al afiliado.");
                }
            } catch (Throwable $e) {
                error_log("[admin/dashboard] Error enviando mail al afiliado: " . $e->getMessage());
            }

            try {
                if ($admin !== '') {
                    @send_email($admin, $subjectAdm, $bodyAdm);
                } else {
                    error_log("[admin/dashboard] ADMIN_EMAIL no definido; no se envía notificación admin.");
                }
            } catch (Throwable $e) {
                error_log("[admin/dashboard] Error enviando mail al admin: " . $e->getMessage());
            }
        }
    }
}

/* ----------------------- Cargas para vista ----------------------- */
$products = $pdo->query("
SELECT
  p.*, s.id AS sale_id, s.title AS sale_title, s.is_active AS sale_active,
  a.id AS aff_id, a.email AS aff_email,
  (SELECT status FROM sale_fees f WHERE f.sale_id = s.id ORDER BY f.id DESC LIMIT 1) AS fee_status
FROM products p
LEFT JOIN sales s ON s.id = p.sale_id
LEFT JOIN affiliates a ON a.id = s.affiliate_id
ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$orders = $pdo->query("
  SELECT o.*, p.name AS product_name
  FROM orders o
  JOIN products p ON p.id=o.product_id
  ORDER BY o.created_at DESC
  LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$ex = (float)($settings['exchange_rate'] ?? 540.00);

$affiliates = $pdo->query("
  SELECT id, name, email, phone, is_active, created_at
  FROM affiliates
  ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Backoffice - <?php echo h(APP_NAME); ?></title>
<link rel="stylesheet" href="../assets/style.css">
<!-- Estilos rápidos para mejorar las miniaturas y la tabla -->
<style>
/* Miniaturas controladas */
.thumb {
  max-width: 84px;
  max-height: 84px;
  width: auto;
  height: auto;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #e6e6e6;
  display: inline-block;
}

/* Tabla y layout */
.container { max-width:1200px; margin:18px auto; padding:0 12px; }
.table { width:100%; border-collapse:collapse; margin-bottom:18px; font-family: Arial, Helvetica, sans-serif; }
.table th, .table td { padding:8px 10px; border-bottom:1px solid #f1f1f1; vertical-align:middle; text-align:left; }
.table th { background:#fafafa; font-weight:600; font-size:0.95rem; color:#333; }
.small { font-size:0.85rem; color:#666; }

/* Mensajes */
.success { background:#f0fff4; border:1px solid #cfead2; padding:10px; margin-bottom:12px; border-radius:6px; }

/* Formularios */
.form .input { width:100%; box-sizing:border-box; padding:8px; margin-top:6px; margin-bottom:10px; }

/* Acciones */
.actions { margin-top:8px; }

/* Hacer tablas scrollables en pantallas pequeñas */
.table-wrap { overflow-x:auto; }

/* Ajustes responsive */
@media (max-width:800px) {
  .thumb { max-width:64px; max-height:64px; }
  .container { padding:0 8px; }
}
</style>
</head>
<body>
<header class="header">
  <div class="logo">⚙️ Backoffice</div>
  <nav>
    <a class="btn" href="../index.php">Ver tienda</a>
    <a class="btn" href="dashboard.php">Dashboard</a>
    <a class="btn" href="dashboard_ext.php">Productos (Extendido)</a>
    <a class="btn" href="sales_admin.php">Espacios</a>
    <a class="btn" href="affiliates.php">Afiliados</a>
    <a class="btn" href="settings_fee.php">Costo por espacio</a>
    <a class="btn" href="email_marketing.php">📧 Email Marketing</a>
    <a class="btn" href="../tools/sql_exec.php" target="_blank">🧩 SQL Tools</a>
    <a class="btn" href="logout.php">Salir</a>
  </nav>
</header>

<div class="container">
<?php if(!empty($msg)): ?><div class="success"><?php echo h($msg); ?></div><?php endif; ?>

<!-- =================== Configuración =================== -->
<h2>Configuración</h2>
<form class="form" method="post">
  <input type="hidden" name="action" value="save_settings">
  <label>Tipo de cambio (CRC por 1 USD)
    <input class="input" type="number" name="exchange_rate" step="0.01" min="100" value="<?php echo h(number_format($ex,2,'.','')); ?>">
  </label>
  <div class="actions"><button class="btn primary">Guardar</button></div>
</form>

<!-- =================== Crear / Editar producto =================== -->
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
  <label>Imagen 2 (opcional) <input class="input" type="file" name="image2" accept="image/*"></label>
  <label><input type="checkbox" name="active" id="active" checked> Activo</label>
  <div class="actions">
    <button class="btn primary" name="action" value="create" type="submit">Crear</button>
    <button class="btn" name="action" value="update" type="submit">Actualizar</button>
  </div>
</form>

<!-- =================== Productos =================== -->
<h2>Productos</h2>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr>
      <th>ID</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Moneda</th><th>Stock</th><th>Activo</th>
      <th>Espacio</th><th>Afiliado</th><th>Fee</th><th>Activo (Esp.)</th><th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($products as $p):
      $cur = strtoupper(trim($p['currency'] ?? 'CRC'));
      $sym = ($cur === 'USD') ? '$' : '₡';
    ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td>
        <?php if(!empty($p['image'])): ?>
          <img src="../uploads/<?php echo h($p['image']); ?>" class="thumb" alt="img-<?php echo (int)$p['id']; ?>">
        <?php endif; ?>
        <?php if(!empty($p['image2'])): ?>
          <img src="../uploads/<?php echo h($p['image2']); ?>" class="thumb" style="margin-left:6px" alt="img2-<?php echo (int)$p['id']; ?>">
        <?php endif; ?>
      </td>
      <td><?= h($p['name']) ?></td>
      <td><?= h($sym . number_format((float)$p['price'], $sym==='$'?2:0, ',', '.')) ?></td>
      <td><?= h($cur) ?></td>
      <td><?= (int)$p['stock'] ?></td>
      <td><?= $p['active'] ? 'Sí' : 'No' ?></td>
      <td><?= h($p['sale_title'] ?? '—') ?></td>
      <td><?= h($p['aff_email'] ?? '—') ?></td>
      <td><?= h($p['fee_status'] ?? '—') ?></td>
      <td><?= $p['sale_active'] ? 'Sí' : 'No' ?></td>
      <td>
        <button class="btn" onclick='fillForm(<?= json_encode($p, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Editar</button>
        <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar producto?');">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn" name="action" value="delete">Eliminar</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- =================== Pedidos =================== -->
<h2>Pedidos recientes</h2>
<div class="table-wrap">
<table class="table">
  <thead>
    <tr><th>ID</th><th>Fecha</th><th>Producto</th><th>Cant</th><th>Cliente</th><th>Residencia</th><th>Estado</th><th>Comprobante</th><th>Acción</th></tr>
  </thead>
  <tbody>
    <?php foreach($orders as $o): ?>
    <tr>
      <td><?= (int)$o['id'] ?></td>
      <td><?= h($o['created_at']) ?></td>
      <td><?= h($o['product_name']) ?></td>
      <td><?= (int)$o['qty'] ?></td>
      <td><?= h($o['buyer_email'] . " / " . $o['buyer_phone']) ?></td>
      <td><?= h($o['residency']) ?></td>
      <td><?= h($o['status']) ?></td>
      <td>
        <?php if(!empty($o['proof_image'])): ?>
          <a href="../uploads/payments/<?php echo h($o['proof_image']); ?>" target="_blank">
            <img class="thumb" src="../uploads/payments/<?php echo h($o['proof_image']); ?>" alt="proof-<?php echo (int)$o['id']; ?>">
          </a>
        <?php else: ?>
          <span class="small">Sin comprobante</span>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px;align-items:center">
          <input type="hidden" name="action" value="update_order_status">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <select name="status" class="input" style="padding:6px">
            <?php foreach(['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'] as $st): ?>
              <option value="<?= h($st) ?>" <?= $o['status']===$st?'selected':'' ?>><?= h($st) ?></option>
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

<!-- =================== Afiliados =================== -->
<h2>Afiliados</h2>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Activo</th><th>Creado</th><th>Acción</th></tr></thead>
  <tbody>
    <?php foreach ($affiliates as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
      <td><?= h($a['name']) ?></td>
      <td><?= h($a['email']) ?></td>
      <td><?= h($a['phone']) ?></td>
      <td><?= $a['is_active'] ? '✅ Sí' : '⛔ No' ?></td>
      <td class="small"><?= h($a['created_at']) ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="toggle_affiliate" value="1">
          <input type="hidden" name="aff_id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="new_state" value="<?= $a['is_active'] ? 0 : 1 ?>">
          <button class="btn"><?= $a['is_active'] ? 'Desactivar' : 'Activar' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
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
</body>