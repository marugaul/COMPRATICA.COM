<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/live_embed.php';
require_once __DIR__ . '/includes/chat_helpers.php';
require_once __DIR__ . '/includes/live_cam.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName   = $_SESSION['name'] ?? 'Usuario';
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}

$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) { header('Location: emprendedores-catalogo.php'); exit; }

$pdo = db();

// Datos del vendedor
$stSeller = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COALESCE(u.is_live,0)         AS is_live,
           u.live_title, u.live_link,
           COALESCE(u.live_type,'link')  AS live_type,
           u.live_session_id,
           COUNT(p.id)                   AS product_count,
           SUM(p.sales_count)            AS total_sales
    FROM users u
    LEFT JOIN entrepreneur_products p ON p.user_id = u.id AND p.is_active = 1
    WHERE u.id = ?
    GROUP BY u.id
");
$stSeller->execute([$sid]);
$seller = $stSeller->fetch(PDO::FETCH_ASSOC);
if (!$seller) { header('Location: emprendedores-catalogo.php'); exit; }

// Inicializar tablas de cámara (también añade columnas si es necesario)
initLiveCamTables($pdo);

// ¿La vendedora tiene plan de pago? (controla live embed y chat)
$sellerHasPaidPlan = hasPaidPlan($pdo, $sid);

// Tipo de live
$liveIsCam  = ($seller['is_live'] && ($seller['live_type'] ?? 'link') === 'camera');
$liveIsLink = ($seller['is_live'] && ($seller['live_type'] ?? 'link') !== 'camera' && !empty($seller['live_link']));

// Usuario visitante
$visitorUid  = (int)($_SESSION['uid'] ?? 0);
$isTheSeller = ($visitorUid === $sid);

// Categorías del vendedor
$categories = $pdo->prepare("
    SELECT DISTINCT c.id, c.name
    FROM entrepreneur_products p
    JOIN entrepreneur_categories c ON c.id = p.category_id
    WHERE p.user_id = ? AND p.is_active = 1
    ORDER BY c.name
");
$categories->execute([$sid]);
$categories = $categories->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim($_GET['q'] ?? '');

// Productos del vendedor
$sql = "SELECT p.id, p.name, p.price, p.stock, p.image_1, p.featured,
               p.views_count, p.sales_count, p.description,
               c.name AS category_name
        FROM entrepreneur_products p
        LEFT JOIN entrepreneur_categories c ON c.id = p.category_id
        WHERE p.is_active = 1 AND p.user_id = ?";
$params = [$sid];
if ($catFilter > 0) { $sql .= " AND p.category_id = ?"; $params[] = $catFilter; }
if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY p.featured DESC, p.sales_count DESC, p.id DESC";
$stProds = $pdo->prepare($sql);
$stProds->execute($params);
$products = $stProds->fetchAll(PDO::FETCH_ASSOC);

// Carrito
$empCartCount = 0;
foreach ($_SESSION['emp_cart'] ?? [] as $it) $empCartCount += (int)$it['qty'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seller['name']) ?> | Mercadito Emprendedores</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Toldo de la tienda */
        .store-awning {
            height: 90px;
            background: repeating-linear-gradient(
                -45deg,
                #667eea 0px,  #667eea 28px,
                #764ba2 28px, #764ba2 56px
            );
            position: relative;
            overflow: visible;
        }
        /* Banda roja en la base */
        .store-awning::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 7px;
            background: #dc2626;
        }
        /* Flecos triangulares (picos) colgando */
        .store-awning::after {
            content: '';
            position: absolute;
            bottom: -18px; left: 0; right: 0;
            height: 18px;
            background:
                linear-gradient(135deg, #dc2626 50%, transparent 50%),
                linear-gradient(225deg, #dc2626 50%, transparent 50%);
            background-size: 18px 18px;
            background-repeat: repeat-x;
            background-position: 0 0, 9px 0;
            z-index: 1;
        }

        .store-header {
            max-width: 960px; margin: 0 auto; padding: 48px 20px 24px;
            display: flex; align-items: flex-start; gap: 24px;
        }
        .store-avatar {
            width: 80px; height: 80px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg,#667eea,#764ba2);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; font-weight: 800; color: white;
            box-shadow: 0 6px 20px rgba(102,126,234,.35);
            border: 4px solid white;
            margin-top: -60px;
        }
        .store-meta h1 { font-size: 1.8rem; font-weight: 800; color: #222; margin: 0 0 6px; }
        .store-stats { display: flex; gap: 20px; font-size: 0.88rem; color: #777; flex-wrap: wrap; }
        .store-stats span { display: flex; align-items: center; gap: 5px; }

        .live-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #ef4444; color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 0.82rem;
            font-weight: 700; text-decoration: none; margin-left: 8px;
        }
        .live-dot { width: 9px; height: 9px; background: white; border-radius: 50%; animation: pls 1.2s infinite; }
        @keyframes pls { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

        /* Filtros */
        .store-filters {
            max-width: 960px; margin: 0 auto 28px; padding: 0 20px;
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        }
        .store-filters form { display: flex; gap: 10px; flex: 1; flex-wrap: wrap; }
        .store-filters input {
            flex: 1; min-width: 180px; padding: 10px 16px;
            border: 2px solid #e0e0e0; border-radius: 10px; font-size: 0.9rem;
        }
        .store-filters select {
            padding: 10px 14px; border: 2px solid #e0e0e0; border-radius: 10px;
            font-size: 0.9rem; background: white;
        }
        .store-filters button {
            background: linear-gradient(135deg,#667eea,#764ba2); color: white;
            border: none; padding: 10px 20px; border-radius: 10px;
            font-weight: 700; cursor: pointer;
        }

        /* Grid productos */
        .store-products {
            max-width: 960px; margin: 0 auto; padding: 0 20px 80px;
            display: grid; grid-template-columns: repeat(auto-fill,minmax(240px,1fr)); gap: 24px;
        }
        .store-product-card {
            background: white; border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            overflow: hidden; text-decoration: none; color: inherit;
            transition: all .25s; display: block;
        }
        .store-product-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,.15); }
        .store-product-card img {
            width: 100%; height: 200px; object-fit: contain; background: #f8f8f8;
        }
        .store-product-noimg {
            width: 100%; height: 200px; background: #f5f5f5;
            display: flex; align-items: center; justify-content: center;
        }
        .spc-body { padding: 14px 16px; }
        .spc-cat  { font-size: 0.75rem; color: #667eea; font-weight: 700; margin-bottom: 4px; }
        .spc-name { font-size: 0.98rem; font-weight: 700; color: #222; margin-bottom: 6px; line-height: 1.3; }
        .spc-price { font-size: 1.25rem; font-weight: 800; color: #667eea; margin-bottom: 10px; }
        .spc-add {
            display: block; width: 100%; padding: 9px;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; border-radius: 8px;
            font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all .2s;
        }
        .spc-add:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(102,126,234,.4); }
        .spc-add:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .spc-nostock { text-align: center; color: #ef4444; font-size: 0.8rem; font-weight: 700; padding: 8px 0 0; }

        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #ddd; display: block; margin-bottom: 16px; }

        /* FAB carrito — encima del chat, con separación clara */
        .emp-cart-fab {
            position: fixed; bottom: 110px; right: 28px; z-index: 9001;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; width: 62px; height: 62px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px rgba(102,126,234,.5);
            text-decoration: none; font-size: 1.4rem; transition: transform .2s;
        }
        .emp-cart-fab:hover { transform: scale(1.1); }
        .fab-count {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: white; border-radius: 50%;
            width: 22px; height: 22px; font-size: 0.72rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .catalog-toast {
            position: fixed; bottom: 104px; right: 28px; z-index: 9999;
            background: #111; color: white; padding: 12px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem; box-shadow: 0 6px 20px rgba(0,0,0,.3);
            transform: translateY(60px); opacity: 0; transition: all .3s; pointer-events: none;
        }
        .catalog-toast.show { transform: translateY(0); opacity: 1; }

        @media (max-width: 540px) {
            .store-header { flex-direction: column; }
            .store-avatar { margin-top: -50px; }
            .store-products { grid-template-columns: repeat(2,1fr); gap: 14px; }
        }

        /* ── Live embed en tienda ── */
        .store-live-section {
            max-width: 960px; margin: 0 auto 24px; padding: 0 20px;
        }
        .store-live-box {
            border-radius: 14px; overflow: hidden;
            box-shadow: 0 6px 28px rgba(0,0,0,.15);
        }
        .store-live-box-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 16px; color: white; gap: 10px;
        }
        .store-live-box-header span {
            display: flex; align-items: center; gap: 8px;
            font-weight: 700; font-size: 0.95rem;
        }
        .store-live-iframe-wrap {
            position: relative; width: 100%; padding-bottom: 56.25%; background: #000;
        }
        .store-live-iframe-wrap iframe {
            position: absolute; inset: 0; width: 100%; height: 100%; border: none;
        }
        .store-live-footer {
            background: #111; padding: 8px 14px;
            display: flex; align-items: center; justify-content: flex-end;
        }
        .store-live-footer a {
            color: #ccc; font-size: 0.8rem; text-decoration: none;
            display: flex; align-items: center; gap: 5px;
        }
        .store-live-footer a:hover { color: white; }
        /* Para Instagram/TikTok (sin embed) */
        .store-live-nonembed {
            border-radius: 14px; overflow: hidden;
            box-shadow: 0 6px 28px rgba(0,0,0,.15);
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px; gap: 12px;
        }
        .store-live-nonembed span {
            font-size: 0.9rem; color: #555; display: flex; align-items: center; gap: 8px;
        }
        .store-live-nonembed a {
            font-weight: 700; padding: 8px 16px; border-radius: 10px;
            text-decoration: none; color: white; white-space: nowrap; font-size: 0.85rem;
        }

        /* ═══════════════════════════════════════════════════════
           CHAT EN VIVO — widget flotante
        ═══════════════════════════════════════════════════════ */
        #chat-fab {
            position: fixed; bottom: 28px; right: 28px; z-index: 9000;
            display: flex; flex-direction: column; align-items: flex-end; gap: 12px;
        }
        #chat-toggle-btn {
            width: 58px; height: 58px; border-radius: 50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; box-shadow: 0 6px 20px rgba(102,126,234,.5);
            transition: all .25s; position: relative;
        }
        #chat-toggle-btn:hover { transform: scale(1.08); }
        #chat-toggle-btn.disabled-chat {
            background: #d1d5db; box-shadow: 0 4px 10px rgba(0,0,0,.1); cursor: default;
        }
        #chat-unread-badge {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: white; border-radius: 50%;
            width: 20px; height: 20px; font-size: 0.7rem; font-weight: 800;
            display: none; align-items: center; justify-content: center;
        }
        #chat-activate-tip {
            background: #1f2937; color: white; border-radius: 10px;
            padding: 8px 14px; font-size: 0.82rem; white-space: nowrap;
            box-shadow: 0 4px 14px rgba(0,0,0,.25); max-width: 220px; text-align: center;
        }
        /* Panel principal del chat */
        #chat-panel {
            width: 340px; max-height: 480px;
            background: white; border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0,0,0,.2);
            display: none; flex-direction: column; overflow: hidden;
        }
        #chat-panel.open { display: flex; }
        @media (max-width: 400px) { #chat-panel { width: calc(100vw - 16px); } }
        .chat-header {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; padding: 12px 16px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .chat-header-title { font-weight: 700; font-size: .95rem; display: flex; align-items: center; gap: 8px; }
        .chat-header-close { background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; padding: 2px 6px; }
        #chat-messages {
            flex: 1; overflow-y: auto; padding: 12px;
            display: flex; flex-direction: column; gap: 8px;
            min-height: 180px; max-height: 280px;
            background: #f8f9ff;
        }
        .chat-msg { max-width: 80%; }
        .chat-msg.mine { align-self: flex-end; }
        .chat-msg.theirs { align-self: flex-start; }
        .chat-msg.seller-msg { align-self: flex-start; }
        .chat-bubble {
            padding: 8px 12px; border-radius: 12px;
            font-size: 0.85rem; line-height: 1.4; word-break: break-word;
        }
        .chat-msg.mine   .chat-bubble { background: #667eea; color: white; border-bottom-right-radius: 4px; }
        .chat-msg.theirs .chat-bubble { background: white; color: #1f2937; box-shadow: 0 1px 4px rgba(0,0,0,.08); border-bottom-left-radius: 4px; }
        .chat-msg.seller-msg .chat-bubble { background: #fef3c7; color: #92400e; border-bottom-left-radius: 4px; }
        .chat-msg.private-msg .chat-bubble { background: #f0fdf4; color: #166534; border: 1px dashed #86efac; }
        .chat-meta { font-size: 0.72rem; color: #9ca3af; margin-top: 3px; padding: 0 4px; }
        .chat-meta.right { text-align: right; }
        .chat-private-label { font-size: 0.7rem; color: #059669; font-weight: 600; }
        #chat-input-area {
            padding: 10px 12px; border-top: 1px solid #e5e7eb;
            display: flex; gap: 8px; align-items: flex-end; background: white;
        }
        #chat-input {
            flex: 1; border: 2px solid #e5e7eb; border-radius: 10px;
            padding: 8px 12px; font-size: 0.88rem; resize: none;
            max-height: 80px; font-family: inherit; line-height: 1.4;
        }
        #chat-input:focus { border-color: #667eea; outline: none; }
        #chat-send-btn {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; border-radius: 10px;
            padding: 9px 14px; cursor: pointer; font-size: 1rem;
            transition: all .2s; flex-shrink: 0;
        }
        #chat-send-btn:hover { transform: scale(1.05); }
        #chat-send-btn:disabled { opacity: .5; cursor: default; transform: none; }
        #chat-status-bar {
            padding: 10px 14px; font-size: 0.82rem; text-align: center; color: #6b7280;
        }
        .chat-empty { text-align: center; color: #9ca3af; font-size: .85rem; padding: 20px 10px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Toldo de la tienda -->
<div class="store-awning"></div>

<div class="store-header">
    <div class="store-avatar"><?= strtoupper(mb_substr($seller['name'], 0, 1)) ?></div>
    <div class="store-meta">
        <h1>
            <?= htmlspecialchars($seller['name']) ?>
            <?php if ($seller['is_live']): ?>
                <a href="<?= htmlspecialchars($seller['live_link'] ?? '#') ?>" target="_blank" class="live-badge">
                    <span class="live-dot"></span>
                    <?= htmlspecialchars($seller['live_title'] ?: 'EN VIVO') ?>
                </a>
            <?php endif; ?>
        </h1>
        <div class="store-stats">
            <span><i class="fas fa-box" style="color:#667eea;"></i> <?= (int)$seller['product_count'] ?> productos</span>
            <span><i class="fas fa-shopping-cart" style="color:#667eea;"></i> <?= number_format((int)$seller['total_sales']) ?> ventas</span>
        </div>
    </div>
</div>

<?php if ($liveIsLink): ?>
<?php $lv = parseLiveUrl($seller['live_link']); ?>
<div class="store-live-section">
    <?php if ($lv['embedUrl']): ?>
    <div class="store-live-box">
        <div class="store-live-box-header" style="background:<?= $lv['color'] ?>">
            <span>
                <i class="<?= $lv['icon'] ?>"></i>
                <?= htmlspecialchars($seller['live_title'] ?: 'EN VIVO') ?>
            </span>
            <span style="font-size:0.78rem;opacity:.85;">
                <i class="fas fa-circle" style="color:#fff;animation:pulse-dot 1.2s infinite;"></i> EN VIVO
            </span>
        </div>
        <div class="store-live-iframe-wrap">
            <iframe src="<?= htmlspecialchars($lv['embedUrl']) ?>"
                    allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
        </div>
        <div class="store-live-footer">
            <a href="<?= htmlspecialchars($seller['live_link']) ?>" target="_blank">
                <i class="fas fa-external-link-alt"></i> Abrir en <?= $lv['platform'] ?>
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="store-live-nonembed" style="background:#fef2f2;border:2px solid <?= $lv['color'] ?>20">
        <span>
            <i class="<?= $lv['icon'] ?>" style="color:<?= $lv['color'] ?>;font-size:1.4rem;"></i>
            Esta emprendedora está en vivo en <?= $lv['platform'] ?>.<br>
            <small>El live de <?= $lv['platform'] ?> solo se puede ver en la app o el sitio oficial.</small>
        </span>
        <a href="<?= htmlspecialchars($seller['live_link']) ?>" target="_blank"
           style="background:<?= $lv['color'] ?>">
            <i class="fas fa-external-link-alt"></i> Ver en <?= $lv['platform'] ?>
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($liveIsCam): ?>
<!-- ── Player de Cámara Live ─────────────────────────────────────────── -->
<div class="store-live-section">
    <div class="store-live-box">
        <div class="store-live-box-header" style="background:linear-gradient(135deg,#374151,#111827);">
            <span>
                <i class="fas fa-video"></i>
                <?= htmlspecialchars($seller['live_title'] ?: 'En Vivo con Cámara') ?>
            </span>
            <span style="font-size:.78rem;opacity:.85;">
                <i class="fas fa-circle" style="color:#ef4444;animation:pulse-dot 1.2s infinite;"></i> EN VIVO
            </span>
        </div>
        <!-- Video con MediaSource API -->
        <div class="store-live-iframe-wrap" style="background:#111;position:relative;">
            <video id="cam-live-player" controls autoplay playsinline muted
                   style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#000;">
            </video>
            <!-- Overlay de estado (buffering / error) -->
            <div id="cam-buffering" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,.75);color:white;font-size:.9rem;gap:12px;text-align:center;padding:20px;">
                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i>
                <span>Conectando con la vendedora…</span>
            </div>
            <!-- Botón unmute (aparece cuando el video ya está reproduciendo) -->
            <button id="cam-unmute-btn" onclick="camUnmute()" title="Activar sonido"
                    style="display:none;position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.7);color:white;border:none;border-radius:8px;padding:7px 12px;cursor:pointer;font-size:.85rem;z-index:10;">
                <i class="fas fa-volume-mute"></i> Activar sonido
            </button>
        </div>
        <div class="store-live-footer">
            <span style="color:#9ca3af;font-size:.8rem;">
                <i class="fas fa-video" style="color:#10b981;"></i>
                Transmisión en directo desde la cámara de la vendedora
            </span>
        </div>
    </div>
</div>

<script>
(function(){
const SESSION_ID  = <?= json_encode($seller['live_session_id'] ?? '') ?>;
const video       = document.getElementById('cam-live-player');
const bufferMsg   = document.getElementById('cam-buffering');

if (!SESSION_ID || !video) {
    if (bufferMsg) bufferMsg.innerHTML = '<i class="fas fa-info-circle" style="font-size:1.5rem;"></i><span>No hay transmisión activa en este momento.</span>';
    return;
}

// Soporte: Chrome/Edge/Firefox usan MediaSource; iOS 17+ usa ManagedMediaSource
const MSClass = window.ManagedMediaSource || window.MediaSource;

if (!MSClass) {
    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    if (isIOS) {
        if (bufferMsg) bufferMsg.innerHTML = '<i class="fas fa-exclamation-triangle" style="font-size:1.5rem;color:#f59e0b;"></i><span>El live en cámara no está disponible en iOS. Actualiza a iOS 17 o superior, o abre esta página desde una computadora con Chrome.</span>';
    } else {
        if (bufferMsg) bufferMsg.innerHTML = '<i class="fas fa-exclamation-circle" style="font-size:1.5rem;color:#ef4444;"></i><span>Tu navegador no soporta este reproductor. Usa Chrome o Edge.</span>';
    }
    return;
}

// Codec preferido (debe coincidir con el del vendedor)
const mimeType = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm']
    .find(t => MSClass.isTypeSupported(t)) || 'video/webm';

// ManagedMediaSource (iOS 17+) requiere que el video tenga disableRemotePlayback
if (window.ManagedMediaSource && !window.MediaSource) {
    video.disableRemotePlayback = true;
}

const ms = new MSClass();
video.src = URL.createObjectURL(ms);

// ManagedMediaSource (iOS) requiere startstreaming para abrir el source
if (ms.addEventListener && window.ManagedMediaSource && !window.MediaSource) {
    ms.addEventListener('startstreaming', () => { video.play().catch(()=>{}); });
}

ms.addEventListener('sourceopen', async () => {
    console.log('[CAM-LIVE] sourceopen, mimeType:', mimeType);
    let sb;
    try {
        sb = ms.addSourceBuffer(mimeType);
    } catch(e) {
        console.error('[CAM-LIVE] addSourceBuffer failed:', e.message);
        if (bufferMsg) bufferMsg.innerHTML = '<i class="fas fa-exclamation-circle" style="font-size:1.5rem;color:#ef4444;"></i><span>Codec no soportado: ' + e.message + '</span>';
        return;
    }

    sb.addEventListener('error', e => console.error('[CAM-LIVE] SourceBuffer error:', e));

    const unmuteBtn = document.getElementById('cam-unmute-btn');
    let nextChunk = 0;
    let ended     = false;
    let initDone  = false;

    async function appendChunk(idx) {
        const r = await fetch(`/api/live-cam-serve.php?session_id=${SESSION_ID}&index=${idx}`);
        if (!r.ok) { console.warn('[CAM-LIVE] serve HTTP', r.status, 'idx', idx); return false; }
        const buf = await r.arrayBuffer();
        console.log('[CAM-LIVE] appending chunk', idx, buf.byteLength, 'bytes');
        try {
            // Esperar a que el buffer no esté actualizando
            if (sb.updating) await new Promise(r => sb.addEventListener('updateend', r, {once:true}));
            await new Promise((res, rej) => {
                sb.addEventListener('updateend', res, {once:true});
                sb.addEventListener('error',     rej, {once:true});
                sb.appendBuffer(buf);
            });
        } catch(e) {
            console.error('[CAM-LIVE] appendBuffer error idx', idx, e.message);
            return false;
        }
        return true;
    }

    async function poll() {
        if (ended) return;
        try {
            const r = await fetch(`/api/live-cam-poll.php?session_id=${SESSION_ID}`);
            const d = await r.json();
            console.log('[CAM-LIVE] poll:', d);

            if (d.ended && d.chunk_count === 0) {
                if (bufferMsg) bufferMsg.innerHTML = '<i class="fas fa-info-circle" style="font-size:1.5rem;"></i><span>La transmisión ha finalizado.</span>';
                return;
            }

            const available = d.chunk_count || 0;

            if (!initDone && available > 0) {
                // Cargar chunk 0 (init segment + primer frame)
                const ok = await appendChunk(0);
                if (ok) {
                    initDone = true;
                    nextChunk = 1;
                    if (bufferMsg) bufferMsg.style.display = 'none';
                    if (unmuteBtn) unmuteBtn.style.display = 'block';
                    // Video tiene muted para sortear política de autoplay
                    video.play().catch(e => console.warn('[CAM-LIVE] play():', e.message));
                }
            }

            // Cargar chunks nuevos disponibles (max 3 por ciclo)
            let loaded = 0;
            while (nextChunk < available && loaded < 3) {
                const ok = await appendChunk(nextChunk);
                if (ok) { nextChunk++; loaded++; }
                else break;
            }

            if (d.ended) {
                ended = true;
                if (ms.readyState === 'open') ms.endOfStream();
                return;
            }
        } catch(e) { console.error('[CAM-LIVE] poll error:', e.message); }
        setTimeout(poll, 2000);
    }

    poll();
});

window.camUnmute = function() {
    video.muted = false;
    const btn = document.getElementById('cam-unmute-btn');
    if (btn) btn.style.display = 'none';
    video.play().catch(()=>{});
};
})();
</script>
<?php endif; /* liveIsCam */ ?>

<!-- Filtros -->
<div class="store-filters">
    <form method="GET">
        <input type="hidden" name="id" value="<?= $sid ?>">
        <input type="text" name="q" placeholder="Buscar en esta tienda…" value="<?= htmlspecialchars($search) ?>">
        <?php if (!empty($categories)): ?>
        <select name="cat" onchange="this.form.submit()">
            <option value="0">Todas las categorías</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
    <a href="emprendedores-catalogo.php" style="color:#667eea;font-size:0.9rem;white-space:nowrap;">
        <i class="fas fa-arrow-left"></i> Volver al Mercadito
    </a>
</div>

<!-- Productos -->
<?php if (empty($products)): ?>
<div class="empty-state">
    <i class="fas fa-box-open"></i>
    <h3 style="color:#555;">No se encontraron productos</h3>
    <a href="?id=<?= $sid ?>" style="color:#667eea;">Ver todos</a>
</div>
<?php else: ?>
<div class="store-products">
    <?php foreach ($products as $prod): ?>
    <a href="emprendedores-producto.php?id=<?= $prod['id'] ?>" class="store-product-card">
        <?php if ($prod['image_1']): ?>
            <img src="<?= htmlspecialchars($prod['image_1']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="store-product-noimg"><i class="fas fa-image" style="font-size:3rem;color:#ccc;"></i></div>
        <?php endif; ?>
        <div class="spc-body">
            <div class="spc-cat"><?= htmlspecialchars($prod['category_name'] ?? '') ?></div>
            <div class="spc-name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="spc-price">₡<?= number_format($prod['price'], 0) ?></div>
            <?php if (($prod['stock'] ?? 0) > 0): ?>
                <button class="spc-add" onclick="event.preventDefault();addToCart(<?= $prod['id'] ?>,this)">
                    <i class="fas fa-cart-plus"></i> Agregar al carrito
                </button>
            <?php else: ?>
                <div class="spc-nostock">Sin stock</div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<a href="emprendedores-carrito.php" class="emp-cart-fab" id="emp-fab"
   style="display:<?= $empCartCount > 0 ? 'flex' : 'none' ?>">
    <i class="fas fa-shopping-bag"></i>
    <span class="fab-count" id="fab-count"><?= $empCartCount ?></span>
</a>
<div class="catalog-toast" id="catalog-toast"></div>

<script>
document.querySelectorAll('#hamburger-menu a').forEach(function(a) {
    if (a.getAttribute('href') === 'cart' || a.getAttribute('href') === '/cart') {
        a.setAttribute('href', '/emprendedores-carrito.php');
    }
});

function showToast(msg, ok) {
    const t = document.getElementById('catalog-toast');
    t.innerHTML = (ok ? '🛍️ ' : '⚠️ ') + msg;
    t.style.background = ok ? '#111' : '#ef4444';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
function updateFab(count) {
    const fab = document.getElementById('emp-fab');
    document.getElementById('fab-count').textContent = count;
    fab.style.display = count > 0 ? 'flex' : 'none';
}
function addToCart(pid, btn) {
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando…';
    fetch('/api/emp-cart.php?action=add', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid, qty: 1})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            showToast(d.message || '¡Agregado!', true);
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Listo!';
            updateFab(d.cart_count);
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 2000);
        } else {
            showToast(d.error || 'Error', false);
            btn.disabled = false; btn.innerHTML = orig;
        }
    })
    .catch(() => { showToast('Error de conexión', false); btn.disabled = false; btn.innerHTML = orig; });
}
fetch('/api/emp-cart.php?action=get', {credentials:'same-origin'})
    .then(r => r.json())
    .then(d => { if (d.ok) updateFab(d.count); })
    .catch(() => {});
</script>

<?php if ($sellerHasPaidPlan && $seller['is_live']): ?>
<!-- ══════════════════════════════════════════════════════════════════════
     CHAT EN VIVO — Widget flotante para el cliente
     El widget del carrito (emp-cart-fab) está bottom:24px/right:90px aprox.
     Este FAB del chat va a bottom:24px/right:24px (izquierda del carrito).
══════════════════════════════════════════════════════════════════════ -->
<div id="chat-fab">

    <?php if ($visitorUid && !$isTheSeller): ?>
    <!-- Estado inicial: tip para activar -->
    <div id="chat-activate-tip" style="display:none;">
        <i class="fas fa-comment-dots"></i> ¿Quieres hacer preguntas a la emprendedora?<br>
        <strong style="color:#a78bfa;">Clic para activar el chat</strong>
    </div>

    <!-- Panel de chat (oculto inicialmente) -->
    <div id="chat-panel">
        <div class="chat-header">
            <span class="chat-header-title">
                <i class="fas fa-comments"></i>
                Chat con <?= htmlspecialchars(explode(' ', $seller['name'])[0]) ?>
            </span>
            <button class="chat-header-close" onclick="closeChat()" title="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <div id="chat-messages">
            <div class="chat-empty" id="chat-empty-msg">
                <i class="fas fa-comment-slash" style="font-size:1.8rem;color:#d1d5db;display:block;margin-bottom:8px;"></i>
                Aún no hay mensajes. ¡Sé el primero en preguntar!
            </div>
        </div>
        <div id="chat-status-bar" style="display:none;"></div>
        <div id="chat-input-area">
            <textarea id="chat-input" rows="1" maxlength="500"
                placeholder="Escribe tu pregunta..." onkeydown="chatKeydown(event)"></textarea>
            <button id="chat-send-btn" onclick="sendChatMsg()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <?php elseif (!$visitorUid): ?>
    <!-- No logueado: panel con link a login -->
    <div id="chat-panel" style="display:none;">
        <div class="chat-header">
            <span class="chat-header-title">
                <i class="fas fa-comments"></i>
                Chat con <?= htmlspecialchars(explode(' ', $seller['name'])[0]) ?>
            </span>
            <button class="chat-header-close" onclick="closeChatGuest()"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding:22px;text-align:center;">
            <i class="fas fa-lock" style="font-size:2rem;color:#9ca3af;display:block;margin-bottom:10px;"></i>
            <strong>Inicia sesión para chatear con<br><?= htmlspecialchars($seller['name']) ?></strong>
            <div style="margin-top:14px;">
                <a href="/login" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:700;">
                    <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Botón flotante principal -->
    <?php if ($isTheSeller): ?>
    <!-- El vendedor ve su panel en el dashboard, no botón aquí -->
    <?php elseif (!$visitorUid): ?>
    <!-- No logueado: botón con etiqueta -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
        <button id="chat-toggle-btn" class="disabled-chat"
                title="Inicia sesión para chatear"
                onclick="toggleGuestChat()"
                style="width:58px;height:58px;border-radius:50%;border:none;cursor:pointer;
                       background:#d1d5db;color:#6b7280;font-size:1.4rem;
                       display:flex;align-items:center;justify-content:center;
                       box-shadow:0 4px 10px rgba(0,0,0,.1);">
            <i class="fas fa-comment-dots"></i>
        </button>
        <span id="chat-guest-label" style="background:#1f2937;color:white;border-radius:8px;
              padding:5px 10px;font-size:0.72rem;text-align:center;max-width:130px;
              line-height:1.3;white-space:nowrap;box-shadow:0 3px 10px rgba(0,0,0,.2);">
            Logueate para chatear<br>con <?= htmlspecialchars(explode(' ', $seller['name'])[0]) ?>
        </span>
    </div>
    <?php else: ?>
    <button id="chat-toggle-btn" class="disabled-chat"
            title="Chat con la emprendedora"
            onclick="onChatBtnClick()"
            data-activated="0">
        <i class="fas fa-comment-dots"></i>
        <span id="chat-unread-badge"></span>
    </button>
    <?php endif; ?>

</div>

<script>
(function(){
const SELLER_ID   = <?= (int)$sid ?>;
const VISITOR_UID = <?= $visitorUid ?>;
let activated  = false;
let pollTimer  = null;
let lastId     = 0;
let unread     = 0;
let isBanned   = false;
let tipShown   = false;

const fab      = document.getElementById('chat-fab');
const btn      = document.getElementById('chat-toggle-btn');
const panel    = document.getElementById('chat-panel');
const msgs     = document.getElementById('chat-messages');
const emptyMsg = document.getElementById('chat-empty-msg');
const statusBar= document.getElementById('chat-status-bar');
const input    = document.getElementById('chat-input');
const badge    = document.getElementById('chat-unread-badge');
const tip      = document.getElementById('chat-activate-tip');

// Mostrar tip después de 3 segundos
<?php if ($visitorUid && !$isTheSeller): ?>
setTimeout(function(){
    if (!activated && tip) { tip.style.display = 'block'; tipShown = true; }
}, 3000);
<?php endif; ?>

window.onChatBtnClick = function() {
    if (!activated) {
        // Primera vez: activar
        if (tip) tip.style.display = 'none';
        activated = true;
        btn.classList.remove('disabled-chat');
        btn.dataset.activated = '1';
        openChat();
    } else {
        // Ya activado: toggle
        if (panel && panel.classList.contains('open')) {
            closeChat();
        } else {
            openChat();
        }
    }
};

window.openChat = function() {
    if (!panel) return;
    panel.classList.add('open');
    btn.innerHTML = '<i class="fas fa-times"></i>';
    unread = 0;
    const currentBadge = document.getElementById('chat-unread-badge');
    if (currentBadge) currentBadge.style.display = 'none';
    if (!pollTimer) startPolling();
    if (input) setTimeout(()=>input.focus(), 200);
};

window.closeChat = function() {
    if (!panel) return;
    panel.classList.remove('open');
    if (btn) btn.innerHTML = '<i class="fas fa-comment-dots"></i>' +
        '<span id="chat-unread-badge" style="display:none"></span>';
};

function startPolling() {
    poll();
    pollTimer = setInterval(poll, 3000);
}

function poll() {
    fetch('/api/chat-poll.php?seller_id=' + SELLER_ID + '&last_id=' + lastId, {credentials:'same-origin'})
    .then(r => r.json())
    .then(data => {
        if (data.is_banned && !isBanned) {
            isBanned = true;
            showBanned();
        }
        if (data.messages && data.messages.length) {
            data.messages.forEach(appendMessage);
            lastId = data.messages[data.messages.length-1].id;
            if (!panel.classList.contains('open')) {
                unread += data.messages.length;
                showBadge(unread);
            }
        }
    })
    .catch(()=>{});
}

function appendMessage(m) {
    if (emptyMsg) emptyMsg.style.display = 'none';
    const isMe = (m.sender_id == VISITOR_UID);
    const isSeller = (m.sender_type === 'seller');
    const isPrivate = (m.is_public == 0);

    const wrap = document.createElement('div');
    wrap.className = 'chat-msg ' +
        (isMe ? 'mine' : (isSeller ? 'seller-msg' : 'theirs')) +
        (isPrivate ? ' private-msg' : '');

    let label = '';
    if (isPrivate) label = '<div class="chat-private-label"><i class="fas fa-lock"></i> Mensaje privado</div>';
    if (isSeller && !isMe) label += '<div class="chat-meta">' +
        '<i class="fas fa-store" style="color:#d97706;"></i> Emprendedora</div>';

    wrap.innerHTML = label +
        '<div class="chat-bubble">' + escHtml(m.message) + '</div>' +
        '<div class="chat-meta' + (isMe?' right':'') + '">' +
            (!isMe && !isSeller ? escHtml(m.sender_name) + ' · ' : '') + (m.time||'') +
        '</div>';

    if (msgs) {
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
    }
}

function showBanned() {
    clearInterval(pollTimer);
    if (statusBar) {
        statusBar.style.display = 'block';
        statusBar.innerHTML = '<i class="fas fa-ban" style="color:#ef4444;font-size:1.5rem;display:block;margin-bottom:6px;"></i>' +
            '<strong style="color:#dc2626;">Has sido bloqueado por esta emprendedora.</strong>';
    }
    if (document.getElementById('chat-input-area'))
        document.getElementById('chat-input-area').style.display = 'none';
}

function showBadge(n) {
    const b = document.getElementById('chat-unread-badge');
    if (!b) return;
    b.style.display = 'flex'; b.textContent = n > 9 ? '9+' : n;
}

window.sendChatMsg = function() {
    if (!input || isBanned) return;
    const txt = input.value.trim();
    if (!txt) return;
    const sendBtn = document.getElementById('chat-send-btn');
    sendBtn.disabled = true;

    fetch('/api/chat-send.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'seller_id=' + SELLER_ID + '&message=' + encodeURIComponent(txt)
    })
    .then(r => r.json())
    .then(d => {
        sendBtn.disabled = false;
        if (d.ok) {
            input.value = '';
            if (statusBar) statusBar.style.display = 'none';
            poll();
        } else if (d.error === 'profanity') {
            showStatus(d.msg, '#ef4444');
        } else if (d.error === 'banned') {
            showBanned();
        } else {
            showStatus(d.msg || 'Error al enviar', '#ef4444');
        }
    })
    .catch(()=>{ sendBtn.disabled = false; showStatus('Error de conexión','#ef4444'); });
};

window.chatKeydown = function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMsg(); }
};

function showStatus(msg, color) {
    if (!statusBar) return;
    statusBar.style.display = 'block';
    statusBar.style.color = color || '#6b7280';
    statusBar.innerHTML = msg;
    setTimeout(()=>{ statusBar.style.display = 'none'; }, 4000);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
})();
</script>
<?php endif; /* sellerHasPaidPlan && is_live (bloque JS usuario logueado) */ ?>

<?php if ($sellerHasPaidPlan && $seller['is_live'] && !$visitorUid && !$isTheSeller): ?>
<script>
function toggleGuestChat() {
    const p = document.getElementById('chat-panel');
    const l = document.getElementById('chat-guest-label');
    if (!p) return;
    const open = p.style.display === 'flex';
    p.style.display = open ? 'none' : 'flex';
    if (l) l.style.display = open ? '' : 'none';
}
function closeChatGuest() {
    const p = document.getElementById('chat-panel');
    const l = document.getElementById('chat-guest-label');
    if (p) p.style.display = 'none';
    if (l) l.style.display = '';
}
</script>
<?php endif; ?>
</body>
</html>
