<?php
echo "=== VERIFICACIÓN DE COMPRATICA.DB ===\n\n";

$dbFile = __DIR__ . '/compratica.db';
echo "Archivo: $dbFile\n";
echo "Existe: " . (file_exists($dbFile) ? "✅ Sí" : "❌ No") . "\n";

if (file_exists($dbFile)) {
    echo "Tamaño: " . filesize($dbFile) . " bytes\n\n";

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Verificar si tiene tabla sales
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tablas en compratica.db:\n";
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "- $table: $count filas\n";
        }

        if (in_array('sales', $tables)) {
            echo "\n=== ESPACIOS EN COMPRATICA.DB ===\n";
            $sales = $pdo->query("SELECT id, title, start_at, end_at, is_active FROM sales ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sales as $s) {
                echo "ID {$s['id']}: {$s['title']} (activo: {$s['is_active']}, inicio: {$s['start_at']})\n";
            }

            $total = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
            echo "\nTotal de espacios: $total\n";

            $maxId = $pdo->query("SELECT MAX(id) FROM sales")->fetchColumn();
            echo "ID más alto: $maxId\n";
        }
    } catch (Exception $e) {
        echo "❌ Error al leer la BD: " . $e->getMessage() . "\n";
    }
}

echo "\n=== COMPARACIÓN ===\n";
echo "data.sqlite: " . filesize(__DIR__ . '/data.sqlite') . " bytes\n";
echo "compratica.db: " . (file_exists($dbFile) ? filesize($dbFile) . " bytes" : "No existe") . "\n";
