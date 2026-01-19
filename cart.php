<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

/* ==== SESIÓN (alineada) ==== */
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    // Fallback a /tmp si no se puede escribir en sessions
    ini_set('session.save_path', '/tmp');
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
            'lifetime'=>0,'path'=>'/','domain'=>'',
            'secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax',
        ]);
    }
    ini_set('session.use_strict_mode','0');
    ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','86400');
    session_start();
}

/* ==== HOTFIX uid → user_id ==== */
if ((!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) && isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) {
    $_SESSION['user_id'] = (int)$_SESSION['uid'];
    error_log('CHK normalized_user_id_from_uid='.(int)$_SESSION['user_id']);
}

/* ==== IMPORTS ==== */
try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/db.php';
} catch (Exception $e) {
    die('Error al cargar configuración: ' . $e->getMessage());
}

/* ==== CSRF ==== */
$isHttps = $__isHttps;
$csrf_cookie = $_COOKIE['vg_csrf'] ?? '';
if (!preg_match('/^[a-f0-9]{32,128}$/i', $csrf_cookie)) {
    $csrf_cookie = bin2hex(random_bytes(32));
}
if (PHP_VERSION_ID < 70300) {
    @setcookie('vg_csrf', $csrf_cookie, time()+7200, '/', '', $isHttps, false);
} else {
    @setcookie('vg_csrf', $csrf_cookie, [
        'expires'=>time()+7200,'path'=>'/','domain'=>'',
        'secure'=>$isHttps,'httponly'=>false,'samesite'=>'Lax',
    ]);
}
$csrf_meta = $csrf_cookie;

/* ==== DB ==== */
try { $pdo = db(); } catch (Exception $e) { die('Error de conexión a base de datos: '.$e->getMessage()); }

/* ==== Helpers de carrito ==== */
function get_cart_id_from_session() {
    global $pdo;
    $uid = (int)($_SESSION['uid'] ?? 0);
    $guest_sid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
    $cartId = null;
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$uid]);
        $cartId = $st->fetchColumn();
    }
    if (!$cartId && $guest_sid !== '') {
        $st = $pdo->prepare("SELECT id FROM carts WHERE guest_sid=? ORDER BY id DESC LIMIT 1");
        $st->execute([$guest_sid]);
        $cartId = $st->fetchColumn();
    }
    return $cartId ? (int)$cartId : 0;
}

function load_cart_from_db($cart_id) {
    global $pdo;
    $sql = "
        SELECT 
            ci.sale_id, ci.product_id, ci.qty, ci.unit_price,
            p.name AS product_name, p.image AS product_image, p.description, p.currency,
            s.id AS sale_real_id, s.title AS sale_title, s.affiliate_id,
            a.name AS affiliate_name, a.email AS affiliate_email, a.phone AS affiliate_phone
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN sales s   ON s.id = ci.sale_id
        LEFT JOIN affiliates a ON a.id = s.affiliate_id
        WHERE ci.cart_id = ?
        ORDER BY s.id, p.name
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$cart_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($rows as $r) {
        $sale_id = (int)($r['sale_id'] ?? 0);
        if ($sale_id <= 0) $sale_id = (int)($r['sale_real_id'] ?? 0);
        if (!isset($groups[$sale_id])) {
            $groups[$sale_id] = [
                'sale_id'=>$sale_id,
                'sale_title'=>$r['sale_title'] ?? 'Espacio #'.$sale_id,
                'affiliate_id'=>(int)($r['affiliate_id'] ?? 0),
                'affiliate_name'=>$r['affiliate_name'] ?? '',
                'affiliate_email'=>$r['affiliate_email'] ?? '',
                'affiliate_phone'=>$r['affiliate_phone'] ?? '',
                'currency'=>strtoupper($r['currency'] ?? 'CRC'),
                'items'=>[],
                'total'=>0,
                'totals'=>['count'=>0,'subtotal'=>0,'tax_total'=>0,'grand_total'=>0]
            ];
        }
        $qty = (float)$r['qty']; $unit = (float)$r['unit_price']; $line = $qty*$unit; $tax = $line*0.13;
        $img = $r['product_image'] ? '/uploads/'.ltrim($r['product_image'],'/') : null;

        $groups[$sale_id]['items'][] = [
            'product_id'=>(int)$r['product_id'],
            'product_name'=>$r['product_name'] ?? 'Producto',
            'product_image_url'=>$img,
            'description'=>$r['description'] ?? '',
            'qty'=>$qty,'unit_price'=>$unit,'line_total'=>$line,
        ];
        $groups[$sale_id]['total'] += $line;
        $groups[$sale_id]['totals']['count'] += $qty;
        $groups[$sale_id]['totals']['subtotal'] += $line;
        $groups[$sale_id]['totals']['tax_total'] += $tax;
        $groups[$sale_id]['totals']['grand_total'] += $line + $tax;
    }
    return array_values($groups);
}

/* ==== Cargar carrito a sesión ==== */
$cart_id = get_cart_id_from_session();
$groups = [];
if ($cart_id > 0) {
    $groups = load_cart_from_db($cart_id);
    $_SESSION['cart']['groups'] = $groups;
}

/* ==== Utils UI ==== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_price($n, $cur='CRC'){
    $cur = strtoupper($cur);
    if ($cur === 'USD') return '$'.number_format($n, 2);
    return '₡'.number_format($n, 0);
}

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8'); ini_set('default_charset','UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras — <?= defined('APP_NAME') ? APP_NAME : 'Compratica'; ?></title>
    <meta name="csrf-token" content="<?= h($csrf_meta); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Colores sobrios y elegantes */
            --primary: #2c3e50;           /* Azul oscuro elegante */
            --primary-light: #34495e;     /* Azul oscuro claro */
            --accent: #3498db;            /* Azul suave */
            --success: #27ae60;           /* Verde natural */
            --danger: #c0392b;            /* Rojo discreto */
            
            /* Neutros */
            --dark: #1a1a1a;
            --gray-900: #2d3748;
            --gray-700: #4a5568;
            --gray-500: #718096;
            --gray-300: #cbd5e0;
            --gray-100: #f7fafc;
            --white: #ffffff;
            
            /* Fondos */
            --bg-primary: #f8f9fa;
            --bg-card: #ffffff;
            
            /* Sombras elegantes */
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            
            /* Transiciones suaves */
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ===================================== */
        /* HEADER ELEGANTE */
        /* ===================================== */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-300);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
        }

        .logo i {
            font-size: 1.25rem;
            color: var(--accent);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.9375rem;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-500);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        /* ===================================== */
        /* CONTENEDOR PRINCIPAL */
        /* ===================================== */
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            letter-spacing: -0.02em;
        }

        /* ===================================== */
        /* ESTADO VACÍO */
        /* ===================================== */
        .empty-state {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 4rem 2rem;
            text-align: center;
            max-width: 500px;
            margin: 0 auto;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .empty-text {
            color: var(--gray-500);
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* ===================================== */
        /* CARDS DE GRUPOS */
        /* ===================================== */
        .cart-container {
            display: grid;
            gap: 1.5rem;
        }

        .cart-group {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }

        .cart-group:hover {
            box-shadow: var(--shadow-lg);
        }

        .group-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(to right, #f8f9fa, #ffffff);
            border-bottom: 1px solid var(--gray-300);
        }

        .group-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .group-title i {
            font-size: 1.125rem;
            color: var(--accent);
        }

        .group-subtitle {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
            margin-left: 2rem;
        }

        /* ===================================== */
        /* ITEMS DEL CARRITO */
        /* ===================================== */
        .cart-items {
            padding: 1.5rem;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--gray-300);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--gray-300);
        }

        .item-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-header {
            margin-bottom: 0.75rem;
        }

        .item-name {
            font-size: 1.0625rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.375rem;
            line-height: 1.4;
        }

        .item-desc {
            color: var(--gray-500);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .item-price {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        .item-price strong {
            color: var(--dark);
            font-weight: 700;
        }

        /* ===================================== */
        /* CONTROLES DE CANTIDAD */
        /* ===================================== */
        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--gray-100);
            border-radius: var(--radius);
            padding: 0.25rem;
            border: 1px solid var(--gray-300);
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: var(--white);
            border: 1px solid var(--gray-300);
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-700);
            transition: var(--transition);
        }

        .qty-btn:hover {
            background: var(--gray-100);
            border-color: var(--gray-500);
        }

        .qty-display {
            min-width: 36px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--dark);
        }

        .remove-btn {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            padding: 0.5rem 0.875rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remove-btn:hover {
            background: rgba(192, 57, 43, 0.05);
            border-color: var(--danger);
        }

        /* ===================================== */
        /* CARD DE VENDEDOR */
        /* ===================================== */
        .vendor-card {
            display: flex;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-300);
            border-bottom: 1px solid var(--gray-300);
        }

        .vendor-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: var(--white);
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .vendor-info {
            flex: 1;
        }

        .vendor-name {
            font-size: 1.0625rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .vendor-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.625rem;
        }

        .vendor-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            background: var(--white);
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .vendor-action:hover {
            background: var(--gray-100);
            border-color: var(--gray-500);
            transform: translateY(-1px);
        }

        .vendor-action.contact {
            background: rgba(52, 152, 219, 0.08);
            border-color: rgba(52, 152, 219, 0.3);
            color: var(--accent);
        }

        .vendor-action.contact:hover {
            background: rgba(52, 152, 219, 0.15);
            border-color: var(--accent);
        }

        /* ===================================== */
        /* FOOTER DEL GRUPO */
        /* ===================================== */
        .group-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            background: var(--white);
        }

        .total-container {
            display: flex;
            flex-direction: column;
        }

        .total-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .total-amount {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        .checkout-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.875rem 1.75rem;
            background: var(--success);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .checkout-btn:hover {
            background: #229954;
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        /* ===================================== */
        /* RESPONSIVE */
        /* ===================================== */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
            }

            .logo {
                font-size: 1.25rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .item-image {
                max-width: 180px;
                margin: 0 auto;
            }

            .item-actions {
                flex-direction: row;
                justify-content: space-between;
            }

            .group-footer {
                flex-direction: column;
                gap: 1.25rem;
                align-items: stretch;
            }

            .checkout-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <div class="logo">
            <i class="fas fa-shopping-cart"></i>
            <?= defined('APP_NAME') ? APP_NAME : 'Compratica'; ?>
        </div>
        <div></div>
    </header>

    <div class="container">
        <h1 class="page-title">Tu Carrito de Compras</h1>
        
        <?php if (empty($groups)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="empty-title">Tu carrito está vacío</h2>
                <p class="empty-text">Explora nuestros espacios y añade productos para comenzar tu compra</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-store"></i> Explorar Espacios
                </a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <?php 
                $grandTotal = 0;
                foreach ($groups as $g): 
                    $grandTotal += $g['total'];
                ?>
                    <div class="cart-group">
                        <div class="group-header">
                            <h2 class="group-title">
                                <i class="fas fa-store-alt"></i> <?= h($g['sale_title']); ?>
                            </h2>
                            <p class="group-subtitle">Vendedor: <?= h($g['affiliate_name']); ?></p>
                        </div>
                        
                        <div class="cart-items">
                            <?php foreach ($g['items'] as $it): ?>
                                <div class="cart-item">
                                    <img class="item-image" src="<?= h($it['product_image_url'] ?: '/assets/placeholder.jpg'); ?>" alt="<?= h($it['product_name']); ?>">
                                    <div class="item-info">
                                        <div class="item-header">
                                            <h3 class="item-name"><?= h($it['product_name']); ?></h3>
                                            <?php if (!empty($it['description'])): ?>
                                                <p class="item-desc"><?= nl2br(h($it['description'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <p class="item-price">
                                            <?= fmt_price($it['unit_price'], $g['currency']); ?> × <?= (float)$it['qty']; ?> = 
                                            <strong><?= fmt_price($it['line_total'], $g['currency']); ?></strong>
                                        </p>
                                    </div>
                                    <div class="item-actions">
                                        <div class="qty-control">
                                            <button class="qty-btn qty-minus" data-pid="<?= (int)$it['product_id']; ?>" data-sale-id="<?= (int)$g['sale_id']; ?>">−</button>
                                            <span class="qty-display"><?= (float)$it['qty']; ?></span>
                                            <button class="qty-btn qty-plus" data-pid="<?= (int)$it['product_id']; ?>" data-sale-id="<?= (int)$g['sale_id']; ?>">+</button>
                                        </div>
                                        <button class="remove-btn" data-pid="<?= (int)$it['product_id']; ?>" data-sale-id="<?= (int)$g['sale_id']; ?>">
                                            <i class="fas fa-trash-alt"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($g['affiliate_phone'] || $g['affiliate_email']): ?>
                            <div class="vendor-card">
                                <div class="vendor-badge"><?= strtoupper(mb_substr(trim($g['affiliate_name'] ?: 'V'),0,1,'UTF-8')); ?></div>
                                <div class="vendor-info">
                                    <h3 class="vendor-name"><?= h($g['affiliate_name'] ?: 'Vendedor'); ?></h3>
                                    <div class="vendor-actions">
                                        <?php
                                        if (!empty($g['affiliate_phone'])):
                                            $raw_phone = trim($g['affiliate_phone']);
                                            $wa_digits = preg_replace('/\D/', '', $raw_phone);
                                            if ($wa_digits !== '') {
                                                if (strpos($wa_digits, '506') !== 0) {
                                                    $wa_digits = '506' . $wa_digits;
                                                }
                                                $wa_text = rawurlencode("Hola, estoy interesado en productos del espacio " . ($g['sale_title'] ?? ''));
                                        ?>
                                                <a class="vendor-action contact" href="https://wa.me/<?= h($wa_digits); ?>?text=<?= h($wa_text); ?>" target="_blank" rel="noopener noreferrer" title="Enviar WhatsApp al afiliado" aria-label="WhatsApp">
                                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                                </a>
                                        <?php
                                            }
                                        endif;
                                        ?>

                                        <?php if ($g['affiliate_email']): ?>
                                            <a class="vendor-action contact" href="mailto:<?= h($g['affiliate_email']); ?>">
                                                <i class="fas fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>
                                        <a class="vendor-action" href="store.php?sale_id=<?= (int)$g['sale_id']; ?>">
                                            <i class="fas fa-shopping-bag"></i> Seguir comprando
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="group-footer">
                            <div class="total-container">
                                <span class="total-label">Total del espacio</span>
                                <span class="total-amount"><?= fmt_price($g['total'], $g['currency']); ?></span>
                            </div>
                            <form action="checkout.php" method="post">
                                <input type="hidden" name="sale_id" value="<?= (int)$g['sale_id']; ?>">
                                <input type="hidden" name="csrf" value="<?= h($csrf_meta); ?>">
                                <button type="submit" class="checkout-btn">
                                    <i class="fas fa-credit-card"></i> Pagar este espacio
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    const API = '/api/cart.php';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function updateQty(pid, saleId, qty){
        const token = (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || CSRF;
        const headers = { 'Content-Type':'application/json' };
        if (token) headers['X-CSRF-Token'] = token;
        const r = await fetch(API+'?action=update', { 
            method:'POST', 
            headers, 
            body:JSON.stringify({ product_id:pid, sale_id:saleId, qty }), 
            credentials:'include', 
            cache:'no-store' 
        });
        const txt = await r.text(); 
        try { return JSON.parse(txt); } 
        catch { return { ok:false, error: txt }; }
    }

    async function removeItem(pid, saleId){
        const token = (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || CSRF;
        const headers = { 'Content-Type':'application/json' };
        if (token) headers['X-CSRF-Token'] = token;
        const r = await fetch(API+'?action=remove', { 
            method:'POST', 
            headers, 
            body:JSON.stringify({ product_id:pid, sale_id:saleId }), 
            credentials:'include', 
            cache:'no-store' 
        });
        const txt = await r.text(); 
        try { return JSON.parse(txt); } 
        catch { return { ok:false, error: txt }; }
    }

    document.addEventListener('click', async (e)=>{
        const plus = e.target.closest('.qty-plus');
        if (plus) {
            const pid = Number(plus.dataset.pid), saleId = Number(plus.dataset.saleId);
            const item = plus.closest('.cart-item'); 
            const display = item.querySelector('.qty-display'); 
            const current = Number(display.textContent);
            const j = await updateQty(pid, saleId, current + 1);
            if (j && j.ok) location.reload(); 
            else alert(j?.error || 'Error al actualizar');
            return;
        }
        
        const minus = e.target.closest('.qty-minus');
        if (minus) {
            const pid = Number(minus.dataset.pid), saleId = Number(minus.dataset.saleId);
            const item = minus.closest('.cart-item'); 
            const display = item.querySelector('.qty-display'); 
            const current = Number(display.textContent);
            if (current <= 1) return;
            const j = await updateQty(pid, saleId, current - 1);
            if (j && j.ok) location.reload(); 
            else alert(j?.error || 'Error al actualizar');
            return;
        }
        
        const remove = e.target.closest('.remove-btn');
        if (remove) {
            const pid = Number(remove.dataset.pid), saleId = Number(remove.dataset.saleId);
            if (!confirm('¿Eliminar este producto del carrito?')) return;
            const j = await removeItem(pid, saleId);
            if (j && j.ok) location.reload(); 
            else alert(j?.error || 'Error al eliminar');
            return;
        }
    });
    </script>
</body>
</html>
