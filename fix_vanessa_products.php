<?php
/**
 * FIX: Corregir affiliate_id de productos de Vanessa Castro
 *
 * Problema: Los productos del espacio "Venta de garage ropa mujer" (sale_id=21)
 * tienen affiliate_id=1 pero deberían tener affiliate_id=8 (Vanessa Castro)
 *
 * Ejecuta desde: https://compratica.com/fix_vanessa_products.php
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$pdo = db();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrección de Productos - Vanessa Castro</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; max-width: 900px; margin: 0 auto; }
        h1 { color: #2c3e50; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        table { border-collapse: collapse; width: 100%; background: white; margin: 10px 0; }
        th { background: #34495e; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .btn:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<h1>🔧 Corrección de Productos - Vanessa Castro</h1>

<div class="info">
    <strong>📋 Problema identificado:</strong><br>
    Los productos del espacio "Venta de garage ropa mujer" (sale_id=21) tienen <code>affiliate_id=1</code>
    pero deberían tener <code>affiliate_id=8</code> (Vanessa Castro).<br><br>
    <strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?>
</div>

<?php

// Verificar si se solicitó la corrección
if (isset($_POST['fix'])) {
    echo "<h2>🔄 Ejecutando corrección...</h2>";

    try {
        $pdo->beginTransaction();

        // Actualizar affiliate_id de los productos
        $sql = "UPDATE products
                SET affiliate_id = 8
                WHERE sale_id = 21
                AND affiliate_id != 8";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $affected = $stmt->rowCount();

        $pdo->commit();

        echo "<div class='success'>";
        echo "<strong>✅ Corrección completada exitosamente</strong><br>";
        echo "Se actualizaron <strong>$affected producto(s)</strong><br>";
        echo "Los productos ahora tienen <code>affiliate_id = 8</code> (Vanessa Castro)";
        echo "</div>";

        echo "<p><strong>Siguiente paso:</strong> Pídele a Vanessa que refresque su panel de afiliado (<code>affiliate/products.php</code>) y verifique que ahora vea sus 3 productos.</p>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='error'>";
        echo "<strong>❌ Error al ejecutar corrección:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

// Mostrar estado actual
echo "<h2>📊 Estado Actual</h2>";

echo "<h3>1. Afiliado Vanessa Castro</h3>";
$stmt = $pdo->query("SELECT id, name, email FROM affiliates WHERE email = 'vanecastro@gmail.com'");
$vanessa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($vanessa) {
    echo "<div class='success'>";
    echo "✓ Afiliado encontrado: <strong>{$vanessa['name']}</strong> (ID: {$vanessa['id']})";
    echo "</div>";
} else {
    echo "<div class='error'>✗ Afiliado no encontrado</div>";
    exit;
}

echo "<h3>2. Espacio 'Venta de garage ropa mujer'</h3>";
$stmt = $pdo->query("SELECT id, affiliate_id, title, is_active FROM sales WHERE id = 21");
$space = $stmt->fetch(PDO::FETCH_ASSOC);

if ($space) {
    $spaceOk = ($space['affiliate_id'] == $vanessa['id']);
    echo "<div class='" . ($spaceOk ? 'success' : 'error') . "'>";
    echo ($spaceOk ? '✓' : '✗') . " Espacio ID: {$space['id']}<br>";
    echo "Título: <strong>{$space['title']}</strong><br>";
    echo "affiliate_id: <strong>{$space['affiliate_id']}</strong> ";
    echo $spaceOk ? "(correcto ✓)" : "(incorrecto ✗ - debería ser {$vanessa['id']})";
    echo "<br>Activo: " . ($space['is_active'] ? 'SÍ' : 'NO');
    echo "</div>";
} else {
    echo "<div class='error'>✗ Espacio no encontrado</div>";
    exit;
}

echo "<h3>3. Productos del espacio (sale_id = 21)</h3>";
$stmt = $pdo->query("SELECT id, affiliate_id, name, active, stock FROM products WHERE sale_id = 21 ORDER BY id");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($products) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Nombre</th><th>affiliate_id</th><th>Estado</th><th>Stock</th></tr>";

    $hasError = false;
    foreach ($products as $p) {
        $isCorrect = ($p['affiliate_id'] == $vanessa['id']);
        if (!$isCorrect) $hasError = true;

        $rowClass = $isCorrect ? '' : ' style="background: #f8d7da;"';
        echo "<tr$rowClass>";
        echo "<td>{$p['id']}</td>";
        echo "<td><strong>{$p['name']}</strong></td>";
        echo "<td>{$p['affiliate_id']} " . ($isCorrect ? '✓' : '❌ (debería ser ' . $vanessa['id'] . ')') . "</td>";
        echo "<td>" . ($p['active'] ? 'Activo' : 'Inactivo') . "</td>";
        echo "<td>{$p['stock']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($hasError) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Problema detectado:</strong> Algunos productos tienen <code>affiliate_id</code> incorrecto.<br>";
        echo "Esto hace que NO aparezcan en el panel del afiliado Vanessa Castro.";
        echo "</div>";

        echo "<h2>🔧 Aplicar Corrección</h2>";
        echo "<div class='warning'>";
        echo "<p><strong>Esta corrección hará lo siguiente:</strong></p>";
        echo "<pre>UPDATE products \nSET affiliate_id = {$vanessa['id']} \nWHERE sale_id = 21 \nAND affiliate_id != {$vanessa['id']}</pre>";
        echo "<p>Esto actualizará el <code>affiliate_id</code> de los productos para que coincida con el del espacio.</p>";
        echo "<form method='post'>";
        echo "<button type='submit' name='fix' value='1' class='btn' onclick='return confirm(\"¿Confirmas que deseas aplicar esta corrección?\")'>✅ Aplicar Corrección</button>";
        echo "</form>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "✅ Todos los productos tienen el <code>affiliate_id</code> correcto. No se requiere corrección.";
        echo "</div>";
    }
} else {
    echo "<div class='error'>✗ No se encontraron productos para este espacio</div>";
}

?>

<h2>📚 Información Adicional</h2>
<div class="info">
    <strong>¿Por qué pasó esto?</strong><br>
    Probablemente los productos fueron creados desde otra cuenta de afiliado o hubo un error al asignar el <code>affiliate_id</code> durante la creación.<br><br>

    <strong>¿Qué hace la corrección?</strong><br>
    Actualiza el <code>affiliate_id</code> de los productos para que coincida con el <code>affiliate_id</code> del espacio (sale_id=21).<br><br>

    <strong>¿Es seguro?</strong><br>
    Sí. Solo modifica el campo <code>affiliate_id</code> de los productos que pertenecen al espacio 21.
</div>

</body>
</html>
