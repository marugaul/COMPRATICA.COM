#!/usr/bin/php
<?php
/**
 * cron-cleanup-carts.php — Limpieza de carritos abandonados
 * ============================================================
 * Carritos invitados (guest_sid, sin user_id): se eliminan tras 30 días sin actividad.
 * Carritos de usuario vacíos (sin items): se eliminan tras 60 días sin actividad.
 * Carritos de usuario CON items: se conservan siempre (el usuario puede retomar).
 *
 * CONFIGURAR EN CRONTAB (cpanel o servidor):
 *   0 3 * * * /usr/bin/php /home/comprati/public_html/cron-cleanup-carts.php >> /home/comprati/public_html/logs/cart-cleanup.log 2>&1
 *
 * USO MANUAL:
 *   php cron-cleanup-carts.php [--dry-run]
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv ?? [], true);

$rootDir = __DIR__;
require_once $rootDir . '/includes/config.php';
require_once $rootDir . '/includes/db.php';

$ts   = date('Y-m-d H:i:s');
$line = function(string $msg) use ($ts) { echo "[$ts] $msg" . PHP_EOL; };

$line("=== INICIO LIMPIEZA CARRITOS " . ($dryRun ? '[DRY-RUN] ' : '') . "===");

try {
    $pdo = db();

    // --- 1. Contar antes ---
    $guestCount = (int)$pdo->query("SELECT COUNT(*) FROM carts WHERE user_id IS NULL AND updated_at < datetime('now', '-30 days')")->fetchColumn();
    $emptyUserCount = (int)$pdo->query("SELECT COUNT(*) FROM carts WHERE user_id IS NOT NULL AND updated_at < datetime('now', '-60 days') AND NOT EXISTS (SELECT 1 FROM cart_items WHERE cart_id = carts.id)")->fetchColumn();
    $orphanItemsCount = (int)$pdo->query("SELECT COUNT(*) FROM cart_items WHERE cart_id NOT IN (SELECT id FROM carts)")->fetchColumn();

    $line("Carritos invitados >30 días: {$guestCount}");
    $line("Carritos de usuario vacíos >60 días: {$emptyUserCount}");
    $line("Items huérfanos: {$orphanItemsCount}");

    if ($dryRun) {
        $line("Modo DRY-RUN: no se eliminó nada.");
    } else {
        // --- 2. Eliminar items de carritos invitados viejos ---
        $pdo->exec("DELETE FROM cart_items WHERE cart_id IN (
            SELECT id FROM carts WHERE user_id IS NULL AND updated_at < datetime('now', '-30 days')
        )");
        $pdo->exec("DELETE FROM carts WHERE user_id IS NULL AND updated_at < datetime('now', '-30 days')");
        $line("Carritos invitados eliminados: {$guestCount}");

        // --- 3. Eliminar carritos de usuario vacíos y viejos ---
        $pdo->exec("DELETE FROM carts WHERE user_id IS NOT NULL AND updated_at < datetime('now', '-60 days')
            AND NOT EXISTS (SELECT 1 FROM cart_items WHERE cart_id = carts.id)");
        $line("Carritos usuario vacíos eliminados: {$emptyUserCount}");

        // --- 4. Eliminar items huérfanos (seguridad) ---
        if ($orphanItemsCount > 0) {
            $pdo->exec("DELETE FROM cart_items WHERE cart_id NOT IN (SELECT id FROM carts)");
            $line("Items huérfanos eliminados: {$orphanItemsCount}");
        }
    }

    $line("=== FIN ===");
} catch (Throwable $e) {
    $line("ERROR: " . $e->getMessage());
    exit(1);
}
