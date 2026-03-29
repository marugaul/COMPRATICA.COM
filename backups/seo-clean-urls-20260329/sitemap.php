<?php
/**
 * Sitemap dinámico — CompraTica
 * Genera el sitemap XML con todas las páginas públicas y publicaciones activas.
 * Accesible en: https://compratica.com/sitemap
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=UTF-8');

// Páginas estáticas están en sitemap.xml — este sitemap solo genera URLs dinámicas.
$base = 'https://compratica.com';
$pdo  = db();
$urls = [];

// ─── EMPLEOS Y SERVICIOS (job_listings) ──────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id, listing_type,
               COALESCE(updated_at, created_at) AS lastmod
        FROM job_listings
        WHERE is_active = 1
        ORDER BY lastmod DESC
        LIMIT 50000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $urls[] = [
            'loc'        => $base . '/publicacion-detalle?id=' . (int)$row['id'],
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.70',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── BIENES RAÍCES ───────────────────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id,
               COALESCE(updated_at, created_at) AS lastmod
        FROM real_estate_listings
        WHERE is_active = 1
        ORDER BY lastmod DESC
        LIMIT 20000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $urls[] = [
            'loc'        => $base . '/propiedad-detalle?id=' . (int)$row['id'],
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.70',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── VENTA GARAJE (tiendas) ───────────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id,
               COALESCE(updated_at, created_at) AS lastmod
        FROM sales
        WHERE active = 1
        ORDER BY lastmod DESC
        LIMIT 20000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $urls[] = [
            'loc'        => $base . '/store?sale_id=' . (int)$row['id'],
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.65',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── EMPRENDEDORAS (productos) ────────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id,
               COALESCE(updated_at, created_at) AS lastmod
        FROM entrepreneur_products
        WHERE is_active = 1
        ORDER BY lastmod DESC
        LIMIT 20000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $urls[] = [
            'loc'        => $base . '/emprendedores-producto?id=' . (int)$row['id'],
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.65',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── OUTPUT XML ──────────────────────────────────────────────────────────────
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($u['lastmod'], ENT_XML1) . "</lastmod>\n";
    echo "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
    echo "    <priority>" . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
