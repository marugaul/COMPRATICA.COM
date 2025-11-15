<?php
// affiliate/products.php — gestión de productos por afiliado con asignación de espacio (sale_id)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];
$msg = '';

// Espacios activos del afiliado
$ms = $pdo->prepare("SELECT id, title FROM sales WHERE affiliate_id=? AND is_active=1 ORDER BY datetime(start_at) DESC");
$ms->execute([$aff_id]);
$my_sales = $ms->fetchAll(PDO::FETCH_ASSOC);

// Crear/Actualizar/Eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['create']) || isset($_POST['update'])) {
      $id          = (int)($_POST['id'] ?? 0);
      $name        = trim($_POST['name'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $price       = (float)($_POST['price'] ?? 0);
      $stock       = (int)($_POST['stock'] ?? 0);
      $currency    = ($_POST['currency'] ?? 'CRC') === 'USD' ? 'USD' : 'CRC';
      $sale_id     = (int)($_POST['sale_id'] ?? 0);
      $active      = isset($_POST['active']) ? 1 : 0;

      if ($name === '' || $price <= 0 || $stock < 0 || !$sale_id) {
        throw new RuntimeException('Datos incompletos: nombre, precio>0, stock>=0 y espacio requerido.');
      }

      // Validar que el espacio sea del afiliado y esté activo
      $chk = $pdo->prepare("SELECT 1 FROM sales WHERE id=? AND affiliate_id=? AND is_active=1");
      $chk->execute([$sale_id, $aff_id]);
      if (!$chk->fetchColumn()) {
        throw new RuntimeException('El espacio seleccionado no es válido o no está activo.');
      }

      // Imagen (opcional)
      $image = null;
      if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        @mkdir(__DIR__ . '/../uploads', 0775, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
        $image = 'img_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $image)) {
          $image = null;
        }
      }

      if (isset($_POST['create'])) {
        $sql = "INSERT INTO products
          (affiliate_id, sale_id, name, description, price, stock, image, currency, active, created_at, updated_at)
          VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
        $pdo->prepare($sql)->execute([$aff_id, $sale_id, $name, $description, $price, $stock, $image, $currency, $active]);
        $msg = 'Producto creado.';
      } else {
        // update
        $cur = $pdo->prepare("SELECT image FROM products WHERE id=? AND affiliate_id=?");
        $cur->execute([$id, $aff_id]);
        $curr = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$curr) throw new RuntimeException('Producto no encontrado.');
        if (!$image) $image = $curr['image'];

        $sql = "UPDATE products
                SET sale_id=?, name=?, description=?, price=?, stock=?, image=?, currency=?, active=?, updated_at=datetime('now')
                WHERE id=? AND affiliate_id=?";
        $pdo->prepare($sql)->execute([$sale_id, $name, $description, $price, $stock, $image, $currency, $active, $id, $aff_id]);
        $msg = 'Producto actualizado.';
      }
    } elseif (isset($_POST['delete'])) {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM products WHERE id=? AND affiliate_id=?")->execute([$id, $aff_id]);
      $msg = 'Producto eliminado.';
    }
  } catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
    error_log('[affiliate/products.php] ' . $e->getMessage());
  }
}

// Listado de productos del afiliado (cualquiera sea su sale_id)
$q = $pdo->prepare("SELECT id, name, sale_id, price, stock, currency, active, image, created_at
                    FROM products
                    WHERE affiliate_id=?
                    ORDER BY id DESC
                    LIMIT 200");
$q->execute([$aff_id]);
$products = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Mis productos</title>
  <link rel="stylesheet" href="../assets/style.css?v=24">
  <style>.thumb{width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb}</style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Mis productos</div>
  <nav><a class="btn" href="dashboard.php">Panel</a> <a class="btn" href="sales.php">Mis espacios</a></nav>
</header>
<div class="container">

  <?php if ($msg): ?>
    <div class="alert"><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if (empty($my_sales)): ?>
    <div class="alert">
      No tenés espacios activos. Creá y pagá uno en <a href="sales.php">Mis espacios</a> para poder subir productos.
    </div>
  <?php endif; ?>

  <div class="card">
    <h3>Crear producto</h3>
    <form class="form" method="post" enctype="multipart/form-data">
      <label>Nombre <input class="input" name="name" required></label>
      <label>Descripción <textarea class="input" name="description" rows="3"></textarea></label>
      <label>Precio <input class="input" type="number" step="0.01" name="price" required></label>
      <label>Stock <input class="input" type="number" name="stock" min="0" required></label>
      <label>Moneda
        <select class="input" name="currency">
          <option value="CRC">CRC</option>
          <option value="USD">USD</option>
        </select>
      </label>
      <label>Espacio (venta)
        <select class="input" name="sale_id" required>
          <option value="" disabled selected>Seleccioná un espacio activo</option>
          <?php foreach($my_sales as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Imagen <input class="input" type="file" name="image" accept="image/*"></label>
      <label><input type="checkbox" name="active" checked> Activo</label>
      <button class="btn primary" name="create" value="1" <?= empty($my_sales)?'disabled':''; ?>>Crear</button>
    </form>
  </div>

  <div class="card">
    <h3>Mis productos</h3>
    <table class="table">
      <tr><th>ID</th><th>Foto</th><th>Nombre</th><th>Espacio</th><th>Precio</th><th>Stock</th><th>Activo</th><th>Acciones</th></tr>
      <?php foreach($products as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td><?php if(!empty($p['image'])): ?><img class="thumb" src="../uploads/<?= htmlspecialchars($p['image']) ?>"><?php endif; ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td>
            <?php
              if (!empty($p['sale_id'])) {
                $t = $pdo->prepare("SELECT title FROM sales WHERE id=? AND affiliate_id=?");
                $t->execute([(int)$p['sale_id'], $aff_id]);
                echo htmlspecialchars($t->fetchColumn() ?: '—');
              } else { echo '—'; }
            ?>
          </td>
          <td><?php
            $cur = $p['currency']==='USD' ? '$' : '₡';
            echo $cur . ($p['currency']==='USD'
              ? number_format((float)$p['price'],2,'.',',')
              : number_format((float)$p['price'],0,',','.'));
          ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= !empty($p['active']) ? 'Sí' : 'No' ?></td>
          <td class="actions">
            <!-- Editar -->
            <form method="post" enctype="multipart/form-data" style="display:inline-block; max-width:480px">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <details>
                <summary class="btn">Editar</summary>
                <div style="padding:8px 0; display:grid; gap:6px">
                  <label>Nombre <input class="input" name="name" value="<?= htmlspecialchars($p['name']) ?>" required></label>
                  <label>Descripción <textarea class="input" name="description" rows="3"></textarea></label>
                  <label>Precio <input class="input" type="number" step="0.01" name="price" value="<?= htmlspecialchars((string)$p['price']) ?>" required></label>
                  <label>Stock <input class="input" type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>" required></label>
                  <label>Moneda
                    <select class="input" name="currency">
                      <option value="CRC" <?= $p['currency']==='CRC'?'selected':''; ?>>CRC</option>
                      <option value="USD" <?= $p['currency']==='USD'?'selected':''; ?>>USD</option>
                    </select>
                  </label>
                  <label>Espacio (venta)
                    <select class="input" name="sale_id" required>
                      <?php foreach($my_sales as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ((int)$p['sale_id']===(int)$s['id'])?'selected':''; ?>>
                          <?= htmlspecialchars($s['title']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Imagen <input class="input" type="file" name="image" accept="image/*"></label>
                  <label><input type="checkbox" name="active" <?= !empty($p['active'])?'checked':''; ?>> Activo</label>
                  <button class="btn" name="update" value="1">Guardar</button>
                </div>
              </details>
            </form>

            <!-- Eliminar -->
            <form method="post" onsubmit="return confirm('¿Eliminar producto?');" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn" name="delete" value="1">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>