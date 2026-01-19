<?php
// Script de diagnóstico para shipping_options.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Diagnóstico de shipping_options.php</h1>";

// 1. Verificar archivos requeridos
echo "<h2>1. Verificando archivos requeridos:</h2>";
$files = [
    '../includes/config.php' => __DIR__ . '/includes/config.php',
    '../includes/db.php' => __DIR__ . '/includes/db.php',
    '../includes/affiliate_auth.php' => __DIR__ . '/includes/affiliate_auth.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name existe<br>";
    } else {
        echo "❌ $name NO existe: $path<br>";
    }
}

// 2. Intentar cargar config y db
echo "<h2>2. Cargando configuración y base de datos:</h2>";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "✅ config.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error en config.php: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/includes/db.php';
    echo "✅ db.php cargado<br>";
    $pdo = db();
    echo "✅ Conexión a base de datos OK<br>";
} catch (Exception $e) {
    echo "❌ Error en db.php: " . $e->getMessage() . "<br>";
}

// 3. Verificar tabla affiliate_shipping_options
echo "<h2>3. Verificando tabla affiliate_shipping_options:</h2>";
try {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'");
    $exists = $stmt->fetchColumn();

    if ($exists) {
        echo "✅ Tabla affiliate_shipping_options existe<br>";

        // Verificar estructura
        $stmt = $pdo->query("PRAGMA table_info(affiliate_shipping_options)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } else {
        echo "❌ Tabla affiliate_shipping_options NO existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando tabla: " . $e->getMessage() . "<br>";
}

// 4. Verificar sesión
echo "<h2>4. Verificando sesión:</h2>";
session_start();
if (isset($_SESSION['aff_id'])) {
    echo "✅ Sesión activa - Afiliado ID: " . $_SESSION['aff_id'] . "<br>";
} else {
    echo "⚠️ No hay sesión de afiliado activa (normal si no has iniciado sesión)<br>";
}

echo "<h2>✅ Diagnóstico completo</h2>";
echo "<p>Si todo está OK, el problema puede ser de sesión. Asegúrate de estar logueado como afiliado.</p>";
