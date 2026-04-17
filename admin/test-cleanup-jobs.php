<?php
require_once __DIR__ . '/../includes/config.php';
if (($_GET['key'] ?? '') !== ADMIN_PASS_PLAIN) { http_response_code(403); die('Acceso denegado'); }

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

$total     = $pdo->query("SELECT COUNT(*) FROM job_listings")->fetchColumn();
$importados = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source IS NOT NULL")->fetchColumn();
$a_borrar  = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source IS NOT NULL AND created_at < datetime('now', '-14 days')")->fetchColumn();
$recientes = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source IS NOT NULL AND created_at >= datetime('now', '-14 days')")->fetchColumn();
$clientes  = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source IS NULL")->fetchColumn();

echo "=== Estado empleos ===\n";
echo "Total: $total\n";
echo "Importados automáticamente: $importados\n";
echo "  → Serían eliminados (>14 días): $a_borrar\n";
echo "  → Se conservarían (<14 días):  $recientes\n";
echo "Publicados por clientes: $clientes\n\n";

// Último log del cron
$log = '/home/comprati/public_html/logs/cleanup_jobs.log';
if (file_exists($log)) {
    echo "=== Últimas 5 ejecuciones del cron ===\n";
    $lines = file($log);
    echo implode('', array_slice($lines, -5));
} else {
    echo "⚠️  Log no encontrado en: $log\n";
    echo "El cron aún no ha corrido o el log está en otra ruta.\n";
}
