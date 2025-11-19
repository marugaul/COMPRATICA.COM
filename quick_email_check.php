<?php
$config = require __DIR__ . '/config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password']
);

$total = $pdo->query("SELECT COUNT(*) FROM places_cr")->fetchColumn();
$withEmail = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags LIKE '%email%'")->fetchColumn();

echo "Total: $total | Con email: $withEmail (" . round(($withEmail/$total)*100, 2) . "%)\n";

// Mostrar un ejemplo
$example = $pdo->query("SELECT name, tags FROM places_cr WHERE tags LIKE '%email%' LIMIT 1")->fetch();
if ($example) {
    echo "\nEjemplo: {$example['name']}\n";
    echo "Tags: {$example['tags']}\n";
}
?>
