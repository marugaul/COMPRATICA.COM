<?php
// Script temporal para verificar imágenes de espacios
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();

echo "<h2>Verificación de Imágenes de Espacios</h2>\n";
echo "<style>
body { font-family: monospace; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #4CAF50; color: white; }
img { max-width: 150px; max-height: 100px; }
.error { color: red; }
.success { color: green; }
</style>";

$sales = $pdo->query("SELECT id, title, cover_image FROM sales ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Título</th><th>Nombre Archivo</th><th>Ruta Completa</th><th>¿Existe?</th><th>Preview</th></tr>";

foreach ($sales as $sale) {
    $fileName = $sale['cover_image'];
    $fullPath = __DIR__ . '/uploads/affiliates/' . $fileName;
    $webPath = 'uploads/affiliates/' . htmlspecialchars($fileName);
    $exists = $fileName && file_exists($fullPath);

    echo "<tr>";
    echo "<td>{$sale['id']}</td>";
    echo "<td>" . htmlspecialchars($sale['title']) . "</td>";
    echo "<td>" . htmlspecialchars($fileName ?: 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($webPath) . "</td>";

    if ($exists) {
        echo "<td class='success'>✓ SÍ</td>";
        echo "<td><img src='{$webPath}' alt='Preview'></td>";
    } else {
        echo "<td class='error'>✗ NO</td>";
        echo "<td>-</td>";
    }

    echo "</tr>";
}

echo "</table>";

echo "\n\n<h3>Archivos en uploads/affiliates/</h3>";
echo "<pre>";
$files = scandir(__DIR__ . '/uploads/affiliates/');
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo $file . "\n";
    }
}
echo "</pre>";
