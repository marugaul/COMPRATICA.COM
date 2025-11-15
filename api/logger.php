<?php
/**
 * api/logger.php
 * Recibe errores de cliente (JS) vía POST (sendBeacon / fetch)
 * y los registra en logs/js-YYYYMMDD.log como JSON-lines.
 */
declare(strict_types=1);

// -------- Ajustes básicos --------
$LOG_DIR = dirname(__DIR__) . '/logs';
@is_dir($LOG_DIR) || @mkdir($LOG_DIR, 0775, true);
@touch($LOG_DIR . '/.keep');

// Forzar JSON de salida
if (!headers_sent()) {
  header('Content-Type: application/json; charset=UTF-8');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: same-origin');
}

// Método permitido
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
  exit;
}

// Protección simple de origen: permitir solo mismo host si viene origin/referrer
$host     = $_SERVER['HTTP_HOST'] ?? '';
$origin   = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer  = $_SERVER['HTTP_REFERER'] ?? '';
$checkUrl = $origin ?: $referer;
if ($checkUrl) {
  $h = parse_url($checkUrl, PHP_URL_HOST);
  if ($h && $host && !hash_equals($host, $h)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden origin']);
    exit;
  }
}

// Límite de tamaño (20KB)
$len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($len > 20000) {
  http_response_code(413);
  echo json_encode(['ok' => false, 'error' => 'Payload too large']);
  exit;
}

// Leer JSON
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
  exit;
}

// Normalizar campos
$now = (new DateTimeImmutable('now'));
$entry = [
  'ts'         => $now->format('Y-m-d H:i:s.u'),
  'level'      => strtoupper((string)($data['level'] ?? 'error')),
  'type'       => (string)($data['type'] ?? 'js'),
  'message'    => (string)($data['message'] ?? ''),
  'stack'      => (string)($data['stack'] ?? ''),
  'url'        => (string)($data['url'] ?? ''),
  'file'       => (string)($data['file'] ?? ''),
  'line'       => (int)($data['line'] ?? 0),
  'col'        => (int)($data['col'] ?? 0),
  'page_title' => (string)($data['page_title'] ?? ''),
  'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
  'ip'         => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
  'referer'    => (string)$referer,
  'extra'      => is_array($data['extra'] ?? null) ? $data['extra'] : null,
];

// Escribir a archivo por día
$path = $LOG_DIR . '/js-' . $now->format('Ymd') . '.log';
try {
  file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
} catch (Throwable $e) {
  error_log('[logger.php] write failed: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Write failed']);
  exit;
}

echo json_encode(['ok' => true]);
