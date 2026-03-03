<?php
/**
 * DIAGNÓSTICO SQL - Vanessa Castro
 * Ejecuta desde: https://compratica.com/sql_diagnostic.php
 *
 * Este script usa data.sqlite (tu archivo de producción)
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$pdo = db();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico SQL - Vanessa Castro</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .query { background: #ecf0f1; padding: 10px; margin: 10px 0; border-left: 4px solid #3498db; }
        .query-title { font-weight: bold; color: #2c3e50; margin-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; background: white; }
        th { background: #34495e; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f8f9fa; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 10px 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 10px; margin: 10px 0; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico SQL - Vanessa Castro</h1>
    <div class="info">
        <strong>Base de datos:</strong> <?= __DIR__ . '/data.sqlite' ?><br>
        <strong>Fecha:</strong> <?= date('Y-m-d H:i:s') ?><br>
        <strong>Servidor:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>
    </div>

<?php

// Array de queries a ejecutar
$queries = [
    [
        'title' => '1. Buscar afiliado Vanessa Castro',
        'sql' => "SELECT id, name, email, created_at
                  FROM affiliates
                  WHERE email = 'vanecastro@gmail.com'",
        'description' => 'Verifica que el afiliado exista'
    ],
    [
        'title' => '2. Espacios de Vanessa (JOIN con affiliates)',
        'sql' => "SELECT s.id, s.affiliate_id, s.title, s.is_active, s.start_at, s.end_at, s.created_at
                  FROM sales s
                  JOIN affiliates a ON a.id = s.affiliate_id
                  WHERE a.email = 'vanecastro@gmail.com'
                  ORDER BY s.id DESC",
        'description' => 'Espacios asociados al email vanecastro@gmail.com'
    ],
    [
        'title' => '3. Buscar espacio por título (garage + mujer)',
        'sql' => "SELECT s.id, s.affiliate_id, s.title, s.is_active, s.start_at, s.end_at,
                         a.id as aff_id, a.name as aff_name, a.email as aff_email
                  FROM sales s
                  LEFT JOIN affiliates a ON a.id = s.affiliate_id
                  WHERE s.title LIKE '%garage%' AND s.title LIKE '%mujer%'",
        'description' => 'Busca el espacio "Venta de garage ropa mujer"'
    ],
    [
        'title' => '4. Productos con "palazzo" o "blusa" en el nombre',
        'sql' => "SELECT p.id, p.affiliate_id, p.sale_id, p.name, p.active, p.stock,
                         s.title as space_title, a.name as aff_name, a.email as aff_email
                  FROM products p
                  LEFT JOIN sales s ON s.id = p.sale_id
                  LEFT JOIN affiliates a ON a.id = p.affiliate_id
                  WHERE p.name LIKE '%palazzo%' OR p.name LIKE '%blusa%'",
        'description' => 'Busca los productos que aparecen en la captura de pantalla'
    ],
    [
        'title' => '5. Todos los espacios activos con productos',
        'sql' => "SELECT s.id, s.affiliate_id, s.title, s.is_active, a.name as aff_name, a.email,
                         COUNT(p.id) as product_count
                  FROM sales s
                  LEFT JOIN affiliates a ON a.id = s.affiliate_id
                  LEFT JOIN products p ON p.sale_id = s.id
                  WHERE s.is_active = 1
                  GROUP BY s.id
                  HAVING product_count > 0
                  ORDER BY s.id DESC",
        'description' => 'Espacios activos que tienen productos'
    ],
    [
        'title' => '6. Últimos 10 espacios creados',
        'sql' => "SELECT s.id, s.affiliate_id, s.title, s.is_active, a.name as aff_name, a.email,
                         s.created_at, s.start_at, s.end_at
                  FROM sales s
                  LEFT JOIN affiliates a ON a.id = s.affiliate_id
                  ORDER BY s.created_at DESC
                  LIMIT 10",
        'description' => 'Últimos espacios creados para verificar si existe el de Vanessa'
    ],
    [
        'title' => '7. Todos los productos (últimos 20)',
        'sql' => "SELECT p.id, p.affiliate_id, p.sale_id, p.name, p.active, p.stock,
                         s.title as space_title, a.name as aff_name
                  FROM products p
                  LEFT JOIN sales s ON s.id = p.sale_id
                  LEFT JOIN affiliates a ON a.id = p.affiliate_id
                  ORDER BY p.id DESC
                  LIMIT 20",
        'description' => 'Últimos productos creados'
    ]
];

// Ejecutar cada query
foreach ($queries as $index => $queryInfo) {
    echo "<div class='query'>";
    echo "<div class='query-title'>{$queryInfo['title']}</div>";
    echo "<div style='color: #7f8c8d; font-size: 0.9em; margin: 5px 0;'>{$queryInfo['description']}</div>";

    try {
        $stmt = $pdo->query($queryInfo['sql']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            echo "<div class='success'>✓ Encontrados " . count($results) . " resultado(s)</div>";
            echo "<table>";

            // Headers
            echo "<tr>";
            foreach (array_keys($results[0]) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";

            // Rows
            foreach ($results as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }

            echo "</table>";
        } else {
            echo "<div class='warning'>⚠️ No se encontraron resultados</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    echo "</div>";
}

?>

<h2>📊 Análisis del Problema</h2>
<div class="info">
    <strong>Revisa los resultados anteriores:</strong>
    <ul>
        <li><strong>Query 1:</strong> ¿Existe el afiliado vanecastro@gmail.com? ¿Cuál es su ID?</li>
        <li><strong>Query 2:</strong> ¿Tiene espacios asociados a ese ID?</li>
        <li><strong>Query 3:</strong> ¿Existe el espacio "Venta de garage ropa mujer"? ¿Qué affiliate_id tiene?</li>
        <li><strong>Query 4:</strong> ¿Existen los productos (palazzo, blusa)? ¿Qué affiliate_id tienen?</li>
    </ul>

    <p><strong>Problema probable:</strong></p>
    <p>Si la Query 3 muestra un espacio con un <code>affiliate_id</code> diferente al ID de Vanessa (Query 1),
    entonces el espacio NO está asociado correctamente al afiliado y por eso no aparece en su panel.</p>
</div>

</body>
</html>
