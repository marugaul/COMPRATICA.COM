<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== VERIFICACIÓN AFILIADO vanecastro@gmail.com ===\n\n";

// 1. Buscar en tabla affiliates
echo "1. BÚSQUEDA EN TABLA AFFILIATES:\n";
$stmt = $pdo->prepare("SELECT * FROM affiliates WHERE email = ?");
$stmt->execute(['vanecastro@gmail.com']);
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

if ($affiliate) {
    echo "✅ ENCONTRADO:\n";
    print_r($affiliate);
    $affiliateId = $affiliate['id'];
} else {
    echo "❌ NO ENCONTRADO en tabla affiliates\n";
    $affiliateId = null;
}

// 2. Buscar en tabla users
echo "\n2. BÚSQUEDA EN TABLA USERS:\n";
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['vanecastro@gmail.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ ENCONTRADO:\n";
    print_r($user);
} else {
    echo "❌ NO ENCONTRADO en tabla users\n";
}

// 3. Buscar espacios (sales) del afiliado
if ($affiliateId) {
    echo "\n3. ESPACIOS DEL AFILIADO (ID: $affiliateId):\n";
    $stmt = $pdo->prepare("SELECT id, title, is_active, start_at, end_at FROM sales WHERE affiliate_id = ? ORDER BY id");
    $stmt->execute([$affiliateId]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($sales) > 0) {
        foreach ($sales as $sale) {
            echo "   - Espacio #{$sale['id']}: {$sale['title']} - " .
                 ($sale['is_active'] ? 'ACTIVO' : 'INACTIVO') .
                 " (Inicio: {$sale['start_at']}, Fin: {$sale['end_at']})\n";
        }
    } else {
        echo "   (ningún espacio asignado a este afiliado)\n";
    }
}

// 4. Listar TODOS los afiliados
echo "\n4. LISTA DE TODOS LOS AFILIADOS:\n";
$affiliates = $pdo->query("SELECT id, name, email FROM affiliates ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($affiliates as $aff) {
    echo "   ID {$aff['id']}: {$aff['name']} ({$aff['email']})\n";
}

// 5. Buscar si hay algún espacio que no tenga afiliado asignado
echo "\n5. ESPACIOS SIN AFILIADO O CON AFILIADO INVÁLIDO:\n";
$sales = $pdo->query("
    SELECT s.id, s.title, s.affiliate_id, s.is_active
    FROM sales s
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    WHERE a.id IS NULL
    ORDER BY s.id
")->fetchAll(PDO::FETCH_ASSOC);

if (count($sales) > 0) {
    foreach ($sales as $sale) {
        echo "   ⚠️ Espacio #{$sale['id']}: {$sale['title']} - affiliate_id: {$sale['affiliate_id']} (NO EXISTE EN TABLA AFFILIATES)\n";
    }
} else {
    echo "   ✅ Todos los espacios tienen un afiliado válido\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";
