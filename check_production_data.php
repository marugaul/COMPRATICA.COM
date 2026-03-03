<?php
// DIAGNÓSTICO DE PRODUCCIÓN - Vanessa Castro
// Ejecuta este archivo desde https://compratica.com/check_production_data.php

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== DIAGNÓSTICO DE PRODUCCIÓN ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Servidor: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n\n";

// 1. Buscar afiliado Vanessa
echo "1. AFILIADO VANESSA CASTRO:\n";
$stmt = $pdo->prepare("SELECT id, name, email FROM affiliates WHERE email = 'vanecastro@gmail.com'");
$stmt->execute();
$vanessa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($vanessa) {
    echo "   ID: {$vanessa['id']}\n";
    echo "   Nombre: {$vanessa['name']}\n";
    echo "   Email: {$vanessa['email']}\n\n";

    $vanessaId = $vanessa['id'];

    // 2. Espacios de Vanessa
    echo "2. ESPACIOS DE VANESSA (affiliate_id = $vanessaId):\n";
    $stmt = $pdo->prepare("SELECT id, title, is_active, start_at, end_at FROM sales WHERE affiliate_id = ? ORDER BY id DESC");
    $stmt->execute([$vanessaId]);
    $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($spaces) > 0) {
        foreach ($spaces as $s) {
            echo "   Espacio ID: {$s['id']} - {$s['title']}\n";
            echo "   Activo: " . ($s['is_active'] ? 'SÍ' : 'NO') . "\n";
            echo "   Fechas: {$s['start_at']} a {$s['end_at']}\n";

            // Productos del espacio
            $stmt2 = $pdo->prepare("SELECT id, name, affiliate_id, active FROM products WHERE sale_id = ?");
            $stmt2->execute([$s['id']]);
            $prods = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            echo "   Productos: " . count($prods) . "\n";
            foreach ($prods as $p) {
                echo "     - [{$p['id']}] {$p['name']} (affiliate_id: {$p['affiliate_id']}, activo: " . ($p['active'] ? 'SÍ' : 'NO') . ")\n";
            }
            echo "\n";
        }
    } else {
        echo "   ⚠️ NO HAY ESPACIOS para este afiliado\n\n";
    }

    // 3. Productos directos de Vanessa
    echo "3. PRODUCTOS DIRECTOS (affiliate_id = $vanessaId en tabla products):\n";
    $stmt = $pdo->prepare("SELECT id, sale_id, name, active FROM products WHERE affiliate_id = ?");
    $stmt->execute([$vanessaId]);
    $directProds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($directProds) > 0) {
        foreach ($directProds as $p) {
            echo "   - [{$p['id']}] {$p['name']} (sale_id: {$p['sale_id']}, activo: " . ($p['active'] ? 'SÍ' : 'NO') . ")\n";
        }
    } else {
        echo "   ⚠️ NO HAY PRODUCTOS con affiliate_id = $vanessaId\n";
    }
    echo "\n";

} else {
    echo "   ✗ AFILIADO NO ENCONTRADO\n\n";
}

// 4. Buscar espacio por título
echo "4. BUSCAR ESPACIO 'Venta de garage ropa mujer':\n";
$stmt = $pdo->prepare("SELECT s.id, s.affiliate_id, s.title, s.is_active, a.name, a.email
                       FROM sales s
                       LEFT JOIN affiliates a ON a.id = s.affiliate_id
                       WHERE s.title LIKE '%garage%' AND s.title LIKE '%ropa%' AND s.title LIKE '%mujer%'");
$stmt->execute();
$garageSpace = $stmt->fetch(PDO::FETCH_ASSOC);

if ($garageSpace) {
    echo "   ✓ ENCONTRADO\n";
    echo "   ID: {$garageSpace['id']}\n";
    echo "   Título: {$garageSpace['title']}\n";
    echo "   Afiliado ID: {$garageSpace['affiliate_id']}\n";
    echo "   Afiliado: {$garageSpace['name']} ({$garageSpace['email']})\n";
    echo "   Activo: " . ($garageSpace['is_active'] ? 'SÍ' : 'NO') . "\n\n";

    // Productos de este espacio
    $stmt2 = $pdo->prepare("SELECT id, affiliate_id, name, active, stock FROM products WHERE sale_id = ?");
    $stmt2->execute([$garageSpace['id']]);
    $prods = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "   Productos: " . count($prods) . "\n";
    foreach ($prods as $p) {
        echo "     - [{$p['id']}] {$p['name']}\n";
        echo "       affiliate_id del producto: {$p['affiliate_id']}\n";
        echo "       Activo: " . ($p['active'] ? 'SÍ' : 'NO') . ", Stock: {$p['stock']}\n";
    }
    echo "\n";

    // AQUÍ ESTÁ EL PROBLEMA PROBABLE
    if (isset($vanessa) && $garageSpace['affiliate_id'] != $vanessa['id']) {
        echo "   ⚠️ ⚠️ ⚠️ PROBLEMA DETECTADO ⚠️ ⚠️ ⚠️\n";
        echo "   El espacio tiene affiliate_id = {$garageSpace['affiliate_id']}\n";
        echo "   Pero Vanessa Castro tiene ID = {$vanessa['id']}\n";
        echo "   → El espacio NO está asociado al afiliado correcto\n\n";
    }

    // Verificar affiliate_id de los productos
    foreach ($prods as $p) {
        if (isset($vanessa) && $p['affiliate_id'] != $vanessa['id']) {
            echo "   ⚠️ Producto [{$p['id']}] tiene affiliate_id = {$p['affiliate_id']} (debería ser {$vanessa['id']})\n";
        }
    }

} else {
    echo "   ✗ NO ENCONTRADO\n\n";
}

// 5. Verificar qué muestra la consulta de affiliate/products.php
echo "\n5. SIMULACIÓN DE CONSULTA EN affiliate/products.php:\n";
if (isset($vanessaId)) {
    // Consulta de espacios activos (línea 13 de affiliate/products.php)
    echo "   a) Espacios activos para dropdown (WHERE affiliate_id=$vanessaId AND is_active=1):\n";
    $stmt = $pdo->prepare("SELECT id, title FROM sales WHERE affiliate_id=? AND is_active=1 ORDER BY datetime(start_at) DESC");
    $stmt->execute([$vanessaId]);
    $activeSpaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($activeSpaces) > 0) {
        foreach ($activeSpaces as $s) {
            echo "      - [{$s['id']}] {$s['title']}\n";
        }
    } else {
        echo "      ⚠️ NINGÚN ESPACIO ACTIVO (por eso no puede crear/editar productos)\n";
    }
    echo "\n";

    // Consulta de productos (línea 122 de affiliate/products.php)
    echo "   b) Productos para tabla (WHERE affiliate_id=$vanessaId):\n";
    $stmt = $pdo->prepare("SELECT id, sale_id, name FROM products WHERE affiliate_id=? ORDER BY id DESC LIMIT 200");
    $stmt->execute([$vanessaId]);
    $allProds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($allProds) > 0) {
        foreach ($allProds as $p) {
            echo "      - [{$p['id']}] {$p['name']} (sale_id: {$p['sale_id']})\n";
        }
    } else {
        echo "      ⚠️ NINGÚN PRODUCTO (la tabla aparece vacía)\n";
    }
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
