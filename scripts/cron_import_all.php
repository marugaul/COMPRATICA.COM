#!/usr/bin/env php
<?php
/**
 * scripts/cron_import_all.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Script maestro de importación de empleos para CompraTica
 *
 * Ejecuta todas las fuentes que funcionan:
 *   ✓ APIs Remotas (Arbeitnow, Remotive, Jobicy)
 *   ✓ Telegram (STEMJobsCR, STEMJobsLATAM)
 *   ✗ BAC (deshabilitado - no funciona)
 *
 * Uso:
 *   php scripts/cron_import_all.php
 *
 * Cron (2 veces al día: 8am y 6pm):
 *   0 8,18 * * *  cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
 */

define('IS_CLI', PHP_SAPI === 'cli');

if (IS_CLI) {
    chdir(dirname(__DIR__));
}

// Configuración
$logFile = __DIR__ . '/../logs/cron_import_all.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function log_msg(string $msg, bool $echo = true): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    if ($echo) echo $line;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function run_script(string $scriptPath, string $name): array {
    log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    log_msg("▶ Ejecutando: {$name}");
    log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

    $startTime = microtime(true);
    $output = [];
    $returnCode = 0;

    // Ejecutar script y capturar salida
    exec("php {$scriptPath} 2>&1", $output, $returnCode);

    $duration = round(microtime(true) - $startTime, 2);

    // Guardar output en el log
    foreach ($output as $line) {
        log_msg("  " . $line, false);
    }

    // Buscar resumen en la última línea del output
    $summary = '';
    foreach (array_reverse($output) as $line) {
        // Buscar líneas que contengan números de empleos importados
        if (preg_match('/\+(\d+)\s+nuevos/i', $line, $matches)) {
            $summary = $line;
            break;
        }
        if (preg_match('/insertados?:\s*(\d+)/i', $line, $matches)) {
            $summary = $line;
            break;
        }
    }

    if ($returnCode === 0) {
        log_msg("✅ {$name} completado en {$duration}s");
        if ($summary) {
            log_msg("   📊 {$summary}");
        }
    } else {
        log_msg("❌ {$name} falló (código: {$returnCode}) en {$duration}s");
    }

    log_msg("");

    return [
        'success' => $returnCode === 0,
        'duration' => $duration,
        'summary' => $summary,
        'output' => $output
    ];
}

// ============================================================================
// INICIO DE IMPORTACIÓN
// ============================================================================

log_msg("");
log_msg("╔════════════════════════════════════════════════════════════╗");
log_msg("║        COMPRATICA - IMPORTACIÓN AUTOMÁTICA DE EMPLEOS     ║");
log_msg("╚════════════════════════════════════════════════════════════╝");
log_msg("");

$totalStartTime = microtime(true);
$results = [];

// ── Script 1: APIs Remotas (Arbeitnow, Remotive, Jobicy) ───────────────────
$results['remote_apis'] = run_script(
    __DIR__ . '/import_jobs.php',
    'APIs Remotas (Arbeitnow, Remotive, Jobicy)'
);

// ── Script 2: Telegram (STEMJobsCR, STEMJobsLATAM) ─────────────────────────
$telegramConfigExists = file_exists(__DIR__ . '/../includes/telegram_config.php');

if ($telegramConfigExists) {
    $results['telegram'] = run_script(
        __DIR__ . '/import_telegram_jobs.php',
        'Telegram (STEMJobsCR, STEMJobsLATAM)'
    );
} else {
    log_msg("⚠️  Telegram: OMITIDO (no configurado)");
    log_msg("   Crea includes/telegram_config.php para habilitar");
    log_msg("");
    $results['telegram'] = [
        'success' => false,
        'duration' => 0,
        'summary' => 'No configurado',
        'output' => []
    ];
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================

$totalDuration = round(microtime(true) - $totalStartTime, 2);
$successCount = count(array_filter($results, fn($r) => $r['success']));
$totalScripts = count($results);

log_msg("╔════════════════════════════════════════════════════════════╗");
log_msg("║                    RESUMEN DE IMPORTACIÓN                  ║");
log_msg("╚════════════════════════════════════════════════════════════╝");
log_msg("");
log_msg("⏱️  Duración total: {$totalDuration}s");
log_msg("📊 Scripts ejecutados: {$successCount}/{$totalScripts} exitosos");
log_msg("");

foreach ($results as $key => $result) {
    $icon = $result['success'] ? '✅' : '❌';
    $name = ucfirst(str_replace('_', ' ', $key));
    $summary = $result['summary'] ?: ($result['success'] ? 'OK' : 'Error');
    log_msg("{$icon} {$name}: {$summary}");
}

log_msg("");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("🏁 Importación finalizada");
log_msg("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_msg("");

// Código de salida: 0 si todo fue bien, 1 si hubo algún error
exit($successCount === $totalScripts ? 0 : 1);
