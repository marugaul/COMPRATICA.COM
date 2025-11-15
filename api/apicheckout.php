<?php
/**
 * apicheckout.php — API para procesar checkout
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/* ============= Sesión (igual que checkout.php) ============= */
$__sessPath = __DIR__ . '/../sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', '');
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/', '', $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'    => '/',
            'domain'   => '',
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Logging
$logFile = __DIR__ . '/../logs/checkout-api-' . date('Ymd') . '.log';
function logMsg(string $level, string $msg, $ctx = []) {
  global $logFile;
  $entry = json_encode([
    'ts' => date('Y-m-d H:i:s.u'),
    'level' => $level,
    'msg' => $msg,
    'ctx' => $ctx
  ]) . "\n";
  @file_put_contents($logFile, $entry, FILE_APPEND);
}

try {
  // Validar método POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido');
  }

  // Leer datos JSON
  $json = file_get_contents('php://input');
  $data = json_decode($json, true);

  if (!$data) {
    throw new Exception('Datos inválidos');
  }

  logMsg('INFO', 'Checkout iniciado', ['data' => $data, 'session_id' => session_id()]);

  // Extraer datos
  $saleId = (int)($data['sale_id'] ?? 0);
  $affiliateId = (int)($data['affiliate_id'] ?? 0);
  $currency = $data['currency'] ?? 'CRC';
  $subtotal = (float)($data['subtotal'] ?? 0);
  $tax = (float)($data['tax'] ?? 0);
  $grandTotal = (float)($data['grand_total'] ?? 0);
  $buyerName = trim($data['buyer_name'] ?? '');
  $buyerEmail = trim($data['buyer_email'] ?? '');
  $buyerPhone = trim($data['buyer_phone'] ?? '');
  $shippingMethod = $data['shipping_method'] ?? 'pickup';
  $paymentMethod = $data['payment_method'] ?? 'sinpe';
  $note = trim($data['note'] ?? '');
  $shippingCost = (float)($data['shipping_cost'] ?? 0);
  $paymentType = $data['payment_type'] ?? $paymentMethod;

  // Validaciones
  if ($saleId <= 0) {
    throw new Exception('Sale ID inválido');
  }
  if ($affiliateId <= 0) {
    throw new Exception('Affiliate ID inválido');
  }
  if (empty($buyerEmail)) {
    throw new Exception('Email requerido');
  }
  if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email inválido');
  }

  $pdo = db();

  // Buscar el carrito (IGUAL QUE checkout.php)
  $cartId = null;
  $uid = (int)($_SESSION['uid'] ?? 0);

  // 1. Por user_id si está logueado
  if ($uid > 0) {
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$uid]);
    $cartId = $stmt->fetchColumn();
    logMsg('INFO', 'Búsqueda por user_id', ['user_id' => $uid, 'cart_id' => $cartId]);
  }

  // 2. Por guest_sid (IGUAL QUE checkout.php)
  if (!$cartId) {
    $guestSid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
    logMsg('INFO', 'Búsqueda por guest_sid', ['guest_sid' => $guestSid]);
    
    if ($guestSid !== '') {
      $stmt = $pdo->prepare("SELECT id FROM carts WHERE guest_sid = ? ORDER BY id DESC LIMIT 1");
      $stmt->execute([$guestSid]);
      $cartId = $stmt->fetchColumn();
      logMsg('INFO', 'Resultado guest_sid', ['cart_id' => $cartId]);
    }
  }

  // 3. Fallback: buscar por session_id actual
  if (!$cartId) {
    $currentSid = session_id();
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE guest_sid = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$currentSid]);
    $cartId = $stmt->fetchColumn();
    logMsg('INFO', 'Fallback por session_id actual', ['session_id' => $currentSid, 'cart_id' => $cartId]);
  }

  // 4. Último recurso: carrito más reciente con items de este sale_id
  if (!$cartId) {
    $stmt = $pdo->prepare("
      SELECT DISTINCT c.id 
      FROM carts c
      JOIN cart_items ci ON ci.cart_id = c.id
      WHERE ci.sale_id = ?
      ORDER BY c.updated_at DESC
      LIMIT 1
    ");
    $stmt->execute([$saleId]);
    $cartId = $stmt->fetchColumn();
    logMsg('INFO', 'Fallback por sale_id', ['sale_id' => $saleId, 'cart_id' => $cartId]);
  }

  if (!$cartId) {
    throw new Exception('Carrito no encontrado. Por favor, regresa al carrito y vuelve a intentar.');
  }

  logMsg('INFO', 'Cart encontrado', ['cart_id' => $cartId]);

  // Cargar items del carrito para este sale_id
  $stmt = $pdo->prepare("
    SELECT 
      ci.id,
      ci.product_id,
      ci.qty,
      ci.unit_price,
      p.name as product_name,
      p.image as product_image
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    WHERE ci.cart_id = ? AND ci.sale_id = ?
  ");
  $stmt->execute([$cartId, $saleId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($items)) {
    throw new Exception('No hay productos en el carrito para este vendedor');
  }

  logMsg('INFO', 'Items cargados', ['count' => count($items), 'cart_id' => $cartId, 'sale_id' => $saleId]);

  // Calcular totales
  $calculatedSubtotal = 0;
  $calculatedTax = 0;
  $platformFeeRate = 0.10; // 10% comisión de plataforma
  $taxRate = 0.13; // 13% IVA

  foreach ($items as &$item) {
    $itemSubtotal = $item['qty'] * $item['unit_price'];
    $itemTax = $itemSubtotal * $taxRate;
    $itemTotal = $itemSubtotal + $itemTax;
    $itemPlatformFee = $itemTotal * $platformFeeRate;
    $itemSellerAmount = $itemTotal - $itemPlatformFee;

    $item['subtotal'] = $itemSubtotal;
    $item['tax'] = $itemTax;
    $item['total'] = $itemTotal;
    $item['platform_fee'] = $itemPlatformFee;
    $item['seller_amount'] = $itemSellerAmount;

    $calculatedSubtotal += $itemSubtotal;
    $calculatedTax += $itemTax;
  }

  $calculatedTotal = $calculatedSubtotal + $calculatedTax + $shippingCost;
  $totalPlatformFee = $calculatedTotal * $platformFeeRate;
  $totalSellerAmount = $calculatedTotal - $totalPlatformFee;

  logMsg('INFO', 'Totales calculados', [
    'subtotal' => $calculatedSubtotal,
    'tax' => $calculatedTax,
    'shipping_cost' => $shippingCost,
    'total' => $calculatedTotal,
    'platform_fee' => $totalPlatformFee,
    'seller_amount' => $totalSellerAmount
  ]);

  // Generar número de orden único
  $orderNumber = 'ORD-' . strtoupper(uniqid()) . '-' . time();

  // Iniciar transacción
  $pdo->beginTransaction();

  try {
    // Insertar órdenes (una por cada producto)
    $stmtOrder = $pdo->prepare("
      INSERT INTO orders (
        order_number, sale_id, affiliate_id, product_id, qty,
        buyer_name, buyer_email, buyer_phone,
        status, payment_method, payment_type, shipping_method, shipping_cost,
        note, currency, subtotal, tax, platform_fee,
        seller_amount, grand_total, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))
    ");

    foreach ($items as $item) {
      $stmtOrder->execute([
        $orderNumber,
        $saleId,
        $affiliateId,
        $item['product_id'],
        $item['qty'],
        $buyerName,
        $buyerEmail,
        $buyerPhone,
        'Pendiente',
        $paymentMethod,
        $paymentType,
        $shippingMethod,
        $shippingCost,
        $note,
        $currency,
        $item['subtotal'],
        $item['tax'],
        $item['platform_fee'],
        $item['seller_amount'],
        $calculatedTotal
      ]);
    }

    logMsg('INFO', 'Órdenes creadas', ['order_number' => $orderNumber, 'items_count' => count($items)]);

    // Eliminar items del carrito
    $stmtDelete = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND sale_id = ?");
    $stmtDelete->execute([$cartId, $saleId]);

    logMsg('INFO', 'Items eliminados del carrito', ['cart_id' => $cartId, 'sale_id' => $saleId]);

    // Commit transacción
    $pdo->commit();

    // Enviar emails
    try {
      // Email al comprador
      $buyerSubject = "Confirmación de Pedido - $orderNumber";
      $buyerBody = "
        <h2>¡Gracias por tu compra!</h2>
        <p>Hola <strong>$buyerName</strong>,</p>
        <p>Tu pedido ha sido recibido exitosamente.</p>
        <p><strong>Número de Orden:</strong> $orderNumber</p>
        <p><strong>Total:</strong> " . number_format($calculatedTotal, 2) . " $currency</p>
        <p><strong>Método de Pago:</strong> " . ucfirst($paymentMethod) . "</p>
        <p><strong>Método de Envío:</strong> " . ucfirst($shippingMethod) . "</p>
        <h3>Productos:</h3>
        <ul>
      ";
      
      foreach ($items as $item) {
        $buyerBody .= "<li>{$item['product_name']} - Cantidad: {$item['qty']} - Precio: " . number_format($item['unit_price'], 2) . " $currency</li>";
      }
      
      $buyerBody .= "
        </ul>
        <p>Recibirás una notificación cuando tu pedido sea procesado.</p>
        <p>Gracias por tu preferencia.</p>
      ";

      $buyerHeaders = "MIME-Version: 1.0\r\n";
      $buyerHeaders .= "Content-type: text/html; charset=utf-8\r\n";
      $buyerHeaders .= "From: noreply@compratica.com\r\n";

      @mail($buyerEmail, $buyerSubject, $buyerBody, $buyerHeaders);

      logMsg('INFO', 'Email enviado al comprador', ['email' => $buyerEmail]);

      // Email al vendedor
      $stmt = $pdo->prepare("SELECT email, name FROM affiliates WHERE id = ? LIMIT 1");
      $stmt->execute([$affiliateId]);
      $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($affiliate && !empty($affiliate['email'])) {
        $sellerEmail = $affiliate['email'];
        $sellerName = $affiliate['name'];
        
        $sellerSubject = "Nueva Orden Recibida - $orderNumber";
        $sellerBody = "
          <h2>Nueva Orden Recibida</h2>
          <p>Hola <strong>$sellerName</strong>,</p>
          <p>Has recibido una nueva orden.</p>
          <p><strong>Número de Orden:</strong> $orderNumber</p>
          <p><strong>Cliente:</strong> $buyerName</p>
          <p><strong>Email:</strong> $buyerEmail</p>
          <p><strong>Teléfono:</strong> $buyerPhone</p>
          <p><strong>Total:</strong> " . number_format($calculatedTotal, 2) . " $currency</p>
          <p><strong>Tu ganancia:</strong> " . number_format($totalSellerAmount, 2) . " $currency</p>
          <p><strong>Método de Pago:</strong> " . ucfirst($paymentMethod) . "</p>
          <p><strong>Método de Envío:</strong> " . ucfirst($shippingMethod) . "</p>
          <h3>Productos:</h3>
          <ul>
        ";
        
        foreach ($items as $item) {
          $sellerBody .= "<li>{$item['product_name']} - Cantidad: {$item['qty']} - Precio: " . number_format($item['unit_price'], 2) . " $currency</li>";
        }
        
        $sellerBody .= "
          </ul>
          <p><strong>Notas del cliente:</strong> " . ($note ?: 'Ninguna') . "</p>
          <p>Por favor procesa esta orden lo antes posible.</p>
        ";

        $sellerHeaders = "MIME-Version: 1.0\r\n";
        $sellerHeaders .= "Content-type: text/html; charset=utf-8\r\n";
        $sellerHeaders .= "From: noreply@compratica.com\r\n";

        @mail($sellerEmail, $sellerSubject, $sellerBody, $sellerHeaders);

        logMsg('INFO', 'Email enviado al vendedor', ['email' => $sellerEmail]);
      }

    } catch (Exception $e) {
      logMsg('WARNING', 'Error al enviar emails', ['error' => $e->getMessage()]);
    }

    // Respuesta exitosa
    echo json_encode([
      'ok' => true,
      'success' => true,
      'order_number' => $orderNumber,
      'message' => 'Orden procesada exitosamente',
      'redirect' => '/order-success.php?order=' . urlencode($orderNumber)
    ]);

    logMsg('INFO', 'Checkout completado', ['order_number' => $orderNumber]);

  } catch (Exception $e) {
    $pdo->rollBack();
    logMsg('ERROR', 'Error al crear orden', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    throw new Exception('Error al procesar la orden: ' . $e->getMessage());
  }

} catch (Exception $e) {
  logMsg('ERROR', 'Error en checkout', ['msg' => $e->getMessage()]);
  
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'success' => false,
    'error' => $e->getMessage()
  ]);
}