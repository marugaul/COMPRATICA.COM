<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== SIMULACIÓN DE VISTA DE ADMIN (sales_admin.php) ===\n\n";

// Esta es la misma consulta que usa sales_admin.php
$sql = "
SELECT s.*, a.email AS aff_email,
  (SELECT status FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_status,
  (SELECT id FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_id
FROM sales s
LEFT JOIN affiliates a ON a.id=s.affiliate_id
ORDER BY datetime(s.created_at) DESC
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Total de espacios mostrados en la interfaz: " . count($rows) . "\n\n";
echo "LISTA DE ESPACIOS (como se ven en la interfaz de admin):\n";
echo str_repeat("=", 120) . "\n";
printf("%-4s | %-30s | %-25s | %-8s | %-10s\n", "ID", "Título", "Afiliado (email)", "Activo", "Fee Status");
echo str_repeat("=", 120) . "\n";

foreach ($rows as $r) {
    printf(
        "%-4s | %-30s | %-25s | %-8s | %-10s\n",
        "#" . $r['id'],
        substr($r['title'] ?? '—', 0, 30),
        substr($r['aff_email'] ?: '(sin afiliado)', 0, 25),
        !empty($r['is_active']) ? '✅ Sí' : '❌ No',
        $r['fee_status'] ?: '—'
    );
}

echo str_repeat("=", 120) . "\n\n";

// Buscar específicamente espacios con ID 20 o 21
echo "=== BÚSQUEDA DE ESPACIOS #20 y #21 ===\n";
$space20 = $pdo->query("SELECT * FROM sales WHERE id=20")->fetch(PDO::FETCH_ASSOC);
$space21 = $pdo->query("SELECT * FROM sales WHERE id=21")->fetch(PDO::FETCH_ASSOC);

if ($space20) {
    echo "Espacio #20 encontrado:\n";
    print_r($space20);
} else {
    echo "❌ Espacio #20 NO EXISTE en la base de datos\n";
}

if ($space21) {
    echo "\nEspacio #21 encontrado:\n";
    print_r($space21);
} else {
    echo "❌ Espacio #21 NO EXISTE en la base de datos\n";
}

echo "\n=== ESPACIOS DEL AFILIADO vanecastro@gmail.com ===\n";
$stmt = $pdo->prepare("
    SELECT s.*, a.email AS aff_email
    FROM sales s
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    WHERE a.email = ?
    ORDER BY s.id
");
$stmt->execute(['vanecastro@gmail.com']);
$vane_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($vane_sales) > 0) {
    foreach ($vane_sales as $s) {
        echo "   - Espacio #{$s['id']}: {$s['title']} - " .
             (!empty($s['is_active']) ? 'ACTIVO' : 'INACTIVO') . "\n";
    }
} else {
    echo "   (ningún espacio encontrado para vanecastro@gmail.com)\n";
}

echo "\n=== FIN ===\n";
