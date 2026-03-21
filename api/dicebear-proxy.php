<?php
/**
 * Proxy para DiceBear avataaars — evita CORB sirviendo el SVG desde el mismo origen
 */
require_once __DIR__ . '/../includes/avatar_builder.php';

$cfg  = json_decode($_GET['cfg'] ?? '{}', true);
if (!is_array($cfg)) $cfg = [];
$size = min(400, max(32, (int)($_GET['size'] ?? 100)));

$url = avatarUrl($cfg, $size);

// Intenta con curl, cae en file_get_contents si no está disponible
$svg = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'COMPRATICA-Avatar-Proxy/1.0',
    ]);
    $svg  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) $svg = false;
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $svg = @file_get_contents($url, false, $ctx);
}

if (!$svg) {
    // Fallback: círculo de color sólido
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=300');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
       . '<circle cx="' . ($size/2) . '" cy="' . ($size/2) . '" r="' . ($size/2) . '" fill="#8b5cf6"/>'
       . '</svg>';
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('Access-Control-Allow-Origin: *');
echo $svg;
