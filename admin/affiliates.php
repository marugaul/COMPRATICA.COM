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

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Afiliados - <?= h(APP_NAME ?? 'Admin') ?></title>
<link rel="stylesheet" href="../assets/style.css?v=24">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --primary: #2c3e50;
    --primary-light: #34495e;
    --accent: #3498db;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
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

  .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
  }

  .section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
  }

  .section-title {
    font-size: 1.5rem;
    color: var(--primary);
    margin: 0 0 1.5rem 0;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: 1px solid #60a5fa;
    color: #1e40af;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
  }

  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .form-group label {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .input {
    padding: 0.75rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 1rem;
    transition: all 0.2s ease;
    width: 100%;
  }

  .input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
  }

  .btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
    color: white;
  }

  .btn.primary {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
  }

  .btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
  }

  .btn.secondary {
    background: linear-gradient(135deg, var(--accent) 0%, #2980b9 100%);
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
  }

  .btn.secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
  }

  .btn.danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
  }

  .btn.danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
  }

  .table-wrap {
    overflow-x: auto;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .table thead {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
  }

  .table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-800);
    border-bottom: 2px solid var(--gray-300);
    white-space: nowrap;
  }

  .table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
  }

  .table tbody tr {
    transition: background 0.2s ease;
  }

  .table tbody tr:hover {
    background: var(--gray-50);
  }

  .actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
  }

  details {
    margin-top: 0.5rem;
  }

  details summary {
    cursor: pointer;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
  }

  details[open] summary {
    margin-bottom: 1rem;
  }

  details .edit-form {
    padding: 1rem;
    background: var(--gray-50);
    border-radius: 8px;
    display: grid;
    gap: 1rem;
  }

  @media (max-width: 768px) {
    .container {
      padding: 1rem;
    }
    .form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-shield-alt"></i>
    Panel de Administración
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="dashboard_ext.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-box-open"></i>
      <span>Productos (Ext)</span>
    </a>
    <a class="nav-btn" href="sales_admin.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="affiliates.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-users"></i>
      <span>Afiliados</span>
    </a>
    <a class="nav-btn" href="settings_fee.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-dollar-sign"></i>
      <span>Costo Espacio</span>
    </a>
    <a class="nav-btn" href="email_marketing.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-envelope"></i>
      <span>Email Marketing</span>
    </a>
    <a class="nav-btn" href="../tools/sql_exec.php" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-database"></i>
      <span>SQL Tools</span>
    </a>
    <a class="nav-btn" href="logout.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-sign-out-alt"></i>
      <span>Salir</span>
    </a>
  </nav>
</header>

<div class="container">
  <h2 style="margin-bottom: 2rem; color: var(--primary);">
    <i class="fas fa-users"></i> Gestión de Afiliados
  </h2>

  <?php if($msg): ?>
  <div class="alert">
    <i class="fas fa-info-circle"></i>
    <span><?= h($msg) ?></span>
  </div>
  <?php endif; ?>

  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-user-plus"></i> Crear Nuevo Afiliado
    </h3>
    <form method="post">
      <div class="form-grid">
        <div class="form-group">
          <label>
            <i class="fas fa-id-card"></i> Nombre
          </label>
          <input class="input" name="name" required>
        </div>
        <div class="form-group">
          <label>
            <i class="fas fa-envelope"></i> Correo Electrónico
          </label>
          <input class="input" name="email" type="email" required>
        </div>
        <div class="form-group">
          <label>
            <i class="fas fa-phone"></i> Teléfono
          </label>
          <input class="input" name="phone">
        </div>
        <div class="form-group">
          <label>
            <i class="fas fa-lock"></i> Contraseña (obligatoria al crear)
          </label>
          <input class="input" name="pass" type="password" required>
        </div>
      </div>
      <button class="btn primary" name="create" value="1">
        <i class="fas fa-plus-circle"></i>
        Crear Afiliado
      </button>
    </form>
  </div>

  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-list"></i> Afiliados Registrados (<?= count($rows) ?>)
    </h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Fecha Creación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><strong>#<?= (int)$r['id'] ?></strong></td>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td><?= h($r['phone'] ?: '—') ?></td>
            <td><span style="color: var(--gray-600); font-size: 0.85rem;"><?= h($r['created_at'] ?: '—') ?></span></td>
            <td class="actions">
              <form method="post" style="display:inline-block;max-width:520px">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <details>
                  <summary class="btn secondary">
                    <i class="fas fa-edit"></i>
                    Editar
                  </summary>
                  <div class="edit-form">
                    <div class="form-group">
                      <label>
                        <i class="fas fa-id-card"></i> Nombre
                      </label>
                      <input class="input" name="name" value="<?= h($r['name']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label>
                        <i class="fas fa-envelope"></i> Correo
                      </label>
                      <input class="input" name="email" type="email" value="<?= h($r['email']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label>
                        <i class="fas fa-phone"></i> Teléfono
                      </label>
                      <input class="input" name="phone" value="<?= h($r['phone'] ?: '') ?>">
                    </div>
                    <div class="form-group">
                      <label>
                        <i class="fas fa-lock"></i> Nueva contraseña (opcional)
                      </label>
                      <input class="input" name="pass" type="password" placeholder="Dejar en blanco para no cambiar">
                    </div>
                    <button class="btn primary" name="update" value="1">
                      <i class="fas fa-save"></i>
                      Guardar Cambios
                    </button>
                  </div>
                </details>
              </form>
              <form method="post" onsubmit="return confirm('¿Está seguro de eliminar este afiliado?');" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn danger" name="delete" value="1">
                  <i class="fas fa-trash-alt"></i>
                  Eliminar
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
