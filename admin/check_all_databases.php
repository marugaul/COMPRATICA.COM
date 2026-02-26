<?php
/**
 * Buscar espacio ID 21 en todas las bases de datos disponibles
 */

$databases = [
    '/home/user/COMPRATICA.COM/data.sqlite',
    '/home/user/COMPRATICA.COM/compratica.db',
    '/home/user/COMPRATICA.COM/sitepro/dat/project.db'
];

echo "=== BÚSQUEDA EN TODAS LAS BASES DE DATOS ===\n\n";

foreach ($databases as $dbPath) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📁 Base de datos: $dbPath\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if (!file_exists($dbPath)) {
        echo "⚠️  El archivo NO EXISTE\n\n";
        continue;
    }

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Verificar si existe la tabla sales
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sales'")->fetchAll();

        if (empty($tables)) {
            echo "⚠️  No tiene tabla 'sales'\n\n";
            continue;
        }

        echo "✅ Tiene tabla 'sales'\n\n";

        // Buscar espacio ID 21
        $space21 = $pdo->query("SELECT * FROM sales WHERE id = 21")->fetch(PDO::FETCH_ASSOC);

        if ($space21) {
            echo "🎯 ¡ENCONTRADO! Espacio ID 21:\n";
            foreach ($space21 as $key => $value) {
                echo "   - $key: $value\n";
            }
            echo "\n";

            // Verificar el afiliado
            $affId = $space21['affiliate_id'];
            echo "   🔍 Verificando affiliate_id=$affId:\n";

            // En tabla users
            $userCheck = $pdo->query("SELECT email, name FROM users WHERE id = $affId")->fetch(PDO::FETCH_ASSOC);
            if ($userCheck) {
                echo "      En 'users': {$userCheck['email']} - {$userCheck['name']}\n";
            }

            // En tabla affiliates (si existe)
            $affTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliates'")->fetchAll();
            if (!empty($affTables)) {
                $affCheck = $pdo->query("SELECT email, name FROM affiliates WHERE id = $affId")->fetch(PDO::FETCH_ASSOC);
                if ($affCheck) {
                    echo "      En 'affiliates': {$affCheck['email']} - {$affCheck['name']}\n";
                }
            }

        } else {
            echo "❌ NO se encontró el espacio ID 21\n";

            // Mostrar últimos 10 espacios
            echo "\n   Últimos 10 espacios:\n";
            $lastSpaces = $pdo->query("SELECT id, affiliate_id, title, is_active FROM sales ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lastSpaces as $sp) {
                $status = $sp['is_active'] ? "✅" : "❌";
                echo "   $status ID {$sp['id']}: affiliate_id={$sp['affiliate_id']}, {$sp['title']}\n";
            }
        }

        echo "\n";

    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💡 La base de datos que usa el sistema está definida en:\n";
echo "   /home/user/COMPRATICA.COM/includes/db.php (línea 7)\n";
