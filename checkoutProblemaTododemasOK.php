<?php
/**
 * checkout.php — CHECKOUT PREMIUM POR ESPACIO
 * Con logging detallado para diagnóstico
 */
declare(strict_types=1);

/* ============= LOGGING DETALLADO ============= */
$LOG_DIR = __DIR__ . '/logs';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0775, true);
@ini_set('log_errors', '1');
@ini_set('error_log', $LOG_DIR . '/php-error.log');
@error_reporting(E_ALL);

function log_checkout(string $level, string $msg, array $ctx = []): void {
  global $LOG_DIR;
  $date = date('Ymd');
  $row = [
    'ts' => date('Y-m-d H:i:s.u'),
    'level' => strtoupper($level),
    'msg' => $msg,
    'ctx' => $ctx
  ];
  @file_put_contents("{$LOG_DIR}/checkout-{$date}.log", json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
}

set_exception_handler(function(Throwable $e){
  log_checkout('FATAL', 'Uncaught exception', [
    'type' => get_class($e),
    'msg' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ]);
  http_response_code(500);
  die('<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;padding:40px;text-align:center"><h1>😔 Error en el checkout</h1><p>Por favor intenta de nuevo o contacta soporte.</p><a href="cart.php" style="color:#0ea5e9">← Volver al carrito</a></body>');
});

log_checkout('INFO', '=== CHECKOUT INICIADO ===', [
  'GET' => $_GET,
  'session_id' => session_id(),
  'session_status' => session_status()
]);

/* ============= DEPENDENCIAS ============= */
try {
  require_once __DIR__ . '/includes/config.php';
  log_checkout('INFO', 'config.php cargado', ['session_id_after_config' => session_id()]);
} catch(Throwable $e){
  log_checkout('CRITICAL', 'Failed to load config.php', ['msg' => $e->getMessage()]);
  throw $e;
}

try {
  require_once __DIR__ . '/includes/db.php';
  log_checkout('INFO', 'db.php cargado');
} catch(Throwable $e){
  log_checkout('ERROR', 'Failed to load db.php', ['msg' => $e->getMessage()]);
}

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

/* ============= HELPERS ============= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_crc($n){ return '₡'.number_format((float)$n, 0, ',', '.'); }
function fmt_usd($n){ return '$'.number_format((float)$n, 2, '.', ','); }
function fmt_price($n, $c='CRC'){ return strtoupper($c)==='USD' ? fmt_usd($n) : fmt_crc($n); }

/* ============= CARGAR DATOS DEL ESPACIO ============= */
$sale_id = (int)($_GET['sale_id'] ?? 0);
$uid = (int)($_SESSION['uid'] ?? 0);
$user_name = (string)($_SESSION['name'] ?? '');
$user_email = (string)($_SESSION['email'] ?? '');
$user_phone = (string)($_SESSION['phone'] ?? '');

log_checkout('INFO', 'Parámetros iniciales', [
  'sale_id' => $sale_id,
  'uid' => $uid,
  'user_name' => $user_name,
  'user_email' => $user_email
]);

$group = null;
$payment_methods = [];

// LOG: Contenido completo de la sesión
log_checkout('INFO', 'Contenido de $_SESSION', [
  'keys' => array_keys($_SESSION),
  'cart_exists' => isset($_SESSION['cart']),
  'cart_is_array' => isset($_SESSION['cart']) && is_array($_SESSION['cart']),
  'cart_keys' => isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_keys($_SESSION['cart']) : null,
  'cart_groups_exists' => isset($_SESSION['cart']['groups']),
  'cart_groups_is_array' => isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups']),
  'cart_groups_count' => isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups']) ? count($_SESSION['cart']['groups']) : 0,
  'full_session' => $_SESSION
]);

// 1) Buscar en sesión $_SESSION['cart']['groups']
if (isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
  log_checkout('INFO', 'Buscando en $_SESSION[cart][groups]', [
    'groups_count' => count($_SESSION['cart']['groups']),
    'groups' => $_SESSION['cart']['groups']
  ]);
  
  foreach ($_SESSION['cart']['groups'] as $idx => $g) {
    $g_sale_id = (int)($g['sale_id'] ?? 0);
    log_checkout('INFO', "Revisando grupo #{$idx}", [
      'group_sale_id' => $g_sale_id,
      'target_sale_id' => $sale_id,
      'match' => $g_sale_id === $sale_id,
      'group_data' => $g
    ]);
    
    if ($g_sale_id === $sale_id) {
      $group = $g;
      log_checkout('INFO', '✓ Grupo encontrado en sesión', ['group' => $group]);
      break;
    }
  }
  
  if (!$group) {
    log_checkout('WARNING', 'No se encontró grupo con sale_id='.$sale_id.' en $_SESSION[cart][groups]');
  }
} else {
  log_checkout('WARNING', '$_SESSION[cart][groups] no existe o no es array');
}

// 2) Fallback: $_SESSION['cart_payload']
if (!$group && isset($_SESSION['cart_payload']) && is_array($_SESSION['cart_payload'])) {
  log_checkout('INFO', 'Intentando fallback con $_SESSION[cart_payload]', [
    'cart_payload' => $_SESSION['cart_payload']
  ]);
  
  $groups = $_SESSION['cart_payload']['groups'] ?? [];
  if (is_array($groups)) {
    foreach ($groups as $g) {
      if ((int)($g['sale_id'] ?? 0) === $sale_id) {
        $group = $g;
        log_checkout('INFO', '✓ Grupo encontrado en cart_payload', ['group' => $group]);
        break;
      }
    }
  }
}

// 3) Fallback: BD
if (!$group && function_exists('db')) {
  log_checkout('INFO', 'Intentando fallback con BD');
  
  try {
    $pdo = db();
    
    // Detectar carrito
    $cartId = null;
    if ($uid > 0) {
      $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
      $st->execute([$uid]);
      $cartId = $st->fetchColumn();
      log_checkout('INFO', 'Buscando cart por user_id', ['uid' => $uid, 'cart_id' => $cartId]);
    }
    
    if (!$cartId) {
      $sid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
      if ($sid !== '') {
        $st = $pdo->prepare("SELECT id FROM carts WHERE guest_sid=? ORDER BY id DESC LIMIT 1");
        $st->execute([$sid]);
        $cartId = $st->fetchColumn();
        log_checkout('INFO', 'Buscando cart por guest_sid', ['guest_sid' => $sid, 'cart_id' => $cartId]);
      }
    }
    
    if ($cartId) {
      $sql = "SELECT ci.*, p.name AS product_name, p.image AS product_image, p.currency
              FROM cart_items ci
              JOIN products p ON p.id = ci.product_id
              WHERE ci.cart_id=? AND ci.sale_id=?";
      $st = $pdo->prepare($sql);
      $st->execute([$cartId, $sale_id]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);
      
      log_checkout('INFO', 'Resultados de BD', [
        'cart_id' => $cartId,
        'sale_id' => $sale_id,
        'rows_count' => count($rows),
        'rows' => $rows
      ]);
      
      if ($rows) {
        $currency = $rows[0]['currency'] ?? 'CRC';
        $items = [];
        $count = 0; $subtotal=0; $tax_total=0; $grand_total=0;
        foreach ($rows as $r) {
          $qty  = (float)$r['qty'];
          $unit = (float)$r['unit_price'];
          $line = $qty * $unit;
          $count += $qty;
          $subtotal += $line;
          $tax_total += (float)($r['tax'] ?? 0);
          $grand_total += (float)($r['line_total'] ?? $line);
          $items[] = [
            'product_id'        => (int)$r['product_id'],
            'product_name'      => (string)$r['product_name'],
            'product_image_url' => !empty($r['product_image']) ? 'uploads/'.$r['product_image'] : 'assets/no-image.png',
            'qty'               => $qty,
            'unit_price'        => $unit,
            'line_total'        => $line,
          ];
        }
        $group = [
          'sale_id'        => $sale_id,
          'sale_title'     => 'Espacio #'.$sale_id,
          'affiliate_id'   => (int)($_GET['affiliate_id'] ?? 0),
          'affiliate_name' => '',
          'currency'       => $currency,
          'items'          => $items,
          'totals'         => [
            'count'       => $count,
            'subtotal'    => $subtotal,
            'tax_total'   => $tax_total,
            'grand_total' => $grand_total,
          ],
        ];
        log_checkout('INFO', '✓ Grupo construido desde BD', ['group' => $group]);
      }
    } else {
      log_checkout('WARNING', 'No se encontró cart_id en BD');
    }
  } catch(Throwable $e){
    log_checkout('ERROR', 'Error en fallback BD', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
  }
}

// 4) Cargar métodos de pago del afiliado
if ($group) {
  log_checkout('INFO', 'Cargando métodos de pago para sale_id='.$sale_id);
  
  try {
    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT 
        a.id, a.name, a.email, a.phone,
        a.sinpe_phone, a.sinpe_enabled,
        a.paypal_email, a.paypal_enabled
      FROM affiliates a
      JOIN sales s ON s.affiliate_id = a.id
      WHERE s.id = ?
      LIMIT 1
    ");
    $stmt->execute([$sale_id]);
    $aff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    log_checkout('INFO', 'Datos del afiliado', ['affiliate' => $aff]);
    
    if ($aff) {
      $group['affiliate_name'] = $aff['name'] ?? '';
      $group['affiliate_email'] = $aff['email'] ?? '';
      $group['affiliate_phone'] = $aff['phone'] ?? '';
      
      // SINPE
      if (!empty($aff['sinpe_enabled']) && !empty($aff['sinpe_phone'])) {
        $payment_methods['sinpe'] = [
          'name' => 'SINPE Móvil',
          'icon' => '📱',
          'phone' => $aff['sinpe_phone'],
          'hint' => 'Transferencia instantánea al teléfono del vendedor'
        ];
        log_checkout('INFO', 'SINPE habilitado', ['phone' => $aff['sinpe_phone']]);
      }
      
      // PayPal
      if (!empty($aff['paypal_enabled']) && !empty($aff['paypal_email'])) {
        $payment_methods['paypal'] = [
          'name' => 'PayPal',
          'icon' => '💳',
          'email' => $aff['paypal_email'],
          'hint' => 'Pago seguro con tu cuenta PayPal'
        ];
        log_checkout('INFO', 'PayPal habilitado', ['email' => $aff['paypal_email']]);
      }
      
      log_checkout('INFO', 'Métodos de pago cargados', ['methods' => array_keys($payment_methods)]);
    }
  } catch(Throwable $e){
    log_checkout('ERROR', 'Error al cargar métodos de pago', ['msg' => $e->getMessage()]);
  }
}

// Validaciones finales
$error = '';
if ($sale_id <= 0) {
  $error = 'No se especificó el espacio de venta.';
  log_checkout('ERROR', 'sale_id inválido', ['sale_id' => $sale_id]);
} elseif (!$group) {
  $error = 'No se encontró el carrito para este espacio. Por favor vuelve al carrito.';
  log_checkout('ERROR', 'Grupo no encontrado después de todos los fallbacks', [
    'sale_id' => $sale_id,
    'session_cart_groups' => $_SESSION['cart']['groups'] ?? null,
    'session_cart_payload' => $_SESSION['cart_payload'] ?? null
  ]);
} elseif (empty($payment_methods)) {
  $error = 'Este vendedor no tiene métodos de pago configurados. Contacta al vendedor directamente.';
  log_checkout('ERROR', 'Sin métodos de pago', ['sale_id' => $sale_id, 'group' => $group]);
}

log_checkout('INFO', 'Estado final', [
  'error' => $error,
  'has_group' => !empty($group),
  'payment_methods_count' => count($payment_methods)
]);

// Agrupar productos
function group_items(array $items): array {
  $map = [];
  foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $name = (string)($it['product_name'] ?? '');
    $unit = (float)($it['unit_price'] ?? 0);
    $img = (string)($it['product_image_url'] ?? '');
    $qty = (float)($it['qty'] ?? 0);
    $key = "P{$pid}_".number_format($unit, 4, '.', '');
    
    if (!isset($map[$key])) {
      $map[$key] = [
        'product_id' => $pid,
        'product_name' => $name,
        'product_image_url' => $img,
        'unit_price' => $unit,
        'qty' => 0,
        'line_total' => 0,
      ];
    }
    $map[$key]['qty'] += $qty;
    $map[$key]['line_total'] = $map[$key]['qty'] * $map[$key]['unit_price'];
  }
  usort($map, fn($a,$b) => strcasecmp($a['product_name'], $b['product_name']));
  return array_values($map);
}

$items = [];
$currency = 'CRC';
$subtotal = 0;
$shipping_cost = 0;
$tax = 0;
$total = 0;

if ($group && empty($error)) {
  $items = group_items($group['items'] ?? []);
  $currency = strtoupper($group['currency'] ?? 'CRC');
  foreach ($items as $it) {
    $subtotal += (float)$it['line_total'];
  }
  $tax = $subtotal * 0.13;
  $total = $subtotal + $tax + $shipping_cost;
  
  log_checkout('INFO', 'Totales calculados', [
    'items_count' => count($items),
    'currency' => $currency,
    'subtotal' => $subtotal,
    'tax' => $tax,
    'total' => $total
  ]);
}

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout — <?= h($APP_NAME) ?></title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    :root{
      --primary:#0ea5e9; --primary-dark:#0284c7; --success:#10b981; --danger:#ef4444;
      --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
      --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151;
      --gray-800:#1f2937; --gray-900:#111827;
      --radius:12px; --shadow:0 1px 3px rgba(0,0,0,0.1); --shadow-lg:0 10px 25px rgba(0,0,0,0.15);
    }
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
      background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);min-height:100vh;color:var(--gray-900);line-height:1.6}
    
    .header{background:#fff;box-shadow:var(--shadow);padding:16px 24px;display:flex;justify-content:space-between;align-items:center}
    .logo{font-size:1.5rem;font-weight:800;color:var(--primary);text-decoration:none}
    .nav{display:flex;gap:12px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:var(--radius);
      text-decoration:none;font-weight:600;font-size:0.95rem;transition:all 0.2s;border:none;cursor:pointer}
    .btn-secondary{background:var(--gray-100);color:var(--gray-700)}
    .btn-secondary:hover{background:var(--gray-200)}
    .btn-primary{background:var(--primary);color:#fff}
    .btn-primary:hover{background:var(--primary-dark);transform:translateY(-1px);box-shadow:var(--shadow-lg)}
    
    .container{max-width:1200px;margin:32px auto;padding:0 20px}
    
    .error-box{background:#fff;border:2px solid var(--danger);border-radius:var(--radius);padding:24px;
      text-align:center;box-shadow:var(--shadow-lg)}
    .error-box h2{color:var(--danger);margin-bottom:12px}
    .error-box pre{background:var(--gray-100);padding:12px;border-radius:8px;text-align:left;
      overflow:auto;font-size:0.85rem;margin-top:16px}
  </style>
</head>
<body>

<header class="header">
  <a href="index.php" class="logo">🛍️ <?= h($APP_NAME) ?></a>
  <nav class="nav">
    <a href="index.php" class="btn btn-secondary">Inicio</a>
    <a href="cart.php" class="btn btn-secondary">Carrito</a>
  </nav>
</header>

<div class="container">
  <div class="error-box">
    <h2>⚠️ <?= $error ?: 'Error desconocido' ?></h2>
    <p>Revisa el log en <code>/logs/checkout-<?= date('Ymd') ?>.log</code> para más detalles.</p>
    <pre><?php
      echo "sale_id solicitado: {$sale_id}\n";
      echo "Grupos en sesión: " . (isset($_SESSION['cart']['groups']) ? count($_SESSION['cart']['groups']) : 0) . "\n";
      if (isset($_SESSION['cart']['groups'])) {
        echo "Sale IDs disponibles: " . implode(', ', array_map(fn($g) => $g['sale_id'] ?? 'N/A', $_SESSION['cart']['groups']));
      }
    ?></pre>
    <div style="margin-top:20px">
      <a href="cart.php" class="btn btn-primary">← Volver al carrito</a>
    </div>
  </div>
</div>

</body>
</html>