<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

// Reproducir la consulta EXACTA del admin
$sql = "
SELECT s.*, a.email AS aff_email,
  (SELECT status FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_status,
  (SELECT id FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_id
FROM sales s
LEFT JOIN affiliates a ON a.id=s.affiliate_id
ORDER BY datetime(s.created_at) DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "=== DATOS QUE VE EL ADMIN (sales_admin.php) ===\n";
echo "Total de espacios: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    echo sprintf(
        "ID: #%-3d | Título: %-35s | Código: %-10s | Inicio: %s | Fin: %s | Pagado: %s | Activo: %s\n",
        $row['id'],
        substr($row['title'], 0, 35),
        $row['code'] ?? 'N/A',
        $row['start_at'],
        $row['end_at'],
        $row['fee_status'] ?? 'N/A',
        $row['is_active'] ? '✅ Sí' : '❌ No'
    );
}

// Buscar específicamente el código 764698
echo "\n=== BÚSQUEDA POR CÓDIGO 764698 ===\n";
$search = $pdo->query("SELECT * FROM sales WHERE code='764698'")->fetch(PDO::FETCH_ASSOC);
if ($search) {
    echo "Encontrado:\n";
    print_r($search);
} else {
    echo "❌ No se encontró ningún espacio con código 764698\n";
}

// Buscar por título parcial "ropa mujer"
echo "\n=== BÚSQUEDA POR TÍTULO 'ropa mujer' ===\n";
$searchTitle = $pdo->query("SELECT * FROM sales WHERE title LIKE '%ropa mujer%'")->fetchAll(PDO::FETCH_ASSOC);
if ($searchTitle) {
    echo "Encontrados " . count($searchTitle) . " espacios:\n";
    foreach ($searchTitle as $s) {
        print_r($s);
    }
} else {
    echo "❌ No se encontró ningún espacio con título que contenga 'ropa mujer'\n";
}
