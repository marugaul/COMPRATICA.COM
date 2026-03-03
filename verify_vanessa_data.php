<?php
// Verificación completa de datos de Vanessa Castro
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== DIAGNÓSTICO COMPLETO - VANESSA CASTRO ===\n\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Base de datos: " . __DIR__ . '/data.sqlite' . "\n\n";

// 1. Buscar afiliado por email
echo "1. BUSCAR AFILIADO 'vanecastro@gmail.com':\n";
$stmt = $pdo->prepare("SELECT * FROM affiliates WHERE email = 'vanecastro@gmail.com'");
$stmt->execute();
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

if ($affiliate) {
    echo "   ✓ Afiliado encontrado\n";
    echo "   ID: {$affiliate['id']}\n";
    echo "   Nombre: {$affiliate['name']}\n";
    echo "   Email: {$affiliate['email']}\n\n";

    $affId = $affiliate['id'];

    // 2. Buscar espacios del afiliado
    echo "2. ESPACIOS DEL AFILIADO (ID: $affId):\n";
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE affiliate_id = ? ORDER BY id DESC");
    $stmt->execute([$affId]);
    $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($spaces) > 0) {
        foreach ($spaces as $space) {
            echo "   Espacio ID: {$space['id']}\n";
            echo "   Título: {$space['title']}\n";
            echo "   Activo: " . ($space['is_active'] ? 'SÍ' : 'NO') . "\n";
            echo "   Inicio: {$space['start_at']}\n";
            echo "   Fin: {$space['end_at']}\n";

            // Contar productos
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sale_id = ?");
            $stmt2->execute([$space['id']]);
            $productCount = $stmt2->fetchColumn();
            echo "   Productos: $productCount\n\n";
        }
    } else {
        echo "   ✗ NO SE ENCONTRARON ESPACIOS para este afiliado\n\n";
    }

    // 3. Productos del afiliado
    echo "3. PRODUCTOS DEL AFILIADO (directamente por affiliate_id):\n";
    $stmt = $pdo->prepare("SELECT id, sale_id, name, active, stock FROM products WHERE affiliate_id = ?");
    $stmt->execute([$affId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) > 0) {
        foreach ($products as $p) {
            echo "   Producto ID: {$p['id']}\n";
            echo "   Nombre: {$p['name']}\n";
            echo "   Espacio ID: {$p['sale_id']}\n";
            echo "   Activo: " . ($p['active'] ? 'SÍ' : 'NO') . "\n";
            echo "   Stock: {$p['stock']}\n\n";
        }
    } else {
        echo "   ✗ NO SE ENCONTRARON PRODUCTOS para este afiliado\n\n";
    }

} else {
    echo "   ✗ AFILIADO NO ENCONTRADO\n\n";
}

// 4. Buscar el espacio por título
echo "4. BUSCAR ESPACIO POR TÍTULO 'Venta de garage ropa mujer':\n";
$stmt = $pdo->prepare("SELECT s.*, a.name as affiliate_name, a.email
                       FROM sales s
                       LEFT JOIN affiliates a ON a.id = s.affiliate_id
                       WHERE s.title = 'Venta de garage ropa mujer'");
$stmt->execute();
$space = $stmt->fetch(PDO::FETCH_ASSOC);

if ($space) {
    echo "   ✓ Espacio encontrado\n";
    echo "   ID: {$space['id']}\n";
    echo "   Afiliado ID: {$space['affiliate_id']}\n";
    echo "   Afiliado: {$space['affiliate_name']} ({$space['email']})\n";
    echo "   Activo: " . ($space['is_active'] ? 'SÍ' : 'NO') . "\n\n";

    // Productos de este espacio
    $stmt2 = $pdo->prepare("SELECT id, affiliate_id, name FROM products WHERE sale_id = ?");
    $stmt2->execute([$space['id']]);
    $products = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "   Productos: " . count($products) . "\n";
    foreach ($products as $p) {
        echo "     - {$p['name']} (afiliado: {$p['affiliate_id']})\n";
    }
} else {
    echo "   ✗ ESPACIO NO ENCONTRADO con ese título exacto\n\n";
}

// 5. Total de espacios y productos
echo "\n5. RESUMEN GENERAL:\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM sales");
echo "   Total espacios en BD: " . $stmt->fetchColumn() . "\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
echo "   Total productos en BD: " . $stmt->fetchColumn() . "\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM affiliates");
echo "   Total afiliados en BD: " . $stmt->fetchColumn() . "\n";
