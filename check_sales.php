<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

// Verificar si existe la tabla sales
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "=== TABLAS EN LA BASE DE DATOS ===\n";
foreach ($tables as $table) {
    echo "- $table\n";
}

// Si existe la tabla sales, mostrar los espacios
if (in_array('sales', $tables)) {
    echo "\n=== ESPACIOS EN LA TABLA SALES ===\n";
    $sales = $pdo->query("SELECT id, title, start_at, end_at, is_active FROM sales ORDER BY id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sales as $sale) {
        echo sprintf(
            "ID: %-3d | Activo: %d | Título: %-30s | Inicio: %s | Fin: %s\n",
            $sale['id'],
            $sale['is_active'],
            substr($sale['title'], 0, 30),
            $sale['start_at'],
            $sale['end_at']
        );
    }

    echo "\n=== RESUMEN ===\n";
    $total = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM sales WHERE is_active=1")->fetchColumn();
    echo "Total de espacios: $total\n";
    echo "Espacios activos: $active\n";

    // Verificar específicamente los espacios #20 y #21
    echo "\n=== ESPACIOS #20 y #21 ===\n";
    $sale20 = $pdo->query("SELECT * FROM sales WHERE id=20")->fetch(PDO::FETCH_ASSOC);
    $sale21 = $pdo->query("SELECT * FROM sales WHERE id=21")->fetch(PDO::FETCH_ASSOC);

    if ($sale20) {
        echo "Espacio #20:\n";
        print_r($sale20);
    } else {
        echo "Espacio #20: NO EXISTE\n";
    }

    if ($sale21) {
        echo "\nEspacio #21:\n";
        print_r($sale21);
    } else {
        echo "Espacio #21: NO EXISTE\n";
    }
} else {
    echo "\n⚠️ LA TABLA 'sales' NO EXISTE EN LA BASE DE DATOS\n";
}
