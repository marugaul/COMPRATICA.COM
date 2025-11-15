<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (isset($_POST['create']) || isset($_POST['update'])) {
      $id    = (int)($_POST['id'] ?? 0);
      $name  = trim($_POST['name'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $pass  = (string)($_POST['pass'] ?? '');

      if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Nombre y correo válidos son requeridos.');
      }

      if (isset($_POST['create'])) {
        // *** Contraseña OBLIGATORIA al crear ***
        if ($pass === '') {
          throw new RuntimeException('La contraseña es obligatoria para crear el afiliado.');
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        // Nota: usamos password_hash (columna) NO pass_hash.
        $stmt = $pdo->prepare("
          INSERT INTO affiliates (name, email, phone, password_hash, created_at, updated_at)
          VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$name, $email, $phone, $hash]);
        $msg = 'Afiliado creado.';

      } else { // update
        // Si se proporciona nueva contraseña, se actualiza; si no, se deja igual.
        if ($pass !== '') {
          $hash = password_hash($pass, PASSWORD_BCRYPT);
          $stmt = $pdo->prepare("
            UPDATE affiliates
            SET name=?, email=?, phone=?, password_hash=?, updated_at=datetime('now')
            WHERE id=?
          ");
          $stmt->execute([$name, $email, $phone, $hash, $id]);
        } else {
          $stmt = $pdo->prepare("
            UPDATE affiliates
            SET name=?, email=?, phone=?, updated_at=datetime('now')
            WHERE id=?
          ");
          $stmt->execute([$name, $email, $phone, $id]);
        }
        $msg = 'Afiliado actualizado.';
      }

    } elseif (isset($_POST['delete'])) {
      $id = (int)$_POST['id'];
      $pdo->prepare("DELETE FROM affiliates WHERE id=?")->execute([$id]);
      $msg = 'Afiliado eliminado.';
    }
  } catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
  }
}

// Listado
$rows = $pdo->query("SELECT id, name, email, phone, created_at FROM affiliates ORDER BY created_at DESC LIMIT 200")
            ->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Afiliados</title>
<link rel="stylesheet" href="../assets/style.css">
</head><body>
<header class="header">
  <div class="logo">🛒 Admin — Afiliados</div>
  <nav>
    <a class="btn" href="dashboard.php">Dashboard</a>
    <a class="btn" href="sales_admin.php">Espacios</a>
  </nav>
</header>

<div class="container">
  <?php if($msg): ?><div class="alert"><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="card">
    <h3>Crear afiliado</h3>
    <form class="form" method="post">
      <label>Nombre
        <input class="input" name="name" required>
      </label>
      <label>Correo
        <input class="input" name="email" type="email" required>
      </label>
      <label>Teléfono
        <input class="input" name="phone">
      </label>
      <label>Contraseña (obligatoria al crear)
        <input class="input" name="pass" type="password" required>
      </label>
      <button class="btn primary" name="create" value="1">Crear</button>
    </form>
  </div>

  <div class="card">
    <h3>Afiliados</h3>
    <table class="table">
      <tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Acciones</th></tr>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= htmlspecialchars($r['phone'] ?: '—') ?></td>
        <td class="actions">
          <form method="post" style="display:inline-block;max-width:520px">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <details>
              <summary class="btn">Editar</summary>
              <div style="padding:8px 0;display:grid;gap:6px">
                <label>Nombre
                  <input class="input" name="name" value="<?= htmlspecialchars($r['name']) ?>" required>
                </label>
                <label>Correo
                  <input class="input" name="email" type="email" value="<?= htmlspecialchars($r['email']) ?>" required>
                </label>
                <label>Teléfono
                  <input class="input" name="phone" value="<?= htmlspecialchars($r['phone'] ?: '') ?>">
                </label>
                <label>Nueva contraseña (opcional)
                  <input class="input" name="pass" type="password" placeholder="Dejar en blanco para no cambiar">
                </label>
                <button class="btn" name="update" value="1">Guardar</button>
              </div>
            </details>
          </form>
          <form method="post" onsubmit="return confirm('¿Eliminar afiliado?');" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn" name="delete" value="1">Eliminar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body></html>
