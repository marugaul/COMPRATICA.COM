<?php
/**
 * API: Proxy hacia DiceBear (evita CORB — mismo origen)
 */
require_once __DIR__ . '/../includes/avatar_builder.php';

$cfg  = json_decode($_GET['cfg'] ?? '{}', true);
if (!is_array($cfg)) $cfg = [];
$size = max(20, min(300, (int)($_GET['size'] ?? 100)));

// Redirige al proxy local en vez del CDN directo
header('Location: dicebear-proxy.php?size=' . $size . '&cfg=' . rawurlencode(json_encode($cfg)), true, 302);
