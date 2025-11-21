<?php
// Quick check de estructura de tabla
$_SESSION['is_admin'] = true;

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<pre>";
echo "ESTRUCTURA DE email_smtp_configs:\n\n";

$stmt = $pdo->query("DESCRIBE email_smtp_configs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\nDATA ACTUAL:\n\n";
$data = $pdo->query("SELECT * FROM email_smtp_configs")->fetchAll(PDO::FETCH_ASSOC);
print_r($data);

echo "</pre>";
?>
