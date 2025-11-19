<?php
require_once __DIR__ . '/includes/db_places.php';
header('Content-Type: application/json');

try {
    $pdo = db_places();

    // Contar por categoría
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as count
        FROM places_cr
        GROUP BY category
        ORDER BY category
    ");

    $categories = [];
    while ($row = $stmt->fetch()) {
        $categories[$row['category']] = $row['count'];
    }

    // Total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM places_cr");
    $total = $stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'total' => $total,
        'by_category' => $categories
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
