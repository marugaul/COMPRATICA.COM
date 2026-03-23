<?php
/**
 * Sitemap dinámico — CompraTica
 * Genera el sitemap XML con todas las páginas públicas y publicaciones activas.
 * Accesible en: https://compratica.com/sitemap
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$base  = 'https://compratica.com';
$today = date('Y-m-d');
$pdo   = db();
$urls  = [];

// ─── PÁGINAS ESTÁTICAS ───────────────────────────────────────────────────────
$staticPages = [
    ['loc' => '/',                        'priority' => '1.00', 'changefreq' => 'daily'],
    ['loc' => '/servicios',               'priority' => '0.95', 'changefreq' => 'daily'],
    ['loc' => '/empleos',                 'priority' => '0.95', 'changefreq' => 'daily'],
    ['loc' => '/venta-garaje',            'priority' => '0.90', 'changefreq' => 'daily'],
    ['loc' => '/bienes-raices',           'priority' => '0.90', 'changefreq' => 'daily'],
    ['loc' => '/emprendedoras-catalogo',  'priority' => '0.85', 'changefreq' => 'daily'],
    ['loc' => '/shuttle_search',          'priority' => '0.80', 'changefreq' => 'weekly'],
    ['loc' => '/ofertas-servicios',       'priority' => '0.75', 'changefreq' => 'weekly'],
];

foreach ($staticPages as $page) {
    $urls[] = [
        'loc'        => $base . $page['loc'],
        'lastmod'    => $today,
        'changefreq' => $page['changefreq'],
        'priority'   => $page['priority'],
    ];
}

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
            'loc'        => $base . '/emprendedoras-producto?id=' . (int)$row['id'],
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
