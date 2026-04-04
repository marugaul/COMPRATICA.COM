<?php
/**
 * admin/cleanup_jobs.php
 * Cron script: elimina empleos automáticos con más de 14 días.
 * Los empleos de clientes (import_source IS NULL) no se tocan.
 *
 * Cron sugerido (diario a las 3am):
 *   0 3 * * * php /home/comprati/public_html/admin/cleanup_jobs.php >> /home/comprati/public_html/logs/cleanup_jobs.log 2>&1
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$deleted = $pdo->exec(
    "DELETE FROM job_listings
     WHERE import_source IS NOT NULL
       AND created_at < datetime('now', '-14 days')"
);

$remaining = (int)$pdo->query(
    "SELECT COUNT(*) FROM job_listings WHERE import_source IS NOT NULL"
)->fetchColumn();

echo date('Y-m-d H:i:s')
    . " | cleanup_jobs | eliminados={$deleted} | automáticos restantes={$remaining}\n";
