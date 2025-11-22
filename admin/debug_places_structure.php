<?php
/**
 * Debug: Ver estructura de tabla places_cr
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('No autorizado');
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== ESTRUCTURA DE TABLA places_cr ===\n\n";

// Obtener columnas
$stmt = $pdo->query("DESCRIBE places_cr");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "COLUMNAS DISPONIBLES:\n";
foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== EJEMPLO DE REGISTRO ===\n\n";
$stmt = $pdo->query("SELECT * FROM places_cr LIMIT 1");
$example = $stmt->fetch(PDO::FETCH_ASSOC);

if ($example) {
    foreach ($example as $key => $value) {
        $display = is_string($value) ? substr($value, 0, 100) : $value;
        echo "$key: $display\n";
    }
} else {
    echo "No hay registros en la tabla\n";
}
