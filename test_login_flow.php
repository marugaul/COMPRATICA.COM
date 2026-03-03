<?php
// Simular el flujo completo de login de affiliate/login.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== SIMULACIÓN DE LOGIN - vanecastro@gmail.com ===\n\n";

// Paso 1: Cargar dependencias
echo "1. Cargando dependencias...\n";
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';
echo "   ✓ Dependencias cargadas\n\n";

// Paso 2: Credenciales
$email = 'vanecastro@gmail.com';
$password = 'Compratica2024!';

echo "2. Credenciales a probar:\n";
echo "   Email: $email\n";
echo "   Password: $password\n\n";

// Paso 3: Verificar usuario en DB
echo "3. Verificando usuario en base de datos...\n";
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "   ❌ ERROR: Usuario no encontrado\n";
    exit(1);
}

echo "   ✓ Usuario encontrado (ID: {$user['id']})\n";
echo "   - Nombre: {$user['name']}\n";
echo "   - Email: {$user['email']}\n";
echo "   - is_active: {$user['is_active']}\n\n";

// Paso 4: Verificar contraseña
echo "4. Verificando contraseña...\n";
$passwordMatch = password_verify($password, $user['password_hash']);
echo "   " . ($passwordMatch ? "✓" : "❌") . " password_verify() = " . ($passwordMatch ? "true" : "false") . "\n\n";

// Paso 5: Verificar is_active
echo "5. Verificando is_active...\n";
$isActive = (int)($user['is_active'] ?? 0) === 1;
echo "   is_active valor: {$user['is_active']}\n";
echo "   is_active convertido a int: " . (int)($user['is_active'] ?? 0) . "\n";
echo "   " . ($isActive ? "✓" : "❌") . " Usuario " . ($isActive ? "activo" : "INACTIVO") . "\n\n";

// Paso 6: Llamar a authenticate_user()
echo "6. Llamando a authenticate_user()...\n";
try {
    $authenticatedUser = authenticate_user($email, $password);

    if ($authenticatedUser) {
        echo "   ✓ authenticate_user() exitoso\n";
        echo "   - Usuario ID: {$authenticatedUser['id']}\n";
        echo "   - Nombre: {$authenticatedUser['name']}\n\n";
    } else {
        echo "   ❌ authenticate_user() retornó FALSE\n";
        echo "   Esto significa que la contraseña no coincide.\n\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "   ❌ authenticate_user() lanzó excepción:\n";
    echo "   Mensaje: {$e->getMessage()}\n";
    echo "   Archivo: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}

// Paso 7: Verificar tabla affiliates
echo "7. Verificando tabla affiliates...\n";
try {
    $affStmt = $pdo->prepare("SELECT id, name, email FROM affiliates WHERE email = ? LIMIT 1");
    $affStmt->execute([$email]);
    $affiliate = $affStmt->fetch(PDO::FETCH_ASSOC);

    if ($affiliate) {
        echo "   ✓ Encontrado en tabla affiliates\n";
        echo "   - Affiliate ID: {$affiliate['id']}\n";
        echo "   - Nombre: {$affiliate['name']}\n\n";
    } else {
        echo "   ⚠️  NO encontrado en tabla affiliates\n";
        echo "   Esto puede ser normal si es un usuario nuevo.\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error consultando affiliates: {$e->getMessage()}\n\n";
}

echo "=== RESULTADO FINAL ===\n";
if ($passwordMatch && $isActive) {
    echo "✓✓✓ El login DEBERÍA funcionar correctamente.\n";
    echo "Si aún falla, el problema está en:\n";
    echo "  - Configuración de sesiones\n";
    echo "  - Cookies/dominios\n";
    echo "  - Redireccionamiento\n";
} else {
    echo "❌ El login NO debería funcionar:\n";
    if (!$passwordMatch) echo "  - Contraseña incorrecta\n";
    if (!$isActive) echo "  - Usuario inactivo\n";
}
?>
