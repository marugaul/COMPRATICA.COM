<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== VERIFICACIÓN DE BASE DE DATOS ===\n\n";

// Verificar ruta de la BD
$dbFile = __DIR__ . '/data.sqlite';
echo "Archivo BD: $dbFile\n";
echo "Existe: " . (file_exists($dbFile) ? "✅ Sí" : "❌ No") . "\n";
echo "Tamaño: " . filesize($dbFile) . " bytes\n\n";

// Contar todos los espacios
$total = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
echo "Total de espacios en la BD: $total\n\n";

// Listar los últimos 5 espacios
echo "Últimos 5 espacios:\n";
$last5 = $pdo->query("SELECT id, title, start_at, is_active FROM sales ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($last5 as $s) {
    echo "ID {$s['id']}: {$s['title']} (activo: {$s['is_active']})\n";
}

// Buscar específicamente el espacio 21
echo "\n=== BÚSQUEDA ESPACIO #21 ===\n";
$space21 = $pdo->query("SELECT * FROM sales WHERE id=21")->fetch(PDO::FETCH_ASSOC);
if ($space21) {
    echo "✅ ENCONTRADO:\n";
    print_r($space21);
} else {
    echo "❌ NO ENCONTRADO\n";
    echo "El ID más alto es: " . $pdo->query("SELECT MAX(id) FROM sales")->fetchColumn() . "\n";
}

// Verificar si hay algún problema con el ID de secuencia
echo "\n=== VERIFICAR SECUENCIA ===\n";
$seq = $pdo->query("SELECT * FROM sqlite_sequence WHERE name='sales'")->fetch(PDO::FETCH_ASSOC);
if ($seq) {
    echo "Secuencia actual: {$seq['seq']}\n";
}
