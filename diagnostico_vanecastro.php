<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "===============================================\n";
echo "  DIAGNÓSTICO COMPLETO: vanecastro@gmail.com  \n";
echo "===============================================\n\n";

// 1. Información del afiliado
echo "1. INFORMACIÓN DEL AFILIADO:\n";
echo str_repeat("-", 80) . "\n";
$aff = $pdo->query("SELECT * FROM affiliates WHERE email = 'vanecastro@gmail.com'")->fetch(PDO::FETCH_ASSOC);
if ($aff) {
    echo "   ✅ Afiliado ENCONTRADO\n";
    echo "   ID: {$aff['id']}\n";
    echo "   Nombre: {$aff['name']}\n";
    echo "   Email: {$aff['email']}\n";
    echo "   Activo: " . ($aff['is_active'] ? 'Sí' : 'No') . "\n";
    $aff_id = $aff['id'];
} else {
    echo "   ❌ Afiliado NO ENCONTRADO\n";
    exit;
}

// 2. Espacios del afiliado
echo "\n2. ESPACIOS ASIGNADOS AL AFILIADO (ID: $aff_id):\n";
echo str_repeat("-", 80) . "\n";
$spaces = $pdo->prepare("SELECT * FROM sales WHERE affiliate_id = ? ORDER BY id");
$spaces->execute([$aff_id]);
$all_spaces = $spaces->fetchAll(PDO::FETCH_ASSOC);

if (count($all_spaces) > 0) {
    foreach ($all_spaces as $s) {
        echo "   📦 Espacio #{$s['id']}:\n";
        echo "      Título: {$s['title']}\n";
        echo "      Activo: " . ($s['is_active'] ? '✅ Sí' : '❌ No') . "\n";
        echo "      Inicio: {$s['start_at']}\n";
        echo "      Fin: {$s['end_at']}\n";
        echo "      Privado: " . ($s['is_private'] ? 'Sí' : 'No') . "\n";
        if ($s['is_private']) {
            echo "      Código de acceso: {$s['access_code']}\n";
        }
        echo "\n";
    }
} else {
    echo "   ⚠️ NO hay espacios asignados a este afiliado\n\n";
}

// 3. Verificar si hay espacios que deberían estar asignados pero no lo están
echo "3. ESPACIOS HUÉRFANOS (sin afiliado válido):\n";
echo str_repeat("-", 80) . "\n";
$orphans = $pdo->query("
    SELECT s.*
    FROM sales s
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    WHERE a.id IS NULL
    ORDER BY s.id
")->fetchAll(PDO::FETCH_ASSOC);

if (count($orphans) > 0) {
    echo "   ⚠️ Se encontraron " . count($orphans) . " espacios sin afiliado válido:\n";
    foreach ($orphans as $o) {
        echo "      - Espacio #{$o['id']}: {$o['title']} (affiliate_id inválido: {$o['affiliate_id']})\n";
    }
} else {
    echo "   ✅ No hay espacios huérfanos\n";
}

// 4. Verificar la tabla users
echo "\n4. INFORMACIÓN EN TABLA USERS:\n";
echo str_repeat("-", 80) . "\n";
$user = $pdo->query("SELECT * FROM users WHERE email = 'vanecastro@gmail.com'")->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "   ✅ Usuario ENCONTRADO\n";
    echo "   ID: {$user['id']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Nombre: {$user['name']}\n";
} else {
    echo "   ❌ Usuario NO ENCONTRADO en tabla users\n";
}

// 5. Todos los espacios en la BD
echo "\n5. RESUMEN GENERAL DE ESPACIOS:\n";
echo str_repeat("-", 80) . "\n";
$all = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$active = $pdo->query("SELECT COUNT(*) FROM sales WHERE is_active = 1")->fetchColumn();
$max_id = $pdo->query("SELECT MAX(id) FROM sales")->fetchColumn();

echo "   Total de espacios en la BD: $all\n";
echo "   Espacios activos: $active\n";
echo "   ID más alto: $max_id\n";
echo "   Espacios asignados a vanecastro@gmail.com: " . count($all_spaces) . "\n";

// 6. Conclusión
echo "\n";
echo str_repeat("=", 80) . "\n";
echo "CONCLUSIÓN:\n";
echo str_repeat("=", 80) . "\n";

if (count($all_spaces) > 0) {
    echo "✅ El afiliado vanecastro@gmail.com tiene " . count($all_spaces) . " espacio(s) asignado(s):\n";
    foreach ($all_spaces as $s) {
        echo "   - Espacio #{$s['id']}: {$s['title']} - " . ($s['is_active'] ? 'ACTIVO' : 'INACTIVO') . "\n";
    }
    echo "\n🔍 Si estás buscando un espacio diferente (como #20 o #21), estos NO EXISTEN en la BD.\n";
    echo "   El ID más alto en la base de datos es: $max_id\n";
} else {
    echo "⚠️ El afiliado vanecastro@gmail.com NO tiene espacios asignados.\n";
    echo "   Si esperas ver espacios para este afiliado, necesitas asignarlos manualmente.\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
