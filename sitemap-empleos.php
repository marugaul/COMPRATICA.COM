<?php
/**
 * Sitemap XML para Empleos
 * Genera un sitemap dinámico con todos los empleos activos
 * URL: https://compratica.com/sitemap-empleos.php
 */

header('Content-Type: application/xml; charset=utf-8');

require_once __DIR__ . '/includes/db.php';

$pdo = db();

// Obtener todos los empleos activos
$stmt = $pdo->query("
    SELECT
        id,
        title,
        updated_at,
        created_at
    FROM job_listings
    WHERE is_active = 1
      AND listing_type = 'job'
    ORDER BY created_at DESC
    LIMIT 1000
");

$empleos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Página principal de empleos -->
  <url>
    <loc>https://compratica.com/empleos.php</loc>
    <lastmod><?php echo date('c'); ?></lastmod>
    <changefreq>hourly</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Empleos individuales -->
  <?php foreach ($empleos as $empleo): ?>
  <url>
    <loc>https://compratica.com/publicacion-detalle.php?id=<?php echo $empleo['id']; ?></loc>
    <lastmod><?php echo date('c', strtotime($empleo['updated_at'] ?? $empleo['created_at'])); ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>

</urlset>
