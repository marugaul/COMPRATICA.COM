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
      $category    = trim($_POST['category'] ?? '');

      if ($name === '' || $price <= 0 || $stock < 0 || !$sale_id) {
        throw new RuntimeException('Datos incompletos: nombre, precio>0, stock>=0 y espacio requerido.');
      }

      // Validar que el espacio sea del afiliado y esté activo
      $chk = $pdo->prepare("SELECT 1 FROM sales WHERE id=? AND affiliate_id=? AND is_active=1");
      $chk->execute([$sale_id, $aff_id]);
      if (!$chk->fetchColumn()) {
        throw new RuntimeException('El espacio seleccionado no es válido o no está activo.');
      }

      // Imagen 1 (opcional)
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

      // NUEVO: Imagen 2 (opcional)
      $image2 = null;
      if (!empty($_FILES['image2']['name']) && is_uploaded_file($_FILES['image2']['tmp_name'])) {
        @mkdir(__DIR__ . '/../uploads', 0775, true);
        $ext2 = strtolower(pathinfo($_FILES['image2']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext2, ['jpg','jpeg','png','webp','gif'])) $ext2 = 'jpg';
        $image2 = 'img2_' . uniqid() . '.' . $ext2;
        if (!move_uploaded_file($_FILES['image2']['tmp_name'], __DIR__ . '/../uploads/' . $image2)) {
          $image2 = null;
        }
      }

      if (isset($_POST['create'])) {
        $sql = "INSERT INTO products
          (affiliate_id, sale_id, name, description, price, stock, image, currency, active, category, created_at, updated_at)
          VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))";
        $pdo->prepare($sql)->execute([$aff_id, $sale_id, $name, $description, $price, $stock, $image, $currency, $active, $category]);

        // Guardar image2 si se subió
        if ($image2 !== null) {
          try {
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE products SET image2=? WHERE id=?")->execute([$image2, $newId]);
          } catch (Throwable $e) {
            error_log('[affiliate/products.php] No se pudo guardar image2 en INSERT: ' . $e->getMessage());
          }
        }

        $msg = 'Producto creado.';
      } else {
        // update
        $cur = $pdo->prepare("SELECT image, image2 FROM products WHERE id=? AND affiliate_id=?");
        $cur->execute([$id, $aff_id]);
        $curr = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$curr) throw new RuntimeException('Producto no encontrado.');

        if (!$image)  $image  = $curr['image'];
        if (!$image2) $image2 = $curr['image2'];

        $sql = "UPDATE products
                SET sale_id=?, name=?, description=?, price=?, stock=?, image=?, image2=?, currency=?, active=?, category=?, updated_at=datetime('now')
                WHERE id=? AND affiliate_id=?";
        $pdo->prepare($sql)->execute([$sale_id, $name, $description, $price, $stock, $image, $image2, $currency, $active, $category, $id, $aff_id]);

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
$q = $pdo->prepare("SELECT id, name, sale_id, price, stock, currency, active, image, image2, category, created_at
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Variables de color corporativas */
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --accent-hover: #2980b9;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-600: #6b7280;
      --gray-800: #1f2937;
    }

    body {
      background: var(--gray-50);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Header empresarial */
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
      padding: 1.5rem 2rem;
    }

    .header .logo {
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Cards mejorados */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid var(--gray-200);
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 1.5rem 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Formulario mejorado */
    .form label {
      font-weight: 500;
      color: var(--gray-800);
      margin-bottom: 0.5rem;
      display: block;
    }

    .form .input, .form textarea, .form select {
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      width: 100%;
    }

    .form .input:focus, .form textarea:focus, .form select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    /* Tabla profesional */
    .table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    .table th {
      background: var(--gray-100);
      color: var(--gray-800);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
      padding: 1rem;
      text-align: left;
      border-bottom: 2px solid var(--gray-300);
    }

    .table th:first-child {
      border-radius: 8px 0 0 0;
    }

    .table th:last-child {
      border-radius: 0 8px 0 0;
    }

    .table td {
      padding: 1rem;
      border-bottom: 1px solid var(--gray-200);
      color: var(--gray-800);
      vertical-align: middle;
    }

    .table tr:last-child td {
      border-bottom: none;
    }

    .table tr:hover {
      background: var(--gray-50);
    }

    /* Botones mejorados */
    .btn {
      padding: 0.625rem 1.25rem;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }

    .btn.primary {
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    }

    .btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    /* Alert mejorado */
    .alert {
      background: rgba(231, 76, 60, 0.1);
      border: 1px solid rgba(231, 76, 60, 0.3);
      border-left: 4px solid var(--danger);
      color: #c0392b;
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Thumbnails */
    .thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 6px;
      border: 2px solid var(--gray-200);
      transition: transform 0.2s;
    }

    .thumb:hover {
      transform: scale(2.5);
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10;
    }

    /* Grid para formulario */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    .actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    details {
      border: 1px solid var(--gray-200);
      border-radius: 8px;
      padding: 0.5rem;
      margin-top: 0.5rem;
    }

    summary {
      cursor: pointer;
      font-weight: 500;
      padding: 0.5rem;
    }

    details[open] {
      background: var(--gray-50);
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: var(--gray-600);
    }

    .empty-state-icon {
      font-size: 4rem;
      color: var(--gray-300);
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    Panel de Afiliado
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
  </nav>
</header>
<div class="container">

  <?php if ($msg): ?>
    <div class="alert">
      <i class="fas fa-exclamation-triangle"></i>
      <span><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($my_sales)): ?>
    <div class="alert">
      <i class="fas fa-info-circle"></i>
      <span>
        No tenés espacios activos. Creá y pagá uno en <a href="sales.php" style="font-weight: 600; color: #e74c3c;">Mis Espacios</a> para poder subir productos.
      </span>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3><i class="fas fa-plus-circle"></i> Crear Nuevo Producto</h3>
    <form class="form" method="post" enctype="multipart/form-data">
      <div class="form-grid">
        <label>
          <i class="fas fa-tag"></i> Nombre del Producto
          <input class="input" name="name" placeholder="Ej: Camisa Polo Azul" required>
        </label>

        <label>
          <i class="fas fa-store"></i> Espacio de Venta
          <select class="input" name="sale_id" required>
            <option value="" disabled selected>Selecciona un espacio activo</option>
            <?php foreach($my_sales as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label>
        <i class="fas fa-align-left"></i> Descripción
        <textarea class="input" name="description" rows="3" placeholder="Describe tu producto..."></textarea>
      </label>

      <label>
        <i class="fas fa-folder-open"></i> Categoría
        <input class="input" name="category" placeholder="Ej: Ropa, Electrónica, Hogar, Deportes...">
      </label>

      <div class="form-grid">
        <label>
          <i class="fas fa-dollar-sign"></i> Precio
          <input class="input" type="number" step="0.01" name="price" placeholder="0.00" required>
        </label>

        <label>
          <i class="fas fa-boxes"></i> Stock Disponible
          <input class="input" type="number" name="stock" min="0" placeholder="0" required>
        </label>
      </div>

      <div class="form-grid">
        <label>
          <i class="fas fa-money-bill-wave"></i> Moneda
          <select class="input" name="currency">
            <option value="CRC">₡ Colones (CRC)</option>
            <option value="USD">$ Dólares (USD)</option>
          </select>
        </label>

        <label style="display: flex; align-items: center; gap: 0.5rem; padding-top: 2rem;">
          <input type="checkbox" name="active" checked style="width: auto; margin: 0;">
          <span><i class="fas fa-eye"></i> Producto Activo</span>
        </label>
      </div>

      <div class="form-grid">
        <label>
          <i class="fas fa-image"></i> Imagen Principal
          <input class="input" type="file" name="image" accept="image/*">
        </label>

        <label>
          <i class="fas fa-images"></i> Imagen Secundaria (Opcional)
          <input class="input" type="file" name="image2" accept="image/*">
        </label>
      </div>

      <button class="btn primary" name="create" value="1" <?= empty($my_sales)?'disabled':''; ?>>
        <i class="fas fa-plus-circle"></i>
        Crear Producto
      </button>
    </form>
  </div>

  <div class="card">
    <h3><i class="fas fa-list"></i> Mis Productos</h3>
    <?php if (empty($products)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <i class="fas fa-box-open"></i>
        </div>
        <h4>No tienes productos creados aún</h4>
        <p>Crea tu primer producto usando el formulario de arriba para comenzar a vender.</p>
      </div>
    <?php else: ?>
    <table class="table">
      <tr>
        <th><i class="fas fa-hashtag"></i> ID</th>
        <th><i class="fas fa-image"></i> Fotos</th>
        <th><i class="fas fa-tag"></i> Nombre</th>
        <th><i class="fas fa-folder-open"></i> Categoría</th>
        <th><i class="fas fa-store"></i> Espacio</th>
        <th><i class="fas fa-dollar-sign"></i> Precio</th>
        <th><i class="fas fa-boxes"></i> Stock</th>
        <th><i class="fas fa-toggle-on"></i> Activo</th>
        <th><i class="fas fa-cog"></i> Acciones</th>
      </tr>
      <?php foreach($products as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td>
            <?php if(!empty($p['image'])): ?>
              <img class="thumb" src="../uploads/<?= htmlspecialchars($p['image']) ?>">
            <?php endif; ?>
            <?php if(!empty($p['image2'])): ?>
              <img class="thumb" src="../uploads/<?= htmlspecialchars($p['image2']) ?>" style="margin-left:4px">
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td>
            <?php if (!empty($p['category'])): ?>
              <span style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05)); border: 1px solid rgba(52, 152, 219, 0.3); color: #2980b9; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 500;">
                <?= htmlspecialchars($p['category']) ?>
              </span>
            <?php else: ?>
              <span style="color: var(--gray-600); font-style: italic;">Sin categoría</span>
            <?php endif; ?>
          </td>
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
                  <label>Categoría <input class="input" name="category" value="<?= htmlspecialchars($p['category'] ?? '') ?>" placeholder="Ej: Ropa, Electrónica..."></label>
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
                  <!-- NUEVO: Imagen 2 -->
                  <label>Imagen 2 (opcional) <input class="input" type="file" name="image2" accept="image/*"></label>
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
    <?php endif; ?>
  </div>
</div>
</body>
</html>
