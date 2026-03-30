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

// url_slug(), clean_url_* vienen de includes/config.php

// ─── EMPLEOS Y SERVICIOS (job_listings) ──────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id, title,
               COALESCE(updated_at, created_at) AS lastmod
        FROM job_listings
        WHERE is_active = 1
          AND title IS NOT NULL AND TRIM(title) != ''
          AND description IS NOT NULL AND LENGTH(TRIM(description)) >= 80
        ORDER BY lastmod DESC
        LIMIT 5000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = url_slug($row['title'] ?? '');
        $urls[] = [
            'loc'        => $base . '/publicacion/' . (int)$row['id'] . ($slug ? '-' . $slug : ''),
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.70',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── BIENES RAÍCES ───────────────────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT id, title,
               COALESCE(updated_at, created_at) AS lastmod
        FROM real_estate_listings
        WHERE is_active = 1
          AND title IS NOT NULL AND TRIM(title) != ''
          AND description IS NOT NULL AND LENGTH(TRIM(description)) >= 80
        ORDER BY lastmod DESC
        LIMIT 5000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = url_slug($row['title'] ?? '');
        $urls[] = [
            'loc'        => $base . '/propiedad/' . (int)$row['id'] . ($slug ? '-' . $slug : ''),
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.70',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── VENTA GARAJE (tiendas con al menos 1 producto activo) ───────────────────
try {
    $stmt = $pdo->query("
        SELECT s.id, s.title,
               COALESCE(s.updated_at, s.created_at) AS lastmod
        FROM sales s
        WHERE s.active = 1
          AND s.title IS NOT NULL AND TRIM(s.title) != ''
          AND EXISTS (
              SELECT 1 FROM products p
              WHERE p.sale_id = s.id AND p.active = 1
          )
        ORDER BY lastmod DESC
        LIMIT 5000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = url_slug($row['title'] ?? '');
        $urls[] = [
            'loc'        => $base . '/tienda/' . (int)$row['id'] . ($slug ? '-' . $slug : ''),
            'lastmod'    => date('Y-m-d', strtotime($row['lastmod'])),
            'changefreq' => 'weekly',
            'priority'   => '0.65',
        ];
    }
} catch (Exception $e) { /* continuar */ }

// ─── EMPRENDEDORAS (productos con imagen y descripción) ──────────────────────
try {
    $stmt = $pdo->query("
        SELECT id, name,
               COALESCE(updated_at, created_at) AS lastmod
        FROM entrepreneur_products
        WHERE is_active = 1
          AND name IS NOT NULL AND TRIM(name) != ''
          AND description IS NOT NULL AND LENGTH(TRIM(description)) >= 50
          AND image_url IS NOT NULL AND TRIM(image_url) != ''
        ORDER BY lastmod DESC
        LIMIT 5000
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = url_slug($row['name'] ?? '');
        $urls[] = [
            'loc'        => $base . '/producto/' . (int)$row['id'] . ($slug ? '-' . $slug : ''),
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
