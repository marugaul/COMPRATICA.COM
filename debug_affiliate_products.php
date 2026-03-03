<?php
// Script de diagnóstico para verificar productos del espacio 18 y afiliado 7
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== DIAGNÓSTICO DE PRODUCTOS DEL ESPACIO 18 ===\n\n";

// 1. Verificar espacio
echo "1. INFORMACIÓN DEL ESPACIO:\n";
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = 18");
$stmt->execute();
$space = $stmt->fetch(PDO::FETCH_ASSOC);
if ($space) {
    echo "ID: {$space['id']}\n";
    echo "Título: {$space['title']}\n";
    echo "Afiliado ID: {$space['affiliate_id']}\n";
    echo "Activo: " . ($space['is_active'] ? 'SÍ' : 'NO') . "\n";
    echo "Inicio: {$space['start_at']}\n";
    echo "Fin: {$space['end_at']}\n\n";
} else {
    echo "ERROR: Espacio no encontrado\n\n";
    exit;
}

// 2. Verificar afiliado
echo "2. INFORMACIÓN DEL AFILIADO:\n";
$stmt = $pdo->prepare("SELECT id, name, email FROM affiliates WHERE id = ?");
$stmt->execute([$space['affiliate_id']]);
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
if ($affiliate) {
    echo "ID: {$affiliate['id']}\n";
    echo "Nombre: {$affiliate['name']}\n";
    echo "Email: {$affiliate['email']}\n\n";
} else {
    echo "ERROR: Afiliado no encontrado\n\n";
}

// 3. Productos del espacio
echo "3. PRODUCTOS DEL ESPACIO 18:\n";
$stmt = $pdo->prepare("SELECT id, affiliate_id, sale_id, name, active, stock, price, currency FROM products WHERE sale_id = 18");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total productos encontrados: " . count($products) . "\n\n";
foreach ($products as $p) {
    echo "  - ID: {$p['id']}\n";
    echo "    Nombre: {$p['name']}\n";
    echo "    Afiliado ID: {$p['affiliate_id']}\n";
    echo "    Espacio ID: {$p['sale_id']}\n";
    echo "    Activo: " . ($p['active'] ? 'SÍ' : 'NO') . "\n";
    echo "    Stock: {$p['stock']}\n";
    echo "    Precio: {$p['currency']} {$p['price']}\n";
    echo "\n";
}

// 4. Verificar consulta que usa affiliate/products.php
echo "4. PRODUCTOS SEGÚN LA CONSULTA DE affiliate/products.php:\n";
$stmt = $pdo->prepare("
    SELECT id, name, sale_id, price, stock, currency, active
    FROM products
    WHERE affiliate_id=?
    ORDER BY id DESC
    LIMIT 200
");
$stmt->execute([$space['affiliate_id']]);
$affiliateProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total productos del afiliado " . $space['affiliate_id'] . ": " . count($affiliateProducts) . "\n\n";

// Filtrar los del espacio 18
$productsInSpace18 = array_filter($affiliateProducts, function($p) {
    return $p['sale_id'] == 18;
});
echo "Productos del espacio 18 en esta consulta: " . count($productsInSpace18) . "\n";
foreach ($productsInSpace18 as $p) {
    echo "  - ID: {$p['id']} - {$p['name']} (Activo: " . ($p['active'] ? 'SÍ' : 'NO') . ")\n";
}
echo "\n";

// 5. Verificar espacios activos del afiliado
echo "5. ESPACIOS ACTIVOS DEL AFILIADO (consulta de affiliate/products.php línea 13):\n";
$stmt = $pdo->prepare("SELECT id, title FROM sales WHERE affiliate_id=? AND is_active=1 ORDER BY datetime(start_at) DESC");
$stmt->execute([$space['affiliate_id']]);
$activeSpaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total espacios activos: " . count($activeSpaces) . "\n";
foreach ($activeSpaces as $s) {
    echo "  - ID: {$s['id']} - {$s['title']}\n";
}
