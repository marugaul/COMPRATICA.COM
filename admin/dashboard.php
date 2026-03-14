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

// Obtener categorías disponibles
$categories = [];
try {
  $cats = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 ORDER BY display_order ASC");
  $categories = $cats->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[admin/dashboard.php] No se pudieron cargar categorías: ' . $e->getMessage());
}

// ⬅️ NUEVO: Obtener espacios activos
$sales = [];
try {
  $salesStmt = $pdo->query("SELECT s.id, s.title, s.affiliate_id, a.name as affiliate_name
                            FROM sales s
                            LEFT JOIN affiliates a ON a.id = s.affiliate_id
                            WHERE s.is_active=1
                            ORDER BY s.start_at DESC");
  $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[admin/dashboard.php] No se pudieron cargar espacios: ' . $e->getMessage());
}

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

/* ── Empleos Automáticos: importar desde Indeed CR ─────────────────────── */
if ($action === 'run_import_jobs') {
    require_once __DIR__ . '/../includes/shipping_emprendedoras.php'; // solo para autoload db
    $source  = in_array($_POST['job_source'] ?? '', ['indeed', 'remote']) ? $_POST['job_source'] : 'indeed';
    $script  = escapeshellarg(dirname(__DIR__) . '/scripts/import_jobs.php');
    $srcArg  = '--source=' . escapeshellarg($source);
    $importOutput = trim((string)shell_exec("php {$script} {$srcArg} 2>&1")) ?: 'Sin salida';
    $msg     = "✅ Importación ejecutada.\n" . $importOutput;
}

// Datos para la sección de Empleos Automáticos
$importLogs = [];
$importBySource = [];
try {
    // Intentar cargar tabla — se crea al cargar db()
    $importLogs = $pdo->query("SELECT * FROM job_import_log ORDER BY started_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $importBySource = $pdo->query("
        SELECT import_source, COUNT(*) as total, SUM(is_active) as active, MAX(created_at) as last_import
        FROM job_listings WHERE import_source IS NOT NULL
        GROUP BY import_source ORDER BY last_import DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $_e) { /* tabla aún no existe */ }

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
    $category = trim($_POST['category'] ?? '');
    $currency = strtoupper(trim($_POST['currency'] ?? 'CRC'));
    if (!in_array($currency, ['CRC','USD'])) $currency = 'CRC';
    $active = isset($_POST['active']) ? 1 : 0;

    // ⬅️ NUEVO: Obtener sale_id y auto-asignar affiliate_id
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $affiliate_id = null;

    if ($sale_id > 0) {
        // Auto-obtener affiliate_id del espacio
        $stmt = $pdo->prepare("SELECT affiliate_id FROM sales WHERE id=?");
        $stmt->execute([$sale_id]);
        $affiliate_id = (int)$stmt->fetchColumn();

        if (!$affiliate_id) {
            $msg = "Error: Espacio no válido.";
            $sale_id = null;
        }
    }

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

    if ($action === 'create' && !isset($msg)) {
        // ⬅️ MODIFICADO: Incluir affiliate_id y sale_id
        $stmt = $pdo->prepare("INSERT INTO products (affiliate_id, sale_id, name, description, price, stock, image, currency, active, category, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$affiliate_id, $sale_id, $name,$desc,$price,$stock,$imageName,$currency,$active,$category,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);

        // ⬅️ NUEVO: si hay segunda imagen, guardarla
        if ($imageName2 !== null) {
            try { $pdo->prepare("UPDATE products SET image2=? WHERE id=?")->execute([$imageName2, (int)$pdo->lastInsertId()]); }
            catch (Throwable $e) { error_log("[admin/dashboard] image2 INSERT update: ".$e->getMessage()); }
        }

        $msg = "Producto creado.";
    } else if (!isset($msg)) {
        // ⬅️ MODIFICADO: Incluir affiliate_id y sale_id en UPDATE
        if ($imageName) {
            $stmt = $pdo->prepare("UPDATE products SET affiliate_id=?, sale_id=?, name=?, description=?, price=?, stock=?, image=?, currency=?, active=?, category=?, updated_at=? WHERE id=?");
            $stmt->execute([$affiliate_id, $sale_id, $name,$desc,$price,$stock,$imageName,$currency,$active,$category,date('Y-m-d H:i:s'),$id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET affiliate_id=?, sale_id=?, name=?, description=?, price=?, stock=?, currency=?, active=?, category=?, updated_at=? WHERE id=?");
            $stmt->execute([$affiliate_id, $sale_id, $name,$desc,$price,$stock,$currency,$active,$category,date('Y-m-d H:i:s'),$id]);
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
<link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
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
    <a class="nav-btn" href="emprendedoras.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.25)'; this.style.borderColor='rgba(255,255,255,0.5)';" onmouseout="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='rgba(255,255,255,0.3)';">
      <i class="fas fa-crown"></i>
      <span>EMPRENDEDORAS</span>
    </a>
    <a class="nav-btn" href="bienes_raices_config.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.25)'; this.style.borderColor='rgba(255,255,255,0.5)';" onmouseout="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='rgba(255,255,255,0.3)';">
      <i class="fas fa-home"></i>
      <span>BIENES RAICES</span>
    </a>
    <a class="nav-btn" href="servicios_config.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.25)'; this.style.borderColor='rgba(255,255,255,0.5)';" onmouseout="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='rgba(255,255,255,0.3)';">
      <i class="fas fa-briefcase"></i>
      <span>SERVICIOS</span>
    </a>
    <a class="nav-btn" href="empleos_config.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.3);" onmouseover="this.style.background='rgba(255,255,255,0.25)'; this.style.borderColor='rgba(255,255,255,0.5)';" onmouseout="this.style.background='rgba(255,255,255,0.15)'; this.style.borderColor='rgba(255,255,255,0.3)';">
      <i class="fas fa-user-tie"></i>
      <span>EMPLEOS</span>
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
          <label>Espacio de Venta</label>
          <select class="input" name="sale_id" id="sale_id">
            <option value="">Selecciona un espacio (opcional)</option>
            <?php foreach($sales as $sale): ?>
              <option value="<?= (int)$sale['id'] ?>">
                <?= h($sale['title']) ?> - <?= h($sale['affiliate_name'] ?? 'Sin afiliado') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Categoría</label>
          <select class="input" name="category" id="category">
            <option value="">Selecciona una categoría (opcional)</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?= h($cat['name']) ?>">
                <?= h($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
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
            <th>ID</th><th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Moneda</th><th>Stock</th><th>Activo</th>
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
            <td>
              <?php if (!empty($p['category'])): ?>
                <span style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05)); border: 1px solid rgba(52, 152, 219, 0.3); color: #2980b9; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 500;">
                  <?= h($p['category']) ?>
                </span>
              <?php else: ?>
                <span style="color: #999; font-style: italic;">—</span>
              <?php endif; ?>
            </td>
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

  <!-- ═══════════════════════════════════════════════════════════
       SECCIÓN: EMPLEOS AUTOMÁTICOS
  ════════════════════════════════════════════════════════════════ -->
  <div class="section" id="empleos-automaticos" style="border:2px solid #e0e7ff;">
    <h3 class="section-title" style="color:#4f46e5;">
      <i class="fas fa-robot" style="color:#4f46e5;"></i> Empleos Automáticos
      <span style="font-size:.78rem;font-weight:400;color:#6b7280;margin-left:10px;">
        Importación desde Indeed Costa Rica — empleos reales, sin costo
      </span>
    </h3>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;">
      <!-- Ejecutar importación -->
      <div style="background:#f5f3ff;border:1px solid #c7d2fe;border-radius:12px;padding:18px;">
        <p style="margin:0 0 12px;font-weight:700;color:#4f46e5;"><i class="fas fa-download"></i> Importar ahora</p>
        <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="action" value="run_import_jobs">
          <select name="job_source" style="padding:8px 12px;border:2px solid #c7d2fe;border-radius:8px;font-size:.88rem;flex:1;min-width:180px;">
            <option value="indeed">Indeed Costa Rica (empleos locales)</option>
            <option value="remote">Empleos Remotos Internacionales</option>
          </select>
          <button type="submit" style="background:#4f46e5;color:white;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;white-space:nowrap;">
            <i class="fas fa-play"></i> Ejecutar
          </button>
        </form>
        <p style="margin:10px 0 0;font-size:.78rem;color:#6b7280;">
          <i class="fas fa-info-circle"></i> Importa empleos reales de Indeed CR por RSS (sin API key). Los duplicados se omiten. Cada empleo expira en 30 días.
        </p>
      </div>

      <!-- Cron Job -->
      <div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:18px;">
        <p style="margin:0 0 10px;font-weight:700;color:#374151;"><i class="fas fa-clock" style="color:#f59e0b;"></i> Cron automático (cPanel)</p>
        <code style="background:#1e293b;color:#e2e8f0;display:block;padding:10px 12px;border-radius:8px;font-size:.75rem;word-break:break-all;line-height:1.6;">
          0 6 * * * php <?= dirname(dirname(__DIR__)) ?>/scripts/import_jobs.php --source=indeed
        </code>
        <p style="margin:8px 0 0;font-size:.78rem;color:#6b7280;">
          Pega este comando en <em>cPanel → Cron Jobs → Custom</em> para importar diariamente a las 6am.
        </p>
      </div>
    </div>

    <!-- Estadísticas por fuente -->
    <?php if (!empty($importBySource)): ?>
    <p style="margin:0 0 10px;font-weight:700;font-size:.9rem;color:#374151;">Empleos importados por fuente:</p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;">
      <?php foreach ($importBySource as $src): ?>
      <div style="background:white;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;min-width:180px;">
        <div style="font-family:monospace;font-size:.8rem;color:#6b7280;margin-bottom:4px;"><?= htmlspecialchars($src['import_source']) ?></div>
        <div style="font-size:1.3rem;font-weight:800;color:#4f46e5;"><?= (int)$src['active'] ?></div>
        <div style="font-size:.78rem;color:#9ca3af;"><?= (int)$src['total'] ?> total · último: <?= substr($src['last_import'],0,10) ?></div>
        <form method="POST" style="margin-top:8px;" onsubmit="return confirm('¿Eliminar todos los empleos de esta fuente?')">
          <input type="hidden" name="action" value="run_import_jobs">
          <input type="hidden" name="job_source" value="delete_source_<?= htmlspecialchars($src['import_source']) ?>">
          <!-- Usamos link directo al panel dedicado -->
        </form>
        <a href="import_jobs.php" style="font-size:.78rem;color:#4f46e5;text-decoration:none;">
          <i class="fas fa-external-link-alt"></i> Gestionar
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Últimas importaciones -->
    <?php if (!empty($importLogs)): ?>
    <p style="margin:0 0 8px;font-weight:700;font-size:.9rem;color:#374151;">Últimas importaciones:</p>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
        <thead>
          <tr style="background:#f1f5f9;">
            <th style="padding:8px 10px;text-align:left;color:#6b7280;">Fuente</th>
            <th style="padding:8px 10px;color:#6b7280;">Fecha</th>
            <th style="padding:8px 10px;color:#6b7280;">Nuevos</th>
            <th style="padding:8px 10px;color:#6b7280;">Dupl.</th>
            <th style="padding:8px 10px;color:#6b7280;">Errores</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($importLogs, 0, 10) as $log): ?>
          <tr style="border-bottom:1px solid #f0f0f0;">
            <td style="padding:7px 10px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['source']) ?>">
              <?= htmlspecialchars(substr($log['source'], 0, 50)) ?>
            </td>
            <td style="padding:7px 10px;color:#6b7280;white-space:nowrap;"><?= substr($log['started_at'], 0, 16) ?></td>
            <td style="padding:7px 10px;">
              <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-weight:700;">+<?= (int)$log['inserted'] ?></span>
            </td>
            <td style="padding:7px 10px;color:#9ca3af;"><?= (int)$log['skipped'] ?></td>
            <td style="padding:7px 10px;">
              <?php if ($log['errors'] > 0): ?>
              <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;"><?= (int)$log['errors'] ?></span>
              <?php else: ?>
              <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a href="import_jobs.php" style="display:inline-block;margin-top:10px;color:#4f46e5;font-size:.85rem;font-weight:600;text-decoration:none;">
      <i class="fas fa-arrow-right"></i> Ver panel completo de importación
    </a>
    <?php else: ?>
    <p style="color:#9ca3af;font-size:.88rem;margin:0;">
      Aún no se ha ejecutado ninguna importación. Haz clic en <strong>Ejecutar</strong> para importar los primeros empleos.
    </p>
    <?php endif; ?>
  </div>
  <!-- FIN EMPLEOS AUTOMÁTICOS -->

</div>

<script>
function fillForm(p){
  document.getElementById('id').value = p.id || '';
  document.getElementById('name').value = p.name || '';
  document.getElementById('price').value = p.price || 0;
  document.getElementById('currency').value = (p.currency || 'CRC').toUpperCase();
  document.getElementById('stock').value = p.stock || 0;
  document.getElementById('description').value = p.description || '';
  document.getElementById('sale_id').value = p.sale_id || ''; // ⬅️ NUEVO
  document.getElementById('category').value = p.category || '';
  document.getElementById('active').checked = p.active == 1 || p.active === '1';
  window.scrollTo({top:0, behavior:'smooth'});
}
</script>
</body>
</html>
