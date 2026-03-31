<?php
// aqu�� se supone que ya existen $cantidadProductos y $isLoggedIn
?>
<link rel="stylesheet" href="/assets/css/responsive.css">
<header class="header">
  <a href="/" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </a>
  <nav class="header-nav">
    <?php if (!empty($extra_nav_item)) echo $extra_nav_item; ?>
    <button class="btn-icon" id="cartButton" title="Carrito">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" id="cartBadge"><?php echo $cantidadProductos; ?></span>
    </button>
    <button id="menuButton" class="btn-icon" title="Menú" aria-label="Abrir menú">
      <i class="fas fa-bars"></i>
    </button>
  </nav>
</header>

<div class="top-bar"></div>

<!-- Overlay del menú -->
<div id="menu-overlay"></div>

<!-- Menú hamburguesa -->
<aside id="hamburger-menu">
  <button class="menu-close" id="menu-close" aria-label="Cerrar menú">
    <i class="fas fa-times"></i>
  </button>

  <div class="menu-header">
    <?php if ($isLoggedIn): ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <?php echo strtoupper(substr($userName, 0, 1)); ?>
        </div>
        <div class="menu-user-info">
          <h3><?php echo htmlspecialchars($userName); ?></h3>
          <p>Bienvenido de nuevo</p>
        </div>
      </div>
    <?php else: ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <i class="fas fa-user"></i>
        </div>
        <div class="menu-user-info">
          <h3>Hola, Invitado</h3>
          <p>Inicia sesión para más opciones</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="menu-body">
    <?php if ($isLoggedIn): ?>
      <a href="/my_orders" class="menu-item">
        <i class="fas fa-box"></i>
        <span>Mis Órdenes</span>
      </a>
      <a href="/cart" class="menu-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Mi Carrito</span>
      </a>
    <?php else: ?>
      <a href="/login" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
    <?php endif; ?>

    <!-- ── CLIENTES ─────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-shopping-bag" style="color:#3b82f6;margin-right:4px;"></i>Para Clientes
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="/" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>

    <a href="/servicios" class="menu-item">
      <i class="fas fa-concierge-bell"></i>
      <span>Empleos y Servicios</span>
    </a>

    <a href="/venta-garaje" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
    </a>

    <a href="/bienes-raices" class="menu-item">
      <i class="fas fa-building"></i>
      <span>Bienes Raíces</span>
    </a>

    <a href="/emprendedores-catalogo" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Emprendedores</span>
    </a>

    <!-- ── VENDEDORES ────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-store-alt" style="color:#10b981;margin-right:4px;"></i>Para Vendedores
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="/select-publication-type" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>

    <a href="/affiliate/login.php" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Portal Venta Garaje</span>
    </a>

    <a href="/jobs_service/login.php" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Portal Empleos y Servicios</span>
    </a>

    <a href="/real-estate/login.php" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Portal Bienes Raíces</span>
    </a>

    <a href="/emprendedores-dashboard" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Portal Emprendedores</span>
    </a>

    <?php if ($isLoggedIn): ?>
      <div class="menu-divider"></div>
      <a href="/logout" class="menu-item danger">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar Sesión</span>
      </a>
    <?php endif; ?>
  </div>
</aside>

<script>
// MENÚ HAMBURGUESA
const menuButton = document.getElementById('menuButton');
const menuOverlay = document.getElementById('menu-overlay');
const hamburgerMenu = document.getElementById('hamburger-menu');
const menuClose = document.getElementById('menu-close');

function openMenu() {
  menuOverlay.classList.add('show');
  hamburgerMenu.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  menuOverlay.classList.remove('show');
  hamburgerMenu.classList.remove('show');
  document.body.style.overflow = '';
}

if (menuButton) menuButton.addEventListener('click', openMenu);
if (menuClose) menuClose.addEventListener('click', closeMenu);
if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);

// Cerrar con ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && hamburgerMenu && hamburgerMenu.classList.contains('show')) {
    closeMenu();
  }
});
</script>

<?php require_once __DIR__ . '/chat-support.php'; ?>
