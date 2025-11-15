<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    header('Location: index.php');
    exit;
}

$pdo = db();

// Obtener órdenes
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->execute([$order_number]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die('Orden no encontrada');
}

// Calcular total
$total = 0;
$currency = 'USD';
foreach ($orders as $order) {
    $total += (float)$order['total'];
    $currency = strtoupper($order['currency'] ?? 'CRC');
}

// Convertir a USD si es CRC (aproximado)
if ($currency === 'CRC') {
    $total = $total / 530; // Tasa aproximada
    $currency = 'USD';
}

$paypal_email = defined('PAYPAL_EMAIL') ? PAYPAL_EMAIL : 'marco.ulate@crv-soft.com';
$return_url = rtrim(BASE_URL, '/') . '/order-success.php?order=' . urlencode($order_number);
$cancel_url = rtrim(BASE_URL, '/') . '/cart.php';
$notify_url = rtrim(BASE_URL, '/') . '/paypal-ipn.php';

// Usar el primer order_id como custom
$first_order_id = $orders[0]['id'];

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirigiendo a PayPal...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            text-align: center;
            max-width: 500px;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f4f6;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #111;
        }
        p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h1>Redirigiendo a PayPal...</h1>
        <p>Serás redirigido automáticamente al sistema de pago de PayPal.</p>
        <p>Si no eres redirigido automáticamente, haz clic en el botón de abajo.</p>

        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" id="paypalForm">
            <input type="hidden" name="cmd" value="_xclick">
            <input type="hidden" name="business" value="<?= htmlspecialchars($paypal_email) ?>">
            <input type="hidden" name="item_name" value="Pedido <?= htmlspecialchars($order_number) ?>">
            <input type="hidden" name="item_number" value="<?= (int)$first_order_id ?>">
            <input type="hidden" name="custom" value="<?= (int)$first_order_id ?>">
            <input type="hidden" name="amount" value="<?= number_format($total, 2, '.', '') ?>">
            <input type="hidden" name="currency_code" value="<?= htmlspecialchars($currency) ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($return_url) ?>">
            <input type="hidden" name="cancel_return" value="<?= htmlspecialchars($cancel_url) ?>">
            <input type="hidden" name="notify_url" value="<?= htmlspecialchars($notify_url) ?>">
            <input type="hidden" name="no_shipping" value="1">
            <input type="hidden" name="no_note" value="1">

            <button type="submit" class="btn">Ir a PayPal</button>
        </form>
    </div>

    <script>
        // Auto-submit después de 2 segundos
        setTimeout(function() {
            document.getElementById('paypalForm').submit();
        }, 2000);
    </script>
</body>
</html>
