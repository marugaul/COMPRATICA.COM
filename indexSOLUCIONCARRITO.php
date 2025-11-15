<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = db();
$sales = $pdo->query("
  SELECT s.*, a.name AS affiliate_name
  FROM sales s
  JOIN affiliates a ON a.id = s.affiliate_id
  WHERE s.is_active = 1
  ORDER BY datetime(s.start_at) ASC
")->fetchAll(PDO::FETCH_ASSOC);

function same_date($tsA, $tsB) {
  return date('Y-m-d', $tsA) === date('Y-m-d', $tsB);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?> — Marketplace</title>
  <link rel="stylesheet" href="assets/style.css?v=20251012a">
</head>
<body>

<!-- ========================= HEADER ========================= -->
<header class="header">
  <div class="logo">🛒 <?php echo APP_NAME; ?> — Marketplace</div>
  <nav>
    <a class="btn" href="affiliate/login.php">Afiliados</a>
    <a class="btn primary" href="affiliate/register.php">Publicar mi venta</a>
  </nav>
</header>

<!-- ========================= MAIN ========================= -->
<div class="container">
  <h1>Espacios de venta</h1>
  <p class="small">Descubre ventas de garaje activas y próximas cerca de ti.</p>

  <div class="grid">
    <?php
    $nowTs = time();
    foreach ($sales as $s):
      $st  = strtotime($s['start_at']);
      $en  = strtotime($s['end_at']);

      // Estado principal
      $state = 'Próxima';
      $color = '#2563eb';
      if ($nowTs >= $st && $nowTs <= $en) {
        $state = 'En vivo';
        $color = '#059669';
      } elseif ($nowTs > $en) {
        $state = 'Finalizada';
        $color = '#6b7280';
      }

      // Etiqueta secundaria (máximo 1): Último día > Hoy > Nuevo
      $secondary = null; $secClass = '';
      if ($state === 'En vivo' && same_date($en, $nowTs)) {
        $secondary = 'Último día'; $secClass = 'chip chip-red';
      } elseif (same_date($st, $nowTs)) {
        $secondary = 'Hoy';        $secClass = 'chip chip-orange';
      } elseif ($st >= strtotime('-2 days', $nowTs)) {
        $secondary = 'Nuevo';      $secClass = 'chip chip-blue';
      }

      $img = $s['cover_image'] ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image']) : 'assets/placeholder.jpg';
      $img2 = !empty($s['cover_image2']) ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image2']) : null;
      $imgs = $img2 ? [$img, $img2] : [$img];
    ?>
      <div class="card">
        <div class="imgbox">
          <img class="sale-img" data-images='<?php echo json_encode($imgs, JSON_UNESCAPED_SLASHES); ?>' src="<?php echo $imgs[0]; ?>" alt="Portada de <?php echo htmlspecialchars($s['title']); ?>">
        </div>

        <div class="badges-row">
          <span class="badge" style="background:<?php echo $color; ?>;color:#fff;border:none">
            <?php echo $state; ?>
          </span>
          <?php if ($secondary): ?>
            <span class="<?php echo $secClass; ?>"><?php echo $secondary; ?></span>
          <?php endif; ?>
        </div>

        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
        <p class="small">
          <?php echo htmlspecialchars($s['affiliate_name']); ?><br>
          <?php echo date('d/m/Y H:i', $st); ?> — <?php echo date('d/m/Y H:i', $en); ?>
        </p>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>">Entrar</a>
          <?php elseif ($state === 'Próxima'): ?>
            <span class="btn" style="opacity:0.6;cursor:not-allowed">Aún no inicia</span>
          <?php else: ?>
            <span class="btn" style="opacity:0.7;cursor:not-allowed">Finalizada</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($sales)): ?>
      <div class="card">
        <p class="small">Aún no hay espacios activos.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ========================= FOOTER ========================= -->
<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo ADMIN_EMAIL; ?>">Contacto</a>
      <a href="affiliate/login.php">Ser afiliado</a>
      <a href="admin/login.php">Administrador</a>
    </div>
  </div>
</footer>


<script>
(function(){
  var nodes = document.querySelectorAll('.sale-img[data-images]');
  nodes.forEach(function(img){
    try {
      var arr = JSON.parse(img.getAttribute('data-images')||'[]');
      if (!Array.isArray(arr) || arr.length < 2) return;
      var i = 0;
      setInterval(function(){
        i = (i + 1) % arr.length;
        img.src = arr[i];
      }, 3500);
    } catch(e){}
  });
})();
</script>

</body>
</html>
