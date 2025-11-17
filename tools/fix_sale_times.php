<?php
/**
 * Script para actualizar horas en ventas de garaje existentes
 * Ejecutar UNA VEZ desde: https://compratica.com/tools/fix_sale_times.php
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

// Solo permitir ejecución si estás logueado como admin o desde localhost
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
$isAdmin = isset($_SESSION['uid']) && $_SESSION['uid'] > 0; // Ajusta según tu lógica de admin

if (!$isLocal && !$isAdmin) {
    die('Acceso denegado. Solo admin o localhost.');
}

$pdo = db();
$fixed = 0;
$errors = [];

try {
    // Obtener todas las ventas con hora 00:00:00
    $stmt = $pdo->query("
        SELECT id, title, start_at, end_at
        FROM sales
        WHERE start_at LIKE '%00:00:00'
           OR end_at LIKE '%00:00:00'
    ");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>🔧 Actualizando horas de ventas de garaje</h2>\n";
    echo "<p>Ventas encontradas con hora 00:00:00: <strong>" . count($sales) . "</strong></p>\n";
    echo "<hr>\n";

    foreach ($sales as $sale) {
        $saleId = $sale['id'];
        $title = htmlspecialchars($sale['title']);

        // Parsear fechas actuales
        $startOriginal = $sale['start_at'];
        $endOriginal = $sale['end_at'];

        // Extraer solo la fecha (YYYY-MM-DD) y agregar nueva hora
        $startDate = substr($startOriginal, 0, 10);
        $endDate = substr($endOriginal, 0, 10);

        // Nuevas fechas con horas razonables
        $newStart = $startDate . ' 08:00:00'; // 8:00 AM
        $newEnd = $endDate . ' 18:00:00';     // 6:00 PM

        // Actualizar
        $updateStmt = $pdo->prepare("
            UPDATE sales
            SET start_at = ?,
                end_at = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $now = date('Y-m-d H:i:s');
        $result = $updateStmt->execute([$newStart, $newEnd, $now, $saleId]);

        if ($result) {
            $fixed++;
            echo "<div style='background:#e8f5e9; padding:10px; margin:10px 0; border-left:4px solid #4caf50;'>\n";
            echo "<strong>✅ #{$saleId} - {$title}</strong><br>\n";
            echo "Inicio: <code>{$startOriginal}</code> → <code>{$newStart}</code><br>\n";
            echo "Fin: <code>{$endOriginal}</code> → <code>{$newEnd}</code>\n";
            echo "</div>\n";
        } else {
            $errors[] = "Error actualizando venta #{$saleId}";
            echo "<div style='background:#ffebee; padding:10px; margin:10px 0; border-left:4px solid #f44336;'>\n";
            echo "❌ Error en venta #{$saleId} - {$title}\n";
            echo "</div>\n";
        }
    }

    echo "<hr>\n";
    echo "<h3>📊 Resumen</h3>\n";
    echo "<p>✅ Ventas actualizadas: <strong>{$fixed}</strong></p>\n";

    if (!empty($errors)) {
        echo "<p>❌ Errores: <strong>" . count($errors) . "</strong></p>\n";
        echo "<ul>\n";
        foreach ($errors as $err) {
            echo "<li>" . htmlspecialchars($err) . "</li>\n";
        }
        echo "</ul>\n";
    }

    echo "<hr>\n";
    echo "<p><strong>🎯 Nuevos horarios aplicados:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Inicio: <strong>8:00 AM</strong></li>\n";
    echo "<li>Fin: <strong>6:00 PM</strong></li>\n";
    echo "</ul>\n";

    echo "<p style='color:#666; margin-top:20px;'>\n";
    echo "⚠️ Este script puede ejecutarse múltiples veces sin problema. ";
    echo "Solo actualiza ventas que tengan 00:00:00 en sus horas.\n";
    echo "</p>\n";

    echo "<hr>\n";
    echo "<p><a href='../affiliate/sales.php' style='background:#2563eb; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>← Volver a Mis Espacios</a></p>\n";

} catch (Exception $e) {
    echo "<div style='background:#ffebee; padding:20px; border-left:4px solid #f44336;'>\n";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    echo "</div>\n";
}
