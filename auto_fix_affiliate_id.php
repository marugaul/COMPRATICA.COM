<?php
/**
 * AUTO-FIX: Sincronizar affiliate_id de productos con sus espacios
 *
 * Este script corrige automáticamente productos que tienen un affiliate_id
 * diferente al del espacio (sale_id) al que pertenecen.
 *
 * Ejecución:
 * - Manual: https://compratica.com/auto_fix_affiliate_id.php
 * - Cron: php /path/to/auto_fix_affiliate_id.php
 */

require_once __DIR__ . '/includes/db.php';

$pdo = db();
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h1>🔧 Auto-Fix: Sincronización de affiliate_id</h1>";
    echo "<p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>";
}

try {
    // Encontrar productos con affiliate_id incorrecto
    $sql = "
        SELECT p.id, p.name, p.sale_id, p.affiliate_id as current_aff_id,
               s.affiliate_id as correct_aff_id, s.title as space_title
        FROM products p
        JOIN sales s ON s.id = p.sale_id
        WHERE p.affiliate_id != s.affiliate_id
        OR p.affiliate_id IS NULL
    ";

    $stmt = $pdo->query($sql);
    $problematicProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($problematicProducts) > 0) {
        if ($isCli) {
            echo "Encontrados " . count($problematicProducts) . " productos con affiliate_id incorrecto:\n";
        } else {
            echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0;'>";
            echo "<strong>⚠️ Encontrados " . count($problematicProducts) . " productos con affiliate_id incorrecto:</strong>";
            echo "<table border='1' cellpadding='5' style='margin-top: 10px; border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Producto</th><th>Espacio</th><th>Actual</th><th>Correcto</th></tr>";
        }

        foreach ($problematicProducts as $p) {
            if ($isCli) {
                echo sprintf(
                    "  - [%d] %s (espacio: %s) | Actual: %s | Correcto: %d\n",
                    $p['id'],
                    $p['name'],
                    $p['space_title'],
                    $p['current_aff_id'] ?? 'NULL',
                    $p['correct_aff_id']
                );
            } else {
                echo sprintf(
                    "<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td></tr>",
                    $p['id'],
                    htmlspecialchars($p['name']),
                    htmlspecialchars($p['space_title']),
                    $p['current_aff_id'] ?? 'NULL',
                    $p['correct_aff_id']
                );
            }
        }

        if (!$isCli) {
            echo "</table></div>";
        }

        // Aplicar corrección
        $updateSql = "
            UPDATE products
            SET affiliate_id = (SELECT affiliate_id FROM sales WHERE id = products.sale_id)
            WHERE sale_id IS NOT NULL
            AND (affiliate_id IS NULL OR affiliate_id != (SELECT affiliate_id FROM sales WHERE id = products.sale_id))
        ";

        $pdo->beginTransaction();
        $stmt = $pdo->exec($updateSql);
        $pdo->commit();

        if ($isCli) {
            echo "\n✅ Corrección aplicada: $stmt productos actualizados\n";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0;'>";
            echo "<strong>✅ Corrección aplicada:</strong> $stmt productos actualizados correctamente.";
            echo "</div>";
        }

    } else {
        $msg = "✅ No se encontraron productos con affiliate_id incorrecto. Todo está correcto.";
        if ($isCli) {
            echo "$msg\n";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0;'>$msg</div>";
        }
    }

} catch (Exception $e) {
    $error = "❌ Error: " . $e->getMessage();
    if ($isCli) {
        echo "$error\n";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 15px 0;'>$error</div>";
    }
}

if (!$isCli) {
    echo "<hr><p><strong>Configurar en Cron (recomendado):</strong></p>";
    echo "<pre>0 */6 * * * /usr/bin/php " . __DIR__ . "/auto_fix_affiliate_id.php >/dev/null 2>&1</pre>";
    echo "<p>Esto ejecutará el auto-fix cada 6 horas.</p>";
}
