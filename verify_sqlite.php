<?php
/**
 * Verificación de data.sqlite
 * Verifica que la base de datos esté funcionando correctamente
 */

$dbFile = __DIR__ . '/data.sqlite';

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Verificación SQLite - COMPRATICA.COM</title>\n";
echo "<style>\n";
echo "body { font-family: Arial; padding: 40px; background: #f5f5f5; max-width: 1000px; margin: 0 auto; }\n";
echo ".success { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo ".error { background: #fee; border-left: 4px solid #dc2626; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo ".info { background: #eff6ff; border-left: 4px solid #0891b2; padding: 20px; margin: 15px 0; border-radius: 4px; }\n";
echo "table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }\n";
echo "th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }\n";
echo "th { background: #3b82f6; color: white; }\n";
echo "h1 { color: #16a34a; }\n";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🔍 Verificación de Base de Datos SQLite</h1>\n";

try {
    // Verificar que el archivo existe
    if (!file_exists($dbFile)) {
        throw new Exception("El archivo data.sqlite no existe en: $dbFile");
    }

    echo "<div class='success'>";
    echo "<strong>✓ Archivo encontrado:</strong> " . $dbFile . "<br>";
    echo "<strong>Tamaño:</strong> " . number_format(filesize($dbFile) / 1024, 2) . " KB<br>";
    echo "<strong>Última modificación:</strong> " . date('Y-m-d H:i:s', filemtime($dbFile));
    echo "</div>";

    // Conectar a la base de datos
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'><strong>✓ Conexión a SQLite exitosa</strong></div>";

    // Verificar tablas
    echo "<div class='info'>";
    echo "<h3>📊 Tablas en la base de datos:</h3>";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
    echo "</div>";

    // Verificar productos
    echo "<div class='info'>";
    echo "<h3>🛍️ Productos:</h3>";
    $countProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<p><strong>Total de productos:</strong> $countProducts</p>";

    if ($countProducts > 0) {
        $products = $pdo->query("SELECT id, name, price, currency, stock, active FROM products LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Moneda</th><th>Stock</th><th>Activo</th></tr>";
        foreach ($products as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['name']}</td>";
            echo "<td>" . number_format($p['price'], 2) . "</td>";
            echo "<td>{$p['currency']}</td>";
            echo "<td>{$p['stock']}</td>";
            echo "<td>" . ($p['active'] ? '✓ Sí' : '✗ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Verificar órdenes
    echo "<div class='info'>";
    echo "<h3>📦 Órdenes:</h3>";
    $countOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    echo "<p><strong>Total de órdenes:</strong> $countOrders</p>";

    if ($countOrders > 0) {
        $orders = $pdo->query("SELECT id, product_id, qty, buyer_email, status, created_at FROM orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table>";
        echo "<tr><th>ID</th><th>Producto</th><th>Cantidad</th><th>Email</th><th>Estado</th><th>Fecha</th></tr>";
        foreach ($orders as $o) {
            echo "<tr>";
            echo "<td>{$o['id']}</td>";
            echo "<td>{$o['product_id']}</td>";
            echo "<td>{$o['qty']}</td>";
            echo "<td>{$o['buyer_email']}</td>";
            echo "<td>{$o['status']}</td>";
            echo "<td>{$o['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Verificar configuración
    echo "<div class='info'>";
    echo "<h3>⚙️ Configuración:</h3>";
    $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        echo "<p><strong>Tipo de cambio USD → CRC:</strong> ₡" . number_format($settings['exchange_rate'], 2) . "</p>";
    }
    echo "</div>";

    echo "<div class='success'>";
    echo "<h3>✅ Verificación Completa</h3>";
    echo "<p>La base de datos SQLite está funcionando correctamente y contiene datos válidos.</p>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ul>";
    echo "<li>El dashboard debería mostrar datos correctamente ahora</li>";
    echo "<li>Las páginas de productos y órdenes deberían funcionar</li>";
    echo "<li>Verifica que <a href='/admin/dashboard.php'>Dashboard</a> y <a href='/admin/dashboard_ext.php'>Dashboard Extendido</a> muestren información</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<strong>Línea:</strong> " . $e->getLine();
    echo "</div>";
}

echo "<p style='text-align:center;margin-top:30px;'>";
echo "<a href='/admin/' style='display:inline-block;padding:12px 24px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;'>← Volver al Admin</a> ";
echo "<a href='/admin/dashboard.php' style='display:inline-block;padding:12px 24px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;'>Ver Dashboard</a> ";
echo "<a href='/admin/dashboard_ext.php' style='display:inline-block;padding:12px 24px;background:#16a34a;color:white;text-decoration:none;border-radius:6px;'>Dashboard Extendido</a>";
echo "</p>";

echo "</body></html>";
?>
