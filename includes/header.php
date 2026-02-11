<?php
// aquí se supone que ya existen $cantidadProductos y $isLoggedIn
?>
<header class="header">
  <div class="logo">
    <span class="flag">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </div>
  <nav class="header-nav">
    <button class="btn-icon" id="cartButton" title="Carrito">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" id="cartBadge"><?php echo $cantidadProductos; ?></span>
    </button>
    <?php if ($isLoggedIn): ?>
      <a href="profile.php" class="btn-icon" title="Mi Perfil">
        <i class="fas fa-user"></i>
      </a>
    <?php else: ?>
      <a href="login.php" class="btn-icon" title="Iniciar Sesión">
        <i class="fas fa-sign-in-alt"></i>
      </a>
    <?php endif; ?>
  </nav>
</header>

<div class="top-bar"></div>
