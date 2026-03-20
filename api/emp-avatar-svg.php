<?php
/**
 * API: Renderiza un avatar SVG chibi en base a config JSON (GET ?cfg=...)
 * Usado por el constructor de avatares en el dashboard (preview en tiempo real)
 */
require_once __DIR__ . '/../includes/avatar_builder.php';

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$cfg  = json_decode($_GET['cfg'] ?? '{}', true);
if (!is_array($cfg)) $cfg = [];

$size = max(20, min(300, (int)($_GET['size'] ?? 100)));

echo avatarSVG($cfg, $size);
