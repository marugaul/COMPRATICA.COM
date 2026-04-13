<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ══════════════════════════════════════════════════════════════════════════
// LOGGING PARA DEBUG - Registra todos los errores en /logs/dashboard_debug.log
// ══════════════════════════════════════════════════════════════════════════
$LOG_FILE = __DIR__ . '/logs/dashboard_debug.log';
if (!is_dir(__DIR__ . '/logs')) @mkdir(__DIR__ . '/logs', 0777, true);

function debug_log($msg) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// Capturar todos los errores
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    debug_log("PHP ERROR [$errno]: $errstr en $errfile:$errline");
    return false; // Propagar el error
});

// Capturar excepciones no manejadas
set_exception_handler(function($e) {
    debug_log("EXCEPTION: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    debug_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "Error 500 - Ver /logs/dashboard_debug.log para detalles";
    exit;
});

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        debug_log("FATAL ERROR: {$error['message']} en {$error['file']}:{$error['line']}");
    }
});

debug_log("========== INICIO DE PETICIÓN ==========");
debug_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
debug_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
// ══════════════════════════════════════════════════════════════════════════

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
debug_log("Sesión iniciada - UID: " . ($_SESSION['uid'] ?? 'no set'));
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/chat_helpers.php';
require_once __DIR__ . '/includes/live_cam.php';
require_once __DIR__ . '/includes/shipping_emprendedoras.php';
require_once __DIR__ . '/includes/avatar_builder.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: emprendedores-login.php');
    exit;
}

$userId = (int)$_SESSION['uid'];
$userName = $_SESSION['name'] ?? 'Usuario';
$userEmail = $_SESSION['email'] ?? '';

$pdo = db();

// Verificar identidad de emprendedora (distingue de clientes finales)
if (empty($_SESSION['entrepreneur_id'])) {
    // Compatibilidad con sesiones existentes: buscar en DB
    $stmtEnt = $pdo->prepare("SELECT id FROM entrepreneurs WHERE user_id = ?");
    $stmtEnt->execute([$userId]);
    $entId = (int)($stmtEnt->fetchColumn() ?: 0);
    if ($entId > 0) {
        $_SESSION['entrepreneur_id'] = $entId;
    } else {
        // No es emprendedora registrada, redirigir a login
        session_destroy();
        header('Location: emprendedores-login.php?error=not_entrepreneur');
        exit;
    }
}
$entrepreneurId = (int)$_SESSION['entrepreneur_id'];

// Obtener suscripción más reciente (activa o pendiente)
try {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as plan_name, p.max_products, p.commission_rate
        FROM entrepreneur_subscriptions s
        JOIN entrepreneur_plans p ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.status IN ('active', 'pending')
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: emprendedores-planes.php');
    exit;
}

// Sin suscripción → ir a planes
if (!$subscription) {
    header('Location: emprendedores-planes.php');
    exit;
}

$isPending   = $subscription['status'] === 'pending';
$isPaidPlan  = !$isPending && ($subscription['price_monthly'] ?? 0) > 0;
// Fallback: consultar directamente por si el join no trajo price_monthly
if (!isset($subscription['price_monthly'])) {
    $isPaidPlan = hasPaidPlan($pdo, $userId);
}

// Obtener estadísticas de productos
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_products,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
        SUM(views_count) as total_views,
        SUM(sales_count) as total_sales
    FROM entrepreneur_products
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener productos
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON p.category_id = c.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$userId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los pedidos con detalle completo
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.price as product_price
    FROM entrepreneur_orders o
    JOIN entrepreneur_products p ON o.product_id = p.id
    WHERE o.seller_user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}
$isLoggedIn = true;

// ── Migración silenciosa: columnas de personalización y seller_type ──────────
try {
    $colsU = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('store_color1',       $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_color1 TEXT DEFAULT '#667eea'");
    if (!in_array('store_color2',       $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_color2 TEXT DEFAULT '#764ba2'");
    if (!in_array('store_banner_style', $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_banner_style TEXT DEFAULT 'stripes'");
    if (!in_array('store_logo',         $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_logo TEXT");
    if (!in_array('seller_type',        $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN seller_type TEXT DEFAULT 'emprendedor'");
    if (!in_array('store_avatar',       $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_avatar TEXT");
    if (!in_array('store_name',         $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_name TEXT");
    if (!in_array('store_banner_text',  $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN store_banner_text TEXT");
    // Métodos de pago globales del emprendedor
    if (!in_array('global_accepts_sinpe',  $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN global_accepts_sinpe INTEGER DEFAULT 0");
    if (!in_array('global_sinpe_phone',    $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN global_sinpe_phone TEXT");
    if (!in_array('global_accepts_paypal', $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN global_accepts_paypal INTEGER DEFAULT 0");
    if (!in_array('global_paypal_email',   $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN global_paypal_email TEXT");
    if (!in_array('global_accepts_card',   $colsU)) $pdo->exec("ALTER TABLE users ADD COLUMN global_accepts_card INTEGER DEFAULT 0");
} catch (Throwable $_e) {}

// ── Migración: columnas de pago y envío en entrepreneur_orders ────────────────
try {
    $colsO = array_column($pdo->query("PRAGMA table_info(entrepreneur_orders)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('payment_method',   $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN payment_method TEXT");
    if (!in_array('payment_ref',      $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN payment_ref TEXT");
    if (!in_array('receipt_url',      $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN receipt_url TEXT");
    if (!in_array('shipping_method',  $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN shipping_method TEXT");
    if (!in_array('shipping_zone',    $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN shipping_zone TEXT");
    if (!in_array('shipping_cost',    $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN shipping_cost INTEGER DEFAULT 0");
    if (!in_array('shipping_address', $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN shipping_address TEXT");
    if (!in_array('buyer_phone',      $colsO)) $pdo->exec("ALTER TABLE entrepreneur_orders ADD COLUMN buyer_phone TEXT");
} catch (Throwable $_e) {}

// ── Manejar guardado de avatar ────────────────────────────────────────────────
$avatarMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_store_avatar') {
    $avType      = in_array($_POST['av_type'] ?? '', ['woman','man','girl','boy']) ? $_POST['av_type'] : 'woman';
    $avStyle     = in_array($_POST['av_style'] ?? '', ['classic','adventure']) ? $_POST['av_style'] : 'classic';
    // Skin acepta hex directo
    $skinPost    = $_POST['av_skin'] ?? '';
    $advSkinPost = $_POST['av_adv_skin'] ?? '';
    $avSkin      = preg_match('/^#[0-9a-fA-F]{6}$/i', $skinPost) ? $skinPost : '#EDB98A';
    $avAdvSkin   = preg_match('/^#[0-9a-fA-F]{6}$/i', $advSkinPost) ? $advSkinPost : '#ecad80';
    $avHStyle    = in_array($_POST['av_hair_style'] ?? '', array_keys(AV_HAIR)) ? $_POST['av_hair_style'] : 'long_straight';
    $avAdvHair   = in_array($_POST['av_adv_hair']   ?? '', array_keys(AV_ADV_HAIR)) ? $_POST['av_adv_hair'] : 'long01';
    $avHColor    = preg_match('/^#[0-9a-fA-F]{6}$/i', $_POST['av_hair_color'] ?? '') ? $_POST['av_hair_color'] : '#8B4513';
    $avAdvHColor = preg_match('/^#[0-9a-fA-F]{6}$/i', $_POST['av_adv_hair_color'] ?? '') ? $_POST['av_adv_hair_color'] : '#8B4513';
    $avEStyle    = in_array($_POST['av_eye_style']   ?? '', array_keys(AV_EYES))     ? $_POST['av_eye_style']   : 'happy';
    $avAdvEyes   = in_array($_POST['av_adv_eyes']    ?? '', array_keys(AV_ADV_EYES)) ? $_POST['av_adv_eyes']    : 'variant09';
    $avAdvMouth  = in_array($_POST['av_adv_mouth']   ?? '', array_keys(AV_ADV_MOUTH))? $_POST['av_adv_mouth']   : 'variant01';
    $avOutfit    = preg_match('/^#[0-9a-fA-F]{6}$/i', $_POST['av_outfit']      ?? '') ? $_POST['av_outfit']   : '#667eea';
    $avAcc       = in_array($_POST['av_accessory']   ?? '', array_keys(AV_ACCESSORIES)) ? $_POST['av_accessory'] : 'none';
    $avClothes   = in_array($_POST['av_clothes']     ?? '', array_keys(AV_CLOTHES)) ? $_POST['av_clothes']     : 'shirtCrewNeck';
    $avFacial    = in_array($_POST['av_facialHair']  ?? '', array_keys(AV_FACIAL_HAIR)) ? $_POST['av_facialHair'] : '';
    $avBodyShape = in_array($_POST['av_body_shape']  ?? '', array_keys(AV_BODY)) ? $_POST['av_body_shape'] : 'average';

    // Construir config final según estilo seleccionado
    if ($avStyle === 'adventure') {
        $avatarCfgSave = [
            'style'       => 'adventure',
            'type'        => $avType,
            'skin'        => $avAdvSkin,
            'hair'        => $avAdvHair,
            'hairColor'   => $avAdvHColor,
            'eyes'        => $avEStyle,    // classic fallback
            'adv_eyes'    => $avAdvEyes,
            'adv_mouth'   => $avAdvMouth,
            'mouth'       => $avAdvMouth,  // usado por avatarUrlAdventure
            'clothesColor'=> $avOutfit,
            'accessory'   => 'none',
            'clothes'     => 'shirtCrewNeck',
            'facialHair'  => '',
            'body_shape'  => $avBodyShape,
        ];
    } else {
        $avatarCfgSave = [
            'style'       => 'classic',
            'type'        => $avType,
            'skin'        => $avSkin,
            'hair'        => $avHStyle,
            'hairColor'   => $avHColor,
            'eyes'        => $avEStyle,
            'clothesColor'=> $avOutfit,
            'accessory'   => $avAcc,
            'clothes'     => $avClothes,
            'facialHair'  => $avFacial,
            'body_shape'  => $avBodyShape,
            'mouth'       => 'smile',
            'adv_eyes'    => $avAdvEyes,
            'adv_mouth'   => $avAdvMouth,
        ];
    }
    try {
        $pdo->prepare("UPDATE users SET store_avatar=?, seller_type=? WHERE id=?")
            ->execute([json_encode($avatarCfgSave), ($avType === 'man' || $avType === 'boy') ? 'emprendedor' : 'emprendedora', $userId]);
        $avatarMsg = ['ok', '✅ Avatar guardado correctamente.'];
    } catch (Exception $e) {
        $avatarMsg = ['err', '❌ Error al guardar el avatar.'];
    }
}

// ── Cargar avatar actual ───────────────────────────────────────────────────────
$currentAvatar = avatarDefaults('woman');
try {
    $avRow = $pdo->prepare("SELECT store_avatar FROM users WHERE id=?");
    $avRow->execute([$userId]);
    $avData = $avRow->fetchColumn();
    if ($avData) {
        $parsed = json_decode($avData, true);
        if (is_array($parsed)) $currentAvatar = array_merge($currentAvatar, $parsed);
    }
} catch (Throwable $_e) {}

// ── Manejar guardado de diseño del puesto ────────────────────────────────────
$designMsg = '';
// ── Tabla store_banners ───────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS store_banners (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        banner_text TEXT,
        image_url TEXT,
        scroll_speed TEXT DEFAULT 'normal',
        starts_at TEXT,
        ends_at TEXT,
        is_active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    // Agregar columna show_on si no existe aún
    $colsBnr = array_column($pdo->query("PRAGMA table_info(store_banners)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('show_on', $colsBnr)) $pdo->exec("ALTER TABLE store_banners ADD COLUMN show_on TEXT DEFAULT 'store'");
} catch (Throwable $_e) {}

// ── POST handlers para banners ────────────────────────────────────────────
$bannerMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bAction = $_POST['action'] ?? '';

    if ($bAction === 'save_banner') {
        $bid    = (int)($_POST['banner_id'] ?? 0);
        $btext  = mb_substr(trim($_POST['banner_text'] ?? ''), 0, 200);
        $bimg   = trim($_POST['image_url'] ?? '');
        if ($bimg && !preg_match('/^https?:\/\//i', $bimg)) $bimg = '';
        $bspeed  = in_array($_POST['scroll_speed'] ?? '', ['slow','normal','fast']) ? $_POST['scroll_speed'] : 'normal';
        $bshowon = in_array($_POST['show_on'] ?? '', ['store','catalog','both']) ? $_POST['show_on'] : 'store';
        $bstart  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['starts_at'] ?? '') ? $_POST['starts_at'] : null;
        $bend    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['ends_at']   ?? '') ? $_POST['ends_at']   : null;
        try {
            if ($bid > 0) {
                $pdo->prepare("UPDATE store_banners SET banner_text=?,image_url=?,scroll_speed=?,show_on=?,starts_at=?,ends_at=? WHERE id=? AND user_id=?")
                    ->execute([$btext ?: null, $bimg ?: null, $bspeed, $bshowon, $bstart, $bend, $bid, $userId]);
                $bannerMsg = ['ok', '✅ Banner actualizado.'];
            } else {
                $pdo->prepare("INSERT INTO store_banners (user_id,banner_text,image_url,scroll_speed,show_on,starts_at,ends_at,is_active) VALUES (?,?,?,?,?,?,?,1)")
                    ->execute([$userId, $btext ?: null, $bimg ?: null, $bspeed, $bshowon, $bstart, $bend]);
                $bannerMsg = ['ok', '✅ Banner creado correctamente.'];
            }
        } catch (Exception $e) { $bannerMsg = ['err', '❌ Error: ' . $e->getMessage()]; }
    }

    if ($bAction === 'delete_banner') {
        $bid = (int)($_POST['banner_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM store_banners WHERE id=? AND user_id=?")->execute([$bid, $userId]);
            $bannerMsg = ['ok', '✅ Banner eliminado.'];
        } catch (Exception $e) { $bannerMsg = ['err', '❌ Error al eliminar.']; }
    }

    if ($bAction === 'toggle_banner_active') {
        $bid = (int)($_POST['banner_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE store_banners SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=? AND user_id=?")->execute([$bid, $userId]);
            $bannerMsg = ['ok', '✅ Estado actualizado.'];
        } catch (Exception $e) { $bannerMsg = ['err', '❌ Error.']; }
    }
}

// ── Cargar banners del usuario ────────────────────────────────────────────
$userBanners = [];
try {
    $s = $pdo->prepare("SELECT * FROM store_banners WHERE user_id=? ORDER BY is_active DESC, sort_order ASC, id DESC");
    $s->execute([$userId]);
    $userBanners = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_store_design') {
    $color1      = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['store_color1'] ?? '') ? $_POST['store_color1'] : '#667eea';
    $color2      = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['store_color2'] ?? '') ? $_POST['store_color2'] : '#764ba2';
    $bStyle      = in_array($_POST['store_banner_style'] ?? '', ['stripes','gradient','solid','wave']) ? $_POST['store_banner_style'] : 'stripes';
    $sType       = in_array($_POST['seller_type'] ?? '', ['emprendedora','emprendedor']) ? $_POST['seller_type'] : 'emprendedora';
    $logo        = trim($_POST['store_logo'] ?? '');
    $storeName   = mb_substr(trim($_POST['store_name'] ?? ''), 0, 80);
    // Validar que la URL del logo sea segura
    if ($logo && !preg_match('/^https?:\/\//i', $logo)) $logo = '';

    try {
        $pdo->prepare("UPDATE users SET store_color1=?, store_color2=?, store_banner_style=?, store_logo=?, seller_type=?, store_name=? WHERE id=?")
            ->execute([$color1, $color2, $bStyle, $logo ?: null, $sType, $storeName ?: null, $userId]);
        $designMsg = ['ok', '✅ Diseño de tu puesto guardado correctamente.'];
    } catch (Exception $e) {
        $designMsg = ['err', '❌ Error al guardar: ' . $e->getMessage()];
    }
}

// ── Cargar configuración de diseño actual del usuario ─────────────────────────
$storeDesign = ['store_color1'=>'#667eea','store_color2'=>'#764ba2','store_banner_style'=>'stripes','store_logo'=>'','seller_type'=>'emprendedor','store_name'=>''];
try {
    $sd = $pdo->prepare("SELECT store_color1, store_color2, store_banner_style, store_logo, seller_type, store_name FROM users WHERE id=?");
    $sd->execute([$userId]);
    $row = $sd->fetch(PDO::FETCH_ASSOC);
    if ($row) $storeDesign = array_merge($storeDesign, array_filter($row, fn($v) => $v !== null));
} catch (Throwable $_e) {}

// ── Manejar guardado de opciones de envío ────────────────────────────────
$shippingMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_shipping') {
    debug_log("POST save_shipping recibido");
    debug_log("POST data: " . json_encode($_POST));

    try {
        $zones = [];
        $zoneNames  = $_POST['zone_name']  ?? [];
        $zonePrices = $_POST['zone_price'] ?? [];
        foreach ($zoneNames as $i => $name) {
            $name  = trim($name);
            $price = (int)($zonePrices[$i] ?? 0);
            if ($name !== '' && $price >= 0) {
                $zones[] = ['name' => $name, 'price' => $price];
            }
        }
        debug_log("Zonas procesadas: " . count($zones));

        $configData = [
            'enable_free_shipping' => isset($_POST['enable_free_shipping']) ? 1 : 0,
            'enable_pickup'        => isset($_POST['enable_pickup'])        ? 1 : 0,
            'enable_express'       => isset($_POST['enable_express'])       ? 1 : 0,
            'free_shipping_min'    => (int)($_POST['free_shipping_min'] ?? 0),
            'pickup_instructions'  => trim($_POST['pickup_instructions'] ?? ''),
            'express_zones'        => $zones,
        ];
        debug_log("Config a guardar: " . json_encode($configData));

        saveShippingConfig($pdo, $userId, $configData);
        debug_log("saveShippingConfig ejecutado OK");

        $shippingMsg = ['ok', '✅ Opciones de envío guardadas correctamente.'];
        debug_log("Redirigiendo a #shipping-section");
        header('Location: emprendedores-dashboard.php#shipping-section');
        exit;

    } catch (Throwable $e) {
        debug_log("ERROR en save_shipping: " . $e->getMessage());
        debug_log("Trace: " . $e->getTraceAsString());
        $shippingMsg = ['error', '❌ Error al guardar: ' . $e->getMessage()];
    }
}

// ── Manejar toggle EN VIVO ────────────────────────────────────────────────
$liveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_live') {
    $goLive    = (int)($_POST['go_live'] ?? 0);
    $liveTitle = trim($_POST['live_title'] ?? '');
    $liveLink  = trim($_POST['live_link'] ?? '');
    if ($goLive) {
        $pdo->prepare("UPDATE users SET is_live=1, live_title=?, live_link=?, live_started_at=datetime('now') WHERE id=?")
            ->execute([$liveTitle ?: 'EN VIVO', $liveLink ?: null, $userId]);
        $liveMsg = ['ok', '🔴 ¡Estás EN VIVO! Tu puesto aparece primero en el mercadito.'];
    } else {
        $pdo->prepare("UPDATE users SET is_live=0, live_title=NULL, live_link=NULL, live_started_at=NULL WHERE id=?")
            ->execute([$userId]);
        $liveMsg = ['info', '⚫ Transmisión finalizada. Tu puesto volvió al orden normal.'];
    }
    header('Location: emprendedores-dashboard.php#live-section');
    exit;
}

// Inicializar tablas de cámara live (también agrega columnas si no existen)
initLiveCamTables($pdo);

// Cargar datos de live actuales
try {
    $liveData = $pdo->prepare("SELECT COALESCE(is_live,0) AS is_live, live_title, live_link,
                                      COALESCE(live_type,'link') AS live_type, live_session_id
                               FROM users WHERE id=?");
    $liveData->execute([$userId]);
    $liveData = $liveData->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {
    $liveData = ['is_live' => 0, 'live_title' => '', 'live_link' => '', 'live_type' => 'link', 'live_session_id' => ''];
}

// Cargar config de envío del vendedor
$shippingConfig = getShippingConfig($pdo, $userId);

// ── Manejar guardado de métodos de pago ────────────────────────────────────
$paymentMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_payment_methods') {
    try {
        $acceptsSinpe  = isset($_POST['global_accepts_sinpe'])  ? 1 : 0;
        $sinpePhone    = trim($_POST['global_sinpe_phone']   ?? '');
        $acceptsPaypal = isset($_POST['global_accepts_paypal']) ? 1 : 0;
        $paypalEmail   = trim($_POST['global_paypal_email']  ?? '');
        $acceptsCard   = isset($_POST['global_accepts_card'])   ? 1 : 0;

        $pdo->prepare("UPDATE users SET
            global_accepts_sinpe=?, global_sinpe_phone=?,
            global_accepts_paypal=?, global_paypal_email=?,
            global_accepts_card=?
            WHERE id=?")
            ->execute([$acceptsSinpe, $sinpePhone, $acceptsPaypal, $paypalEmail, $acceptsCard, $userId]);

        $paymentMsg = ['ok', '✅ Métodos de pago guardados correctamente.'];
        header('Location: emprendedores-dashboard.php#payment-section');
        exit;
    } catch (Throwable $e) {
        $paymentMsg = ['error', '❌ Error al guardar: ' . $e->getMessage()];
    }
}

// Cargar métodos de pago actuales del usuario
try {
    $pmRow = $pdo->prepare("SELECT global_accepts_sinpe, global_sinpe_phone,
                                   global_accepts_paypal, global_paypal_email,
                                   global_accepts_card
                            FROM users WHERE id=?");
    $pmRow->execute([$userId]);
    $userPayment = $pmRow->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $_e) {
    $userPayment = [];
}

// ── Actualizar estado de pedido ──────────────────────────────────────────────
$orderMsg     = '';
$orderMsgType = 'success';
$empOrderStatuses = ['pending'=>'Pendiente','confirmed'=>'Pagado','completed'=>'Completado','cancelled'=>'Cancelado'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_emp_order') {
    $ordId     = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    if ($ordId > 0 && array_key_exists($newStatus, $empOrderStatuses)) {
        try {
            // Cargar pedido verificando pertenencia al vendedor
            $ordRow = $pdo->prepare("
                SELECT o.*, p.name AS product_name
                FROM entrepreneur_orders o
                JOIN entrepreneur_products p ON o.product_id = p.id
                WHERE o.id = ? AND o.seller_user_id = ?
            ");
            $ordRow->execute([$ordId, $userId]);
            $ordData = $ordRow->fetch(PDO::FETCH_ASSOC);
            if (!$ordData) throw new RuntimeException('Pedido no encontrado.');

            $prevStatus  = $ordData['status'] ?? '';
            $buyerEmail  = $ordData['buyer_email'] ?? '';
            $buyerName   = $ordData['buyer_name']  ?? '';
            $productName = $ordData['product_name'] ?? '';
            $total       = (float)($ordData['total_price'] ?? 0);

            $pdo->prepare("UPDATE entrepreneur_orders SET status=?, updated_at=datetime('now') WHERE id=? AND seller_user_id=?")
                ->execute([$newStatus, $ordId, $userId]);

            $orderMsg = 'Pedido #' . $ordId . ' actualizado a "' . $empOrderStatuses[$newStatus] . '".';

            // Email al comprador si el estado cambió
            if ($prevStatus !== $newStatus && $buyerEmail !== '') {
                $statusLabel = $empOrderStatuses[$newStatus];
                $icons = ['pending'=>'⏳','paid'=>'✅','shipped'=>'🚚','delivered'=>'🎉','cancelled'=>'❌','completed'=>'✅'];
                $icon  = $icons[$newStatus] ?? '📦';
                $sellerName = $storeDesign['store_name'] ?: ($_SESSION['name'] ?? 'El vendedor');
                $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                    <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:32px;text-align:center;border-radius:14px 14px 0 0;'>
                        <h1 style='color:#fff;margin:0;font-size:1.6rem;'>{$icon} Actualización de tu pedido</h1>
                    </div>
                    <div style='background:#fff;padding:32px;border:1px solid #e0e0e0;'>
                        <p style='font-size:1rem;'>Hola <strong>" . htmlspecialchars($buyerName) . "</strong>,</p>
                        <p style='font-size:1rem;color:#555;'>Tu pedido de <strong>" . htmlspecialchars($productName) . "</strong> en <strong>" . htmlspecialchars($sellerName) . "</strong> ha cambiado de estado:</p>
                        <div style='text-align:center;margin:24px 0;padding:20px;background:#f8f7ff;border-radius:12px;'>
                            <span style='font-size:1.4rem;font-weight:700;color:#667eea;'>{$icon} {$statusLabel}</span>
                        </div>
                        <p style='font-size:.9rem;color:#777;'>Total: <strong>₡" . number_format($total,0) . "</strong> | Pedido #" . $ordId . "</p>
                    </div>
                    <div style='background:#f9fafb;padding:16px;text-align:center;border-radius:0 0 14px 14px;color:#999;font-size:.8rem;'>
                        CompraTica — El marketplace costarricense
                    </div>
                </div>";
                try {
                    send_email($buyerEmail, "{$icon} Tu pedido está: {$statusLabel} — CompraTica", $html);
                } catch (Throwable $_e) {}
            }

            // PRG para evitar reenvío
            header('Location: ' . $_SERVER['PHP_SELF'] . '?order_ok=' . urlencode($orderMsg) . '#tab-pedidos');
            exit;
        } catch (Throwable $e) {
            $orderMsg     = 'Error: ' . $e->getMessage();
            $orderMsgType = 'error';
        }
    }
}
if (isset($_GET['order_ok'])) {
    $orderMsg     = $_GET['order_ok'];
    $orderMsgType = 'success';
}

// Verificar límite de productos
$canAddProducts = true;
if ($subscription['max_products'] > 0 && $stats['total_products'] >= $subscription['max_products']) {
    $canAddProducts = false;
}

// ── Calcular pasos de onboarding ─────────────────────────────────────────
$ob_hasStore    = !empty($storeDesign['store_name']);
$ob_hasPayment  = !empty($userPayment['global_accepts_sinpe'])  ||
                  !empty($userPayment['global_accepts_paypal']) ||
                  !empty($userPayment['global_accepts_card']);
$ob_hasShipping = !empty($shippingConfig['enable_pickup'])        ||
                  !empty($shippingConfig['enable_free_shipping']) ||
                  !empty($shippingConfig['enable_express']);
$ob_hasProduct  = ($stats['total_products'] ?? 0) > 0;
$ob_hasActiveSub = !$isPending;

$onboardingSteps = [
    [
        'num'   => 1,
        'icon'  => 'fa-store',
        'label' => 'Nombra y personaliza tu puesto',
        'desc'  => 'Elige el nombre de tu tienda, colores y diseño del toldo. Esto es lo primero que verán tus clientes al entrar a tu puesto en el mercadito.',
        'done'  => $ob_hasStore,
        'link'  => '#design-section',
        'btn'   => 'Personalizar puesto',
    ],
    [
        'num'   => 2,
        'icon'  => 'fa-credit-card',
        'label' => 'Configura cómo recibes pagos',
        'desc'  => 'Activa SINPE Móvil, PayPal o tarjeta de crédito para que tus clientes puedan pagarte. Sin esto no podrás recibir pedidos.',
        'done'  => $ob_hasPayment,
        'link'  => '#payment-section',
        'btn'   => 'Configurar pagos',
    ],
    [
        'num'   => 3,
        'icon'  => 'fa-truck',
        'label' => 'Define cómo entregas tus productos',
        'desc'  => 'Indica si haces envíos express por zona, si tienes retiro en local o si ofreces envío gratis. El cliente ve estas opciones al comprar.',
        'done'  => $ob_hasShipping,
        'link'  => '#shipping-section',
        'btn'   => 'Configurar envíos',
    ],
    [
        'num'   => 4,
        'icon'  => 'fa-plus-circle',
        'label' => 'Agrega tu primer producto',
        'desc'  => 'Crea la ficha de tu primer producto con foto, precio, descripción y stock. Una vez publicado aparecerá en el catálogo de Compratica.',
        'done'  => $ob_hasProduct,
        'link'  => 'emprendedoras-producto-crear.php',
        'btn'   => 'Crear producto',
    ],
    [
        'num'   => 5,
        'icon'  => 'fa-crown',
        'label' => 'Activa tu suscripción',
        'desc'  => 'Tu cuenta está pendiente de aprobación por el equipo de Compratica. Una vez aprobada, tu tienda quedará visible para todos los clientes.',
        'done'  => $ob_hasActiveSub,
        'link'  => 'emprendedores-planes.php',
        'btn'   => 'Ver estado',
    ],
];
$ob_done  = (int)array_sum(array_column($onboardingSteps, 'done'));
$ob_total = count($onboardingSteps);
$showOnboarding = $ob_done < $ob_total;

// Primer paso incompleto
$currentStepIdx = 0;
foreach ($onboardingSteps as $i => $step) {
    if (!$step['done']) { $currentStepIdx = $i; break; }
}
$currentStep = $onboardingSteps[$currentStepIdx];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Emprendedores</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .plan-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .section-header h2 {
            font-size: 1.5rem;
            color: #333;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .product-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f5f5f5;
        }
        .product-info {
            padding: 15px;
        }
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .product-price {
            color: #667eea;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .product-stats {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .product-actions {
            display: flex;
            gap: 10px;
        }
        .btn-small {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-edit {
            background: #667eea;
            color: white;
        }
        .btn-edit:hover {
            background: #5568d3;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .orders-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #333;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        /* ── ONBOARDING WIZARD SECUENCIAL ────────────────────── */
        .ob-wizard {
            background: white;
            border: 2px solid #e0e7ff;
            border-radius: 24px;
            padding: 36px 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(102,126,234,.12);
        }

        /* Track de progreso */
        .ob-track {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 36px;
            flex-wrap: nowrap;
            gap: 0;
        }
        .ob-track-step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0; }
        .ob-track-circle {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem;
            background: #f3f4f6; color: #9ca3af;
            border: 2px solid #e5e7eb;
            transition: all .3s;
        }
        .ob-track-done .ob-track-circle  { background: #d1fae5; color: #059669; border-color: #6ee7b7; }
        .ob-track-active .ob-track-circle { background: linear-gradient(135deg,#667eea,#764ba2); color: white; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,.18); }
        .ob-track-label { font-size: 0.7rem; color: #9ca3af; max-width: 72px; text-align: center; line-height: 1.3; white-space: normal; }
        .ob-track-done .ob-track-label   { color: #059669; }
        .ob-track-active .ob-track-label  { color: #4338ca; font-weight: 600; }
        .ob-track-line { flex: 1; height: 2px; background: #e5e7eb; min-width: 20px; margin-bottom: 22px; }
        .ob-track-line-done { background: #6ee7b7; }

        /* Cuerpo del paso actual */
        .ob-current { max-width: 480px; margin: 0 auto; }
        .ob-current-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #ede9fe, #dbeafe);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #667eea;
            margin: 0 auto 16px;
        }
        .ob-current-num { font-size: 0.8rem; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px; }
        .ob-current-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; margin: 0 0 12px; }
        .ob-current-desc { font-size: 0.95rem; color: #6b7280; line-height: 1.65; margin: 0 0 28px; }
        .ob-cta-btn {
            display: inline-flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; text-decoration: none;
            padding: 14px 32px; border-radius: 30px;
            font-size: 1rem; font-weight: 600;
            box-shadow: 0 4px 16px rgba(102,126,234,.35);
            transition: all .2s;
        }
        .ob-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(102,126,234,.4); color: white; }

        /* Footer */
        .ob-footer { margin-top: 24px; display: flex; align-items: center; justify-content: center; gap: 24px; flex-wrap: wrap; }
        .ob-skip-btn {
            background: none; border: none; cursor: pointer;
            color: #9ca3af; font-size: 0.85rem;
            text-decoration: underline; padding: 4px;
        }
        .ob-skip-btn:hover { color: #6b7280; }
        .ob-dash-link { font-size: 0.85rem; color: #667eea; text-decoration: none; font-weight: 500; }
        .ob-dash-link:hover { text-decoration: underline; }

        /* Barra colapsada */
        .ob-collapse-bar {
            display: none;
            align-items: center;
            gap: 12px;
            background: white;
            border: 2px solid #e0e7ff;
            border-radius: 14px;
            padding: 12px 20px;
            margin-bottom: 30px;
            cursor: pointer;
            font-size: 0.9rem;
            color: #4338ca;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(102,126,234,.08);
        }
        .ob-collapse-bar:hover { background: #f0f4ff; }
        .ob-collapse-bar .ob-mini-dots { display: flex; gap: 4px; }
        .ob-mini-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #e0e7ff;
        }
        .ob-mini-dot.done { background: #6ee7b7; }
        .ob-mini-dot.active { background: #667eea; }

        /* ── TAB NAV ───────────────────────────────────────────── */
        .tab-nav {
            display: flex;
            background: white;
            border-radius: 16px;
            padding: 8px;
            gap: 4px;
            margin-bottom: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            flex-wrap: wrap;
            align-items: center;
        }
        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #6b7280;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
            font-family: inherit;
        }
        .tab-btn:hover { background: #f3f4f6; color: #374151; }
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(102,126,234,.3);
        }
        .tab-btn.tab-create {
            margin-left: auto;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(102,126,234,.25);
        }
        .tab-btn.tab-create:hover { opacity: .88; }
        @media (max-width: 768px) {
            .tab-btn { padding: 9px 13px; font-size: .82rem; gap: 5px; }
            .tab-btn.tab-create { margin-left: 0; }
        }

        @media (max-width: 640px) {
            .ob-wizard { padding: 24px 20px; }
            .ob-track-label { display: none; }
            .ob-track-line { min-width: 12px; }
            .ob-current-title { font-size: 1.2rem; }
            .ob-cta-btn { width: 100%; justify-content: center; }
        }
        /* Pre-ocultar tabs no-resumen antes de que corra JS (evita flash) */
        [data-tab]:not([data-tab="resumen"]) { display: none; }
    </style>
</head>
<body>
    <?php
    $isLoggedIn = true;
    $cantidadProductos = 0;
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>👋 Hola, <?php echo htmlspecialchars($userName); ?></h1>
            <p>Bienvenid<?= ($storeDesign['seller_type'] ?? 'emprendedor') === 'emprendedor' ? 'o' : 'a' ?> a tu dashboard de emprendedor/a</p>
            <div class="plan-badge">
                <?php
                $sellerType  = ($storeDesign['seller_type'] ?? 'emprendedor');
                $planDisplay = htmlspecialchars($subscription['plan_name']);
                // Normalizar: siempre mostrar "Emprendedor" en lugar de "Emprendedora"
                $planDisplay = str_ireplace('Emprendedora', 'Emprendedor', $planDisplay);
                $planDisplay = str_ireplace('emprendedora', 'Emprendedor', $planDisplay);
                ?>
                <i class="fas fa-crown"></i> <?= $planDisplay ?>
                <?php if ($isPending): ?>
                    &nbsp;— <span style="color:#fde68a;">⏳ Pendiente de aprobación</span>
                <?php else: ?>
                    &nbsp;— <span style="color:#bbf7d0;">✅ Activo</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isPending): ?>
        <div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:16px;padding:30px 35px;margin-bottom:30px;display:flex;align-items:flex-start;gap:20px;">
            <div style="font-size:3rem;line-height:1;">⏳</div>
            <div>
                <h3 style="margin:0 0 8px;color:#92400e;font-size:1.25rem;">Tu suscripción está pendiente de aprobación</h3>
                <p style="margin:0 0 12px;color:#78350f;line-height:1.6;">
                    Recibimos tu comprobante de pago. Un administrador lo verificará y activará tu cuenta a la brevedad.<br>
                    Recibirás un correo cuando tu cuenta sea aprobada.
                </p>
                <table style="border-collapse:collapse;font-size:0.9rem;color:#78350f;">
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Plan:</td>
                        <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Método de pago:</td>
                        <td><?php echo ucfirst(htmlspecialchars($subscription['payment_method'] ?? 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Solicitud enviada:</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($subscription['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showOnboarding): ?>
        <!-- ── ONBOARDING WIZARD SECUENCIAL ──────────────────────────── -->
        <div class="ob-wizard" id="onboarding-wizard">

            <!-- Track de pasos -->
            <div class="ob-track">
                <?php foreach ($onboardingSteps as $i => $step):
                    $trackClass = $step['done'] ? 'ob-track-done' : ($i === $currentStepIdx ? 'ob-track-active' : '');
                ?>
                <div class="ob-track-step <?= $trackClass ?>">
                    <div class="ob-track-circle">
                        <?= $step['done'] ? '<i class="fas fa-check"></i>' : ($i + 1) ?>
                    </div>
                    <div class="ob-track-label"><?= htmlspecialchars($step['label']) ?></div>
                </div>
                <?php if ($i < $ob_total - 1): ?>
                <div class="ob-track-line <?= $step['done'] ? 'ob-track-line-done' : '' ?>"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Paso actual -->
            <div class="ob-current">
                <div class="ob-current-icon">
                    <i class="fas <?= htmlspecialchars($currentStep['icon']) ?>"></i>
                </div>
                <div class="ob-current-num">Paso <?= $currentStepIdx + 1 ?> de <?= $ob_total ?></div>
                <h2 class="ob-current-title"><?= htmlspecialchars($currentStep['label']) ?></h2>
                <p class="ob-current-desc"><?= htmlspecialchars($currentStep['desc']) ?></p>
                <a href="<?= htmlspecialchars($currentStep['link']) ?>" class="ob-cta-btn">
                    <?= htmlspecialchars($currentStep['btn']) ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <!-- Footer -->
            <div class="ob-footer">
                <button class="ob-skip-btn" onclick="collapseOnboarding()">Omitir configuración</button>
                <a href="#main-dashboard" class="ob-dash-link">
                    <i class="fas fa-chevron-down"></i> Ver mi dashboard
                </a>
            </div>

        </div>

        <!-- Barra mini cuando está colapsado -->
        <div class="ob-collapse-bar" id="ob-collapsed-bar" onclick="expandOnboarding()">
            <i class="fas fa-map-signs" style="color:#667eea;"></i>
            <span>Guía de inicio &mdash; paso <?= $currentStepIdx + 1 ?> de <?= $ob_total ?></span>
            <div class="ob-mini-dots">
                <?php foreach ($onboardingSteps as $i => $step): ?>
                <div class="ob-mini-dot <?= $step['done'] ? 'done' : ($i === $currentStepIdx ? 'active' : '') ?>"></div>
                <?php endforeach; ?>
            </div>
            <i class="fas fa-chevron-down" style="margin-left:auto; color:#9ca3af;"></i>
        </div>
        <!-- ── FIN ONBOARDING WIZARD ──────────────────────────────── -->
        <?php endif; ?>

        <div id="main-dashboard"></div>

        <div class="stats-grid" data-tab="resumen">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-box"></i></div>
                <div class="value"><?php echo number_format($stats['total_products']); ?></div>
                <div class="label">Productos Totales</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value"><?php echo number_format($stats['active_products']); ?></div>
                <div class="label">Productos Activos</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-eye"></i></div>
                <div class="value"><?php echo number_format($stats['total_views']); ?></div>
                <div class="label">Vistas Totales</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="value"><?php echo number_format($stats['total_sales']); ?></div>
                <div class="label">Ventas Totales</div>
            </div>
        </div>

        <!-- ── ACCESO RÁPIDO A SECCIONES ──────────────────────────── -->
        <div class="tab-nav" id="main-tab-nav">
            <button class="tab-btn active" onclick="showTab('resumen',this)"><i class="fas fa-chart-bar"></i> Resumen</button>
            <button class="tab-btn" onclick="showTab('productos',this)"><i class="fas fa-box"></i> Productos</button>
            <button class="tab-btn" onclick="showTab('avatar',this)"><i class="fas fa-user-circle"></i> Avatar</button>
            <button class="tab-btn" onclick="showTab('puesto',this)"><i class="fas fa-store"></i> Mi Puesto</button>
            <button class="tab-btn" onclick="showTab('envios',this)"><i class="fas fa-truck"></i> Envíos</button>
            <button class="tab-btn" onclick="showTab('pagos',this)"><i class="fas fa-credit-card"></i> Pagos</button>
            <button class="tab-btn" onclick="showTab('pedidos',this)"><i class="fas fa-receipt"></i> Pedidos</button>
            <button class="tab-btn" onclick="showTab('live',this)"><i class="fas fa-broadcast-tower"></i> En Vivo</button>
            <button class="tab-btn" onclick="showTab('banners',this)"><i class="fas fa-bullhorn"></i> Banners</button>
            <a href="emprendedoras-producto-crear.php" class="tab-btn tab-create"><i class="fas fa-plus"></i> Nuevo Producto</a>
        </div>

        <?php if (!$isPending && !$canAddProducts): ?>
            <div class="alert alert-warning" data-tab="resumen">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Límite alcanzado:</strong> Has llegado al límite de productos de tu plan (<?php echo $subscription['max_products']; ?> productos).
                <a href="emprendedores-planes.php" style="color: #667eea; font-weight: 600;">Actualiza tu plan</a> para agregar más productos.
            </div>
        <?php endif; ?>

        <!-- ── SECCIÓN EN VIVO ──────────────────────────────────────── -->
        <?php if (!$isPaidPlan): ?>
        <div class="section" id="live-section" data-tab="live" style="border: 2px solid #e2e8f0; opacity:.6; pointer-events:none;">
            <div class="section-header">
                <h2><i class="fas fa-broadcast-tower"></i> Transmisión en Vivo</h2>
                <span style="background:#f3f4f6;color:#6b7280;padding:6px 14px;border-radius:8px;font-size:.85rem;font-weight:600;">
                    <i class="fas fa-lock"></i> Solo planes de pago
                </span>
            </div>
            <div style="background:#fafafa;border-radius:12px;padding:20px;text-align:center;color:#9ca3af;">
                <i class="fas fa-video-slash" style="font-size:2.5rem;margin-bottom:12px;display:block;"></i>
                Activa un <strong>Plan Emprendedor/a</strong> o <strong>Plan Premium</strong> para usar el Live.
                <br><br>
                <a href="emprendedores-planes.php" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;">
                    <i class="fas fa-arrow-up"></i> Ver Planes
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="section" id="live-section" data-tab="live" style="border: 2px solid <?= $liveData['is_live'] ? '#ef4444' : '#e2e8f0' ?>; transition: border-color .3s;">
            <div class="section-header">
                <h2>
                    <?php if ($liveData['is_live']): ?>
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <span style="width:12px;height:12px;background:#ef4444;border-radius:50%;display:inline-block;animation:live-pulse 1.2s infinite;"></span>
                            EN VIVO ahora
                        </span>
                    <?php else: ?>
                        <i class="fas fa-broadcast-tower"></i> Transmisión en Vivo
                    <?php endif; ?>
                </h2>
                <?php if ($liveData['is_live']): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_live">
                        <input type="hidden" name="go_live" value="0">
                        <button type="submit" style="background:#ef4444;color:white;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-stop-circle"></i> Terminar Live
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <style>
            @keyframes live-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
            .live-toggle-card { background: #fafafa; border-radius: 12px; padding: 20px; }
            .live-field { margin-bottom: 14px; }
            .live-field label { display:block; font-weight:600; margin-bottom:6px; color:#555; font-size:.9rem; }
            .live-field input { width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:.95rem; box-sizing:border-box; }
            .live-field input:focus { border-color:#667eea; outline:none; }
            .btn-go-live { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; padding:12px 28px; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; display:inline-flex;align-items:center;gap:8px; transition:all .2s; }
            .btn-go-live:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(239,68,68,.4); }
            </style>

            <style>
            .live-mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
            @media(max-width:600px){ .live-mode-grid { grid-template-columns:1fr; } }
            .live-mode-card {
                border:2px solid #e2e8f0; border-radius:14px; padding:20px;
                cursor:pointer; transition:all .2s; text-align:center; background:#fafafa;
            }
            .live-mode-card:hover { border-color:#667eea; background:#f5f3ff; }
            .live-mode-card.selected { border-color:#667eea; background:#f5f3ff; box-shadow:0 0 0 3px rgba(102,126,234,.15); }
            .live-mode-card i { font-size:2.2rem; display:block; margin-bottom:10px; }
            .live-mode-card h4 { margin:0 0 6px; font-size:1rem; color:#1f2937; }
            .live-mode-card p { margin:0; font-size:.82rem; color:#6b7280; line-height:1.4; }
            #cam-preview-wrap {
                position:relative; background:#000; border-radius:12px; overflow:hidden;
                margin-bottom:14px; aspect-ratio:16/9; max-width:480px;
            }
            #cam-preview { width:100%; height:100%; object-fit:cover; display:block; transform:scaleX(-1); }
            .cam-live-badge {
                position:absolute; top:10px; left:10px;
                background:rgba(220,38,38,.9); color:white; border-radius:20px;
                padding:3px 10px; font-size:.78rem; font-weight:700;
                display:flex; align-items:center; gap:5px;
            }
            .cam-live-badge .dot { width:8px;height:8px;background:white;border-radius:50%;animation:live-pulse 1s infinite; }
            #cam-timer { position:absolute; top:10px; right:10px; background:rgba(0,0,0,.6);
                color:white; border-radius:8px; padding:3px 9px; font-size:.8rem; font-family:monospace; }
            </style>

            <?php if ($liveData['is_live'] && $liveData['live_type'] === 'camera'): ?>
            <!-- ── CÁMARA: en vivo ── -->
            <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start;">
                <div style="flex:1;min-width:260px;">
                    <div id="cam-preview-wrap">
                        <video id="cam-preview" autoplay muted playsinline></video>
                        <div class="cam-live-badge"><span class="dot"></span> EN VIVO</div>
                        <div id="cam-timer">00:00</div>
                    </div>
                    <p style="color:#888;font-size:.82rem;margin:6px 0;">
                        Tu cámara está transmitiendo en directo en tu tienda.
                    </p>
                </div>
                <div>
                    <strong style="color:#dc2626;font-size:1rem;display:block;margin-bottom:8px;">
                        <i class="fas fa-circle" style="animation:live-pulse 1s infinite;"></i>
                        <?= htmlspecialchars($liveData['live_title'] ?? 'En Vivo') ?>
                    </strong>
                    <button id="btn-stop-cam" onclick="stopCamLive()"
                            style="background:#ef4444;color:white;border:none;padding:11px 22px;border-radius:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-stop-circle"></i> Terminar Transmisión
                    </button>
                    <p style="color:#9ca3af;font-size:.78rem;margin-top:8px;">
                        <i class="fas fa-info-circle"></i> Al terminar, los clientes dejarán de verte.
                    </p>
                </div>
            </div>

            <?php elseif ($liveData['is_live'] && $liveData['live_type'] !== 'camera'): ?>
            <!-- ── LINK EXTERNO: en vivo ── -->
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
                <span style="font-size:2rem;">🔴</span>
                <div>
                    <strong style="color:#dc2626;font-size:1.05rem;"><?= htmlspecialchars($liveData['live_title'] ?? 'EN VIVO') ?></strong><br>
                    <?php if ($liveData['live_link']): ?>
                        <a href="<?= htmlspecialchars($liveData['live_link']) ?>" target="_blank" style="color:#667eea;font-size:.88rem;">
                            <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($liveData['live_link']) ?>
                        </a>
                    <?php endif; ?>
                    <div style="color:#888;font-size:.82rem;margin-top:4px;">Tu puesto aparece primero en el mercadito con el badge EN VIVO.</div>
                </div>
            </div>

            <?php else: ?>
            <!-- ── No live: elegir modo ── -->
            <p style="color:#666;margin:0 0 14px;font-size:.9rem;">
                <i class="fas fa-info-circle" style="color:#667eea;"></i>
                Elige cómo quieres transmitir en vivo:
            </p>

            <!-- Selector de modo -->
            <div class="live-mode-grid">
                <div class="live-mode-card selected" id="card-link" onclick="selectLiveMode('link')">
                    <i class="fas fa-link" style="color:#667eea;"></i>
                    <h4>Link Externo</h4>
                    <p>YouTube, Facebook, Instagram, TikTok — pega tu link de stream</p>
                </div>
                <div class="live-mode-card" id="card-cam" onclick="selectLiveMode('cam')">
                    <i class="fas fa-video" style="color:#10b981;"></i>
                    <h4>Mi Cámara</h4>
                    <p>Transmite directo desde tu cámara web sin salir de la página</p>
                </div>
            </div>

            <!-- Formulario modo LINK -->
            <div id="form-link">
                <div class="live-toggle-card">
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_live">
                        <input type="hidden" name="go_live" value="1">
                        <div class="live-field">
                            <label for="live_title_input"><i class="fas fa-tag"></i> Título del live</label>
                            <input type="text" id="live_title_input" name="live_title" placeholder='Ej: "Nueva colección de verano 🌸"' maxlength="80">
                        </div>
                        <div class="live-field">
                            <label for="live_link_input"><i class="fab fa-youtube"></i> Link del live (YouTube, Facebook, Instagram, TikTok)</label>
                            <input type="url" id="live_link_input" name="live_link" placeholder="https://youtube.com/live/...">
                        </div>
                        <button type="submit" class="btn-go-live">
                            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
                            Iniciar EN VIVO con Link
                        </button>
                    </form>
                </div>
            </div>

            <!-- Panel modo CÁMARA -->
            <div id="form-cam" style="display:none;">
                <div class="live-toggle-card" style="background:#f0fdf4;">
                    <div class="live-field">
                        <label for="cam-title-input"><i class="fas fa-tag"></i> Título del live</label>
                        <input type="text" id="cam-title-input" placeholder='Ej: "Mostrando nueva colección 📦"' maxlength="80"
                               style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:.95rem;box-sizing:border-box;">
                    </div>
                    <div id="cam-preview-wrap" style="display:none;">
                        <video id="cam-preview" autoplay muted playsinline></video>
                        <div class="cam-live-badge"><span class="dot"></span> EN VIVO</div>
                        <div id="cam-timer">00:00</div>
                    </div>
                    <div id="cam-controls" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                        <button id="btn-preview-cam" onclick="previewCamera()"
                                style="background:#374151;color:white;border:none;padding:11px 20px;border-radius:10px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-camera"></i> Probar cámara
                        </button>
                        <button id="btn-start-cam" onclick="startCamLive()" disabled
                                class="btn-go-live" style="background:linear-gradient(135deg,#10b981,#059669);">
                            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
                            Iniciar EN VIVO con Cámara
                        </button>
                    </div>
                    <p id="cam-status-msg" style="color:#6b7280;font-size:.82rem;margin:8px 0 0;"></p>
                </div>
            </div>
            <?php endif; /* is_live modes */?>
        </div>
        </div><!-- /.section live-section (plan pago) -->
        <?php endif; /* isPaidPlan */ ?>
        <!-- ── FIN EN VIVO ────────────────────────────────────────────── -->

        <?php if ($isPaidPlan && $liveData['is_live']): ?>
        <!-- ── PANEL DE CHAT EN VIVO (vendedora) ─────────────────────── -->
        <div class="section" id="chat-seller-section" data-tab="live">
            <div class="section-header">
                <h2>
                    <span style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-comments" style="color:#667eea;"></i>
                        Chat en Vivo
                        <span id="seller-chat-new-badge" style="display:none;background:#ef4444;color:white;border-radius:20px;padding:2px 8px;font-size:.75rem;font-weight:800;"></span>
                    </span>
                </h2>
                <span style="color:#10b981;font-size:.85rem;font-weight:600;">
                    <i class="fas fa-circle" style="font-size:.6rem;"></i> Actualiza cada 3s
                </span>
            </div>

            <style>
            .seller-chat-wrap { display:grid; grid-template-columns:1fr 340px; gap:20px; }
            @media(max-width:800px){ .seller-chat-wrap { grid-template-columns:1fr; } }
            #seller-chat-log {
                background:#f8f9ff; border-radius:12px; padding:14px;
                height:320px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;
                border:1px solid #e5e7eb;
            }
            .schat-msg { max-width:90%; }
            .schat-msg.from-client { align-self:flex-start; }
            .schat-msg.from-seller { align-self:flex-end; }
            .schat-bubble {
                padding:9px 13px; border-radius:12px; font-size:.88rem; word-break:break-word;
                line-height:1.45;
            }
            .schat-msg.from-client .schat-bubble {
                background:white; color:#1f2937; box-shadow:0 1px 4px rgba(0,0,0,.08);
                border-bottom-left-radius:4px;
            }
            .schat-msg.from-seller .schat-bubble {
                background:#667eea; color:white; border-bottom-right-radius:4px;
            }
            .schat-msg.private .schat-bubble {
                background:#f0fdf4; color:#166534; border:1px dashed #86efac;
            }
            .schat-meta { font-size:.72rem; color:#9ca3af; margin-top:3px; padding:0 4px; }
            .schat-meta.right { text-align:right; }
            .schat-actions { display:flex; gap:6px; margin-top:4px; flex-wrap:wrap; }
            .schat-actions button {
                font-size:.72rem; padding:3px 9px; border-radius:6px;
                border:none; cursor:pointer; font-weight:600; transition:all .15s;
            }
            .btn-reply-pub { background:#dbeafe; color:#1d4ed8; }
            .btn-reply-priv { background:#d1fae5; color:#065f46; }
            .btn-ban { background:#fee2e2; color:#991b1b; }
            .btn-unban { background:#fef3c7; color:#92400e; }
            #seller-reply-area { background:#f9fafb; border-radius:12px; padding:16px; border:1px solid #e5e7eb; }
            #seller-reply-area h4 { margin:0 0 10px; font-size:.9rem; color:#374151; }
            #seller-reply-mode { font-size:.82rem; color:#059669; font-weight:600; margin-bottom:8px; min-height:20px; }
            #seller-reply-input {
                width:100%; box-sizing:border-box; border:2px solid #e5e7eb; border-radius:10px;
                padding:10px 12px; font-size:.9rem; resize:none; font-family:inherit;
                min-height:80px;
            }
            #seller-reply-input:focus { border-color:#667eea; outline:none; }
            .seller-reply-btns { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
            .seller-reply-btns button {
                padding:9px 18px; border-radius:10px; border:none; cursor:pointer;
                font-weight:700; font-size:.88rem; transition:all .2s;
            }
            #btn-broadcast { background:linear-gradient(135deg,#667eea,#764ba2); color:white; }
            #btn-send-pub  { background:#2563eb; color:white; }
            #btn-send-priv { background:#059669; color:white; }
            #btn-cancel-reply { background:#f3f4f6; color:#374151; }
            .banned-users-list { margin-top:14px; }
            .banned-users-list h5 { font-size:.82rem; color:#6b7280; margin:0 0 8px; }
            .banned-chip {
                display:inline-flex; align-items:center; gap:6px; background:#fef2f2;
                border:1px solid #fecaca; color:#991b1b; border-radius:20px;
                padding:3px 10px; font-size:.78rem; margin:3px;
            }
            .banned-chip button { background:none;border:none;cursor:pointer;color:#dc2626;padding:0;font-size:.9rem; }
            </style>

            <div class="seller-chat-wrap">
                <!-- Columna 1: historial -->
                <div>
                    <div id="seller-chat-log">
                        <div style="text-align:center;color:#9ca3af;font-size:.85rem;padding:20px;" id="seller-chat-empty">
                            <i class="fas fa-comment-slash" style="font-size:1.8rem;display:block;margin-bottom:8px;color:#d1d5db;"></i>
                            Esperando preguntas de tus clientes...
                        </div>
                    </div>
                </div>
                <!-- Columna 2: respuesta + baneados -->
                <div>
                    <div id="seller-reply-area">
                        <h4><i class="fas fa-paper-plane"></i> Enviar mensaje</h4>
                        <div id="seller-reply-mode">Transmisión a todos los clientes</div>
                        <textarea id="seller-reply-input" maxlength="500"
                            placeholder="Escribe tu mensaje..." rows="3"></textarea>
                        <div class="seller-reply-btns">
                            <button id="btn-broadcast" onclick="sellerSend(1,null)">
                                <i class="fas fa-bullhorn"></i> A todos
                            </button>
                            <button id="btn-send-pub" style="display:none" onclick="sellerSendReply(1)">
                                <i class="fas fa-globe"></i> En público
                            </button>
                            <button id="btn-send-priv" style="display:none" onclick="sellerSendReply(0)">
                                <i class="fas fa-lock"></i> Privado
                            </button>
                            <button id="btn-cancel-reply" style="display:none" onclick="cancelReply()">
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <div class="banned-users-list" id="banned-list-wrap" style="display:none;">
                        <h5><i class="fas fa-ban"></i> Usuarios baneados</h5>
                        <div id="banned-chips"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ── FIN PANEL CHAT (vendedora) ───────────────────────────── -->
        <?php elseif ($isPaidPlan && !$liveData['is_live']): ?>
        <div class="section" style="background:#f8f9ff;border:2px dashed #c7d2fe;">
            <div style="text-align:center;padding:16px;color:#6b7280;">
                <i class="fas fa-comments" style="font-size:2rem;color:#a5b4fc;display:block;margin-bottom:8px;"></i>
                <strong>Chat en Vivo disponible</strong> — Inicia tu Live para activar el chat con tus clientes.
            </div>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- ── SECCIÓN CREADOR DE AVATAR ──────────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="section" id="avatar-section" data-tab="avatar">
            <div class="section-header">
                <h2><i class="fas fa-user-astronaut" style="color:#8b5cf6;"></i> Mi Avatar Animado</h2>
                <p style="color:#6b7280;font-size:.9rem;margin-top:4px;">Crea tu personaje chibi que aparecerá en el catálogo como tu representante en el mercado.</p>
            </div>

            <?php if (!empty($avatarMsg)): ?>
            <div style="background:<?= $avatarMsg[0]==='ok'?'#f0fdf4':'#fef2f2' ?>;border:1px solid <?= $avatarMsg[0]==='ok'?'#86efac':'#fecaca' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $avatarMsg[0]==='ok'?'#166534':'#991b1b' ?>;">
                <?= htmlspecialchars($avatarMsg[1]) ?>
            </div>
            <?php endif; ?>

            <style>
            .av-grid { display:grid; grid-template-columns:200px 1fr; gap:24px; }
            @media(max-width:700px){ .av-grid { grid-template-columns:1fr; } }
            .av-preview-panel {
                display:flex; flex-direction:column; align-items:center; gap:12px;
                background:linear-gradient(160deg,#f0f4ff,#fdf0ff);
                border-radius:16px; padding:24px 16px; border:2px solid #e0e7ff;
                position:sticky; top:80px;
            }
            .av-preview-stage {
                width:130px; height:170px;
                display:flex; align-items:flex-end; justify-content:center;
                background:radial-gradient(ellipse at 50% 100%,rgba(139,92,246,.15) 0%,transparent 70%);
                border-radius:12px;
            }
            #av-full-preview { animation: avFloat 2.4s ease-in-out infinite; transform-origin: bottom center; }
            @keyframes avFloat {
                0%,100% { transform: translateY(0px) rotate(0deg); }
                35%     { transform: translateY(-7px) rotate(-1.5deg); }
                70%     { transform: translateY(-4px) rotate(1.2deg); }
            }
            .av-controls { display:flex; flex-direction:column; gap:18px; }
            .av-group { background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:16px; }
            .av-group-title { font-size:.85rem; font-weight:800; color:#4b5563; margin:0 0 12px; text-transform:uppercase; letter-spacing:.5px; display:flex; align-items:center; gap:6px; }
            .av-chips { display:flex; flex-wrap:wrap; gap:8px; }
            .av-chip {
                display:inline-flex; align-items:center; gap:5px;
                padding:8px 14px; border-radius:30px; border:2.5px solid #e5e7eb;
                font-size:.82rem; font-weight:700; cursor:pointer; background:white;
                transition:all .18s; user-select:none;
            }
            .av-chip:hover { border-color:#8b5cf6; color:#8b5cf6; }
            .av-chip.sel { background:#8b5cf6; border-color:#8b5cf6; color:white; box-shadow:0 3px 10px #8b5cf655; }
            .av-chip.sel-pink { background:#ec4899; border-color:#ec4899; color:white; box-shadow:0 3px 10px #ec489955; }
            .av-chip.sel-blue { background:#2563eb; border-color:#2563eb; color:white; box-shadow:0 3px 10px #2563eb55; }
            .av-skins { display:flex; gap:8px; flex-wrap:wrap; }
            .av-skin-btn {
                width:34px; height:34px; border-radius:50%; cursor:pointer; border:3px solid transparent;
                transition:all .18s; flex-shrink:0;
            }
            .av-skin-btn:hover { transform:scale(1.15); }
            .av-skin-btn.sel { border-color:#8b5cf6; box-shadow:0 0 0 2px #8b5cf6; }
            .av-color-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
            .av-color-dot {
                width:26px; height:26px; border-radius:50%; cursor:pointer;
                border:3px solid transparent; transition:all .18s; flex-shrink:0;
            }
            .av-color-dot:hover { transform:scale(1.2); }
            .av-color-dot.sel { border-color:#333; box-shadow:0 0 0 2px rgba(0,0,0,.4); }
            .av-toggle { display:flex; align-items:center; gap:10px; cursor:pointer; }
            .av-toggle input[type=checkbox] { width:18px; height:18px; accent-color:#ec4899; cursor:pointer; }
            </style>

            <form method="POST" id="av-form">
                <input type="hidden" name="action" value="save_store_avatar">

                <div class="av-grid">
                    <!-- PREVIEW -->
                    <div class="av-preview-panel">
                        <div class="av-preview-stage" style="width:160px;height:240px;">
                            <!-- Preview compuesto: DiceBear portrait + cuerpo SVG -->
                            <div id="av-full-preview">
                                <?= avatarFull($currentAvatar, 155) ?>
                            </div>
                        </div>
                        <div style="margin-top:8px;font-size:.78rem;color:#6b7280;text-align:center;line-height:1.5;">
                            Vista previa completa<br>
                            <span style="font-size:.7rem;opacity:.7;">La card del catálogo muestra el retrato circular</span>
                        </div>
                        <button type="submit"
                                style="width:100%;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:white;border:none;padding:12px;border-radius:10px;font-weight:800;font-size:.95rem;cursor:pointer;margin-top:4px;">
                            <i class="fas fa-save"></i> Guardar Avatar
                        </button>
                    </div>

                    <!-- CONTROLES -->
                    <div class="av-controls">

                        <!-- ESTILO DE AVATAR -->
                        <div class="av-group" style="border:2px solid #7c3aed22;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-radius:12px;padding:14px;">
                            <div class="av-group-title" style="color:#7c3aed;"><i class="fas fa-magic"></i> Estilo de avatar</div>
                            <div class="av-chips" id="chips-style">
                                <?php foreach (['classic'=>'👤 Clásico','adventure'=>'🐉 Fantasía'] as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['style']??'classic')===$v?'sel':'' ?>"
                                     data-group="style" data-val="<?= $v ?>"
                                     onclick="avSelect(this,'style');avStyleSwitch('<?= $v ?>')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_style" id="in-style" value="<?= htmlspecialchars($currentAvatar['style']??'classic') ?>">
                            <p style="font-size:.75rem;color:#6d28d9;margin:8px 0 0;opacity:.8;">
                                🐉 Fantasía usa personajes ilustrados con colores únicos, incluyendo tonos de dragón.
                            </p>
                        </div>

                        <!-- ── CONTROLES CLÁSICO ──────────────────── -->
                        <div id="av-classic-controls" style="<?= ($currentAvatar['style']??'classic')==='adventure'?'display:none':''; ?>">

                        <!-- TIPO DE PERSONAJE -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-user"></i> Tipo de personaje</div>
                            <div class="av-chips" id="chips-type">
                                <?php foreach (['woman'=>'👩 Mujer','man'=>'👨 Hombre','girl'=>'👧 Niña','boy'=>'👦 Niño'] as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['type']??'woman')===$v ? ($v==='man'||$v==='boy'?'sel-blue':'sel-pink') : '' ?>"
                                     data-group="type" data-val="<?= $v ?>" onclick="avSelect(this,'type')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_type" id="in-type" value="<?= htmlspecialchars($currentAvatar['type']??'woman') ?>">
                        </div>

                        <!-- TONO DE PIEL -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-hand-paper"></i> Tono de piel</div>
                            <div class="av-skins" id="skins-wrap">
                                <?php
                                $skinHexCurrent = avSkinHex($currentAvatar['skin'] ?? '#EDB98A');
                                foreach ([
                                    '#FDDBB4'=>'Muy claro','#EDB98A'=>'Claro','#D08B5B'=>'Medio',
                                    '#AE5D29'=>'Canela','#7D4E2D'=>'Oscuro','#2A1300'=>'Muy oscuro'
                                ] as $hex=>$lbl): ?>
                                <div class="av-skin-btn <?= $skinHexCurrent===$hex?'sel':'' ?>"
                                     style="background:<?= $hex ?>;" title="<?= $lbl ?>"
                                     data-val="<?= $hex ?>" onclick="avSkin(this)"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_skin" id="in-skin" value="<?= htmlspecialchars($skinHexCurrent) ?>">
                        </div>

                        <!-- ESTILO DE CABELLO -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-wind"></i> Cabello</div>
                            <div class="av-chips" id="chips-hair_style">
                                <?php foreach ([
                                    'long_straight'=>'💇 Largo','short_flat'=>'💈 Corto','long_curly'=>'🌀 Rizado',
                                    'long_bun'=>'🎀 Chongo','long_wavy'=>'🦄 Cola','sides'=>'⚡ Punk',
                                    'dreads'=>'🌿 Trenza','afro'=>'☁️ Afro'
                                ] as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['hair']??'long_straight')===$v?'sel':'' ?>"
                                     data-group="hair_style" data-val="<?= $v ?>" onclick="avSelect(this,'hair_style')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_hair_style" id="in-hair_style" value="<?= htmlspecialchars($currentAvatar['hair']??'long_straight') ?>">

                            <div style="margin-top:12px;">
                                <div style="font-size:.8rem;font-weight:600;color:#6b7280;margin-bottom:6px;">Color del cabello</div>
                                <div class="av-color-row">
                                    <?php foreach ([
                                        '#1a1a1a'=>'Negro','#4a2040'=>'Castaño oscuro','#8B4513'=>'Café',
                                        '#D4A843'=>'Rubio','#FF6B35'=>'Rojizo','#C0392B'=>'Rojo',
                                        '#8E44AD'=>'Morado','#2980B9'=>'Azul','#E8E8E8'=>'Plateado',
                                        '#FFB347'=>'Naranja','#27AE60'=>'Verde','#FF69B4'=>'Rosa',
                                    ] as $hex=>$lbl): ?>
                                    <div class="av-color-dot <?= ($currentAvatar['hairColor']??'#8B4513')===$hex?'sel':'' ?>"
                                         style="background:<?= $hex ?>;" title="<?= $lbl ?>"
                                         data-val="<?= $hex ?>" onclick="avHairColor(this)"></div>
                                    <?php endforeach; ?>
                                    <input type="color" id="av-hair-custom"
                                           value="<?= htmlspecialchars($currentAvatar['hairColor']??'#8B4513') ?>"
                                           oninput="avHairColorCustom(this.value)"
                                           style="width:34px;height:34px;border:2px solid #e5e7eb;border-radius:50%;cursor:pointer;padding:1px;"
                                           title="Color personalizado">
                                </div>
                            </div>
                            <input type="hidden" name="av_hair_color" id="in-hair_color" value="<?= htmlspecialchars($currentAvatar['hairColor']??'#8B4513') ?>">
                        </div>

                        <!-- EXPRESIÓN -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-smile"></i> Expresión de los ojos</div>
                            <div class="av-chips" id="chips-eye_style">
                                <?php foreach ([
                                    'happy'=>'😊 Feliz','default'=>'👁️ Normal','hearts'=>'✨ Estrella',
                                    'wink'=>'😉 Guiño','squint'=>'😴 Soñoliento'
                                ] as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['eyes']??'happy')===$v?'sel':'' ?>"
                                     data-group="eye_style" data-val="<?= $v ?>" onclick="avSelect(this,'eye_style')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_eye_style" id="in-eye_style" value="<?= htmlspecialchars($currentAvatar['eyes']??'happy') ?>">
                        </div>

                        <!-- ROPA -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-tshirt"></i> Color de ropa</div>
                            <div class="av-color-row">
                                <?php foreach ([
                                    '#667eea'=>'Lila','#ec4899'=>'Rosa','#ef4444'=>'Rojo','#f97316'=>'Naranja',
                                    '#f59e0b'=>'Amarillo','#22c55e'=>'Verde','#06b6d4'=>'Cyan',
                                    '#2563eb'=>'Azul','#7c3aed'=>'Morado','#6b7280'=>'Gris',
                                    '#1f2937'=>'Negro','#ffffff'=>'Blanco',
                                ] as $hex=>$lbl): ?>
                                <div class="av-color-dot <?= ($currentAvatar['clothesColor']??'#667eea')===$hex?'sel':'' ?>"
                                     style="background:<?= $hex ?>;<?= $hex==='#ffffff'?'border:2px solid #ddd;':'' ?>"
                                     title="<?= $lbl ?>" data-val="<?= $hex ?>" onclick="avOutfitColor(this)"></div>
                                <?php endforeach; ?>
                                <input type="color" id="av-outfit-custom"
                                       value="<?= htmlspecialchars($currentAvatar['clothesColor']??'#667eea') ?>"
                                       oninput="avOutfitColorCustom(this.value)"
                                       style="width:34px;height:34px;border:2px solid #e5e7eb;border-radius:50%;cursor:pointer;padding:1px;"
                                       title="Color personalizado">
                            </div>
                            <input type="hidden" name="av_outfit" id="in-outfit" value="<?= htmlspecialchars($currentAvatar['clothesColor']??'#667eea') ?>">
                        </div>

                        <!-- CONTORNO DEL CUERPO -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-male"></i> Contorno del cuerpo</div>
                            <div class="av-chips" id="chips-body_shape">
                                <?php foreach (AV_BODY as $v => $info): ?>
                                <div class="av-chip <?= ($currentAvatar['body_shape']??'average')===$v?'sel':'' ?>"
                                     data-group="body_shape" data-val="<?= $v ?>" onclick="avSelect(this,'body_shape')">
                                    <?= $info['label'] ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_body_shape" id="in-body_shape" value="<?= htmlspecialchars($currentAvatar['body_shape']??'average') ?>">
                        </div>

                        </div><!-- /av-classic-controls -->

                        <!-- ── CONTROLES FANTASÍA 🐉 ──────────────────── -->
                        <div id="av-adventure-controls" style="<?= ($currentAvatar['style']??'classic')!=='adventure'?'display:none':''; ?>">

                        <!-- TIPO (se comparte) -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-user"></i> Tipo de personaje</div>
                            <div class="av-chips">
                                <?php foreach (['woman'=>'👩 Mujer','man'=>'👨 Hombre','girl'=>'👧 Niña','boy'=>'👦 Niño'] as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['type']??'woman')===$v ? ($v==='man'||$v==='boy'?'sel-blue':'sel-pink') : '' ?>"
                                     data-group="type" data-val="<?= $v ?>" onclick="avSelect(this,'type')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- TONO DE PIEL FANTASÍA (colores normales + dragón) -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-palette"></i> Tono de piel</div>
                            <div class="av-skins" id="adv-skins-wrap">
                                <?php foreach (AV_ADV_SKIN as $hex=>$lbl): ?>
                                <div class="av-skin-btn <?= ($currentAvatar['skin']??'#ecad80')===$hex?'sel':'' ?>"
                                     style="background:<?= $hex ?>;" title="<?= $lbl ?>"
                                     data-val="<?= $hex ?>" onclick="avAdvSkin(this)"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_adv_skin" id="in-adv-skin" value="<?= htmlspecialchars($currentAvatar['skin']??'#ecad80') ?>">
                        </div>

                        <!-- CABELLO FANTASÍA -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-wind"></i> Cabello</div>
                            <div class="av-chips" id="chips-adv-hair">
                                <?php foreach (AV_ADV_HAIR as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['hair']??'long01')===$v?'sel':'' ?>"
                                     data-group="adv_hair" data-val="<?= $v ?>" onclick="avSelect(this,'adv_hair')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_adv_hair" id="in-adv-hair" value="<?= htmlspecialchars($currentAvatar['hair']??'long01') ?>">

                            <div style="margin-top:10px;">
                                <div style="font-size:.8rem;font-weight:600;color:#6b7280;margin-bottom:6px;">Color del cabello</div>
                                <div class="av-color-row" id="adv-hair-colors">
                                    <?php foreach (['#1a1a1a'=>'Negro','#8B4513'=>'Café','#D4A843'=>'Rubio','#FF6B35'=>'Rojizo','#8E44AD'=>'Morado','#2980B9'=>'Azul','#ff85a1'=>'Rosa','#7dcfb6'=>'Verde'] as $hex=>$lbl): ?>
                                    <div class="av-color-dot <?= ($currentAvatar['hairColor']??'#8B4513')===$hex?'sel':'' ?>"
                                         style="background:<?= $hex ?>;" title="<?= $lbl ?>"
                                         data-val="<?= $hex ?>" onclick="avAdvHairColor(this)"></div>
                                    <?php endforeach; ?>
                                    <input type="color" id="av-adv-hair-custom"
                                           value="<?= htmlspecialchars($currentAvatar['hairColor']??'#8B4513') ?>"
                                           oninput="avAdvHairColorCustom(this.value)"
                                           style="width:34px;height:34px;border:2px solid #e5e7eb;border-radius:50%;cursor:pointer;padding:1px;">
                                </div>
                            </div>
                            <input type="hidden" name="av_adv_hair_color" id="in-adv-hair-color" value="<?= htmlspecialchars($currentAvatar['hairColor']??'#8B4513') ?>">
                        </div>

                        <!-- OJOS FANTASÍA -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-eye"></i> Expresión de los ojos</div>
                            <div class="av-chips" id="chips-adv-eyes">
                                <?php foreach (AV_ADV_EYES as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['adv_eyes']??'variant09')===$v?'sel':'' ?>"
                                     data-group="adv_eyes" data-val="<?= $v ?>" onclick="avSelect(this,'adv_eyes')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_adv_eyes" id="in-adv-eyes" value="<?= htmlspecialchars($currentAvatar['adv_eyes']??'variant09') ?>">
                        </div>

                        <!-- BOCA FANTASÍA -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-smile"></i> Expresión de la boca</div>
                            <div class="av-chips" id="chips-adv-mouth">
                                <?php foreach (AV_ADV_MOUTH as $v=>$l): ?>
                                <div class="av-chip <?= ($currentAvatar['adv_mouth']??'variant01')===$v?'sel':'' ?>"
                                     data-group="adv_mouth" data-val="<?= $v ?>" onclick="avSelect(this,'adv_mouth')">
                                    <?= $l ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="av_adv_mouth" id="in-adv-mouth" value="<?= htmlspecialchars($currentAvatar['adv_mouth']??'variant01') ?>">
                        </div>

                        <!-- CONTORNO DEL CUERPO (compartido) -->
                        <div class="av-group">
                            <div class="av-group-title"><i class="fas fa-male"></i> Contorno del cuerpo</div>
                            <div class="av-chips">
                                <?php foreach (AV_BODY as $v => $info): ?>
                                <div class="av-chip <?= ($currentAvatar['body_shape']??'average')===$v?'sel':'' ?>"
                                     data-group="body_shape" data-val="<?= $v ?>" onclick="avSelect(this,'body_shape')">
                                    <?= $info['label'] ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        </div><!-- /av-adventure-controls -->

                    </div><!-- /av-controls -->
                </div><!-- /av-grid -->
            </form>
        </div>
        <!-- ── FIN SECCIÓN AVATAR ─────────────────────────────────────── -->

        <!-- ── SECCIÓN PERSONALIZAR MI PUESTO ──────────────────────────── -->
        <div class="section" id="design-section" data-tab="puesto">
            <div class="section-header">
                <h2><i class="fas fa-palette" style="color:#8b5cf6;"></i> Personalizar mi Puesto</h2>
            </div>

            <?php if (!empty($designMsg)): ?>
            <div style="background:<?= $designMsg[0]==='ok'?'#f0fdf4':'#fef2f2' ?>;border:1px solid <?= $designMsg[0]==='ok'?'#86efac':'#fecaca' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $designMsg[0]==='ok'?'#166534':'#991b1b' ?>;">
                <?= htmlspecialchars($designMsg[1]) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="design-form">
                <input type="hidden" name="action" value="save_store_design">

                <style>
                .design-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
                @media(max-width:640px){ .design-grid { grid-template-columns:1fr; } }
                .design-block { background:#f9fafb; border-radius:12px; padding:20px; border:1px solid #e5e7eb; }
                .design-block h4 { font-size:.95rem; font-weight:700; color:#374151; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
                .style-presets { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-top:8px; }
                .preset-btn {
                    border:3px solid transparent; border-radius:10px; cursor:pointer;
                    padding:0; overflow:hidden; aspect-ratio:3/2; transition:all .2s;
                    background:none;
                }
                .preset-btn.active { border-color:#8b5cf6; box-shadow:0 0 0 2px #8b5cf6; }
                .preset-btn:hover { transform:scale(1.04); }
                .preset-preview { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
                .color-row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
                .color-item { display:flex; flex-direction:column; gap:4px; }
                .color-item label { font-size:.8rem; font-weight:600; color:#6b7280; }
                .color-item input[type=color] { width:54px; height:38px; border:2px solid #e5e7eb; border-radius:8px; cursor:pointer; padding:2px; }
                .design-preview-wrap { background:#f3f4f6; border-radius:12px; padding:16px; border:1px solid #e5e7eb; }
                .mini-card {
                    max-width:280px; margin:0 auto; border-radius:12px; overflow:visible;
                    box-shadow:0 4px 20px rgba(0,0,0,.12);
                }
                .mini-awning {
                    height:40px; border-radius:10px 10px 0 0;
                    display:flex; align-items:center; justify-content:center;
                }
                .mini-body {
                    background:linear-gradient(to bottom,#f5f1e8,#ede8dc);
                    border:5px solid #d4a574; border-top:none;
                    border-radius:0 0 10px 10px; padding:12px 14px;
                }
                .mini-header { display:flex; align-items:center; gap:8px; }
                .mini-avatar {
                    width:34px; height:34px; border-radius:50%;
                    display:flex; align-items:center; justify-content:center;
                    font-weight:800; color:white; font-size:.9rem; flex-shrink:0;
                }
                .mini-products { display:grid; grid-template-columns:repeat(3,1fr); gap:2px; margin:8px 0; height:50px; }
                .mini-product { border-radius:3px; background:#e5e7eb; }
                .mini-footer { display:flex; justify-content:flex-end; }
                .mini-btn { padding:6px 14px; border-radius:8px; font-size:.75rem; font-weight:700; color:white; border:none; }
                </style>

                <div class="design-grid">
                    <!-- Columna izquierda: controles -->
                    <div>
                        <!-- Tipo de cuenta -->
                        <div class="design-block" style="margin-bottom:16px;">
                            <h4><i class="fas fa-user-tag"></i> Tipo de cuenta</h4>
                            <div style="display:flex;gap:12px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:10px;border:2px solid <?= ($storeDesign['seller_type']==='emprendedora')?'#db2777':'#e5e7eb' ?>;background:<?= ($storeDesign['seller_type']==='emprendedora')?'#fce7f3':'white' ?>;font-weight:600;font-size:.9rem;flex:1;justify-content:center;">
                                    <input type="radio" name="seller_type" value="emprendedora" <?= ($storeDesign['seller_type']==='emprendedora')?'checked':'' ?> style="accent-color:#db2777;">
                                    👩 Emprendedora
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 18px;border-radius:10px;border:2px solid <?= ($storeDesign['seller_type']==='emprendedor')?'#1d4ed8':'#e5e7eb' ?>;background:<?= ($storeDesign['seller_type']==='emprendedor')?'#dbeafe':'white' ?>;font-weight:600;font-size:.9rem;flex:1;justify-content:center;">
                                    <input type="radio" name="seller_type" value="emprendedor" <?= ($storeDesign['seller_type']==='emprendedor')?'checked':'' ?> style="accent-color:#1d4ed8;">
                                    👨 Emprendedor
                                </label>
                            </div>
                        </div>

                        <!-- Nombre del puesto -->
                        <div class="design-block" style="margin-bottom:16px;">
                            <h4><i class="fas fa-store-alt"></i> Nombre de tu puesto / tienda</h4>
                            <input type="text" name="store_name" id="store-name-input"
                                   value="<?= htmlspecialchars($storeDesign['store_name'] ?? '') ?>"
                                   placeholder="Ej: Artesanías Ticas, Café del Valle, Moda Sofía…"
                                   maxlength="80"
                                   oninput="updatePreview()"
                                   style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;font-size:.9rem;box-sizing:border-box;">
                            <p style="font-size:.78rem;color:#9ca3af;margin-top:6px;">
                                <i class="fas fa-info-circle"></i> Si lo dejas vacío se usará tu nombre de cuenta.
                            </p>
                        </div>

                        <!-- Colores -->
                        <div class="design-block" style="margin-bottom:16px;">
                            <h4><i class="fas fa-fill-drip"></i> Colores del toldo</h4>
                            <div class="color-row">
                                <div class="color-item">
                                    <label for="color1-pick">Color principal</label>
                                    <input type="color" name="store_color1" id="color1-pick"
                                           value="<?= htmlspecialchars($storeDesign['store_color1']) ?>"
                                           oninput="updatePreview()">
                                </div>
                                <div class="color-item">
                                    <label for="color2-pick">Color secundario</label>
                                    <input type="color" name="store_color2" id="color2-pick"
                                           value="<?= htmlspecialchars($storeDesign['store_color2']) ?>"
                                           oninput="updatePreview()">
                                </div>
                                <div style="flex:1;">
                                    <div style="font-size:.8rem;font-weight:600;color:#6b7280;margin-bottom:4px;">Paletas rápidas</div>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <?php foreach ([
                                            ['#ef4444','#dc2626','Rojo'],
                                            ['#f97316','#ea580c','Naranja'],
                                            ['#22c55e','#16a34a','Verde'],
                                            ['#8b5cf6','#7c3aed','Morado'],
                                            ['#06b6d4','#0891b2','Cyan'],
                                            ['#f59e0b','#d97706','Dorado'],
                                            ['#ec4899','#db2777','Rosa'],
                                            ['#1d4ed8','#1e40af','Azul'],
                                        ] as [$c1,$c2,$lbl]): ?>
                                        <button type="button" title="<?= $lbl ?>"
                                            onclick="setColors('<?= $c1 ?>','<?= $c2 ?>')"
                                            style="width:28px;height:28px;border-radius:50%;border:3px solid rgba(0,0,0,.1);
                                                   background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);cursor:pointer;
                                                   transition:transform .2s;" onmouseover="this.style.transform='scale(1.2)'"
                                            onmouseout="this.style.transform='scale(1)'">
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estilo del toldo -->
                        <div class="design-block" style="margin-bottom:16px;">
                            <h4><i class="fas fa-store"></i> Estilo del toldo</h4>
                            <div class="style-presets" id="style-presets">
                                <?php foreach ([
                                    ['stripes' ,'Rayas',   'linear-gradient(90deg,var(--c1) 50%,var(--c2) 50%)'],
                                    ['gradient','Degradado','linear-gradient(135deg,var(--c1),var(--c2))'],
                                    ['solid',   'Sólido',  'var(--c1)'],
                                    ['wave',    'Ondas',   'var(--c1)'],
                                ] as [$val,$lbl,$bg]):
                                    $active = $storeDesign['store_banner_style'] === $val;
                                ?>
                                <label style="cursor:pointer;">
                                    <input type="radio" name="store_banner_style" value="<?= $val ?>"
                                           <?= $active?'checked':'' ?> style="display:none"
                                           oninput="updatePreview()">
                                    <div class="preset-btn <?= $active?'active':'' ?>"
                                         onclick="this.parentNode.querySelector('input').checked=true;updatePreview();updateActiveStyle();"
                                         style="--c1:<?= htmlspecialchars($storeDesign['store_color1']) ?>;--c2:<?= htmlspecialchars($storeDesign['store_color2']) ?>;">
                                        <div class="preset-preview" style="background:<?= $bg ?>;"></div>
                                        <div style="text-align:center;font-size:.7rem;font-weight:700;color:#374151;padding:4px 0;"><?= $lbl ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Logo (URL) -->
                        <div class="design-block">
                            <h4><i class="fas fa-image"></i> Logo de tu tienda <span style="font-size:.75rem;font-weight:400;color:#9ca3af;">(opcional)</span></h4>
                            <input type="url" name="store_logo" id="logo-url"
                                   value="<?= htmlspecialchars($storeDesign['store_logo'] ?? '') ?>"
                                   placeholder="https://... URL de imagen de tu logo"
                                   oninput="updatePreview()"
                                   style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:10px;font-size:.9rem;box-sizing:border-box;">
                            <p style="font-size:.78rem;color:#9ca3af;margin-top:6px;">
                                <i class="fas fa-info-circle"></i> Sube tu logo a Google Drive, Imgur, o cualquier host de imágenes y pega la URL aquí.
                            </p>
                        </div>
                    </div>

                    <!-- Columna derecha: preview en vivo -->
                    <div>
                        <div class="design-preview-wrap">
                            <div style="font-size:.85rem;font-weight:700;color:#374151;margin-bottom:12px;text-align:center;">
                                <i class="fas fa-eye"></i> Vista previa en tiempo real
                            </div>
                            <div class="mini-card" id="mini-card-preview">
                                <div class="mini-awning" id="mini-awning" style="background:linear-gradient(135deg,<?= htmlspecialchars($storeDesign['store_color1']) ?>,<?= htmlspecialchars($storeDesign['store_color2']) ?>);">
                                </div>
                                <div class="mini-body">
                                    <div class="mini-header">
                                        <div class="mini-avatar" id="mini-avatar" style="background:<?= htmlspecialchars($storeDesign['store_color1']) ?>;">
                                            <?php if ($storeDesign['store_logo']): ?>
                                                <img src="<?= htmlspecialchars($storeDesign['store_logo']) ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <?= strtoupper(mb_substr($userName, 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div id="mini-store-name" style="font-weight:800;font-size:.88rem;color:#2c2416;"><?= htmlspecialchars(mb_substr($storeDesign['store_name'] ?: $userName, 0, 24)) ?></div>
                                            <div style="font-size:.75rem;color:#6b5d4f;">Mis productos</div>
                                        </div>
                                    </div>
                                    <div class="mini-products">
                                        <div class="mini-product" style="background:#e5e7eb;"></div>
                                        <div class="mini-product" style="background:#d1d5db;"></div>
                                        <div class="mini-product" style="background:#e5e7eb;"></div>
                                    </div>
                                    <div class="mini-footer">
                                        <div class="mini-btn" id="mini-btn" style="background:linear-gradient(135deg,<?= htmlspecialchars($storeDesign['store_color1']) ?>,<?= htmlspecialchars($storeDesign['store_color2']) ?>);">
                                            <i class="fas fa-store" style="font-size:.7rem;"></i> Entrar
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:16px;text-align:center;color:#6b7280;font-size:.82rem;">
                            <i class="fas fa-info-circle"></i> Así verán tu puesto los compradores en el catálogo
                        </div>
                    </div>
                </div>

                <div style="margin-top:24px;">
                    <button type="submit" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white;border:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:1rem;cursor:pointer;display:inline-flex;align-items:center;gap:10px;transition:all .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        <i class="fas fa-save"></i> Guardar diseño
                    </button>
                    <a href="emprendedores-catalogo.php" target="_blank" style="margin-left:16px;color:#667eea;font-size:.9rem;">
                        <i class="fas fa-eye"></i> Ver en el catálogo
                    </a>
                </div>
            </form>
        </div>
        <!-- ── FIN SECCIÓN PERSONALIZAR ──────────────────────────────────── -->

        <!-- ── SECCIÓN ENVÍO Y ENTREGA ─────────────────────────────────── -->
        <div class="section" id="shipping-section" data-tab="envios">
            <div class="section-header">
                <h2><i class="fas fa-truck"></i> Envío y Entrega</h2>
            </div>
            <?php if (!empty($shippingMsg)): ?>
            <div style="background:<?= $shippingMsg[0]==='ok' ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $shippingMsg[0]==='ok' ? '#86efac' : '#fecaca' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $shippingMsg[0]==='ok' ? '#166534' : '#991b1b' ?>;">
                <?= htmlspecialchars($shippingMsg[1]) ?>
            </div>
            <?php endif; ?>

            <p style="color:#6b7280;font-size:.9rem;margin:0 0 18px;">
                <i class="fas fa-info-circle" style="color:#667eea;"></i>
                Activa los métodos de entrega que ofreces. Los clientes los verán en el carrito.
            </p>

            <form method="POST" id="shipping-form">
                <input type="hidden" name="action" value="save_shipping">

                <style>
                .ship-method-card {
                    border: 2px solid #e5e7eb; border-radius: 14px; padding: 18px 20px;
                    margin-bottom: 14px; transition: border-color .2s, background .2s;
                }
                .ship-method-card.active { border-color: #667eea; background: #f5f3ff; }
                .ship-method-header {
                    display: flex; align-items: center; gap: 12px; cursor: pointer;
                }
                .ship-method-header label { cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; margin: 0; }
                .ship-method-body { margin-top: 14px; padding-top: 14px; border-top: 1px solid #e5e7eb; }
                .ship-field { margin-bottom: 12px; }
                .ship-field label { display:block; font-size:.85rem; font-weight:600; color:#555; margin-bottom:4px; }
                .ship-field input, .ship-field textarea {
                    width:100%; padding:9px 13px; border:2px solid #e0e0e0; border-radius:8px;
                    font-size:.92rem; box-sizing:border-box;
                }
                .ship-field input:focus, .ship-field textarea:focus { border-color:#667eea; outline:none; }
                .zone-row { display:grid; grid-template-columns:1fr 160px 36px; gap:8px; align-items:center; margin-bottom:8px; }
                .zone-row input { margin:0; }
                .btn-add-zone {
                    background: #f0f4ff; color: #667eea; border: 2px dashed #c7d2fe;
                    border-radius: 8px; padding: 8px 16px; cursor: pointer; font-size: .88rem;
                    font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
                    transition: background .2s;
                }
                .btn-add-zone:hover { background: #e0e7ff; }
                .btn-remove-zone { background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.1rem; padding:2px; line-height:1; }
                </style>

                <!-- RETIRO EN LOCAL -->
                <div class="ship-method-card <?= $shippingConfig['enable_pickup'] ? 'active' : '' ?>" id="card-pickup">
                    <div class="ship-method-header">
                        <input type="checkbox" name="enable_pickup" id="chk-pickup" value="1"
                               <?= $shippingConfig['enable_pickup'] ? 'checked' : '' ?>
                               onchange="toggleCard('pickup')"
                               style="width:18px;height:18px;accent-color:#667eea;cursor:pointer;">
                        <label for="chk-pickup">
                            <i class="fas fa-store" style="color:#667eea;font-size:1.2rem;"></i>
                            Retiro en local
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — El cliente recoge en tu dirección (sin costo)</span>
                        </label>
                    </div>
                    <div class="ship-method-body" id="body-pickup" style="<?= !$shippingConfig['enable_pickup'] ? 'display:none;' : '' ?>">
                        <div class="ship-field">
                            <label for="pickup_instructions"><i class="fas fa-map-marker-alt"></i> Instrucciones / dirección de retiro</label>
                            <textarea name="pickup_instructions" id="pickup_instructions" rows="2"
                                      placeholder="Ej: Av. Central, San José. Disponible lunes a sábado 9am–6pm."
                                      style="resize:vertical;"><?= htmlspecialchars($shippingConfig['pickup_instructions']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ENVÍO GRATIS -->
                <div class="ship-method-card <?= $shippingConfig['enable_free_shipping'] ? 'active' : '' ?>" id="card-free">
                    <div class="ship-method-header">
                        <input type="checkbox" name="enable_free_shipping" id="chk-free" value="1"
                               <?= $shippingConfig['enable_free_shipping'] ? 'checked' : '' ?>
                               onchange="toggleCard('free')"
                               style="width:18px;height:18px;accent-color:#10b981;cursor:pointer;">
                        <label for="chk-free">
                            <i class="fas fa-gift" style="color:#10b981;font-size:1.2rem;"></i>
                            Envío gratis
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — Tú absorbes el costo de envío</span>
                        </label>
                    </div>
                    <div class="ship-method-body" id="body-free" style="<?= !$shippingConfig['enable_free_shipping'] ? 'display:none;' : '' ?>">
                        <div class="ship-field">
                            <label for="free_shipping_min"><i class="fas fa-coins"></i> Monto mínimo de compra para aplicar (₡0 = siempre gratis)</label>
                            <input type="number" name="free_shipping_min" id="free_shipping_min"
                                   min="0" step="500"
                                   value="<?= (int)$shippingConfig['free_shipping_min'] ?>"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                <!-- ENVÍO EXPRESS -->
                <div class="ship-method-card <?= $shippingConfig['enable_express'] ? 'active' : '' ?>" id="card-express">
                    <div class="ship-method-header">
                        <input type="checkbox" name="enable_express" id="chk-express" value="1"
                               <?= $shippingConfig['enable_express'] ? 'checked' : '' ?>
                               onchange="toggleCard('express')"
                               style="width:18px;height:18px;accent-color:#f59e0b;cursor:pointer;">
                        <label for="chk-express">
                            <i class="fas fa-shipping-fast" style="color:#f59e0b;font-size:1.2rem;"></i>
                            Envío express
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — Con costo por zona que pagas el cliente</span>
                        </label>
                    </div>
                    <div class="ship-method-body" id="body-express" style="<?= !$shippingConfig['enable_express'] ? 'display:none;' : '' ?>">
                        <p style="color:#6b7280;font-size:.85rem;margin:0 0 12px;">
                            Define las zonas de entrega y sus precios. El cliente elegirá su zona al hacer el pedido.
                        </p>
                        <div style="display:grid;grid-template-columns:1fr 160px 36px;gap:8px;margin-bottom:6px;">
                            <span style="font-size:.8rem;font-weight:700;color:#9ca3af;">Zona / Descripción</span>
                            <span style="font-size:.8rem;font-weight:700;color:#9ca3af;">Precio (₡)</span>
                            <span></span>
                        </div>
                        <div id="zones-list">
                        <?php if (!empty($shippingConfig['express_zones'])): ?>
                            <?php foreach ($shippingConfig['express_zones'] as $zi => $zone): ?>
                            <div class="zone-row">
                                <input type="text" name="zone_name[]" value="<?= htmlspecialchars($zone['name']) ?>"
                                       placeholder="Ej: Gran Área Metropolitana">
                                <input type="number" name="zone_price[]" value="<?= (int)$zone['price'] ?>"
                                       min="0" step="100" placeholder="2000">
                                <button type="button" class="btn-remove-zone" onclick="removeZone(this)" title="Eliminar zona">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="zone-row">
                                <input type="text" name="zone_name[]" value="Gran Área Metropolitana (GAM)"
                                       placeholder="Ej: Gran Área Metropolitana">
                                <input type="number" name="zone_price[]" value="2000"
                                       min="0" step="100" placeholder="2000">
                                <button type="button" class="btn-remove-zone" onclick="removeZone(this)" title="Eliminar zona">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                        </div>
                        <button type="button" class="btn-add-zone" onclick="addZone()">
                            <i class="fas fa-plus"></i> Agregar zona
                        </button>
                    </div>
                </div>

                <button type="submit" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;padding:12px 28px;border-radius:10px;font-weight:700;font-size:.95rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px;margin-top:6px;">
                    <i class="fas fa-save"></i> Guardar opciones de envío
                </button>
            </form>
        </div>
        <!-- ── FIN ENVÍO Y ENTREGA ─────────────────────────────────────── -->

        <!-- ── SECCIÓN MÉTODOS DE PAGO ──────────────────────────────────── -->
        <div class="section" id="payment-section" data-tab="pagos">
            <div class="section-header">
                <h2><i class="fas fa-credit-card"></i> Métodos de Pago</h2>
            </div>

            <?php if (!empty($paymentMsg)): ?>
            <div style="background:<?= $paymentMsg[0]==='ok' ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $paymentMsg[0]==='ok' ? '#86efac' : '#fecaca' ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:<?= $paymentMsg[0]==='ok' ? '#166534' : '#991b1b' ?>;">
                <?= htmlspecialchars($paymentMsg[1]) ?>
            </div>
            <?php endif; ?>

            <p style="color:#6b7280;font-size:.9rem;margin:0 0 20px;">
                <i class="fas fa-info-circle" style="color:#667eea;"></i>
                Activa los métodos de pago que aceptas. Se mostrarán en el catálogo y en tu tienda.
            </p>

            <form method="POST" id="payment-form">
                <input type="hidden" name="action" value="save_payment_methods">

                <style>
                .pay-method-card {
                    border: 2px solid #e5e7eb; border-radius: 14px; padding: 18px 20px;
                    margin-bottom: 14px; transition: border-color .2s, background .2s;
                }
                .pay-method-card.active { border-color: #667eea; background: #f5f3ff; }
                .pay-method-header {
                    display: flex; align-items: center; gap: 12px; cursor: pointer;
                }
                .pay-method-header label { cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; margin: 0; }
                .pay-method-body { margin-top: 14px; padding-top: 14px; border-top: 1px solid #e5e7eb; }
                .pay-field { margin-bottom: 12px; }
                .pay-field label { display:block; font-size:.85rem; font-weight:600; color:#555; margin-bottom:4px; }
                .pay-field input { width:100%; padding:9px 13px; border:2px solid #e0e0e0; border-radius:8px; font-size:.92rem; box-sizing:border-box; }
                .pay-field input:focus { border-color:#667eea; outline:none; }
                </style>

                <!-- SINPE MÓVIL -->
                <?php $sinpeOn = !empty($userPayment['global_accepts_sinpe']); ?>
                <div class="pay-method-card <?= $sinpeOn ? 'active' : '' ?>" id="pm-card-sinpe">
                    <div class="pay-method-header">
                        <input type="checkbox" name="global_accepts_sinpe" id="chk-sinpe" value="1"
                               <?= $sinpeOn ? 'checked' : '' ?>
                               onchange="togglePayCard('sinpe')"
                               style="width:18px;height:18px;accent-color:#10b981;cursor:pointer;">
                        <label for="chk-sinpe">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#d1fae5;border-radius:8px;">
                                <i class="fas fa-mobile-alt" style="color:#059669;font-size:1rem;"></i>
                            </span>
                            SINPE Móvil
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — Pago por transferencia SINPE</span>
                        </label>
                    </div>
                    <div class="pay-method-body" id="pm-body-sinpe" style="<?= !$sinpeOn ? 'display:none;' : '' ?>">
                        <div class="pay-field">
                            <label for="global_sinpe_phone"><i class="fas fa-phone"></i> Número de teléfono SINPE</label>
                            <input type="tel" name="global_sinpe_phone" id="global_sinpe_phone"
                                   value="<?= htmlspecialchars($userPayment['global_sinpe_phone'] ?? '') ?>"
                                   placeholder="Ej: 8888-8888"
                                   maxlength="20">
                        </div>
                    </div>
                </div>

                <!-- PAYPAL -->
                <?php $paypalOn = !empty($userPayment['global_accepts_paypal']); ?>
                <div class="pay-method-card <?= $paypalOn ? 'active' : '' ?>" id="pm-card-paypal">
                    <div class="pay-method-header">
                        <input type="checkbox" name="global_accepts_paypal" id="chk-paypal" value="1"
                               <?= $paypalOn ? 'checked' : '' ?>
                               onchange="togglePayCard('paypal')"
                               style="width:18px;height:18px;accent-color:#003087;cursor:pointer;">
                        <label for="chk-paypal">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#dbeafe;border-radius:8px;">
                                <i class="fab fa-paypal" style="color:#003087;font-size:1rem;"></i>
                            </span>
                            PayPal
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — Pago con tarjeta o cuenta PayPal</span>
                        </label>
                    </div>
                    <div class="pay-method-body" id="pm-body-paypal" style="<?= !$paypalOn ? 'display:none;' : '' ?>">
                        <div class="pay-field">
                            <label for="global_paypal_email"><i class="fas fa-envelope"></i> Correo de cuenta PayPal</label>
                            <input type="email" name="global_paypal_email" id="global_paypal_email"
                                   value="<?= htmlspecialchars($userPayment['global_paypal_email'] ?? '') ?>"
                                   placeholder="tu@correo.com">
                        </div>
                    </div>
                </div>

                <!-- SWIFTPAY (TARJETA) -->
                <?php $cardOn = !empty($userPayment['global_accepts_card']); ?>
                <div class="pay-method-card <?= $cardOn ? 'active' : '' ?>" id="pm-card-card">
                    <div class="pay-method-header">
                        <input type="checkbox" name="global_accepts_card" id="chk-card" value="1"
                               <?= $cardOn ? 'checked' : '' ?>
                               onchange="togglePayCard('card')"
                               style="width:18px;height:18px;accent-color:#e53935;cursor:pointer;">
                        <label for="chk-card">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#fee2e2;border-radius:8px;">
                                <i class="fas fa-credit-card" style="color:#e53935;font-size:1rem;"></i>
                            </span>
                            SwiftPay (Tarjeta)
                            <span style="font-size:.8rem;color:#6b7280;font-weight:400;"> — Visa, Mastercard, American Express</span>
                        </label>
                    </div>
                    <div class="pay-method-body" id="pm-body-card" style="<?= !$cardOn ? 'display:none;' : '' ?>">
                        <p style="margin:0;font-size:.88rem;color:#6b7280;">
                            <i class="fas fa-shield-alt" style="color:#e53935;"></i>
                            El cobro se procesa automáticamente a través de SwiftPay. No requieres configuración adicional.
                        </p>
                    </div>
                </div>

                <button type="submit" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;padding:12px 28px;border-radius:10px;font-weight:700;font-size:.95rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px;margin-top:6px;">
                    <i class="fas fa-save"></i> Guardar métodos de pago
                </button>
            </form>
        </div>
        <!-- ── FIN MÉTODOS DE PAGO ───────────────────────────────────────── -->

        <div class="section" id="productos-section" data-tab="productos">
            <div class="section-header">
                <h2><i class="fas fa-box"></i> Mis Productos</h2>
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <?php if (!$isPending && !empty($products)): ?>
                    <a href="emprendedores-bulk-prices.php"
                       style="background:rgba(102,126,234,.1);color:#667eea;padding:10px 18px;border-radius:25px;text-decoration:none;font-weight:600;font-size:.875rem;display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(102,126,234,.3);transition:all .2s;"
                       onmouseover="this.style.background='rgba(102,126,234,.18)'" onmouseout="this.style.background='rgba(102,126,234,.1)'">
                        <i class="fas fa-percentage"></i> Ajuste de Precios
                    </a>
                    <?php endif; ?>
                    <?php if ($isPending): ?>
                    <button class="btn-primary" style="opacity: 0.5; cursor: not-allowed;" disabled>
                        <i class="fas fa-lock"></i> Cuenta pendiente
                    </button>
                    <?php elseif ($canAddProducts): ?>
                    <a href="emprendedores-producto-crear.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </a>
                    <?php else: ?>
                    <button class="btn-primary" style="opacity: 0.5; cursor: not-allowed;" disabled>
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isPending): ?>
                <div class="empty-state">
                    <i class="fas fa-lock" style="color:#f59e0b;"></i>
                    <h3>Sección bloqueada</h3>
                    <p>Podrás agregar productos una vez que tu suscripción sea aprobada por el administrador.</p>
                </div>
            <?php elseif (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No tienes productos aún</h3>
                    <p>Comienza a vender agregando tu primer producto</p>
                    <?php if ($canAddProducts): ?>
                        <a href="emprendedores-producto-crear.php" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Agregar mi primer producto
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['image_1']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_1']); ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">₡<?php echo number_format($product['price'], 0); ?></div>
                                <div class="product-stats">
                                    <span><i class="fas fa-eye"></i> <?php echo $product['views_count']; ?></span>
                                    <span><i class="fas fa-shopping-cart"></i> <?php echo $product['sales_count']; ?></span>
                                    <span><?php echo $product['is_active'] ? '<i class="fas fa-check-circle" style="color: #4ade80;"></i>' : '<i class="fas fa-times-circle" style="color: #ef4444;"></i>'; ?></span>
                                </div>
                                <div class="product-actions">
                                    <a href="emprendedores-producto-editar.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button class="btn-small btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section" data-tab="pedidos" id="orders-section">
            <div class="section-header">
                <h2><i class="fas fa-receipt"></i> Pedidos <span style="font-size:.85rem;font-weight:500;color:#6b7280;">(<?= count($orders) ?> total<?= count($orders) !== 1 ? 'es' : '' ?>)</span></h2>
            </div>
            <?php if ($orderMsg): ?>
                <div style="margin:0 0 14px;padding:12px 16px;border-radius:10px;font-weight:500;
                    background:<?= $orderMsgType==='success'?'#d1fae5':'#fee2e2' ?>;
                    color:<?= $orderMsgType==='success'?'#065f46':'#991b1b' ?>;
                    border:1px solid <?= $orderMsgType==='success'?'#6ee7b7':'#fca5a5' ?>;">
                    <i class="fas fa-<?= $orderMsgType==='success'?'check-circle':'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($orderMsg) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No hay pedidos aún</h3>
                    <p>Los pedidos de tus productos aparecerán aquí</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Producto</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Cant.</th>
                            <th>Total</th>
                            <th>Pago</th>
                            <th>Comprobante / Ref.</th>
                            <th>Envío</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $statusLabels = [
                            'pending'   => ['label' => 'Pendiente',  'color' => '#f59e0b', 'bg' => '#fffbeb'],
                            'confirmed' => ['label' => 'Pagado',     'color' => '#10b981', 'bg' => '#ecfdf5'],
                            'completed' => ['label' => 'Completado', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
                            'cancelled' => ['label' => 'Cancelado',  'color' => '#ef4444', 'bg' => '#fee2e2'],
                        ];
                        $payBadge = [
                            'sinpe'    => ['icon'=>'fa-mobile-alt',  'label'=>'SINPE',    'color'=>'#059669','bg'=>'#d1fae5'],
                            'paypal'   => ['icon'=>'fa-paypal',      'label'=>'PayPal',   'color'=>'#1d4ed8','bg'=>'#dbeafe'],
                            'swiftpay' => ['icon'=>'fa-credit-card', 'label'=>'Tarjeta',  'color'=>'#7c3aed','bg'=>'#ede9fe'],
                            'card'     => ['icon'=>'fa-credit-card', 'label'=>'Tarjeta',  'color'=>'#7c3aed','bg'=>'#ede9fe'],
                        ];
                        $shipLabels = [
                            'pickup'  => ['icon'=>'fa-store',         'label'=>'Retiro en local'],
                            'free'    => ['icon'=>'fa-gift',          'label'=>'Envío gratis'],
                            'express' => ['icon'=>'fa-shipping-fast', 'label'=>'Express'],
                            'mooving' => ['icon'=>'fa-motorcycle',    'label'=>'Mooving'],
                        ];
                        foreach ($orders as $order):
                            $st    = $order['status']          ?? 'pending';
                            $pm    = $order['payment_method']  ?? '';
                            $pref  = $order['payment_ref']     ?? '';
                            $rcpt  = $order['receipt_url']     ?? '';
                            $shm   = $order['shipping_method'] ?? '';
                            $shz   = $order['shipping_zone']   ?? '';
                            $stInfo  = $statusLabels[$st]   ?? ['label'=>ucfirst($st),'color'=>'#6b7280','bg'=>'#f3f4f6'];
                            $pmInfo  = $payBadge[$pm]        ?? null;
                            $shInfo  = $shipLabels[$shm]     ?? null;
                            // Fallback: detectar pago por notas (órdenes antiguas)
                            if (!$pmInfo && !empty($order['notes']) && stripos($order['notes'], 'swiftpay') !== false) {
                                $pmInfo = $payBadge['swiftpay'];
                                if (preg_match('/SwiftPay\s+(\S+)/i', $order['notes'], $m)) $pref = $m[1];
                            }
                        ?>
                        <tr>
                            <td style="font-weight:700;color:#667eea;">#<?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['product_name']) ?></td>
                            <td style="font-weight:600;">
                                <?= htmlspecialchars($order['buyer_name'] ?? '—') ?>
                                <?php if (!empty($order['buyer_email'])): ?>
                                <br><span style="font-size:.75rem;color:#9ca3af;font-weight:400;"><?= htmlspecialchars($order['buyer_email']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($order['buyer_phone'])): ?>
                                <a href="https://wa.me/506<?= preg_replace('/\D/','',$order['buyer_phone']) ?>"
                                   target="_blank" style="color:#25d366;text-decoration:none;font-weight:600;white-space:nowrap;">
                                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($order['buyer_phone']) ?>
                                </a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= (int)($order['quantity'] ?? 1) ?></td>
                            <td style="font-weight:700;white-space:nowrap;">₡<?= number_format($order['total_price'] ?? 0, 0) ?></td>
                            <td>
                                <?php if ($pmInfo): ?>
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.75rem;font-weight:700;color:<?= $pmInfo['color'] ?>;background:<?= $pmInfo['bg'] ?>;">
                                    <i class="fas <?= $pmInfo['icon'] ?>"></i> <?= $pmInfo['label'] ?>
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="font-size:.82rem;">
                                <?php if (!empty($rcpt)): ?>
                                    <a href="<?= htmlspecialchars($rcpt) ?>" target="_blank"
                                       style="color:#667eea;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                                        <i class="fas fa-paperclip"></i> Ver comprobante
                                    </a>
                                <?php elseif (!empty($pref)): ?>
                                    <span style="font-family:monospace;font-size:.78rem;color:#374151;" title="Referencia de transacción">
                                        <?= htmlspecialchars(substr($pref,0,20)) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#d1d5db;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.82rem;">
                                <?php if ($shInfo): ?>
                                <span style="white-space:nowrap;"><i class="fas <?= $shInfo['icon'] ?>" style="color:#667eea;"></i>
                                    <?= $shInfo['label'] ?><?= $shz ? ' — '.$shz : '' ?>
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;
                                    color:<?= $stInfo['color'] ?>;background:<?= $stInfo['bg'] ?>;">
                                    <?= $stInfo['label'] ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <?php
                                $canValidate = !empty($rcpt) && $st === 'pending' && $pm === 'sinpe';
                                ?>
                                <?php if ($canValidate): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('¿Marcar este pedido SINPE como Pagado?');">
                                        <input type="hidden" name="action"     value="update_emp_order">
                                        <input type="hidden" name="order_id"  value="<?= (int)$order['id'] ?>">
                                        <input type="hidden" name="new_status" value="confirmed">
                                        <button type="submit" style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border:none;border-radius:8px;background:#10b981;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;">
                                            <i class="fas fa-check-circle"></i> Validar pago
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline-flex;align-items:center;gap:4px;margin-top:<?= $canValidate?'4':'0' ?>px;">
                                    <input type="hidden" name="action"    value="update_emp_order">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <select name="new_status" style="padding:4px 6px;border:1.5px solid #d1d5db;border-radius:7px;font-size:.76rem;cursor:pointer;">
                                        <?php foreach ($empOrderStatuses as $sv => $sl): ?>
                                            <option value="<?= $sv ?>" <?= $st===$sv?'selected':'' ?>><?= $sl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" style="padding:4px 9px;border:none;border-radius:7px;background:#667eea;color:#fff;font-size:.76rem;font-weight:700;cursor:pointer;" title="Guardar estado">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td style="white-space:nowrap;font-size:.82rem;color:#6b7280;">
                                <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════ BANNERS ══════════════════ -->
        <?php
        $prevC1 = $storeDesign['store_color1'];
        $prevC2 = $storeDesign['store_color2'];
        $prevBg = match($storeDesign['store_banner_style'] ?? 'stripes') {
            'gradient' => "linear-gradient(to right,{$prevC1},{$prevC2})",
            'solid'    => $prevC1,
            'wave'     => "linear-gradient(160deg,{$prevC1} 0%,{$prevC2} 50%,{$prevC1} 100%)",
            default    => "repeating-linear-gradient(-45deg,{$prevC1} 0px,{$prevC1} 28px,{$prevC2} 28px,{$prevC2} 56px)",
        };
        $speedLabels = ['slow'=>'🐢 Lento','normal'=>'⚡ Normal','fast'=>'🚀 Rápido'];
        ?>
        <style>
        .bnr-card { background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;overflow:hidden;margin-bottom:14px;transition:box-shadow .2s; }
        .bnr-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.09); }
        .bnr-preview { height:60px;position:relative;overflow:hidden;display:flex;align-items:center; }
        .bnr-ticker  { white-space:nowrap;animation:bnrScroll 14s linear infinite;font-size:.82rem;font-weight:700;color:rgba(255,255,255,.92);text-shadow:0 1px 3px rgba(0,0,0,.45);padding:0 16px;display:inline-block; }
        @keyframes bnrScroll { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
        .bnr-meta   { padding:10px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;border-top:1px solid #f0f0f0; }
        .bnr-badge  { font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:20px; }
        .bnr-badge.active   { background:#d1fae5;color:#065f46; }
        .bnr-badge.inactive { background:#fee2e2;color:#991b1b; }
        .bnr-badge.speed    { background:#ede9fe;color:#4c1d95; }
        .bnr-badge.dates    { background:#fef3c7;color:#78350f; }
        .bnr-badge.showon   { background:#e0f2fe;color:#0c4a6e; }
        .bnr-show-btn { border:2px solid #e5e7eb;background:#fff;border-radius:9px;padding:7px 14px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .18s; }
        .bnr-show-btn.sel { border-color:<?= htmlspecialchars($prevC1) ?>;background:<?= htmlspecialchars($prevC1) ?>;color:#fff; }
        .bnr-actions { margin-left:auto;display:flex;gap:6px; }
        .bnr-btn  { border:none;border-radius:8px;padding:5px 12px;font-size:.78rem;font-weight:700;cursor:pointer;transition:opacity .2s; }
        .bnr-btn:hover { opacity:.8; }
        .bnr-form-wrap { background:#f8fafc;border:2px dashed #cbd5e1;border-radius:14px;padding:20px;margin-bottom:20px; }
        .bnr-emoji-grid { display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px; }
        .bnr-emoji-grid button { border:1px solid #e5e7eb;background:#fff;border-radius:6px;padding:4px 7px;font-size:1.05rem;cursor:pointer;transition:background .15s;line-height:1; }
        .bnr-emoji-grid button:hover { background:#ede9fe; }
        .bnr-speed-btns { display:flex;gap:8px;flex-wrap:wrap; }
        .bnr-speed-btn  { border:2px solid #e5e7eb;background:#fff;border-radius:9px;padding:7px 16px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .18s; }
        .bnr-speed-btn.sel { border-color:<?= htmlspecialchars($prevC1) ?>;background:<?= htmlspecialchars($prevC1) ?>;color:#fff; }
        .bnr-img-preview { max-height:80px;border-radius:8px;margin-top:8px;display:none;object-fit:cover;width:100%; }
        </style>

        <div class="section" id="banners-section" data-tab="banners" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:10px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-bullhorn"></i> Banners de Oferta</h2>
                <button onclick="bnrShowForm(0)" style="background:<?= htmlspecialchars($prevC1) ?>;color:#fff;border:none;padding:9px 20px;border-radius:10px;font-weight:700;font-size:.88rem;cursor:pointer;">
                    <i class="fas fa-plus"></i> Nuevo Banner
                </button>
            </div>
            <p style="color:#6b7280;font-size:.88rem;margin-bottom:20px;">
                Múltiples banners con texto desplazable o imagen. Programa fechas de inicio y fin para que se activen solos.
                <strong>Si no hay banners activos, el banner de colores se muestra igual.</strong>
            </p>

            <?php if ($bannerMsg): ?>
                <div style="padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:.88rem;background:<?= $bannerMsg[0]==='ok'?'#d1fae5':'#fee2e2' ?>;color:<?= $bannerMsg[0]==='ok'?'#065f46':'#991b1b' ?>;">
                    <?= htmlspecialchars($bannerMsg[1]) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario add/edit (visible si no hay banners) -->
            <div id="bnr-form-wrap" class="bnr-form-wrap" style="display:<?= empty($userBanners) ? 'block' : 'none' ?>;"><?php if (empty($userBanners)): ?><p style="font-size:.8rem;color:#6b7280;margin:0 0 14px;">Crea tu primer banner para activarlo en tu tienda.</p><?php endif; ?>
                <h3 style="margin:0 0 14px;font-size:1rem;color:#111;" id="bnr-form-title"><i class="fas fa-plus-circle" style="color:<?= htmlspecialchars($prevC1) ?>;"></i> Nuevo Banner</h3>
                <form method="POST" id="bnr-form">
                    <input type="hidden" name="action" value="save_banner">
                    <input type="hidden" name="banner_id" id="bnr-id" value="0">

                    <!-- Emoji picker -->
                    <div style="margin-bottom:12px;">
                        <label style="font-size:.8rem;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Insertar emoji / ícono rápido</label>
                        <div class="bnr-emoji-grid">
                            <?php foreach(['🔥','🎁','✨','🚀','💯','⏰','🛒','🎉','💥','🏷️','📦','🚚','⭐','💎','🌟','🆕','🎊','🪄','💸','🤩','🛍️','🎀','🏅','🥳','🎈'] as $em): ?>
                                <button type="button" onclick="bnrInsertEmoji('<?= $em ?>')" title="<?= $em ?>"><?= $em ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Texto -->
                    <div style="margin-bottom:14px;">
                        <label for="bnr-text" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:5px;"><i class="fas fa-align-left"></i> Texto del banner <span style="color:#9ca3af;font-weight:400;">(opcional si usas imagen)</span></label>
                        <textarea name="banner_text" id="bnr-text" maxlength="200" rows="2"
                            placeholder="Ej: 🔥 ¡20% OFF este fin de semana! · Envío gratis en compras mayores a ₡8,000"
                            oninput="document.getElementById('bnr-chars').textContent=(200-this.value.length)+' restantes'"
                            style="width:100%;padding:10px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:.9rem;resize:vertical;font-family:inherit;outline:none;"
                            onfocus="this.style.borderColor='<?= htmlspecialchars($prevC1) ?>'"
                            onblur="this.style.borderColor='#e5e7eb'"></textarea>
                        <span id="bnr-chars" style="font-size:.75rem;color:#9ca3af;">200 restantes</span>
                    </div>

                    <!-- Imagen URL -->
                    <div style="margin-bottom:14px;">
                        <label for="bnr-img-url" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:5px;"><i class="fas fa-image"></i> URL de imagen de banner <span style="color:#9ca3af;font-weight:400;">(opcional)</span></label>
                        <input type="url" name="image_url" id="bnr-img-url" placeholder="https://... (imagen ancha, ej. 960×110px)"
                            oninput="bnrPreviewImg(this.value)"
                            style="width:100%;padding:9px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:.88rem;outline:none;"
                            onfocus="this.style.borderColor='<?= htmlspecialchars($prevC1) ?>'"
                            onblur="this.style.borderColor='#e5e7eb'">
                        <img id="bnr-img-preview" class="bnr-img-preview" alt="Vista previa">
                    </div>

                    <!-- Velocidad -->
                    <div style="margin-bottom:14px;">
                        <label for="bnr-speed-val" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:8px;"><i class="fas fa-tachometer-alt"></i> Velocidad del texto</label>
                        <div class="bnr-speed-btns">
                            <button type="button" class="bnr-speed-btn" data-speed="slow"   onclick="bnrSetSpeed('slow')">🐢 Lento</button>
                            <button type="button" class="bnr-speed-btn sel" data-speed="normal" onclick="bnrSetSpeed('normal')">⚡ Normal</button>
                            <button type="button" class="bnr-speed-btn" data-speed="fast"   onclick="bnrSetSpeed('fast')">🚀 Rápido</button>
                        </div>
                        <input type="hidden" name="scroll_speed" id="bnr-speed-val" value="normal">
                    </div>

                    <!-- Mostrar en -->
                    <div style="margin-bottom:14px;">
                        <label for="bnr-show-val" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:8px;"><i class="fas fa-eye"></i> Mostrar en</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="bnr-show-btn sel" data-show="store"   onclick="bnrSetShow('store')">🏪 Solo tienda</button>
                            <button type="button" class="bnr-show-btn"     data-show="catalog" onclick="bnrSetShow('catalog')">🛍️ Solo catálogo</button>
                            <button type="button" class="bnr-show-btn"     data-show="both"    onclick="bnrSetShow('both')">✅ Ambos</button>
                        </div>
                        <input type="hidden" name="show_on" id="bnr-show-val" value="store">
                    </div>

                    <!-- Fechas -->
                    <div style="margin-bottom:18px;display:flex;gap:14px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:140px;">
                            <label for="bnr-start" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:5px;"><i class="fas fa-calendar-alt"></i> Inicio <span style="color:#9ca3af;font-weight:400;">(opcional)</span></label>
                            <input type="date" name="starts_at" id="bnr-start"
                                style="width:100%;padding:9px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:.88rem;outline:none;"
                                onfocus="this.style.borderColor='<?= htmlspecialchars($prevC1) ?>'"
                                onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                        <div style="flex:1;min-width:140px;">
                            <label for="bnr-end" style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:5px;"><i class="fas fa-calendar-check"></i> Fin <span style="color:#9ca3af;font-weight:400;">(opcional)</span></label>
                            <input type="date" name="ends_at" id="bnr-end"
                                style="width:100%;padding:9px 12px;border:2px solid #e5e7eb;border-radius:9px;font-size:.88rem;outline:none;"
                                onfocus="this.style.borderColor='<?= htmlspecialchars($prevC1) ?>'"
                                onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" style="background:<?= htmlspecialchars($prevC1) ?>;color:#fff;border:none;padding:10px 24px;border-radius:10px;font-weight:700;cursor:pointer;font-size:.9rem;">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" onclick="bnrHideForm()" style="background:#f3f4f6;color:#374151;border:none;padding:10px 18px;border-radius:10px;font-weight:600;cursor:pointer;font-size:.88rem;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de banners existentes -->
            <?php if (empty($userBanners)): ?>
                <div style="text-align:center;padding:40px 20px;background:#f8fafc;border-radius:14px;border:2px dashed #e2e8f0;">
                    <i class="fas fa-bullhorn" style="font-size:2.5rem;color:#d1d5db;display:block;margin-bottom:10px;"></i>
                    <p style="color:#9ca3af;font-weight:600;margin:0;">Aún no tienes banners. ¡Crea el primero!</p>
                </div>
            <?php else: ?>
                <?php foreach ($userBanners as $bnr):
                    $bActive  = (bool)$bnr['is_active'];
                    $bText    = htmlspecialchars($bnr['banner_text'] ?? '');
                    $bImg     = htmlspecialchars($bnr['image_url']   ?? '');
                    $bSpeed   = $speedLabels[$bnr['scroll_speed']] ?? '⚡ Normal';
                    $bStart   = $bnr['starts_at'] ?? '';
                    $bEnd     = $bnr['ends_at']   ?? '';
                    $dateStr  = $bStart || $bEnd ? ('📅 ' . ($bStart ?: '∞') . ' → ' . ($bEnd ?: '∞')) : '';
                    $showLabels = ['store'=>'🏪 Tienda','catalog'=>'🛍️ Catálogo','both'=>'✅ Ambos'];
                    $bShowOn  = $showLabels[$bnr['show_on'] ?? 'store'] ?? '🏪 Tienda';
                    $previewBgBnr = $bImg ? "url(".htmlspecialchars($bnr['image_url']).")  center/cover no-repeat" : $prevBg;
                ?>
                <div class="bnr-card">
                    <!-- Mini preview -->
                    <div class="bnr-preview" style="background:<?= $previewBgBnr ?>;opacity:<?= $bActive ? '1' : '.55' ?>;">
                        <?php if ($bText): ?>
                        <div class="bnr-ticker">
                            <?= $bText ?> &nbsp;✦&nbsp; <?= $bText ?>
                        </div>
                        <?php elseif (!$bImg): ?>
                        <span style="color:rgba(255,255,255,.6);font-size:.8rem;padding:0 16px;font-style:italic;">Solo imagen</span>
                        <?php endif; ?>
                    </div>
                    <!-- Meta -->
                    <div class="bnr-meta">
                        <span class="bnr-badge <?= $bActive ? 'active' : 'inactive' ?>">
                            <?= $bActive ? '● Activo' : '○ Inactivo' ?>
                        </span>
                        <span class="bnr-badge speed"><?= $bSpeed ?></span>
                        <span class="bnr-badge showon"><?= $bShowOn ?></span>
                        <?php if ($dateStr): ?>
                            <span class="bnr-badge dates"><?= htmlspecialchars($dateStr) ?></span>
                        <?php endif; ?>
                        <?php if ($bImg): ?>
                            <span class="bnr-badge" style="background:#e0f2fe;color:#0c4a6e;"><i class="fas fa-image"></i> Con imagen</span>
                        <?php endif; ?>
                        <div class="bnr-actions">
                            <!-- Toggle activo -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_banner_active">
                                <input type="hidden" name="banner_id" value="<?= (int)$bnr['id'] ?>">
                                <button type="submit" class="bnr-btn" style="background:<?= $bActive ? '#fee2e2' : '#d1fae5' ?>;color:<?= $bActive ? '#991b1b' : '#065f46' ?>;" title="<?= $bActive ? 'Desactivar' : 'Activar' ?>">
                                    <i class="fas fa-<?= $bActive ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <!-- Editar -->
                            <button type="button" class="bnr-btn" style="background:#ede9fe;color:#4c1d95;"
                                onclick="bnrShowForm(<?= (int)$bnr['id'] ?>,<?= htmlspecialchars(json_encode([
                                    'text'   => $bnr['banner_text']  ?? '',
                                    'img'    => $bnr['image_url']    ?? '',
                                    'speed'  => $bnr['scroll_speed'] ?? 'normal',
                                    'showon' => $bnr['show_on']      ?? 'store',
                                    'start'  => $bnr['starts_at']    ?? '',
                                    'end'    => $bnr['ends_at']      ?? '',
                                ]), ENT_QUOTES) ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <!-- Eliminar -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este banner?');">
                                <input type="hidden" name="action" value="delete_banner">
                                <input type="hidden" name="banner_id" value="<?= (int)$bnr['id'] ?>">
                                <button type="submit" class="bnr-btn" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- JS del manager de banners -->
            <script>
            function bnrShowForm(id, data) {
                var wrap = document.getElementById('bnr-form-wrap');
                var title = document.getElementById('bnr-form-title');
                wrap.style.display = 'block';
                wrap.scrollIntoView({behavior:'smooth', block:'start'});
                document.getElementById('bnr-id').value = id || 0;
                if (id && data) {
                    title.innerHTML = '<i class="fas fa-pen-square" style="color:<?= htmlspecialchars($prevC1) ?>;"></i> Editar Banner';
                    document.getElementById('bnr-text').value = data.text || '';
                    document.getElementById('bnr-img-url').value = data.img || '';
                    document.getElementById('bnr-start').value = data.start || '';
                    document.getElementById('bnr-end').value   = data.end   || '';
                    bnrSetSpeed(data.speed || 'normal');
                    bnrSetShow(data.showon || 'store');
                    bnrPreviewImg(data.img || '');
                    document.getElementById('bnr-chars').textContent = (200 - (data.text||'').length) + ' restantes';
                } else {
                    title.innerHTML = '<i class="fas fa-plus-circle" style="color:<?= htmlspecialchars($prevC1) ?>;"></i> Nuevo Banner';
                    document.getElementById('bnr-text').value = '';
                    document.getElementById('bnr-img-url').value = '';
                    document.getElementById('bnr-start').value = '';
                    document.getElementById('bnr-end').value   = '';
                    bnrSetSpeed('normal');
                    bnrSetShow('store');
                    bnrPreviewImg('');
                    document.getElementById('bnr-chars').textContent = '200 restantes';
                }
            }
            function bnrHideForm() {
                document.getElementById('bnr-form-wrap').style.display = 'none';
            }
            function bnrInsertEmoji(emoji) {
                var ta = document.getElementById('bnr-text');
                var s = ta.selectionStart, e = ta.selectionEnd;
                var cur = ta.value;
                ta.value = cur.substring(0,s) + emoji + cur.substring(e);
                ta.selectionStart = ta.selectionEnd = s + emoji.length;
                ta.focus();
                document.getElementById('bnr-chars').textContent = (200 - ta.value.length) + ' restantes';
            }
            function bnrSetSpeed(speed) {
                document.getElementById('bnr-speed-val').value = speed;
                document.querySelectorAll('.bnr-speed-btn').forEach(function(b) {
                    b.classList.toggle('sel', b.dataset.speed === speed);
                });
            }
            function bnrSetShow(show) {
                document.getElementById('bnr-show-val').value = show;
                document.querySelectorAll('.bnr-show-btn').forEach(function(b) {
                    b.classList.toggle('sel', b.dataset.show === show);
                });
            }
            function bnrPreviewImg(url) {
                var img = document.getElementById('bnr-img-preview');
                if (url && /^https?:\/\//i.test(url)) {
                    img.src = url;
                    img.style.display = 'block';
                    img.onerror = function(){ img.style.display='none'; };
                } else {
                    img.style.display = 'none';
                    img.src = '';
                }
            }
            </script>
        </div>

    </div>

    <script>
        // ── ONBOARDING WIZARD ──────────────────────────────────────────────
        (function() {
            var KEY = 'ob_collapsed_<?= $userId ?>';
            if (localStorage.getItem(KEY) === '1') {
                var wiz = document.getElementById('onboarding-wizard');
                var bar = document.getElementById('ob-collapsed-bar');
                if (wiz) wiz.style.display = 'none';
                if (bar) bar.style.display = 'flex';
            }
        })();
        function collapseOnboarding() {
            document.getElementById('onboarding-wizard').style.display = 'none';
            var bar = document.getElementById('ob-collapsed-bar');
            if (bar) bar.style.display = 'flex';
            localStorage.setItem('ob_collapsed_<?= $userId ?>', '1');
        }
        function expandOnboarding() {
            document.getElementById('onboarding-wizard').style.display = 'block';
            var bar = document.getElementById('ob-collapsed-bar');
            if (bar) bar.style.display = 'none';
            localStorage.removeItem('ob_collapsed_<?= $userId ?>');
        }

        // ── TAB NAVIGATION ─────────────────────────────────────────────────
        var _hashToTab = {
            'shipping-section':   'envios',
            'payment-section':    'pagos',
            'live-section':       'live',
            'chat-seller-section':'live',
            'avatar-section':     'avatar',
            'design-section':     'puesto',
            'productos-section':  'productos',
            'tab-resumen':   'resumen',
            'tab-productos': 'productos',
            'tab-avatar':    'avatar',
            'tab-puesto':    'puesto',
            'tab-envios':    'envios',
            'tab-pagos':     'pagos',
            'tab-live':      'live',
            'tab-pedidos':   'pedidos',
            'tab-banners':   'banners',
            'banners-section': 'banners',
        };

        function showTab(name, btn) {
            document.querySelectorAll('[data-tab]').forEach(function(el) {
                if (el.dataset.tab === name) {
                    // Forzar display explícito para vencer la regla CSS
                    el.style.display = el.classList.contains('stats-grid') ? 'grid' : 'block';
                } else {
                    el.style.display = 'none';
                }
            });
            document.querySelectorAll('.tab-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            if (btn) {
                btn.classList.add('active');
            } else {
                var found = document.querySelector('.tab-btn[onclick*="' + name + '"]');
                if (found) found.classList.add('active');
            }
            history.replaceState(null, '', '#tab-' + name);
            // Scroll to top of tab content
            var nav = document.getElementById('main-tab-nav');
            if (nav) nav.scrollIntoView({behavior:'smooth', block:'nearest'});
        }

        // Activate tab from URL hash
        (function() {
            var hash = window.location.hash.replace('#','');
            var tab  = _hashToTab[hash] || 'resumen';
            showTab(tab, null);
        })();

        window.addEventListener('hashchange', function() {
            var hash = window.location.hash.replace('#','');
            var tab  = _hashToTab[hash];
            if (tab) showTab(tab, null);
        });
        // ── AVATAR BUILDER ─────────────────────────────────────────────────
        var _avDebounce = null;

        function avApplyTypeDefaults(type) {
            // Los data-val de chips corresponden exactamente a claves de AV_HAIR/AV_EYES/AV_ACCESSORIES
            const defs = {
                woman: { hair_style:'long_straight', hair_color:'#8B4513', outfit:'#ec4899', eye_style:'happy',   accessory:'none' },
                man:   { hair_style:'short_flat',    hair_color:'#1a1a1a', outfit:'#2563eb', eye_style:'default', accessory:'none' },
                girl:  { hair_style:'long_curly',    hair_color:'#D4A843', outfit:'#f97316', eye_style:'happy',   accessory:'glasses' },
                boy:   { hair_style:'short_curly',   hair_color:'#2c1a0e', outfit:'#16a34a', eye_style:'squint',  accessory:'none' },
            };
            const d = defs[type];
            if (!d) return;
            // Cabello — chips-hair_style (guión bajo, coincide con el id del div)
            document.querySelectorAll('#chips-hair_style .av-chip').forEach(c => {
                c.classList.toggle('sel', c.dataset.val === d.hair_style);
            });
            document.getElementById('in-hair_style').value = d.hair_style;
            // Color cabello
            avHairColorCustom(d.hair_color);
            document.getElementById('av-hair-custom').value = d.hair_color;
            // Outfit
            avOutfitColorCustom(d.outfit);
            document.getElementById('av-outfit-custom').value = d.outfit;
            // Ojos
            document.querySelectorAll('#chips-eye_style .av-chip').forEach(c => {
                c.classList.toggle('sel', c.dataset.val === d.eye_style);
            });
            document.getElementById('in-eye_style').value = d.eye_style;
            // Rubor (opcional, puede no existir)
            const blush = document.getElementById('av-blush');
            if (blush) blush.checked = (type === 'woman' || type === 'girl');
            // Sincronizar también los chips de tipo en el bloque de aventura
            document.querySelectorAll('#av-adventure-controls .av-chip[data-group="type"]').forEach(c => {
                c.classList.toggle('sel-pink', c.dataset.val === d && (type==='woman'||type==='girl'));
                c.classList.toggle('sel-blue', c.dataset.val === d && (type==='man'||type==='boy'));
                c.classList.remove('sel');
            });
        }

        function avSkin(el) {
            document.querySelectorAll('#skins-wrap .av-skin-btn').forEach(b => b.classList.remove('sel'));
            el.classList.add('sel');
            document.getElementById('in-skin').value = el.dataset.val;
            avRefreshPreview();
        }

        function avHairColor(el) {
            document.querySelectorAll('.av-color-row .av-color-dot').forEach(d => {
                if (d.closest('#av-form .av-group:nth-of-type(3)')) d.classList.remove('sel');
            });
            el.classList.add('sel');
            document.getElementById('in-hair_color').value = el.dataset.val;
            document.getElementById('av-hair-custom').value = el.dataset.val;
            avRefreshPreview();
        }

        function avHairColorCustom(val) {
            document.querySelectorAll('[onclick="avHairColor(this)"]').forEach(d => d.classList.remove('sel'));
            document.getElementById('in-hair_color').value = val;
            avRefreshPreview();
        }

        function avOutfitColor(el) {
            document.querySelectorAll('[onclick="avOutfitColor(this)"]').forEach(d => d.classList.remove('sel'));
            el.classList.add('sel');
            document.getElementById('in-outfit').value = el.dataset.val;
            document.getElementById('av-outfit-custom').value = el.dataset.val;
            avRefreshPreview();
        }

        function avOutfitColorCustom(val) {
            document.querySelectorAll('[onclick="avOutfitColor(this)"]').forEach(d => d.classList.remove('sel'));
            document.getElementById('in-outfit').value = val;
            avRefreshPreview();
        }

        // Muestra/oculta barba según tipo
        function toggleFacialHair(type) {
            const fg = document.getElementById('facial-hair-group');
            if (fg) fg.style.display = (type === 'man' || type === 'boy') ? '' : 'none';
        }

        function avSelect(el, group) {
            document.querySelectorAll('#chips-' + group + ' .av-chip').forEach(c => {
                c.classList.remove('sel','sel-pink','sel-blue');
            });
            const val = el.dataset.val;
            const isBlue = (val === 'man' || val === 'boy');
            el.classList.add(isBlue ? 'sel-blue' : (group === 'type' ? 'sel-pink' : 'sel'));
            document.getElementById('in-' + group).value = val;
            if (group === 'type') { avApplyTypeDefaults(val); toggleFacialHair(val); }
            avRefreshPreview();
        }

        function avGetConfig() {
            const style = document.getElementById('in-style')?.value || 'classic';
            const type  = document.getElementById('in-type').value;
            const body  = document.getElementById('in-body_shape')?.value || 'average';

            if (style === 'adventure') {
                return {
                    style:       'adventure',
                    type:        type,
                    skin:        document.getElementById('in-adv-skin')?.value  || '#ecad80',
                    hair:        document.getElementById('in-adv-hair')?.value  || 'long01',
                    hairColor:   document.getElementById('in-adv-hair-color')?.value || '#8B4513',
                    eyes:        document.getElementById('in-eye_style')?.value || 'happy',
                    adv_eyes:    document.getElementById('in-adv-eyes')?.value  || 'variant09',
                    mouth:       document.getElementById('in-adv-mouth')?.value || 'variant01',
                    adv_mouth:   document.getElementById('in-adv-mouth')?.value || 'variant01',
                    clothesColor:'#667eea',
                    accessory:   'none',
                    clothes:     'shirtCrewNeck',
                    facialHair:  '',
                    body_shape:  body,
                };
            }
            return {
                style:       'classic',
                type:        type,
                skin:        document.getElementById('in-skin').value,
                hair:        document.getElementById('in-hair_style').value,
                hairColor:   document.getElementById('in-hair_color').value,
                eyes:        document.getElementById('in-eye_style').value,
                clothesColor:document.getElementById('in-outfit').value,
                accessory:   'none',
                body_shape:  body,
                facialHair:  '',
                clothes:     'shirtCrewNeck',
                mouth:       'smile',
            };
        }

        // Cambiar entre estilo clásico y aventura
        function avStyleSwitch(style) {
            document.getElementById('av-classic-controls').style.display  = style === 'classic'   ? '' : 'none';
            document.getElementById('av-adventure-controls').style.display = style === 'adventure' ? '' : 'none';
            avRefreshPreview();
        }

        // Skin para modo aventura
        function avAdvSkin(el) {
            document.querySelectorAll('#adv-skins-wrap .av-skin-btn').forEach(b => b.classList.remove('sel'));
            el.classList.add('sel');
            document.getElementById('in-adv-skin').value = el.dataset.val;
            avRefreshPreview();
        }

        // Color de cabello en aventura
        function avAdvHairColor(el) {
            document.querySelectorAll('#adv-hair-colors .av-color-dot').forEach(d => d.classList.remove('sel'));
            el.classList.add('sel');
            document.getElementById('in-adv-hair-color').value = el.dataset.val;
            document.getElementById('av-adv-hair-custom').value = el.dataset.val;
            avRefreshPreview();
        }
        function avAdvHairColorCustom(val) {
            document.querySelectorAll('#adv-hair-colors .av-color-dot').forEach(d => d.classList.remove('sel'));
            document.getElementById('in-adv-hair-color').value = val;
            avRefreshPreview();
        }

        function avRefreshPreview() {
            clearTimeout(_avDebounce);
            _avDebounce = setTimeout(function() {
                const cfg = avGetConfig();
                // Refresh full-body preview via AJAX (server renders composite SVG)
                fetch('api/emp-avatar-full.php?cfg=' + encodeURIComponent(JSON.stringify(cfg)))
                    .then(r => r.text())
                    .then(svg => {
                        const wrap = document.getElementById('av-full-preview');
                        if (wrap) wrap.innerHTML = svg;
                    })
                    .catch(() => {}); // Silently fail - preview just won't update
            }, 350);
        }

        // ── Fin avatar builder ─────────────────────────────────────────────

        function deleteProduct(productId) {
            if (confirm('¿Estás segura de que quieres eliminar este producto?')) {
                window.location.href = 'emprendedores-producto-eliminar.php?id=' + productId;
            }
        }

        // ── Preview en vivo del diseño del puesto ──────────────────────────
        function setColors(c1, c2) {
            document.getElementById('color1-pick').value = c1;
            document.getElementById('color2-pick').value = c2;
            updatePreview();
            updateStylePresets(c1, c2);
        }

        function updatePreview() {
            const c1 = document.getElementById('color1-pick').value;
            const c2 = document.getElementById('color2-pick').value;
            const style = document.querySelector('input[name="store_banner_style"]:checked')?.value || 'stripes';
            const logoUrl = document.getElementById('logo-url').value.trim();
            const awning = document.getElementById('mini-awning');
            const avatar = document.getElementById('mini-avatar');
            const btn    = document.getElementById('mini-btn');

            if (!awning) return;

            // Actualizar toldo según estilo
            if (style === 'gradient') {
                awning.style.background = `linear-gradient(135deg,${c1},${c2})`;
            } else if (style === 'solid') {
                awning.style.background = c1;
                awning.style.backgroundImage = `repeating-linear-gradient(0deg,transparent,transparent 8px,rgba(255,255,255,.12) 8px,rgba(255,255,255,.12) 10px)`;
            } else if (style === 'wave') {
                awning.style.background = c1;
            } else {
                // stripes
                awning.style.background = `repeating-linear-gradient(90deg,${c1} 0px,${c1} 16px,${c2} 16px,${c2} 32px)`;
            }

            // Avatar / logo
            if (logoUrl) {
                avatar.innerHTML = `<img src="${logoUrl}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;" onerror="this.style.display='none'">`;
            } else {
                avatar.style.background = c1;
                avatar.innerHTML = avatar.dataset.initial || '<?= strtoupper(mb_substr($userName, 0, 1)) ?>';
            }

            // Botón
            btn.style.background = `linear-gradient(135deg,${c1},${c2})`;

            // Nombre del puesto en preview
            const nameEl = document.getElementById('mini-store-name');
            const nameInput = document.getElementById('store-name-input');
            if (nameEl && nameInput) {
                const raw = nameInput.value.trim();
                nameEl.textContent = raw ? raw.substring(0, 24) : '<?= addslashes(mb_substr($userName, 0, 24)) ?>';
            }

            // Actualizar CSS vars de los presets
            updateStylePresets(c1, c2);
        }

        function updateStylePresets(c1, c2) {
            document.querySelectorAll('.preset-btn').forEach(el => {
                el.style.setProperty('--c1', c1);
                el.style.setProperty('--c2', c2);
            });
        }

        function updateActiveStyle() {
            document.querySelectorAll('.preset-btn').forEach(el => el.classList.remove('active'));
            const checked = document.querySelector('input[name="store_banner_style"]:checked');
            if (checked) checked.parentNode.querySelector('.preset-btn')?.classList.add('active');
        }

        // Inicializar avatar data-initial
        const av = document.getElementById('mini-avatar');
        if (av) av.dataset.initial = av.textContent.trim();
    </script>

    <?php if ($isPaidPlan && $liveData['is_live']): ?>
    <script>
    (function(){
    const SELLER_ID = <?= (int)$userId ?>;
    let lastId     = 0;
    let replyToUid = null;
    let bansMap    = {};  // uid → name

    const log      = document.getElementById('seller-chat-log');
    const empty    = document.getElementById('seller-chat-empty');
    const replyInp = document.getElementById('seller-reply-input');
    const replyMode= document.getElementById('seller-reply-mode');
    const newBadge = document.getElementById('seller-chat-new-badge');
    const bannedWrap= document.getElementById('banned-list-wrap');
    const bannedChips= document.getElementById('banned-chips');
    const btnBcast = document.getElementById('btn-broadcast');
    const btnPub   = document.getElementById('btn-send-pub');
    const btnPriv  = document.getElementById('btn-send-priv');
    const btnCancel= document.getElementById('btn-cancel-reply');
    let newCount   = 0;

    function poll() {
        fetch('/api/chat-poll.php?seller_id=' + SELLER_ID + '&last_id=' + lastId, {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if (data.messages && data.messages.length) {
                if (empty) empty.style.display = 'none';
                data.messages.forEach(appendMsg);
                lastId = data.messages[data.messages.length-1].id;
                // Solo acumular badge si la sección no está visible en pantalla
                if (log && !isElementVisible(log)) {
                    newCount += data.messages.filter(m=>m.sender_type==='client').length;
                    if (newCount > 0 && newBadge) {
                        newBadge.style.display='inline';
                        newBadge.textContent = newCount + ' nuevo' + (newCount>1?'s':'');
                    }
                } else {
                    // La vendedora está viendo el chat: limpiar badge
                    newCount = 0;
                    if (newBadge) { newBadge.style.display='none'; newBadge.textContent=''; }
                }
            }
            if (data.bans) {
                bansMap = {};
                Object.entries(data.bans).forEach(([uid,name])=>{ bansMap[uid]=name; });
                renderBans();
            }
        })
        .catch(()=>{});
    }

    function isElementVisible(el) {
        const rect = el.getBoundingClientRect();
        return rect.top < window.innerHeight && rect.bottom > 0;
    }

    function appendMsg(m) {
        const isFromSeller = (m.sender_type === 'seller');
        const isPrivate    = (m.is_public == 0);
        const wrap = document.createElement('div');
        wrap.className = 'schat-msg ' + (isFromSeller ? 'from-seller' : 'from-client') + (isPrivate?' private':'');
        wrap.dataset.uid = m.sender_id;
        wrap.dataset.name= m.sender_name;

        let actHtml = '';
        if (!isFromSeller) {
            const bannedLabel = bansMap[m.sender_id] ? ' (baneado)' : '';
            actHtml = `<div class="schat-actions">
                <button class="btn-reply-pub" onclick="prepReply(${m.sender_id}, '${escJs(m.sender_name)}')">
                    <i class="fas fa-globe"></i> Resp. público</button>
                <button class="btn-reply-priv" onclick="prepReply(${m.sender_id}, '${escJs(m.sender_name)}', true)">
                    <i class="fas fa-lock"></i> Resp. privado</button>
                ${bansMap[m.sender_id]
                    ? `<button class="btn-unban" onclick="banUser(${m.sender_id},'unban')"><i class="fas fa-unlock"></i> Desbanear</button>`
                    : `<button class="btn-ban" onclick="banUser(${m.sender_id},'ban')"><i class="fas fa-ban"></i> Banear</button>`
                }
            </div>`;
        }

        wrap.innerHTML = (isPrivate ? '<div style="font-size:.7rem;color:#059669;font-weight:600;margin-bottom:3px;"><i class="fas fa-lock"></i> Privado</div>' : '') +
            (!isFromSeller ? `<div class="schat-meta">${escHtml(m.sender_name)}</div>` : '') +
            `<div class="schat-bubble">${escHtml(m.message)}</div>` +
            `<div class="schat-meta${isFromSeller?' right':''}">${m.time||''}</div>` +
            actHtml;

        if (log) { log.appendChild(wrap); log.scrollTop = log.scrollHeight; }
    }

    window.prepReply = function(uid, name, priv) {
        replyToUid = uid;
        if (replyMode) replyMode.innerHTML = priv
            ? `<i class="fas fa-lock"></i> Respuesta privada a <strong>${escHtml(name)}</strong>`
            : `<i class="fas fa-globe"></i> Respuesta pública a <strong>${escHtml(name)}</strong>`;
        if (btnBcast) btnBcast.style.display = 'none';
        if (btnPub)   btnPub.style.display   = priv ? 'none' : 'inline-flex';
        if (btnPriv)  btnPriv.style.display  = priv ? 'inline-flex' : 'none';
        if (btnCancel)btnCancel.style.display= 'inline-flex';
        if (replyInp) replyInp.focus();
    };

    window.cancelReply = function() {
        replyToUid = null;
        if (replyMode) replyMode.textContent = 'Transmisión a todos los clientes';
        if (btnBcast) btnBcast.style.display = 'inline-flex';
        if (btnPub)   btnPub.style.display   = 'none';
        if (btnPriv)  btnPriv.style.display  = 'none';
        if (btnCancel)btnCancel.style.display= 'none';
    };

    window.sellerSend = function(isPublic, privateTo) {
        const txt = replyInp ? replyInp.value.trim() : '';
        if (!txt) return;
        const body = `seller_id=${SELLER_ID}&message=${encodeURIComponent(txt)}&is_public=${isPublic}` +
            (privateTo ? `&private_to=${privateTo}` : '');
        fetch('/api/chat-send.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) { replyInp.value=''; cancelReply(); poll(); }
            else alert(d.msg || 'Error al enviar');
        })
        .catch(()=>alert('Error de conexión'));
    };

    window.sellerSendReply = function(isPublic) {
        sellerSend(isPublic, isPublic ? null : replyToUid);
    };

    window.banUser = function(uid, action) {
        fetch('/api/chat-ban.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`banned_user_id=${uid}&action=${action}`
        })
        .then(r => r.json())
        .then(d => { if (d.ok) poll(); })
        .catch(()=>{});
    };

    function renderBans() {
        const uids = Object.keys(bansMap);
        if (!bannedWrap) return;
        if (!uids.length) { bannedWrap.style.display='none'; return; }
        bannedWrap.style.display='block';
        if (!bannedChips) return;
        bannedChips.innerHTML = uids.map(uid =>
            `<span class="banned-chip">${escHtml(bansMap[uid])}
             <button onclick="banUser(${uid},'unban')" title="Desbanear"><i class="fas fa-times"></i></button>
             </span>`
        ).join('');
    }

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escJs(s){ return String(s).replace(/'/g,"\\'"); }

    // Iniciar polling
    poll();
    setInterval(poll, 3000);
    })();
    </script>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer.php'; ?>

<!-- ── JS CÁMARA LIVE (dashboard vendedora) ──────────────────────────── -->
<script>
(function(){
// ── Selector de modo ─────────────────────────────────────────────────────
window.selectLiveMode = function(mode) {
    const cLink = document.getElementById('card-link');
    const cCam  = document.getElementById('card-cam');
    const fLink = document.getElementById('form-link');
    const fCam  = document.getElementById('form-cam');
    if (!cLink) return;
    if (mode === 'link') {
        cLink.classList.add('selected');   cCam.classList.remove('selected');
        fLink.style.display = 'block';     fCam.style.display = 'none';
        stopCameraPreview();
    } else {
        cCam.classList.add('selected');    cLink.classList.remove('selected');
        fLink.style.display = 'none';      fCam.style.display = 'block';
    }
};

// ── Variables de estado ───────────────────────────────────────────────────
let camStream    = null;
let mediaRec     = null;
let sessionId    = null;
let chunkIndex   = 0;
let timerInterval = null;
let timerSeconds  = 0;
const SELLER_SESSION_ID = <?= json_encode($liveData['live_session_id'] ?? '') ?>;
const IS_CAM_LIVE       = <?= json_encode($liveData['is_live'] && $liveData['live_type'] === 'camera') ?>;

// ── Previsualizar cámara ─────────────────────────────────────────────────
window.previewCamera = async function() {
    const btn    = document.getElementById('btn-preview-cam');
    const btnStart = document.getElementById('btn-start-cam');
    const wrap   = document.getElementById('cam-preview-wrap');
    const status = document.getElementById('cam-status-msg');
    try {
        camStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        const video = document.getElementById('cam-preview');
        if (video) {
            // Mostrar el contenedor ANTES de asignar srcObject y play()
            // — algunos browsers (Firefox, Safari) no renderizan si el elemento está oculto
            if (wrap) wrap.style.display = 'block';
            video.srcObject = camStream;
            try { await video.play(); } catch(pe) { /* muted → autoplay siempre permitido */ }
        }
        if (btn)  btn.style.display  = 'none';
        if (btnStart) btnStart.disabled = false;
        if (status) status.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i> Cámara lista. Pulsa "Iniciar EN VIVO con Cámara".';
    } catch(e) {
        if (status) status.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i> No se pudo acceder a la cámara: ' + e.message;
    }
};

function stopCameraPreview() {
    if (camStream) { camStream.getTracks().forEach(t=>t.stop()); camStream = null; }
    const wrap = document.getElementById('cam-preview-wrap');
    if (wrap) wrap.style.display = 'none';
    const btnPrev = document.getElementById('btn-preview-cam');
    const btnStart = document.getElementById('btn-start-cam');
    if (btnPrev) btnPrev.style.display = 'inline-flex';
    if (btnStart) btnStart.disabled = true;
}

// ── Iniciar cámara live ───────────────────────────────────────────────────
window.startCamLive = async function() {
    if (!camStream) { alert('Primero prueba la cámara.'); return; }
    const title  = (document.getElementById('cam-title-input')?.value.trim()) || 'En Vivo con Cámara';
    const status = document.getElementById('cam-status-msg');
    const btnStart = document.getElementById('btn-start-cam');
    const btnPrev  = document.getElementById('btn-preview-cam');
    if (btnStart) btnStart.disabled = true;
    if (status) status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión…';

    let data;
    try {
        const res = await fetch('/api/live-cam-start.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'title='+encodeURIComponent(title)
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error('HTTP ' + res.status + ': ' + text.substring(0, 200));
        }
        data = await res.json();
    } catch(e) {
        if (status) status.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Error: ' + e.message + '</span>';
        if (btnStart) btnStart.disabled = false;
        return;
    }
    if (!data.ok) {
        if (status) status.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i> ' + (data.msg || data.error || 'Error desconocido') + '</span>';
        if (btnStart) btnStart.disabled = false;
        return;
    }
    sessionId  = data.session_id;
    chunkIndex = 0;

    // Elegir codec disponible
    const mimeType = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']
        .find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';

    mediaRec = new MediaRecorder(camStream, {mimeType, videoBitsPerSecond: 800000});
    mediaRec.ondataavailable = sendChunk;
    mediaRec.start(2000); // chunk cada 2 segundos

    startTimer();
    if (btnStart) btnStart.style.display = 'none';
    if (btnPrev)  btnPrev.style.display  = 'none';

    // Asegurar que el preview sigue visible y reproduciendo
    const previewVid = document.getElementById('cam-preview');
    const previewWrap = document.getElementById('cam-preview-wrap');
    if (previewWrap) previewWrap.style.display = 'block';
    if (previewVid) previewVid.play().catch(()=>{});

    // Mostrar botón de parar y badge "EN VIVO" dinámicamente
    const camControls = document.getElementById('cam-controls');
    if (camControls) {
        camControls.innerHTML = `
            <button id="btn-stop-cam" onclick="stopCamLive()"
                    style="background:#ef4444;color:white;border:none;padding:11px 22px;border-radius:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-stop-circle"></i> Terminar Transmisión
            </button>`;
    }
    if (status) status.innerHTML = '<span style="color:#ef4444;font-weight:700;"><i class="fas fa-circle" style="animation:live-pulse 1s infinite;"></i> EN VIVO — Los clientes te están viendo en tu tienda.</span>';
};

async function sendChunk(event) {
    if (!event.data || event.data.size === 0) return;
    const form = new FormData();
    form.append('session_id', sessionId);
    form.append('index',      chunkIndex);
    form.append('chunk',      event.data, 'chunk.webm');
    chunkIndex++;
    try {
        await fetch('/api/live-cam-chunk.php', {method:'POST', credentials:'same-origin', body: form});
    } catch(e) { /* silently ignore transient upload errors */ }
}

// ── Detener transmisión ───────────────────────────────────────────────────
window.stopCamLive = async function() {
    if (!confirm('¿Terminar la transmisión en vivo?')) return;
    const btn = document.getElementById('btn-stop-cam');
    if (btn) btn.disabled = true;

    // Detener MediaRecorder y cámara
    if (mediaRec && mediaRec.state !== 'inactive') {
        mediaRec.stop();
        await new Promise(r => setTimeout(r, 2200)); // esperar último chunk
    }
    if (camStream) camStream.getTracks().forEach(t => t.stop());
    clearInterval(timerInterval);

    await fetch('/api/live-cam-stop.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'session_id='+encodeURIComponent(sessionId||SELLER_SESSION_ID)
    });

    location.reload();
};

// ── Temporizador ──────────────────────────────────────────────────────────
function startTimer() {
    timerSeconds = 0;
    timerInterval = setInterval(() => {
        timerSeconds++;
        const m = String(Math.floor(timerSeconds/60)).padStart(2,'0');
        const s = String(timerSeconds%60).padStart(2,'0');
        const el = document.getElementById('cam-timer');
        if (el) el.textContent = m+':'+s;
    }, 1000);
}

// ── Si ya estaba en cámara live (recarga de página) ───────────────────────
if (IS_CAM_LIVE && SELLER_SESSION_ID) {
    // Obtener chunk_count actual para continuar desde donde se quedó
    fetch('/api/live-cam-poll.php?session_id=' + encodeURIComponent(SELLER_SESSION_ID))
        .then(r => r.json())
        .then(d => {
            chunkIndex = d.chunk_count || 0;
        }).catch(() => {});

    // Re-conectar cámara para el preview del vendedor
    navigator.mediaDevices.getUserMedia({video:true, audio:true}).then(stream => {
        camStream = stream;
        sessionId = SELLER_SESSION_ID;
        const vid  = document.getElementById('cam-preview');
        const wrap = document.getElementById('cam-preview-wrap');
        if (vid) { vid.srcObject = stream; vid.play().catch(()=>{}); }
        if (wrap) wrap.style.display = 'block';

        const mimeType = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm']
            .find(t=>MediaRecorder.isTypeSupported(t))||'video/webm';
        mediaRec = new MediaRecorder(stream, {mimeType, videoBitsPerSecond:800000});
        mediaRec.ondataavailable = sendChunk;
        mediaRec.start(2000);
        startTimer();
    }).catch(()=>{});
}
})();
</script>

<script>
// ── Shipping section JS ──────────────────────────────────────────────────────
function toggleCard(type) {
    const chk  = document.getElementById('chk-'   + type);
    const card = document.getElementById('card-'  + type);
    const body = document.getElementById('body-'  + type);
    if (!chk || !card || !body) return;
    if (chk.checked) {
        card.classList.add('active');
        body.style.display = 'block';
    } else {
        card.classList.remove('active');
        body.style.display = 'none';
    }
}

function addZone() {
    const list = document.getElementById('zones-list');
    const row  = document.createElement('div');
    row.className = 'zone-row';
    row.innerHTML = `
        <input type="text"   name="zone_name[]"  placeholder="Ej: Provincia o ciudad">
        <input type="number" name="zone_price[]" min="0" step="100" placeholder="2000">
        <button type="button" class="btn-remove-zone" onclick="removeZone(this)" title="Eliminar zona">
            <i class="fas fa-times-circle"></i>
        </button>`;
    list.appendChild(row);
    row.querySelector('input').focus();
}

function removeZone(btn) {
    const row = btn.closest('.zone-row');
    if (row) row.remove();
}

// ── Payment methods section JS ────────────────────────────────────────────────
function togglePayCard(type) {
    const chk  = document.getElementById('chk-'     + type);
    const card = document.getElementById('pm-card-' + type);
    const body = document.getElementById('pm-body-' + type);
    if (!chk || !card || !body) return;
    if (chk.checked) {
        card.classList.add('active');
        body.style.display = 'block';
    } else {
        card.classList.remove('active');
        body.style.display = 'none';
    }
}
</script>
</body>
</html>
