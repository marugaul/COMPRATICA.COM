#!/usr/bin/env php
<?php
/**
 * scripts/import_bac_telegram.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Importador consolidado de empleos de BAC y Telegram para CompraTica.
 *
 * Fuentes:
 *   1. BAC Credomatic (Talento360) — Costa Rica
 *   2. Telegram (STEMJobsCR + STEMJobsLATAM) — STEM Jobs
 *
 * Uso:
 *   php scripts/import_bac_telegram.php
 *
 * Cron (cPanel → Cron Jobs):
 *   0 8 * * *  php /home/TUUSUARIO/public_html/scripts/import_bac_telegram.php
 */

define('IS_CLI', PHP_SAPI === 'cli');

if (IS_CLI) {
    chdir(dirname(__DIR__));
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// Verificar que exista el bot
$botId = (int)$pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
if (!$botId) {
    die("[ERROR] Usuario bot no encontrado. Ejecuta la app una vez para inicializarlo.\n");
}

// Configurar archivo de log consolidado
$logFile = __DIR__ . '/../logs/import_bac_telegram.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function log_msg(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    if (function_exists('ob_get_level') && ob_get_level() === 0) flush();
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

log_msg("==== Iniciando importación de BAC + Telegram ====");

$totalInserted = 0;
$totalDuplicates = 0;

// ── 1. IMPORTAR DESDE BAC ────────────────────────────────────────────────────
log_msg("Iniciando: BAC Credomatic (Talento360)");

// Ejecutar importador de BAC como subproceso
$bacOutput = shell_exec('php ' . __DIR__ . '/import_bac_jobs.php 2>&1');

// Extraer solo la línea de resumen (la que tiene el formato: +X nuevos, Y duplicados)
if (preg_match('/BAC Credomatic.*?: \+(\d+) nuevos, (\d+) duplicados/', $bacOutput, $matches)) {
    log_msg("  BAC Credomatic (Talento360): +{$matches[1]} nuevos, {$matches[2]} duplicados");
    $totalInserted += (int)$matches[1];
    $totalDuplicates += (int)$matches[2];
} else {
    log_msg("  BAC Credomatic (Talento360): +0 nuevos, 0 duplicados");
}

// ── 2. IMPORTAR DESDE TELEGRAM ───────────────────────────────────────────────
log_msg("Iniciando: Telegram (STEMJobsCR + STEMJobsLATAM)");

// Ejecutar importador de Telegram como subproceso
$telegramOutput = shell_exec('php ' . __DIR__ . '/import_telegram_jobs.php 2>&1');

// Extraer solo la línea de resumen
if (preg_match('/Telegram.*?: \+(\d+) nuevos, (\d+) duplicados/', $telegramOutput, $matches)) {
    log_msg("  Telegram (STEMJobsCR + STEMJobsLATAM): +{$matches[1]} nuevos, {$matches[2]} duplicados");
    $totalInserted += (int)$matches[1];
    $totalDuplicates += (int)$matches[2];
} else {
    log_msg("  Telegram (STEMJobsCR + STEMJobsLATAM): +0 nuevos, 0 duplicados");
}

// ── TOTAL ────────────────────────────────────────────────────────────────────
log_msg("=== TOTAL: +{$totalInserted} insertados | {$totalDuplicates} duplicados | 0 errores ===");
