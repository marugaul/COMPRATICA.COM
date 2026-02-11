<?php
// aqu�� se supone que ya existen $cantidadProductos y $isLoggedIn
?>
<header class="header">
  <a href="index" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </a>
  <nav class="header-nav">
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
      <a href="my_orders" class="menu-item">
        <i class="fas fa-box"></i>
        <span>Mis Órdenes</span>
      </a>
      <a href="cart" class="menu-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Mi Carrito</span>
      </a>
      <div class="menu-divider"></div>
    <?php else: ?>
      <a href="login" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
      <div class="menu-divider"></div>
    <?php endif; ?>

    <a href="index" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>

    <a href="servicios" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Servicios</span>
    </a>

    <a href="venta-garaje" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
    </a>

    <a href="bienes-raices" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Bienes Raíces</span>
    </a>

    <div class="menu-item" style="opacity: 0.5; cursor: not-allowed;">
      <i class="fas fa-rocket"></i>
      <span>Emprendedores - Muy Pronto</span>
    </div>

    <div class="menu-item" style="opacity: 0.5; cursor: not-allowed;">
      <i class="fas fa-crown"></i>
      <span>Emprendedoras - Muy Pronto</span>
    </div>

    <div class="menu-divider"></div>

    <a href="affiliate/register.php" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>

    <a href="affiliate/register.php" class="menu-item highlight">
      <i class="fas fa-building"></i>
      <span>Publicar Propiedad</span>
    </a>

    <a href="affiliate/login.php" class="menu-item">
      <i class="fas fa-user-tie"></i>
      <span>Portal Afiliados</span>
    </a>

    <a href="admin/login.php" class="menu-item">
      <i class="fas fa-user-shield"></i>
      <span>Administrador</span>
    </a>

    <?php if ($isLoggedIn): ?>
      <div class="menu-divider"></div>
      <a href="logout" class="menu-item danger">
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
