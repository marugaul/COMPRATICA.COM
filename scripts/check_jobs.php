<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

// Total empleos activos
$total = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE is_active = 1")->fetchColumn();
echo "✓ Total empleos activos: $total\n\n";

// Por fuente
$stmt = $pdo->query("
    SELECT
        CASE
            WHEN import_source LIKE 'Telegram%' THEN 'Telegram'
            ELSE 'Otros'
        END as fuente,
        COUNT(*) as total
    FROM job_listings
    WHERE import_source IS NOT NULL AND is_active = 1
    GROUP BY fuente
");
echo "Por fuente:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['fuente']}: {$row['total']} empleos\n";
}

// Algunos ejemplos
$stmt = $pdo->query("
    SELECT jl.id, jl.title, jl.location, jl.created_at
    FROM job_listings jl
    WHERE jl.import_source LIKE 'Telegram%'
    AND jl.is_active = 1
    ORDER BY jl.created_at DESC
    LIMIT 5
");
echo "\nÚltimos 5 empleos de Telegram:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  [{$row['id']}] {$row['title']} - {$row['location']}\n";
}

echo "\n✓ Los empleos están disponibles en:\n";
echo "  https://compratica.com/empleos.php\n";
