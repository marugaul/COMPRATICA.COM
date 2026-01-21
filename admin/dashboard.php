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
// Cargar productos (con manejo de error si tablas no existen)
try {
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
} catch (Exception $e) {
    // Si tablas sales/affiliates no existen, cargar solo productos
    try {
        $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $products = [];
    }
}

// Cargar órdenes
try {
    $orders = $pdo->query("
      SELECT o.*, p.name AS product_name
      FROM orders o
      JOIN products p ON p.id=o.product_id
      ORDER BY o.created_at DESC
      LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$ex = (float)($settings['exchange_rate'] ?? 540.00);

// Cargar affiliates (con manejo de error si tabla no existe)
try {
    $affiliates = $pdo->query("
      SELECT id, name, email, phone, is_active, created_at
      FROM affiliates
      ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $affiliates = [];
}

// Estadísticas para dashboard
$stats = [
    'total_products' => count($products),
    'active_products' => count(array_filter($products, fn($p) => $p['active'])),
    'total_orders' => count($orders),
    'pending_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'Pendiente')),
    'total_affiliates' => count($affiliates),
    'active_affiliates' => count(array_filter($affiliates, fn($a) => $a['is_active'])),
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel de Administración - <?php echo h(APP_NAME); ?></title>
<link rel="stylesheet" href="../assets/style.css?v=24">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  /* Variables corporativas */
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

  /* Dashboard grid */
  .dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  .stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }

  .stat-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
  }

  .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
  }

  .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
  .stat-icon.green { background: #e8f5e9; color: #388e3c; }
  .stat-icon.orange { background: #fff3e0; color: #f57c00; }
  .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }
  .stat-icon.red { background: #ffebee; color: #c62828; }

  .stat-title {
    font-size: 0.9rem;
    color: #666;
    font-weight: 500;
  }

  .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin: 0.5rem 0;
  }

  .stat-subtitle {
    font-size: 0.85rem;
    color: #999;
  }

  /* Container y secciones */
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

  /* Tablas */
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

  .thumb {
    max-width: 60px;
    max-height: 60px;
    width: auto;
    height: auto;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--gray-200);
    transition: transform 0.2s ease;
  }

  .thumb:hover {
    transform: scale(1.05);
    border-color: var(--accent);
  }

  /* Mensajes */
  .success {
    background: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.3);
    border-left: 4px solid var(--success);
    color: #155724;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  /* Formularios */
  .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
  }

  .form-group {
    display: flex;
    flex-direction: column;
  }

  .form-group label {
    font-weight: 500;
    color: var(--gray-800);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
  }

  .input, select, textarea {
    padding: 0.75rem;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
  }

  .input:focus, select:focus, textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
  }

  /* Botones */
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
    background: var(--gray-200);
    color: var(--gray-800);
  }

  .btn:hover {
    background: var(--gray-300);
    transform: translateY(-1px);
  }

  .btn.primary {
    background: linear-gradient(135deg, var(--accent) 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3);
  }

  .btn.primary:hover {
    box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4);
    transform: translateY(-2px);
  }

  .btn.success {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
    color: white;
  }

  .btn.danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: white;
  }

  .actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
  }

  .small {
    font-size: 0.85rem;
    color: var(--gray-600);
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
  <?php if(!empty($msg)): ?>
    <div class="success">
      <i class="fas fa-check-circle"></i>
      <?php echo h($msg); ?>
    </div>
  <?php endif; ?>

  <h2 style="margin-bottom: 2rem; color: var(--primary);">
    <i class="fas fa-chart-line"></i> Panel de Control
  </h2>

  <!-- Estadísticas principales -->
  <div class="dashboard-grid">
    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon blue">
          <i class="fas fa-box"></i>
        </div>
        <div>
          <div class="stat-title">Productos Totales</div>
        </div>
      </div>
      <div class="stat-value"><?= $stats['total_products'] ?></div>
      <div class="stat-subtitle"><?= $stats['active_products'] ?> activos</div>
    </div>

    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon orange">
          <i class="fas fa-shopping-cart"></i>
        </div>
        <div>
          <div class="stat-title">Pedidos Totales</div>
        </div>
      </div>
      <div class="stat-value"><?= $stats['total_orders'] ?></div>
      <div class="stat-subtitle"><?= $stats['pending_orders'] ?> pendientes</div>
    </div>

    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon green">
          <i class="fas fa-users"></i>
        </div>
        <div>
          <div class="stat-title">Afiliados Totales</div>
        </div>
      </div>
      <div class="stat-value"><?= $stats['total_affiliates'] ?></div>
      <div class="stat-subtitle"><?= $stats['active_affiliates'] ?> activos</div>
    </div>
  </div>

  <!-- Configuración -->
  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-cog"></i> Configuración
    </h3>
    <form method="post">
      <input type="hidden" name="action" value="save_settings">
      <div class="form-grid">
        <div class="form-group">
          <label>Tipo de cambio (CRC por 1 USD)</label>
          <input class="input" type="number" name="exchange_rate" step="0.01" min="100" value="<?php echo h(number_format($ex,2,'.','')); ?>">
        </div>
      </div>
      <div class="actions">
        <button class="btn primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>

  <!-- Crear / Editar producto -->
  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-plus-circle"></i> Crear / Editar Producto
    </h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" id="id">
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre</label>
          <input class="input" type="text" name="name" id="name" required>
        </div>
        <div class="form-group">
          <label>Precio</label>
          <input class="input" type="number" name="price" id="price" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label>Moneda</label>
          <select class="input" name="currency" id="currency">
            <option value="CRC">CRC (₡)</option>
            <option value="USD">USD ($)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Inventario</label>
          <input class="input" type="number" name="stock" id="stock" min="0" required>
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Descripción</label>
          <textarea class="input" name="description" id="description" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label>Imagen Principal</label>
          <input class="input" type="file" name="image" accept="image/*">
        </div>
        <div class="form-group">
          <label>Imagen 2 (opcional)</label>
          <input class="input" type="file" name="image2" accept="image/*">
        </div>
        <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
          <input type="checkbox" name="active" id="active" checked style="width: auto;">
          <label for="active" style="margin: 0;">Activo</label>
        </div>
      </div>
      <div class="actions">
        <button class="btn primary" name="action" value="create" type="submit">
          <i class="fas fa-plus"></i> Crear
        </button>
        <button class="btn success" name="action" value="update" type="submit">
          <i class="fas fa-save"></i> Actualizar
        </button>
      </div>
    </form>
  </div>

  <!-- Productos -->
  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-box-open"></i> Productos (<?= count($products) ?>)
    </h3>
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
            <td><?= $p['active'] ? '✅ Sí' : '❌ No' ?></td>
            <td class="small"><?= h($p['sale_title'] ?? '—') ?></td>
            <td class="small"><?= h($p['aff_email'] ?? '—') ?></td>
            <td class="small"><?= h($p['fee_status'] ?? '—') ?></td>
            <td><?= $p['sale_active'] ? '✅ Sí' : '❌ No' ?></td>
            <td>
              <button class="btn" onclick='fillForm(<?= json_encode($p, JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                <i class="fas fa-edit"></i> Editar
              </button>
              <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar producto?');">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn danger" name="action" value="delete">
                  <i class="fas fa-trash"></i> Eliminar
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pedidos -->
  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-shopping-cart"></i> Pedidos Recientes (últimos 100)
    </h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>Fecha</th><th>Producto</th><th>Cant</th><th>Cliente</th><th>Residencia</th><th>Estado</th><th>Comprobante</th><th>Acción</th></tr>
        </thead>
        <tbody>
          <?php foreach($orders as $o): ?>
          <tr>
            <td><strong>#<?= (int)$o['id'] ?></strong></td>
            <td class="small"><?= h($o['created_at']) ?></td>
            <td><?= h($o['product_name']) ?></td>
            <td><strong><?= (int)$o['qty'] ?></strong></td>
            <td class="small"><?= h($o['buyer_email'] . " / " . $o['buyer_phone']) ?></td>
            <td class="small"><?= h($o['residency']) ?></td>
            <td><strong><?= h($o['status']) ?></strong></td>
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
                <select name="status" class="input" style="padding:6px;min-width:120px;">
                  <?php foreach(['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'] as $st): ?>
                    <option value="<?= h($st) ?>" <?= $o['status']===$st?'selected':'' ?>><?= h($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn primary"><i class="fas fa-save"></i> Guardar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Afiliados -->
  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-users"></i> Afiliados (<?= count($affiliates) ?>)
    </h3>
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
                <button class="btn <?= $a['is_active'] ? 'danger' : 'success' ?>">
                  <i class="fas fa-<?= $a['is_active'] ? 'ban' : 'check' ?>"></i>
                  <?= $a['is_active'] ? 'Desactivar' : 'Activar' ?>
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
</html>
