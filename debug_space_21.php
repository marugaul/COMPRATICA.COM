<?php
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "=== INVESTIGACIÓN ESPACIO #21 ===\n\n";

// 1. Verificar que el espacio existe
echo "1. DATOS DEL ESPACIO #21:\n";
$space21 = $pdo->query("SELECT * FROM sales WHERE id=21")->fetch(PDO::FETCH_ASSOC);
if ($space21) {
    print_r($space21);
} else {
    echo "❌ El espacio #21 NO EXISTE\n";
    exit;
}

// 2. Verificar el afiliado
echo "\n2. DATOS DEL AFILIADO (ID: {$space21['affiliate_id']}):\n";
$affiliate = $pdo->query("SELECT * FROM affiliates WHERE id={$space21['affiliate_id']}")->fetch(PDO::FETCH_ASSOC);
if ($affiliate) {
    print_r($affiliate);
} else {
    echo "❌ ERROR: El afiliado con ID {$space21['affiliate_id']} NO EXISTE\n";
    echo "❗ ESTE ES EL PROBLEMA: El espacio tiene un affiliate_id que no existe en la tabla affiliates\n";
}

// 3. Reproducir la consulta exacta de venta-garaje.php
echo "\n3. CONSULTA SQL DE VENTA-GARAJE.PHP:\n";
$sql = "
  SELECT s.*,
         a.name AS affiliate_name,
         (SELECT COUNT(*) FROM products p WHERE p.sale_id = s.id AND p.active = 1) AS product_count
  FROM sales s
  JOIN affiliates a ON a.id = s.affiliate_id
  WHERE s.is_active = 1
  ORDER BY s.start_at ASC
";
echo "SQL:\n$sql\n\n";

$results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Resultados: " . count($results) . " espacios\n\n";

$found21 = false;
foreach ($results as $r) {
    echo "ID {$r['id']}: {$r['title']} (Afiliado: {$r['affiliate_name']})\n";
    if ($r['id'] == 21) {
        $found21 = true;
    }
}

if (!$found21) {
    echo "\n❌ El espacio #21 NO aparece en los resultados del JOIN\n";
    echo "❗ Esto confirma que el problema es el affiliate_id inválido\n";
}

// 4. Verificar todos los affiliates
echo "\n4. LISTA DE AFFILIATES EN LA BD:\n";
$affiliates = $pdo->query("SELECT id, name, email FROM affiliates ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($affiliates as $aff) {
    echo "ID {$aff['id']}: {$aff['name']} ({$aff['email']})\n";
}

// 5. Calcular el estado del espacio #21
echo "\n5. ESTADO DEL ESPACIO #21:\n";
$now = time();
$start = strtotime($space21['start_at']);
$end = strtotime($space21['end_at']);

echo "Hora actual: " . date('Y-m-d H:i:s', $now) . " ($now)\n";
echo "Inicio: {$space21['start_at']} ($start)\n";
echo "Fin: {$space21['end_at']} ($end)\n";

if ($now >= $start && $now <= $end) {
    echo "Estado: EN VIVO ✅\n";
} elseif ($now < $start) {
    echo "Estado: PRÓXIMA ✅\n";
    $diff = $start - $now;
    $hours = floor($diff / 3600);
    echo "Inicia en: $hours horas\n";
} else {
    echo "Estado: FINALIZADA ❌\n";
}

echo "\n=== CONCLUSIÓN ===\n";
echo "Si el afiliado con ID {$space21['affiliate_id']} no existe, el JOIN fallará\n";
echo "y el espacio #21 no aparecerá en venta-garaje.php\n";
