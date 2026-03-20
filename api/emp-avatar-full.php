<?php
/**
 * API: Renderiza el avatar compuesto (DiceBear head + SVG body) para preview del dashboard
 */
require_once __DIR__ . '/../includes/avatar_builder.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

$cfg = json_decode($_GET['cfg'] ?? '{}', true);
if (!is_array($cfg)) $cfg = [];

echo avatarFull($cfg, 155);
