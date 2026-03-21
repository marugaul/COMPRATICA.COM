<?php
/**
 * api/track.php
 * Registra visitas de página para el panel de estadísticas.
 * Se llama vía fetch (JS) desde el footer de la web.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: same-origin');

require_once __DIR__ . '/../includes/db.php';

// Crear tabla si no existe
$pdo = db();
$pdo->exec("
  CREATE TABLE IF NOT EXISTS site_visits (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(64),
    ip         VARCHAR(45),
    page       VARCHAR(500),
    referrer   VARCHAR(500),
    user_agent VARCHAR(500),
    sale_id    INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sv_created ON site_visits(created_at)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sv_session ON site_visits(session_id)");

// Datos recibidos
$page       = substr(trim($_POST['page'] ?? $_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
$referrer   = substr(trim($_POST['referrer'] ?? ''), 0, 500);
$sale_id    = (int)($_POST['sale_id'] ?? 0) ?: null;
$session_id = substr(session_id() ?: ($_COOKIE['PHPSESSID'] ?? bin2hex(random_bytes(16))), 0, 64);
$ip         = $_SERVER['HTTP_X_FORWARDED_FOR']
                ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
                : ($_SERVER['REMOTE_ADDR'] ?? '');
$ip         = substr(trim($ip), 0, 45);
$ua         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Limitar a 1 registro por sesión+página cada 60 segundos (evitar spam)
$dup = $pdo->prepare("
  SELECT COUNT(*) FROM site_visits
  WHERE session_id=? AND page=? AND created_at > datetime('now','-60 seconds')
");
$dup->execute([$session_id, $page]);
if ((int)$dup->fetchColumn() === 0) {
    $ins = $pdo->prepare("
      INSERT INTO site_visits (session_id, ip, page, referrer, user_agent, sale_id, created_at)
      VALUES (?,?,?,?,?,?,datetime('now'))
    ");
    $ins->execute([$session_id, $ip, $page, $referrer, $ua, $sale_id]);
}

// Purgar registros > 30 días
try {
    $pdo->exec("DELETE FROM site_visits WHERE created_at < datetime('now','-30 days')");
} catch (Exception $e) {}

echo json_encode(['ok' => true]);
