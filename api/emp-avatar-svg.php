<?php
/**
 * API: Redirige a DiceBear avataaars URL para preview en tiempo real
 */
require_once __DIR__ . '/../includes/avatar_builder.php';

$cfg  = json_decode($_GET['cfg'] ?? '{}', true);
if (!is_array($cfg)) $cfg = [];
$size = max(20, min(300, (int)($_GET['size'] ?? 100)));

// Redirect to DiceBear CDN (browser caches this efficiently)
$url = avatarUrl($cfg, $size);
header('Location: ' . $url, true, 302);
header('Cache-Control: public, max-age=300');
