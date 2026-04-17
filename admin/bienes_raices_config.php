<?php
// admin/bienes_raices_config.php
// Configuración de planes de BIENES RAICES
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

// Helper para escapar HTML
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$msg = '';
$msgType = 'success';

// Procesar actualizaciones de planes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 0);
        $price_usd = (float)($_POST['price_usd'] ?? 0);
        $price_crc = (float)($_POST['price_crc'] ?? 0);
        $max_photos = (int)($_POST['max_photos'] ?? 3);
        $payment_methods = $_POST['payment_methods'] ?? [];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');

        // Convertir array de métodos de pago a string separado por comas
        $payment_methods_str = is_array($payment_methods) ? implode(',', $payment_methods) : '';

        if (empty($payment_methods_str)) {
            $msg = "❌ Debe seleccionar al menos un método de pago";
            $msgType = 'error';
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE listing_pricing
                    SET name = ?,
                        duration_days = ?,
                        price_usd = ?,
                        price_crc = ?,
                        max_photos = ?,
                        payment_methods = ?,
                        is_active = ?,
                        is_featured = ?,
                        description = ?,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $duration_days,
                    $price_usd,
                    $price_crc,
                    $max_photos,
                    $payment_methods_str,
                    $is_active,
                    $is_featured,
                    $description,
                    $id
                ]);
                $msg = "✅ Plan actualizado correctamente";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error al actualizar: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'create_plan') {
        $name = trim($_POST['name'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 0);
        $price_usd = (float)($_POST['price_usd'] ?? 0);
        $price_crc = (float)($_POST['price_crc'] ?? 0);
        $max_photos = (int)($_POST['max_photos'] ?? 3);
        $payment_methods = $_POST['payment_methods'] ?? [];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');
        $display_order = (int)($_POST['display_order'] ?? 999);

        // Convertir array de métodos de pago a string
        $payment_methods_str = is_array($payment_methods) ? implode(',', $payment_methods) : '';

        if (empty($name) || $duration_days <= 0) {
            $msg = "❌ El nombre y la duración son obligatorios";
            $msgType = 'error';
        } elseif (empty($payment_methods_str)) {
            $msg = "❌ Debe seleccionar al menos un método de pago";
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO listing_pricing
                    (name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active, is_featured, description, display_order, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                $stmt->execute([
                    $name,
                    $duration_days,
                    $price_usd,
                    $price_crc,
                    $max_photos,
                    $payment_methods_str,
                    $is_active,
                    $is_featured,
                    $description,
                    $display_order
                ]);
                $msg = "✅ Plan creado correctamente";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error al crear plan: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'delete_plan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Verificar si hay publicaciones usando este plan
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM real_estate_listings WHERE pricing_plan_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                if ($count > 0) {
                    $msg = "❌ No se puede eliminar este plan porque hay {$count} publicación(es) activas usando este plan";
                    $msgType = 'error';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM listing_pricing WHERE id = ?");
                    $stmt->execute([$id]);
                    $msg = "✅ Plan eliminado correctamente";
                    $msgType = 'success';
                }
            } catch (Exception $e) {
                $msg = "❌ Error al eliminar: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'approve_payment') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE real_estate_listings
                    SET payment_status = 'confirmed',
                        is_active = 1,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$listing_id]);
                $msg = "✅ Pago aprobado correctamente. La publicación está ahora activa.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error al aprobar pago: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'reject_payment') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE real_estate_listings
                    SET payment_status = 'rejected',
                        is_active = 0,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$listing_id]);
                $msg = "✅ Pago rechazado. La publicación ha sido desactivada.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error al rechazar pago: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'deactivate_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $pdo->prepare("UPDATE real_estate_listings SET is_active = 0, updated_at = datetime('now') WHERE id = ?")
                    ->execute([$listing_id]);
                $msg = "✅ Publicación desactivada.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'activate_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $pdo->prepare("UPDATE real_estate_listings SET is_active = 1, updated_at = datetime('now') WHERE id = ?")
                    ->execute([$listing_id]);
                $msg = "✅ Publicación activada.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'delete_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $pdo->prepare("DELETE FROM real_estate_listings WHERE id = ?")
                    ->execute([$listing_id]);
                $msg = "✅ Publicación eliminada.";
                $msgType = 'success';
            } catch (Exception $e) {
                $msg = "❌ Error: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'update_listing') {
        $listing_id = (int)($_POST['listing_id'] ?? 0);
        if ($listing_id > 0) {
            try {
                $title           = trim($_POST['title'] ?? '');
                $description     = trim($_POST['description'] ?? '');
                $category_id     = (int)($_POST['category_id'] ?? 0);
                $price           = (float)($_POST['price'] ?? 0);
                $currency        = trim($_POST['currency'] ?? 'CRC');
                $listing_type    = trim($_POST['listing_type'] ?? 'sale');
                $province        = trim($_POST['province'] ?? '');
                $canton          = trim($_POST['canton'] ?? '');
                $district        = trim($_POST['district'] ?? '');
                $location        = trim($_POST['location'] ?? '');
                $bedrooms        = (int)($_POST['bedrooms'] ?? 0);
                $bathrooms       = (int)($_POST['bathrooms'] ?? 0);
                $area_m2         = (float)($_POST['area_m2'] ?? 0);
                $parking_spaces  = (int)($_POST['parking_spaces'] ?? 0);
                $contact_name    = trim($_POST['contact_name'] ?? '');
                $contact_phone   = trim($_POST['contact_phone'] ?? '');
                $contact_email   = trim($_POST['contact_email'] ?? '');
                $contact_whatsapp = trim($_POST['contact_whatsapp'] ?? '');
                $pricing_plan_id = (int)($_POST['pricing_plan_id'] ?? 0);
                $payment_status  = trim($_POST['payment_status'] ?? 'confirmed');
                $is_active       = isset($_POST['is_active']) ? 1 : 0;
                $is_featured     = isset($_POST['is_featured']) ? 1 : 0;

                // Características
                $selected_features = $_POST['features'] ?? [];
                $features_json = json_encode(array_values($selected_features));

                // Imágenes: viene como JSON desde el hidden input
                $images_raw = trim($_POST['listing_images_json'] ?? '[]');
                $images_arr = json_decode($images_raw, true);
                if (!is_array($images_arr)) $images_arr = [];
                $images_json = json_encode(array_values($images_arr));

                if (empty($title)) {
                    $msg = "❌ El título es obligatorio";
                    $msgType = 'error';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE real_estate_listings SET
                            title = ?,
                            description = ?,
                            category_id = ?,
                            price = ?,
                            currency = ?,
                            listing_type = ?,
                            province = ?,
                            canton = ?,
                            district = ?,
                            location = ?,
                            bedrooms = ?,
                            bathrooms = ?,
                            area_m2 = ?,
                            parking_spaces = ?,
                            contact_name = ?,
                            contact_phone = ?,
                            contact_email = ?,
                            contact_whatsapp = ?,
                            pricing_plan_id = ?,
                            payment_status = ?,
                            is_active = ?,
                            is_featured = ?,
                            features = ?,
                            images = ?,
                            updated_at = datetime('now')
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $description, $category_id, $price, $currency,
                        $listing_type, $province, $canton, $district, $location,
                        $bedrooms, $bathrooms, $area_m2, $parking_spaces,
                        $contact_name, $contact_phone, $contact_email, $contact_whatsapp,
                        $pricing_plan_id ?: null, $payment_status, $is_active, $is_featured,
                        $features_json, $images_json,
                        $listing_id
                    ]);
                    $msg = "✅ Publicación #{$listing_id} actualizada correctamente.";
                    $msgType = 'success';
                }
            } catch (Exception $e) {
                $msg = "❌ Error al actualizar: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

// Cargar todos los planes
$plans = $pdo->query("
    SELECT
        p.*,
        (SELECT COUNT(*) FROM real_estate_listings WHERE pricing_plan_id = p.id) as listings_count
    FROM listing_pricing p
    ORDER BY display_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Categorías de bienes raíces
$br_categories = [];
try {
    $br_categories = $pdo->query("SELECT id, name FROM categories WHERE active=1 AND name LIKE 'BR:%' ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[bienes_raices_config] Error cargando categorías: ' . $e->getMessage());
}

// Características disponibles (igual que en create/edit-listing)
$available_features = [
    'Piscina','Jardín','Terraza','Balcón','Aire Acondicionado',
    'Calentador de Agua','Cocina Equipada','Línea Blanca','Amueblado',
    'Seguridad 24/7','Portón Eléctrico','Cisterna','Tanque de Agua',
    'Zona de Lavandería','Cuarto de Servicio','Área de BBQ','Gimnasio',
    'Sala de Juegos','Oficina/Estudio','Walk-in Closet'
];

// Cargar publicaciones pendientes de pago
$pending_listings = $pdo->query("
    SELECT
        l.*,
        p.name as plan_name,
        p.price_usd,
        p.price_crc,
        p.payment_methods,
        a.name as agent_name,
        a.email as agent_email,
        a.phone as agent_phone
    FROM real_estate_listings l
    LEFT JOIN listing_pricing p ON l.pricing_plan_id = p.id
    LEFT JOIN real_estate_agents a ON l.agent_id = a.id
    WHERE l.payment_status IN ('pending', 'pending_review')
    ORDER BY l.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Cargar TODAS las publicaciones (activas, expiradas, rechazadas)
$all_listings = $pdo->query("
    SELECT
        l.*,
        p.name as plan_name,
        p.duration_days,
        a.name as agent_name,
        a.email as agent_email,
        a.phone as agent_phone
    FROM real_estate_listings l
    LEFT JOIN listing_pricing p ON l.pricing_plan_id = p.id
    LEFT JOIN real_estate_agents a ON l.agent_id = a.id
    WHERE l.payment_status NOT IN ('pending', 'pending_review')
    ORDER BY l.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuración de Planes - Bienes Raíces</title>
<link rel="stylesheet" href="../assets/style.css?v=25">
<link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
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

  .header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    padding: 1rem 2rem;
    margin-bottom: 2rem;
  }

  .header .logo {
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
  }

  .nav-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
  }

  .nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid rgba(255,255,255,0.2);
  }

  .nav-btn:hover {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.4);
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

  .message {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .message.success {
    background: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.3);
    border-left: 4px solid var(--success);
    color: #155724;
  }

  .message.error {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid rgba(231, 76, 60, 0.3);
    border-left: 4px solid var(--danger);
    color: #721c24;
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

  .table tbody tr:hover {
    background: var(--gray-50);
  }

  .badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8125rem;
    font-weight: 500;
    display: inline-block;
  }

  .badge.success {
    background: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.3);
    color: #155724;
  }

  .badge.danger {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid rgba(231, 76, 60, 0.3);
    color: #721c24;
  }

  .badge.info {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.3);
    color: #0c5460;
  }

  .badge.warning {
    background: rgba(243, 156, 18, 0.1);
    border: 1px solid rgba(243, 156, 18, 0.3);
    color: #856404;
  }

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

  .checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
  }

  .checkbox-label input[type="checkbox"] {
    width: auto;
    cursor: pointer;
  }

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

  .btn.warning {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
    color: white;
  }

  .actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    flex-wrap: wrap;
  }

  .plan-card {
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
  }

  .plan-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: var(--accent);
  }

  .plan-card.featured {
    border-color: var(--warning);
    background: rgba(243, 156, 18, 0.02);
  }

  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }

  .modal.active {
    display: flex;
  }

  .modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
  }

  .modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray-600);
  }

  .modal-close:hover {
    color: var(--danger);
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

<div class="header">
  <div class="logo">
    <i class="fas fa-home"></i>
    Configuración de Planes - Bienes Raíces
  </div>
  <div class="nav-buttons">
    <a class="nav-btn" href="dashboard.php">
      <i class="fas fa-arrow-left"></i>
      <span>Volver al Dashboard</span>
    </a>
    <a class="nav-btn" href="/bienes-raices.php" target="_blank">
      <i class="fas fa-building"></i>
      <span>Ver Catálogo</span>
    </a>
    <a class="nav-btn" href="logout.php">
      <i class="fas fa-sign-out-alt"></i>
      <span>Salir</span>
    </a>
  </div>
</div>

<div class="container">
  <?php if(!empty($msg)): ?>
    <div class="message <?= $msgType ?>">
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <!-- Resumen de planes -->
  <div class="section">
    <h2 class="section-title">
      <i class="fas fa-chart-bar"></i>
      Resumen de Planes
    </h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
      <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 1.5rem; border-radius: 12px;">
        <div style="font-size: 2rem; font-weight: bold;"><?= count($plans) ?></div>
        <div style="opacity: 0.9;">Planes Totales</div>
      </div>
      <div style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; padding: 1.5rem; border-radius: 12px;">
        <div style="font-size: 2rem; font-weight: bold;"><?= count(array_filter($plans, fn($p) => $p['is_active'])) ?></div>
        <div style="opacity: 0.9;">Planes Activos</div>
      </div>
      <div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 1.5rem; border-radius: 12px;">
        <div style="font-size: 2rem; font-weight: bold;"><?= count(array_filter($plans, fn($p) => $p['is_featured'])) ?></div>
        <div style="opacity: 0.9;">Planes Destacados</div>
      </div>
      <div style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); color: white; padding: 1.5rem; border-radius: 12px;">
        <div style="font-size: 2rem; font-weight: bold;"><?= array_sum(array_column($plans, 'listings_count')) ?></div>
        <div style="opacity: 0.9;">Publicaciones Activas</div>
      </div>
    </div>
  </div>

  <!-- Publicaciones Pendientes de Pago -->
  <?php if (!empty($pending_listings)): ?>
  <div class="section">
    <h2 class="section-title">
      <i class="fas fa-clock"></i>
      Publicaciones Pendientes de Pago
      <span class="badge warning" style="margin-left: 1rem;"><?= count($pending_listings) ?> pendientes</span>
    </h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Agente</th>
            <th>Plan</th>
            <th>Precio</th>
            <th>Métodos de Pago</th>
            <th>Fecha Creación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($pending_listings as $listing):
            $payment_methods = explode(',', $listing['payment_methods'] ?? '');
          ?>
          <tr style="background: rgba(243, 156, 18, 0.05);">
            <td><strong><?= $listing['id'] ?></strong>
              <?php if (($listing['payment_status'] ?? '') === 'pending_review'): ?>
                <br><span class="badge info" style="font-size:.7rem">Comprobante adjunto</span>
              <?php else: ?>
                <br><span class="badge warning" style="font-size:.7rem">Sin comprobante</span>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= h($listing['title']) ?></strong>
              <br><small style="color: var(--gray-600);"><?= h(substr($listing['description'] ?? '', 0, 50)) ?>...</small>
            </td>
            <td>
              <strong><?= h($listing['agent_name']) ?></strong>
              <br><small style="color: var(--gray-600);"><?= h($listing['agent_email']) ?></small>
              <?php if ($listing['agent_phone']): ?>
                <br><small style="color: var(--gray-600);"><i class="fas fa-phone"></i> <?= h($listing['agent_phone']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= h($listing['plan_name']) ?></strong>
            </td>
            <td>
              <?php if ($listing['price_usd'] > 0): ?>
                <strong>$<?= number_format($listing['price_usd'], 2) ?> USD</strong>
              <?php endif; ?>
              <?php if ($listing['price_crc'] > 0): ?>
                <br><strong>₡<?= number_format($listing['price_crc'], 0) ?> CRC</strong>
              <?php endif; ?>
            </td>
            <td>
              <?php
              $methodLabels = [];
              if (in_array('sinpe', $payment_methods)) $methodLabels[] = '<span class="badge info">SINPE</span>';
              if (in_array('paypal', $payment_methods)) $methodLabels[] = '<span class="badge success">PayPal</span>';
              echo implode(' ', $methodLabels);
              ?>
            </td>
            <td>
              <small><?= date('d/m/Y H:i', strtotime($listing['created_at'])) ?></small>
            </td>
            <td>
              <form method="post" style="display:inline; margin-right: 0.5rem;" onsubmit="return confirm('¿Confirmar que el pago fue recibido?');">
                <input type="hidden" name="action" value="approve_payment">
                <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                <button class="btn success" type="submit" title="Aprobar pago">
                  <i class="fas fa-check"></i> Aprobar
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('¿Rechazar este pago?');">
                <input type="hidden" name="action" value="reject_payment">
                <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                <button class="btn danger" type="submit" title="Rechazar pago">
                  <i class="fas fa-times"></i> Rechazar
                </button>
              </form>
              <a href="../propiedad-detalle?id=<?= $listing['id'] ?>" class="btn" target="_blank" title="Ver publicación" style="display: inline-flex; align-items: center; margin-left: 0.5rem;">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="alert info" style="margin-top: 1rem;">
      <i class="fas fa-info-circle"></i>
      <strong>Instrucciones:</strong>
      <ul style="margin: 0.5rem 0 0 1.5rem;">
        <li><strong>SINPE:</strong> Verificar manualmente el comprobante enviado por el cliente antes de aprobar.</li>
        <li><strong>PayPal:</strong> Los pagos exitosos por PayPal se aprueban automáticamente.</li>
        <li>Al aprobar, la publicación se activará inmediatamente y será visible en el sitio.</li>
        <li>Al rechazar, la publicación permanecerá desactivada.</li>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <!-- Todas las publicaciones -->
  <div class="section">
    <h2 class="section-title">
      <i class="fas fa-home"></i>
      Todas las Publicaciones
      <span class="badge info" style="margin-left:1rem"><?= count($all_listings) ?> publicaciones</span>
    </h2>
    <?php if (empty($all_listings)): ?>
      <p style="color:var(--gray-600);padding:1rem">No hay publicaciones registradas.</p>
    <?php else: ?>
    <div style="margin-bottom:1rem">
      <input type="text" id="listingSearch" onkeyup="filterListings()" placeholder="Buscar por título, agente..." style="padding:.6rem 1rem;border:1px solid var(--gray-300);border-radius:8px;width:280px;font-size:.9rem">
    </div>
    <div class="table-wrap">
      <table class="table" id="listingsTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Propiedad</th>
            <th>Agente</th>
            <th>Plan</th>
            <th>Estado</th>
            <th>Pago</th>
            <th>Creado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_listings as $l): ?>
          <?php
            $isActive  = (bool)$l['is_active'];
            $payStatus = $l['payment_status'] ?? '';
            $rowBg = '';
            if ($isActive)                    $rowBg = 'background:rgba(39,174,96,.04)';
            elseif ($payStatus === 'rejected') $rowBg = 'background:rgba(231,76,60,.04)';
          ?>
          <tr style="<?= $rowBg ?>">
            <td><strong><?= $l['id'] ?></strong></td>
            <td>
              <strong><?= h($l['title']) ?></strong>
              <?php if (!empty($l['property_type'])): ?>
                <br><small style="color:var(--gray-600)"><?= h($l['property_type']) ?> · <?= h($l['operation_type'] ?? '') ?></small>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= h($l['agent_name']) ?></strong>
              <br><small style="color:var(--gray-600)"><?= h($l['agent_email']) ?></small>
              <?php if ($l['agent_phone']): ?>
                <br><small style="color:var(--gray-600)"><i class="fas fa-phone"></i> <?= h($l['agent_phone']) ?></small>
              <?php endif; ?>
            </td>
            <td><small><?= h($l['plan_name'] ?? '—') ?></small></td>
            <td>
              <?php if ($isActive): ?>
                <span class="badge success"><i class="fas fa-check"></i> Activa</span>
              <?php elseif ($payStatus === 'rejected'): ?>
                <span class="badge danger"><i class="fas fa-times"></i> Rechazada</span>
              <?php elseif ($payStatus === 'free'): ?>
                <span class="badge info">Gratuita</span>
              <?php else: ?>
                <span class="badge" style="background:#e2e8f0;color:#4a5568">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $pBadge = [
                  'confirmed' => ['success', 'Confirmado'],
                  'free'      => ['info',    'Gratis'],
                  'rejected'  => ['danger',  'Rechazado'],
                  'pending'   => ['warning', 'Pendiente'],
                  'pending_review' => ['warning', 'En revisión'],
                ];
                [$bc, $bl] = $pBadge[$payStatus] ?? ['', $payStatus];
              ?>
              <span class="badge <?= $bc ?>"><?= $bl ?></span>
            </td>
            <td><small><?= date('d/m/Y', strtotime($l['created_at'])) ?></small></td>
            <td>
              <button class="btn primary" onclick='openListingModal(<?= json_encode($l, JSON_UNESCAPED_UNICODE) ?>)' title="Editar"><i class="fas fa-edit"></i> Editar</button>
              <?php if ($isActive): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Desactivar esta publicación?')">
                  <input type="hidden" name="action" value="deactivate_listing">
                  <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                  <button class="btn warning" type="submit" title="Desactivar"><i class="fas fa-pause"></i></button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Activar esta publicación?')">
                  <input type="hidden" name="action" value="activate_listing">
                  <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                  <button class="btn success" type="submit" title="Activar"><i class="fas fa-play"></i></button>
                </form>
              <?php endif; ?>
              <a href="/propiedad-detalle?id=<?= $l['id'] ?>" class="btn" target="_blank" title="Ver publicación" style="display:inline-flex;align-items:center;gap:.3rem;margin-left:.3rem"><i class="fas fa-eye"></i></a>
              <form method="post" style="display:inline;margin-left:.3rem" onsubmit="return confirm('¿ELIMINAR permanentemente esta publicación?')">
                <input type="hidden" name="action" value="delete_listing">
                <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                <button class="btn danger" type="submit" title="Eliminar"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Botón para crear nuevo plan -->
  <div class="section">
    <button class="btn primary" onclick="openModal('create')">
      <i class="fas fa-plus"></i>
      Crear Nuevo Plan
    </button>
  </div>

  <!-- Lista de planes -->
  <div class="section">
    <h2 class="section-title">
      <i class="fas fa-list"></i>
      Planes Configurados
    </h2>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Duración (días)</th>
            <th>Precio USD</th>
            <th>Precio CRC</th>
            <th>Máx. Fotos</th>
            <th>Métodos de Pago</th>
            <th>Estado</th>
            <th>Destacado</th>
            <th>Publicaciones</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($plans as $plan):
            $payment_methods = explode(',', $plan['payment_methods'] ?? '');
          ?>
          <tr>
            <td><strong><?= $plan['id'] ?></strong></td>
            <td>
              <strong><?= h($plan['name']) ?></strong>
              <?php if($plan['description']): ?>
                <br><small style="color: var(--gray-600);"><?= h($plan['description']) ?></small>
              <?php endif; ?>
            </td>
            <td><?= $plan['duration_days'] ?> días</td>
            <td>$<?= number_format($plan['price_usd'], 2) ?></td>
            <td>₡<?= number_format($plan['price_crc'], 0) ?></td>
            <td><strong><?= $plan['max_photos'] ?? 3 ?></strong> fotos</td>
            <td>
              <?php
              $methodLabels = [];
              if (in_array('sinpe', $payment_methods)) $methodLabels[] = '<span class="badge info">SINPE</span>';
              if (in_array('paypal', $payment_methods)) $methodLabels[] = '<span class="badge success">PayPal</span>';
              echo implode(' ', $methodLabels);
              ?>
            </td>
            <td>
              <?php if($plan['is_active']): ?>
                <span class="badge success"><i class="fas fa-check"></i> Activo</span>
              <?php else: ?>
                <span class="badge danger"><i class="fas fa-times"></i> Inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($plan['is_featured']): ?>
                <span class="badge warning"><i class="fas fa-star"></i> Destacado</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($plan['listings_count'] > 0): ?>
                <span class="badge info"><?= $plan['listings_count'] ?> publicaciones</span>
              <?php else: ?>
                <span style="color: var(--gray-600);">0</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn warning" onclick='editPlan(<?= json_encode($plan, JSON_UNESCAPED_UNICODE) ?>)'>
                <i class="fas fa-edit"></i> Editar
              </button>
              <?php if($plan['listings_count'] == 0): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Estás seguro de eliminar este plan?');">
                  <input type="hidden" name="action" value="delete_plan">
                  <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                  <button class="btn danger" type="submit">
                    <i class="fas fa-trash"></i> Eliminar
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal para editar publicación de bienes raíces -->
<div class="modal" id="listingModal" style="z-index:1100">
  <div class="modal-content" style="max-width:960px;width:96%">
    <span class="modal-close" onclick="closeListingModal()">&times;</span>
    <h2 style="margin-top:0;color:var(--primary)">
      <i class="fas fa-home"></i> Editar Publicación <span id="lmTitle" style="font-size:.9em;color:var(--gray-600)"></span>
    </h2>

    <form method="post" id="listingForm" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_listing">
      <input type="hidden" name="listing_id" id="lmId">
      <input type="hidden" name="listing_images_json" id="lmImagesJson" value="[]">

      <!-- Sección: Información básica -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-info-circle"></i> Información Básica</legend>
        <div class="form-grid">
          <div class="form-group" style="grid-column:1/-1">
            <label>Título *</label>
            <input class="input" type="text" name="title" id="lmTitleInput" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Descripción *</label>
            <textarea class="input" name="description" id="lmDesc" rows="4" required></textarea>
          </div>
          <div class="form-group">
            <label>Categoría</label>
            <select class="input" name="category_id" id="lmCategory">
              <option value="">— Seleccionar —</option>
              <?php foreach ($br_categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= h(preg_replace('/^BR:\s*/i', '', $cat['name'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Tipo de operación</label>
            <select class="input" name="listing_type" id="lmListingType">
              <option value="sale">Venta</option>
              <option value="rent">Alquiler</option>
            </select>
          </div>
          <div class="form-group">
            <label>Precio *</label>
            <input class="input" type="number" name="price" id="lmPrice" min="0" step="0.01">
          </div>
          <div class="form-group">
            <label>Moneda</label>
            <select class="input" name="currency" id="lmCurrency">
              <option value="CRC">₡ Colones (CRC)</option>
              <option value="USD">$ Dólares (USD)</option>
            </select>
          </div>
        </div>
      </fieldset>

      <!-- Sección: Ubicación -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-map-marker-alt"></i> Ubicación</legend>
        <div class="form-grid">
          <div class="form-group">
            <label>Provincia</label>
            <select class="input" name="province" id="lmProvince">
              <option value="">— Seleccionar —</option>
              <?php foreach (['San José','Alajuela','Cartago','Heredia','Guanacaste','Puntarenas','Limón'] as $prov): ?>
                <option value="<?= h($prov) ?>"><?= h($prov) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Cantón</label>
            <input class="input" type="text" name="canton" id="lmCanton" placeholder="Ej: San José">
          </div>
          <div class="form-group">
            <label>Distrito</label>
            <input class="input" type="text" name="district" id="lmDistrict" placeholder="Ej: Escazú">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Dirección / Referencia</label>
            <input class="input" type="text" name="location" id="lmLocation" placeholder="Detalles de ubicación">
          </div>
        </div>
      </fieldset>

      <!-- Sección: Detalles de propiedad -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-building"></i> Detalles de la Propiedad</legend>
        <div class="form-grid">
          <div class="form-group">
            <label>Habitaciones</label>
            <input class="input" type="number" name="bedrooms" id="lmBedrooms" min="0" value="0">
          </div>
          <div class="form-group">
            <label>Baños</label>
            <input class="input" type="number" name="bathrooms" id="lmBathrooms" min="0" value="0">
          </div>
          <div class="form-group">
            <label>Área (m²)</label>
            <input class="input" type="number" name="area_m2" id="lmArea" min="0" step="0.01" value="0">
          </div>
          <div class="form-group">
            <label>Parqueos</label>
            <input class="input" type="number" name="parking_spaces" id="lmParking" min="0" value="0">
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem">
          <label>Características</label>
          <div style="display:flex;flex-wrap:wrap;gap:.5rem .75rem;margin-top:.4rem">
            <?php foreach ($available_features as $feat): $fid = 'lmFeat_' . md5($feat); ?>
              <label style="display:flex;align-items:center;gap:.35rem;cursor:pointer;font-size:.9rem">
                <input type="checkbox" name="features[]" value="<?= h($feat) ?>" id="<?= $fid ?>">
                <?= h($feat) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </fieldset>

      <!-- Sección: Contacto -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-address-card"></i> Información de Contacto</legend>
        <div class="form-grid">
          <div class="form-group">
            <label>Nombre contacto</label>
            <input class="input" type="text" name="contact_name" id="lmContactName">
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input class="input" type="text" name="contact_phone" id="lmContactPhone">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input class="input" type="email" name="contact_email" id="lmContactEmail">
          </div>
          <div class="form-group">
            <label>WhatsApp</label>
            <input class="input" type="text" name="contact_whatsapp" id="lmContactWa">
          </div>
        </div>
      </fieldset>

      <!-- Sección: Imágenes -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-images"></i> Imágenes</legend>
        <div id="lmImageGrid" style="display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem"></div>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
          <label class="btn primary" style="cursor:pointer;margin:0">
            <i class="fas fa-upload"></i> Subir fotos
            <input type="file" id="lmFileInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none" onchange="lmUploadFiles(this)">
          </label>
          <span id="lmUploadStatus" style="font-size:.85rem;color:var(--gray-600)"></span>
        </div>
      </fieldset>

      <!-- Sección: Configuración admin -->
      <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:1rem;margin-bottom:1rem">
        <legend style="font-weight:600;color:var(--primary);padding:0 .5rem"><i class="fas fa-cog"></i> Configuración</legend>
        <div class="form-grid">
          <div class="form-group">
            <label>Plan de precios</label>
            <select class="input" name="pricing_plan_id" id="lmPricingPlan">
              <option value="">— Sin plan —</option>
              <?php foreach ($plans as $pl): ?>
                <option value="<?= $pl['id'] ?>"><?= h($pl['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Estado de pago</label>
            <select class="input" name="payment_status" id="lmPaymentStatus">
              <option value="confirmed">Confirmado</option>
              <option value="free">Gratuito</option>
              <option value="pending">Pendiente</option>
              <option value="pending_review">En revisión</option>
              <option value="rejected">Rechazado</option>
            </select>
          </div>
          <div class="form-group">
            <label class="checkbox-label" style="margin-top:1.5rem">
              <input type="checkbox" name="is_active" id="lmIsActive">
              <strong>Publicación activa</strong>
            </label>
          </div>
          <div class="form-group">
            <label class="checkbox-label" style="margin-top:1.5rem">
              <input type="checkbox" name="is_featured" id="lmIsFeatured">
              <strong>Destacada</strong>
            </label>
          </div>
        </div>
      </fieldset>

      <div class="actions">
        <button class="btn primary" type="submit"><i class="fas fa-save"></i> Guardar Cambios</button>
        <button class="btn" type="button" onclick="closeListingModal()"><i class="fas fa-times"></i> Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal para editar/crear plan -->
<div class="modal" id="planModal">
  <div class="modal-content">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <h2 id="modalTitle" style="margin-top: 0; color: var(--primary);">
      <i class="fas fa-edit"></i> <span id="modalTitleText">Editar Plan</span>
    </h2>
    <form method="post" id="planForm">
      <input type="hidden" name="action" id="formAction" value="update_plan">
      <input type="hidden" name="id" id="planId" value="">

      <div class="form-grid">
        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Nombre del Plan *</label>
          <input class="input" type="text" name="name" id="planName" required placeholder="Ej: Plan Premium 60 días">
        </div>

        <div class="form-group">
          <label>Duración (días) *</label>
          <input class="input" type="number" name="duration_days" id="planDuration" min="1" required placeholder="30">
        </div>

        <div class="form-group">
          <label>Precio en USD *</label>
          <input class="input" type="number" name="price_usd" id="planPriceUSD" step="0.01" min="0" required placeholder="5.00">
        </div>

        <div class="form-group">
          <label>Precio en CRC *</label>
          <input class="input" type="number" name="price_crc" id="planPriceCRC" step="1" min="0" required placeholder="2700">
        </div>

        <div class="form-group">
          <label>Número Máximo de Fotos *</label>
          <input class="input" type="number" name="max_photos" id="planMaxPhotos" min="1" max="20" required placeholder="5">
          <small style="color: var(--gray-600); margin-top: 0.25rem;">Cantidad de fotos que permite este plan</small>
        </div>

        <div class="form-group">
          <label>Orden de Visualización</label>
          <input class="input" type="number" name="display_order" id="planDisplayOrder" min="0" value="999" placeholder="999">
          <small style="color: var(--gray-600); margin-top: 0.25rem;">Menor número = se muestra primero</small>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Métodos de Pago Permitidos *</label>
          <div class="checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" name="payment_methods[]" value="sinpe" id="paymentSinpe">
              <i class="fas fa-mobile-alt" style="color: #3498db;"></i> SINPE Móvil
            </label>
            <label class="checkbox-label">
              <input type="checkbox" name="payment_methods[]" value="paypal" id="paymentPaypal">
              <i class="fab fa-paypal" style="color: #0070ba;"></i> PayPal
            </label>
          </div>
          <small style="color: var(--gray-600); margin-top: 0.5rem;">Selecciona al menos un método de pago</small>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
          <label>Descripción</label>
          <textarea class="input" name="description" id="planDescription" rows="3" placeholder="Descripción del plan (opcional)"></textarea>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_active" id="planIsActive" checked>
            <strong>Plan Activo</strong>
          </label>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_featured" id="planIsFeatured">
            <strong>Plan Destacado</strong> (se mostrará con énfasis)
          </label>
        </div>
      </div>

      <div class="actions">
        <button class="btn primary" type="submit">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
        <button class="btn" type="button" onclick="closeModal()">
          <i class="fas fa-times"></i> Cancelar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(mode) {
  const modal = document.getElementById('planModal');
  const form = document.getElementById('planForm');
  const title = document.getElementById('modalTitleText');

  if (mode === 'create') {
    form.reset();
    document.getElementById('formAction').value = 'create_plan';
    document.getElementById('planId').value = '';
    title.textContent = 'Crear Nuevo Plan';
    document.getElementById('planIsActive').checked = true;
  }

  modal.classList.add('active');
}

function closeModal() {
  document.getElementById('planModal').classList.remove('active');
}

function editPlan(plan) {
  openModal('edit');

  document.getElementById('formAction').value = 'update_plan';
  document.getElementById('planId').value = plan.id;
  document.getElementById('planName').value = plan.name || '';
  document.getElementById('planDuration').value = plan.duration_days || '';
  document.getElementById('planPriceUSD').value = plan.price_usd || '';
  document.getElementById('planPriceCRC').value = plan.price_crc || '';
  document.getElementById('planMaxPhotos').value = plan.max_photos || 3;
  document.getElementById('planDisplayOrder').value = plan.display_order || 999;
  document.getElementById('planDescription').value = plan.description || '';
  document.getElementById('planIsActive').checked = plan.is_active == 1;
  document.getElementById('planIsFeatured').checked = plan.is_featured == 1;

  // Marcar métodos de pago
  const methods = (plan.payment_methods || '').split(',');
  document.getElementById('paymentSinpe').checked = methods.includes('sinpe');
  document.getElementById('paymentPaypal').checked = methods.includes('paypal');

  document.getElementById('modalTitleText').textContent = 'Editar Plan: ' + plan.name;
}

// Cerrar modal al hacer clic fuera
document.getElementById('planModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});

function filterListings() {
  const q = document.getElementById('listingSearch').value.toLowerCase();
  document.querySelectorAll('#listingsTable tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

/* ── Listing Edit Modal ── */
let lmImages = []; // current images array

function openListingModal(listing) {
  lmImages = JSON.parse(listing.images || '[]') || [];

  document.getElementById('lmId').value        = listing.id;
  document.getElementById('lmTitle').textContent = '#' + listing.id;
  document.getElementById('lmTitleInput').value = listing.title || '';
  document.getElementById('lmDesc').value       = listing.description || '';
  document.getElementById('lmPrice').value      = listing.price || 0;
  document.getElementById('lmProvince').value   = listing.province || '';
  document.getElementById('lmCanton').value     = listing.canton || '';
  document.getElementById('lmDistrict').value   = listing.district || '';
  document.getElementById('lmLocation').value   = listing.location || '';
  document.getElementById('lmBedrooms').value   = listing.bedrooms || 0;
  document.getElementById('lmBathrooms').value  = listing.bathrooms || 0;
  document.getElementById('lmArea').value       = listing.area_m2 || 0;
  document.getElementById('lmParking').value    = listing.parking_spaces || 0;
  document.getElementById('lmContactName').value  = listing.contact_name || '';
  document.getElementById('lmContactPhone').value = listing.contact_phone || '';
  document.getElementById('lmContactEmail').value = listing.contact_email || '';
  document.getElementById('lmContactWa').value    = listing.contact_whatsapp || '';
  document.getElementById('lmIsActive').checked   = listing.is_active == 1;
  document.getElementById('lmIsFeatured').checked = listing.is_featured == 1;

  // Selects
  const catSel = document.getElementById('lmCategory');
  catSel.value = listing.category_id || '';

  const typeSel = document.getElementById('lmListingType');
  typeSel.value = listing.listing_type || 'sale';

  const curSel = document.getElementById('lmCurrency');
  curSel.value = listing.currency || 'CRC';

  const planSel = document.getElementById('lmPricingPlan');
  planSel.value = listing.pricing_plan_id || '';

  const paySel = document.getElementById('lmPaymentStatus');
  paySel.value = listing.payment_status || 'confirmed';

  // Características
  document.querySelectorAll('#listingModal input[name="features[]"]').forEach(cb => {
    cb.checked = false;
  });
  const feats = JSON.parse(listing.features || '[]') || [];
  feats.forEach(f => {
    const cb = document.querySelector('#listingModal input[name="features[]"][value="' + CSS.escape(f) + '"]');
    if (cb) cb.checked = true;
  });

  lmRenderImages();
  document.getElementById('lmUploadStatus').textContent = '';
  document.getElementById('listingModal').classList.add('active');
}

function closeListingModal() {
  document.getElementById('listingModal').classList.remove('active');
}

function lmRenderImages() {
  document.getElementById('lmImagesJson').value = JSON.stringify(lmImages);
  const grid = document.getElementById('lmImageGrid');
  grid.innerHTML = '';
  if (lmImages.length === 0) {
    grid.innerHTML = '<p style="color:var(--gray-600);font-size:.9rem">No hay imágenes.</p>';
    return;
  }
  lmImages.forEach((url, idx) => {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;width:120px;height:90px;flex-shrink:0';
    wrap.innerHTML = `
      <img src="${url}" style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid var(--gray-300)" onerror="this.src='/assets/no-image.png'">
      <button type="button" onclick="lmRemoveImage(${idx})"
        style="position:absolute;top:3px;right:3px;background:rgba(231,76,60,.85);color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:.8rem;line-height:1;display:flex;align-items:center;justify-content:center">
        &times;
      </button>`;
    grid.appendChild(wrap);
  });
}

function lmRemoveImage(idx) {
  lmImages.splice(idx, 1);
  lmRenderImages();
}

function lmUploadFiles(input) {
  const status = document.getElementById('lmUploadStatus');
  const files = Array.from(input.files);
  if (!files.length) return;

  status.textContent = 'Subiendo ' + files.length + ' imagen(es)...';
  let pending = files.length;

  files.forEach(file => {
    const fd = new FormData();
    fd.append('images[]', file);
    fd.append('current_images_count', lmImages.length);

    fetch('/real-estate/upload-image.php', { method: 'POST', body: fd })
      .then(async r => {
        const text = await r.text();
        try {
          return JSON.parse(text);
        } catch(e) {
          throw new Error('HTTP ' + r.status + ' — respuesta no-JSON: ' + text.substring(0, 200));
        }
      })
      .then(data => {
        if (data.success && data.images) {
          data.images.forEach(u => lmImages.push(u));
          lmRenderImages();
        } else {
          status.textContent = 'Error: ' + (data.error || 'desconocido');
        }
      })
      .catch(err => {
        status.textContent = 'Error al subir: ' + err.message;
        console.error('Upload error:', err);
      })
      .finally(() => {
        pending--;
        if (pending === 0 && !status.textContent.startsWith('Error')) status.textContent = 'Listo.';
      });
  });

  input.value = ''; // reset so same file can be re-added
}

// Close listing modal on outside click
document.getElementById('listingModal').addEventListener('click', function(e) {
  if (e.target === this) closeListingModal();
});
</script>

</body>
</html>
