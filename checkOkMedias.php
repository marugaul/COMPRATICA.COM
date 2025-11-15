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

/* ============= PARÁMETROS ============= */
$sale_id = (int)($_GET['sale_id'] ?? 0);
$uid = (int)($_SESSION['uid'] ?? 0);

log_checkout('INFO', 'Parámetros iniciales', [
  'sale_id' => $sale_id,
  'uid' => $uid,
  'session_id' => session_id()
]);

/* ============= OPCIÓN 1: USAR $_SESSION['cart']['groups'] ============= */
$group = null;
$error = '';

if (isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
  log_checkout('INFO', 'Buscando en $_SESSION[cart][groups]', [
    'groups_count' => count($_SESSION['cart']['groups'])
  ]);
  
  foreach ($_SESSION['cart']['groups'] as $g) {
    if ((int)($g['sale_id'] ?? 0) === $sale_id) {
      $group = $g;
      log_checkout('INFO', '✓ Grupo encontrado en sesión', ['group' => $group]);
      break;
    }
  }
}

/* ============= OPCIÓN 2: CARGAR DESDE BD (FALLBACK) ============= */
if (!$group) {
  log_checkout('INFO', 'Grupo no encontrado en sesión, cargando desde BD');
  
  try {
    $pdo = db();
    
    // Buscar cart_id
    $cartId = null;
    
    if ($uid > 0) {
      $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
      $stmt->execute([$uid]);
      $cartId = $stmt->fetchColumn();
      log_checkout('INFO', 'Buscando cart por user_id', ['uid' => $uid, 'cart_id' => $cartId]);
    }
    
    if (!$cartId) {
      $guestSid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
      if ($guestSid !== '') {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE guest_sid = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$guestSid]);
        $cartId = $stmt->fetchColumn();
        log_checkout('INFO', 'Buscando cart por guest_sid', ['guest_sid' => $guestSid, 'cart_id' => $cartId]);
      }
    }
    
    if ($cartId) {
      log_checkout('INFO', 'Cart encontrado, cargando items', ['cart_id' => $cartId, 'sale_id' => $sale_id]);
      
      $stmt = $pdo->prepare("
        SELECT 
          ci.id as cart_item_id,
          ci.product_id,
          ci.qty,
          ci.unit_price,
          ci.sale_id,
          p.name as product_name,
          p.image as product_image,
          p.image2 as product_image2,
          p.currency,
          s.title as sale_title,
          s.affiliate_id,
          a.name as affiliate_name,
          a.email as affiliate_email,
          a.phone as affiliate_phone
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        JOIN sales s ON s.id = ci.sale_id
        JOIN affiliates a ON a.id = s.affiliate_id
        WHERE ci.cart_id = ? AND ci.sale_id = ?
        ORDER BY ci.id
      ");
      $stmt->execute([$cartId, $sale_id]);
      $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      log_checkout('INFO', 'Items cargados desde BD', ['count' => count($items)]);
      
      if (!empty($items)) {
        $first = $items[0];
        $group = [
          'sale_id' => $sale_id,
          'sale_title' => $first['sale_title'],
          'affiliate_id' => $first['affiliate_id'],
          'affiliate_name' => $first['affiliate_name'],
          'affiliate_email' => $first['affiliate_email'],
          'affiliate_phone' => $first['affiliate_phone'],
          'currency' => strtoupper($first['currency'] ?? 'CRC'),
          'items' => [],
          'subtotal' => 0,
          'tax' => 0,
          'total' => 0
        ];
        
        foreach ($items as $item) {
          $lineTotal = $item['qty'] * $item['unit_price'];
          
          $group['items'][] = [
            'cart_item_id' => $item['cart_item_id'],
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'product_image_url' => !empty($item['product_image']) ? 'uploads/'.$item['product_image'] : (!empty($item['product_image2']) ? 'uploads/'.$item['product_image2'] : 'assets/no-image.png'),
            'qty' => $item['qty'],
            'unit_price' => $item['unit_price'],
            'line_total' => $lineTotal
          ];
          
          $group['subtotal'] += $lineTotal;
        }
        
        $group['tax'] = $group['subtotal'] * 0.13;
        $group['total'] = $group['subtotal'] + $group['tax'];
        
        log_checkout('INFO', '✓ Grupo construido desde BD', ['group' => $group]);
      }
    } else {
      log_checkout('WARNING', 'No se encontró cart_id');
    }
    
  } catch(Throwable $e) {
    log_checkout('ERROR', 'Error al cargar desde BD', [
      'msg' => $e->getMessage(),
      'trace' => $e->getTraceAsString()
    ]);
  }
}

/* ============= VALIDACIONES ============= */
if ($sale_id <= 0) {
  $error = 'No se especificó el espacio de venta.';
  log_checkout('ERROR', 'sale_id inválido', ['sale_id' => $sale_id]);
} elseif (!$group) {
  $error = 'No se encontró el carrito para este espacio. Por favor vuelve al carrito.';
  log_checkout('ERROR', 'Grupo no encontrado', [
    'sale_id' => $sale_id,
    'session_groups' => $_SESSION['cart']['groups'] ?? null
  ]);
}

/* ============= CARGAR MÉTODOS DE PAGO DESDE affiliate_payment_methods ============= */
$payment_methods = [];

if ($group) {
  $affiliateId = $group['affiliate_id'];
  
  try {
    $pdo = db();
    
    // Cargar métodos de pago del afiliado
    $stmt = $pdo->prepare("
      SELECT 
        id,
        affiliate_id,
        sinpe_phone,
        paypal_email,
        active_sinpe,
        active_paypal
      FROM affiliate_payment_methods
      WHERE affiliate_id = ?
      LIMIT 1
    ");
    $stmt->execute([$affiliateId]);
    $pm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    log_checkout('INFO', 'Métodos de pago cargados desde affiliate_payment_methods', [
      'affiliate_id' => $affiliateId,
      'payment_methods_row' => $pm
    ]);
    
    if ($pm) {
      // SINPE Móvil
      if (!empty($pm['active_sinpe']) && !empty($pm['sinpe_phone'])) {
        $payment_methods[] = [
          'id' => 'sinpe',
          'type' => 'sinpe',
          'label' => '📱 SINPE Móvil',
          'details' => [
            'Teléfono: ' . $pm['sinpe_phone']
          ]
        ];
        log_checkout('INFO', '✓ SINPE habilitado', ['phone' => $pm['sinpe_phone']]);
      }
      
      // PayPal
      if (!empty($pm['active_paypal']) && !empty($pm['paypal_email'])) {
        $payment_methods[] = [
          'id' => 'paypal',
          'type' => 'paypal',
          'label' => '💳 PayPal',
          'details' => [
            'Email: ' . $pm['paypal_email']
          ]
        ];
        log_checkout('INFO', '✓ PayPal habilitado', ['email' => $pm['paypal_email']]);
      }
      
      log_checkout('INFO', 'Métodos de pago construidos', [
        'count' => count($payment_methods),
        'methods' => $payment_methods
      ]);
    } else {
      log_checkout('WARNING', 'No se encontró registro en affiliate_payment_methods', [
        'affiliate_id' => $affiliateId
      ]);
    }
    
  } catch(Throwable $e) {
    log_checkout('ERROR', 'Error al cargar métodos de pago', [
      'msg' => $e->getMessage(),
      'trace' => $e->getTraceAsString()
    ]);
  }
}

if ($group && empty($payment_methods)) {
  log_checkout('WARNING', 'Sin métodos de pago después de cargar', [
    'affiliate_id' => $group['affiliate_id'] ?? null
  ]);
}

/* ============= FUNCIONES AUXILIARES ============= */
function imgUrl($img) {
  if (!$img || $img === 'assets/no-image.png') return '/assets/placeholder.jpg';
  return '/' . ltrim($img, '/');
}

// Agrupar productos idénticos
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

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
$csrf = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));

log_checkout('INFO', 'Estado final antes de renderizar', [
  'error' => $error,
  'has_group' => !empty($group),
  'payment_methods_count' => count($payment_methods),
  'payment_methods' => $payment_methods
]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <title>Checkout — <?= h($APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/style.css?v=20251026">
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
    .btn-success{background:var(--success);color:#fff}
    .btn-success:hover{background:#059669}
    .btn-success:disabled{background:var(--gray-400);cursor:not-allowed}
    
    .container{max-width:1200px;margin:32px auto;padding:0 20px}
    
    .error-box{background:#fff;border:2px solid var(--danger);border-radius:var(--radius);padding:24px;
      text-align:center;box-shadow:var(--shadow-lg)}
    .error-box h2{color:var(--danger);margin-bottom:12px}
    
    .checkout-grid{display:grid;grid-template-columns:1fr 400px;gap:24px;margin-top:24px}
    .checkout-group{background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-lg)}
    .group-header{font-size:1.3rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--gray-200)}
    
    .checkout-item{display:grid;grid-template-columns:60px 1fr auto;gap:12px;padding:12px 0;border-bottom:1px solid var(--gray-100);align-items:center}
    .checkout-item:last-child{border-bottom:none}
    .item-img{width:60px;height:60px;object-fit:cover;border-radius:8px;background:var(--gray-100)}
    .item-name{font-weight:600;font-size:0.95rem}
    .item-qty{color:var(--gray-500);font-size:0.85rem}
    .item-total{font-weight:700;color:var(--success)}
    
    .group-totals{margin-top:16px;padding-top:16px;border-top:2px solid var(--gray-200)}
    .total-row{display:flex;justify-content:space-between;margin-bottom:8px;font-size:0.95rem}
    .total-row.grand{font-size:1.2rem;font-weight:700;color:var(--success);margin-top:8px;padding-top:8px;border-top:2px solid var(--gray-200)}
    
    .payment-section{margin-top:20px}
    .payment-title{font-size:1.1rem;font-weight:700;margin-bottom:12px}
    .payment-method{border:2px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:8px;cursor:pointer;transition:all 0.2s}
    .payment-method:hover{border-color:var(--primary);background:var(--gray-50)}
    .payment-method.selected{border-color:var(--primary);background:#dbeafe}
    .method-label{font-weight:600;margin-bottom:4px}
    .method-details{font-size:0.85rem;color:var(--gray-600)}
    .method-details div{margin-top:2px}
    .no-payment{background:#fef3c7;border:2px solid #fbbf24;border-radius:8px;padding:16px;color:#92400e;text-align:center}
    
    .checkout-sidebar{position:sticky;top:20px;height:fit-content}
    .summary-box{background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-lg)}
    .summary-title{font-size:1.3rem;font-weight:700;margin-bottom:16px}
    .form-group{margin-bottom:16px}
    .form-label{display:block;font-weight:600;margin-bottom:6px;font-size:0.9rem}
    .form-input{width:100%;padding:10px 12px;border:2px solid var(--gray-200);border-radius:8px;font-size:1rem}
    .form-input:focus{outline:none;border-color:var(--primary)}
    
    #flash{display:none;position:fixed;top:20px;right:20px;padding:16px 24px;border-radius:var(--radius);
      box-shadow:var(--shadow-lg);z-index:9999;font-weight:600}
    #flash.ok{background:var(--success);color:#fff}
    #flash.err{background:var(--danger);color:#fff}
    
    @media (max-width:900px){
      .checkout-grid{grid-template-columns:1fr}
      .checkout-sidebar{position:static}
    }
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

<div id="flash"></div>

<div class="container">
  
  <?php if ($error): ?>
    <div class="error-box">
      <h2>⚠️ <?= h($error) ?></h2>
      <p>Revisa el log en <code>/logs/checkout-<?= date('Ymd') ?>.log</code> para más detalles.</p>
      <div style="margin-top:20px">
        <a href="cart.php" class="btn btn-primary">← Volver al carrito</a>
      </div>
    </div>
  <?php else: ?>
    
    <h1 style="color:#fff;text-align:center;margin-bottom:24px;font-size:2rem">🛒 Finalizar Compra</h1>
    
    <div class="checkout-grid">
      
      <!-- COLUMNA IZQUIERDA: PRODUCTOS Y MÉTODOS DE PAGO -->
      <div class="checkout-group">
        <div class="group-header">
          📦 <?= h($group['sale_title']) ?>
        </div>
        
        <?php 
        $groupedItems = group_items($group['items']);
        foreach ($groupedItems as $item): 
        ?>
          <div class="checkout-item">
            <img 
              src="<?= h(imgUrl($item['product_image_url'])) ?>" 
              alt="<?= h($item['product_name']) ?>"
              class="item-img"
            >
            <div>
              <div class="item-name"><?= h($item['product_name']) ?></div>
              <div class="item-qty">
                <?= fmt_price($item['unit_price'], $group['currency']) ?> × <?= $item['qty'] ?>
              </div>
            </div>
            <div class="item-total">
              <?= fmt_price($item['line_total'], $group['currency']) ?>
            </div>
          </div>
        <?php endforeach; ?>
        
        <div class="group-totals">
          <div class="total-row">
            <span>Subtotal:</span>
            <span><?= fmt_price($group['subtotal'], $group['currency']) ?></span>
          </div>
          <div class="total-row">
            <span>IVA (13%):</span>
            <span><?= fmt_price($group['tax'], $group['currency']) ?></span>
          </div>
          <div class="total-row grand">
            <span>Total:</span>
            <span><?= fmt_price($group['total'], $group['currency']) ?></span>
          </div>
        </div>
        
        <!-- MÉTODOS DE PAGO -->
        <div class="payment-section">
          <div class="payment-title">💳 Método de Pago</div>
          
          <?php if (empty($payment_methods)): ?>
            <div class="no-payment">
              ⚠️ Este vendedor no tiene métodos de pago configurados.
            </div>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <div class="payment-method" data-method-id="<?= h($method['id']) ?>" data-method-type="<?= h($method['type']) ?>">
                <div class="method-label">
                  <?= h($method['label']) ?>
                </div>
                <div class="method-details">
                  <?php foreach ($method['details'] as $detail): ?>
                    <div><?= h($detail) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- COLUMNA DERECHA: FORMULARIO -->
      <div class="checkout-sidebar">
        <div class="summary-box">
          <div class="summary-title">Tus Datos</div>
          
          <form id="checkoutForm">
            <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
            
            <div class="form-group">
              <label class="form-label">Nombre completo *</label>
              <input type="text" name="customer_name" class="form-input" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Email *</label>
              <input type="email" name="customer_email" class="form-input" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Teléfono *</label>
              <input type="tel" name="customer_phone" class="form-input" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Notas adicionales</label>
              <textarea name="notes" class="form-input" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-success" id="submitBtn" style="width:100%;padding:14px;font-size:1.1rem" <?= empty($payment_methods) ? 'disabled' : '' ?>>
              Confirmar Pedido
            </button>
          </form>
        </div>
      </div>
      
    </div>
    
  <?php endif; ?>
  
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let selectedPaymentMethod = null;
let selectedPaymentType = null;

function flash(msg, ok=true){
  const el = document.getElementById('flash');
  if (!el) return;
  el.className = ok ? 'ok' : 'err';
  el.textContent = String(msg||'');
  el.style.display = 'block';
  setTimeout(()=>{ el.style.display='none'; }, 3500);
}

// Seleccionar método de pago
document.addEventListener('click', (e) => {
  const method = e.target.closest('.payment-method');
  if (!method) return;
  
  // Deseleccionar otros métodos
  document.querySelectorAll('.payment-method').forEach(m => {
    m.classList.remove('selected');
  });
  
  // Seleccionar este método
  method.classList.add('selected');
  selectedPaymentMethod = method.dataset.methodId;
  selectedPaymentType = method.dataset.methodType;
});

// Enviar pedido
document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  if (!selectedPaymentMethod) {
    flash('⚠️ Por favor selecciona un método de pago', false);
    return;
  }
  
  const formData = new FormData(e.target);
  const data = {
    sale_id: parseInt(formData.get('sale_id')),
    customer_name: formData.get('customer_name'),
    customer_email: formData.get('customer_email'),
    customer_phone: formData.get('customer_phone'),
    notes: formData.get('notes'),
    payment_method: selectedPaymentMethod,
    payment_type: selectedPaymentType
  };
  
  const submitBtn = document.getElementById('submitBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Procesando...';
  
  try {
    const token = (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || CSRF;
    const response = await fetch('/api/checkout.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token
      },
      body: JSON.stringify(data),
      credentials: 'include'
    });
    
    const result = await response.json();
    
    if (result.ok) {
      flash('✓ Pedido confirmado! Redirigiendo...', true);
      setTimeout(() => {
        window.location.href = result.redirect || '/order-success.php?order_id=' + result.order_id;
      }, 1500);
    } else {
      flash('✗ ' + (result.error || 'Error al procesar el pedido'), false);
      submitBtn.disabled = false;
      submitBtn.textContent = 'Confirmar Pedido';
    }
  } catch (error) {
    console.error('Error:', error);
    flash('✗ Error de red. Intenta de nuevo.', false);
    submitBtn.disabled = false;
    submitBtn.textContent = 'Confirmar Pedido';
  }
});
</script>

</body>
</html>