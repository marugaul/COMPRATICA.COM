<?php
/**
 * TEST: Verificar funcionamiento de get_places_by_categories.php
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Places API</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:1200px;margin:0 auto;}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}";
echo ".ok{color:green;} .error{color:red;}</style></head><body>";

echo "<h1>🔍 Test de API de Lugares</h1>";
echo "<p><strong>Hora:</strong> " . date('Y-m-d H:i:s') . "</p><hr>";

// Test 1: Verificar que el archivo existe
echo "<h3>1. Verificar archivo existe</h3>";
$file = __DIR__ . '/get_places_by_categories.php';
if (file_exists($file)) {
    echo "<p class='ok'>✓ Archivo existe: $file</p>";
} else {
    echo "<p class='error'>✗ Archivo NO existe: $file</p>";
    echo "<p>Archivos en /admin/:</p><pre>";
    print_r(glob(__DIR__ . '/get_*.php'));
    echo "</pre>";
}

// Test 2: Simular petición POST
echo "<h3>2. Simular petición con categorías</h3>";

session_start();
$_SESSION['is_admin'] = true;
$_SESSION['admin_user'] = 'test';

$_POST['categories'] = ['hotel', 'restaurant'];

echo "<p>Simulando POST con categorías: hotel, restaurant</p>";

// Capturar output
ob_start();
include __DIR__ . '/get_places_by_categories.php';
$output = ob_get_clean();

echo "<h4>Respuesta del API:</h4>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Intentar decodificar JSON
$data = json_decode($output, true);
if ($data) {
    echo "<h4>JSON Decodificado:</h4>";
    echo "<p class='ok'>✓ JSON válido</p>";
    echo "<p>Success: " . ($data['success'] ? 'true' : 'false') . "</p>";
    echo "<p>Count: " . ($data['count'] ?? 0) . "</p>";
    echo "<p>Places encontrados: " . count($data['places'] ?? []) . "</p>";

    if (!empty($data['places'])) {
        echo "<h4>Primeros 3 lugares:</h4>";
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        echo "<tr><th>Nombre</th><th>Email</th><th>Owner</th><th>Phone</th><th>City</th></tr>";
        foreach (array_slice($data['places'], 0, 3) as $place) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($place['name']) . "</td>";
            echo "<td>" . htmlspecialchars($place['email']) . "</td>";
            echo "<td>" . htmlspecialchars($place['owner']) . "</td>";
            echo "<td>" . htmlspecialchars($place['phone']) . "</td>";
            echo "<td>" . htmlspecialchars($place['city']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='error'>✗ JSON inválido o vacío</p>";
    echo "<p>Error JSON: " . json_last_error_msg() . "</p>";
}

echo "</body></html>";
