<?php
$config = require __DIR__ . '/config/database.php';

// Crear conexión PDO
$dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

echo "=== VERIFICACIÓN ESPACIO #21 Y AFILIADO ===\n\n";

// 1. Verificar información del espacio #21
echo "1. INFORMACIÓN DEL ESPACIO #21:\n";
$stmt = $pdo->query("SELECT * FROM spaces WHERE id = 21");
$space = $stmt->fetch(PDO::FETCH_ASSOC);

if ($space) {
    echo "   ID: " . $space['id'] . "\n";
    echo "   Nombre: " . $space['name'] . "\n";
    echo "   User ID: " . ($space['user_id'] ?? 'NULL') . "\n";
    echo "   Affiliate Email: " . ($space['affiliate_email'] ?? 'NULL') . "\n";
    echo "   Activo: " . ($space['is_active'] ? 'Sí' : 'No') . "\n";
    echo "   Creado: " . $space['created_at'] . "\n";
} else {
    echo "   ⚠️ NO SE ENCONTRÓ EL ESPACIO #21\n";
}

echo "\n2. INFORMACIÓN DEL AFILIADO vanecastro@gmail.com:\n";
$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
$stmt->execute(['vanecastro@gmail.com']);
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

if ($affiliate) {
    echo "   ID: " . $affiliate['id'] . "\n";
    echo "   Email: " . $affiliate['email'] . "\n";
    echo "   Role: " . $affiliate['role'] . "\n";
} else {
    echo "   ⚠️ NO SE ENCONTRÓ EL USUARIO vanecastro@gmail.com\n";
}

echo "\n3. VERIFICAR RELACIÓN:\n";
if ($space && $affiliate) {
    // Verificar si el user_id coincide
    if ($space['user_id'] == $affiliate['id']) {
        echo "   ✓ El espacio #21 está asignado al user_id correcto: " . $affiliate['id'] . "\n";
    } else {
        echo "   ✗ PROBLEMA: El espacio #21 tiene user_id: " . ($space['user_id'] ?? 'NULL') .
             " pero debería ser: " . $affiliate['id'] . "\n";
    }

    // Verificar si el affiliate_email coincide
    if ($space['affiliate_email'] == $affiliate['email']) {
        echo "   ✓ El espacio #21 tiene el affiliate_email correcto: " . $affiliate['email'] . "\n";
    } else {
        echo "   ✗ PROBLEMA: El espacio #21 tiene affiliate_email: " . ($space['affiliate_email'] ?? 'NULL') .
             " pero debería ser: " . $affiliate['email'] . "\n";
    }
}

echo "\n4. TODOS LOS ESPACIOS DEL AFILIADO vanecastro@gmail.com:\n";
if ($affiliate) {
    // Por user_id
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM spaces WHERE user_id = ? ORDER BY id");
    $stmt->execute([$affiliate['id']]);
    $spaces_by_user_id = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "   Por user_id (" . $affiliate['id'] . "):\n";
    if (count($spaces_by_user_id) > 0) {
        foreach ($spaces_by_user_id as $s) {
            echo "      - Espacio #{$s['id']}: {$s['name']} - " . ($s['is_active'] ? 'Activo' : 'Inactivo') . "\n";
        }
    } else {
        echo "      (ninguno)\n";
    }

    // Por affiliate_email
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM spaces WHERE affiliate_email = ? ORDER BY id");
    $stmt->execute([$affiliate['email']]);
    $spaces_by_email = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "   Por affiliate_email ({$affiliate['email']}):\n";
    if (count($spaces_by_email) > 0) {
        foreach ($spaces_by_email as $s) {
            echo "      - Espacio #{$s['id']}: {$s['name']} - " . ($s['is_active'] ? 'Activo' : 'Inactivo') . "\n";
        }
    } else {
        echo "      (ninguno)\n";
    }
}

echo "\n5. ESTRUCTURA DE LA TABLA SPACES:\n";
$stmt = $pdo->query("DESCRIBE spaces");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   - {$col['Field']}: {$col['Type']} " .
         ($col['Null'] == 'YES' ? '(NULL)' : '(NOT NULL)') .
         ($col['Key'] ? " [{$col['Key']}]" : "") . "\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";
