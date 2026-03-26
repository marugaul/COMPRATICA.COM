<?php
/**
 * api/emp-cart.php
 * Carrito de compras para productos de Emprendedoras (sesión).
 * Acciones: get | add | update | remove | clear
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Usar config.php para una sesión consistente con el resto del sitio
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/db.php';

$action = $_GET['action'] ?? '';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function emp_cart_totals(): array {
    $items = $_SESSION['emp_cart'] ?? [];
    $count = 0; $total = 0;
    foreach ($items as $it) { $count += (int)$it['qty']; $total += $it['qty'] * $it['price']; }
    return ['count' => $count, 'total' => $total];
}

function emp_cart_response(bool $ok, array $extra = []): void {
    $t = emp_cart_totals();
    echo json_encode(array_merge(['ok' => $ok, 'cart_count' => $t['count'], 'cart_total' => $t['total']], $extra), JSON_UNESCAPED_UNICODE);
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($action === 'get') {
    echo json_encode(['ok' => true, 'items' => array_values($_SESSION['emp_cart'] ?? []), ...emp_cart_totals()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── ADD ──────────────────────────────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $pid     = (int)($payload['product_id'] ?? 0);
    $qty     = max(1, (int)($payload['qty'] ?? 1));

    if ($pid <= 0) { emp_cart_response(false, ['error' => 'Producto inválido']); exit; }

    try {
        $pdo  = db();
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.stock, p.image_1,
                   p.accepts_sinpe, p.accepts_paypal, p.accepts_card, p.sinpe_phone, p.paypal_email,
                   p.user_id AS seller_id,
                   u.name AS seller_name, u.email AS seller_email,
                   COALESCE(u.seller_type, 'emprendedora') AS seller_type
            FROM entrepreneur_products p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.id = ? AND p.is_active = 1
        ");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) { emp_cart_response(false, ['error' => 'Producto no encontrado']); exit; }

        $cart   = $_SESSION['emp_cart'] ?? [];
        $curQty = isset($cart[$pid]) ? (int)$cart[$pid]['qty'] : 0;
        $newQty = $curQty + $qty;

        if ($newQty > (int)$prod['stock']) {
            emp_cart_response(false, ['error' => 'Stock insuficiente. Disponible: ' . $prod['stock']]); exit;
        }

        $cart[$pid] = [
            'product_id'    => (int)$prod['id'],
            'qty'           => $newQty,
            'name'          => $prod['name'],
            'price'         => (float)$prod['price'],
            'image'         => $prod['image_1'] ?? '',
            'seller_id'     => (int)$prod['seller_id'],
            'seller_name'   => $prod['seller_name'] ?? 'Emprendedor/a',
            'seller_email'  => $prod['seller_email'] ?? '',
            'seller_type'   => $prod['seller_type'] ?? 'emprendedora',
            'sinpe_phone'   => $prod['sinpe_phone'] ?? '',
            'paypal_email'  => $prod['paypal_email'] ?? '',
            'accepts_sinpe' => (int)$prod['accepts_sinpe'],
            'accepts_paypal'=> (int)$prod['accepts_paypal'],
            'accepts_card'  => (int)($prod['accepts_card'] ?? 0),
        ];
        $_SESSION['emp_cart'] = $cart;
        emp_cart_response(true, ['message' => '¡Producto agregado al carrito!']);
    } catch (Throwable $e) {
        emp_cart_response(false, ['error' => 'Error al agregar']);
    }
    exit;
}

// ─── UPDATE ───────────────────────────────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $pid     = (int)($payload['product_id'] ?? 0);
    $qty     = (int)($payload['qty'] ?? 0);

    if ($pid <= 0) { emp_cart_response(false, ['error' => 'Inválido']); exit; }

    $cart = $_SESSION['emp_cart'] ?? [];
    if ($qty <= 0) {
        unset($cart[$pid]);
    } else {
        if (isset($cart[$pid])) $cart[$pid]['qty'] = $qty;
    }
    $_SESSION['emp_cart'] = $cart;
    emp_cart_response(true);
    exit;
}

// ─── REMOVE ───────────────────────────────────────────────────────────────────
if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $pid     = (int)($payload['product_id'] ?? 0);
    $cart    = $_SESSION['emp_cart'] ?? [];
    unset($cart[$pid]);
    $_SESSION['emp_cart'] = $cart;
    emp_cart_response(true);
    exit;
}

// ─── CLEAR ────────────────────────────────────────────────────────────────────
if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['emp_cart'] = [];
    emp_cart_response(true);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
