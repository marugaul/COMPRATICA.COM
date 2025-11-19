<?php
/**
 * Verifica qué categorías fueron realmente importadas
 */

require_once __DIR__ . '/includes/db_places.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = db_places();

    echo "<h1>Estado de la Base de Datos</h1>";
    echo "<style>body { font-family: monospace; padding: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #667eea; color: white; }</style>";

    // Contar por categoría
    $stmt = $pdo->query("
        SELECT category, type, COUNT(*) as count
        FROM places_cr
        GROUP BY category, type
        ORDER BY category, type
    ");

    $results = $stmt->fetchAll();

    echo "<h2>Lugares por Categoría</h2>";
    echo "<table>";
    echo "<tr><th>Categoría</th><th>Tipo</th><th>Cantidad</th></tr>";

    $totalGeneral = 0;
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td>{$row['category']}</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>" . number_format($row['count']) . "</td>";
        echo "</tr>";
        $totalGeneral += $row['count'];
    }

    echo "<tr style='font-weight: bold; background: #f0f0f0;'>";
    echo "<td colspan='2'>TOTAL</td>";
    echo "<td>" . number_format($totalGeneral) . "</td>";
    echo "</tr>";
    echo "</table>";

    // Total general
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM places_cr");
    $total = $stmt->fetch()['total'];

    echo "<h2>Resumen</h2>";
    echo "<p><strong>Total de lugares en la base de datos:</strong> " . number_format($total) . "</p>";

    // Algunos ejemplos
    echo "<h2>Ejemplos de Lugares Importados</h2>";
    $stmt = $pdo->query("SELECT name, type, category, city FROM places_cr LIMIT 20");
    echo "<table>";
    echo "<tr><th>Nombre</th><th>Tipo</th><th>Categoría</th><th>Ciudad</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>{$row['category']}</td>";
        echo "<td>" . ($row['city'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
