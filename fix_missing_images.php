<?php
/**
 * Diagnóstico y corrección de imágenes faltantes en espacios
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f7fa; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h2 { color: #2d3748; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #667eea; color: white; }
img { max-width: 150px; max-height: 100px; border-radius: 4px; }
.error { color: #e53e3e; font-weight: bold; }
.success { color: #38a169; font-weight: bold; }
.warning { color: #d69e2e; font-weight: bold; }
.btn { display: inline-block; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
.btn:hover { background: #5568d3; }
</style>";

echo "<div class='container'>";
echo "<h2>🔍 Diagnóstico de Imágenes de Espacios</h2>";

// Obtener todos los espacios
$sales = $pdo->query("
    SELECT s.id, s.title, s.cover_image, s.is_active, a.name as affiliate_name
    FROM sales s
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total de espacios encontrados: <strong>" . count($sales) . "</strong></p>";

$hasIssues = false;

echo "<table>";
echo "<tr>
    <th>ID</th>
    <th>Título</th>
    <th>Afiliado</th>
    <th>Archivo BD</th>
    <th>Estado</th>
    <th>Preview</th>
    <th>Ruta Web</th>
</tr>";

foreach ($sales as $sale) {
    $fileName = $sale['cover_image'];
    $hasImage = !empty($fileName);
    $fileExists = false;
    $webPath = '';
    $status = '';
    $statusClass = '';

    if ($hasImage) {
        $fullPath = __DIR__ . '/uploads/affiliates/' . $fileName;
        $fileExists = file_exists($fullPath);
        $webPath = 'uploads/affiliates/' . htmlspecialchars($fileName);

        if ($fileExists) {
            $status = '✓ OK';
            $statusClass = 'success';
        } else {
            $status = '✗ Archivo no existe';
            $statusClass = 'error';
            $hasIssues = true;
        }
    } else {
        $status = '⚠ Sin imagen asignada';
        $statusClass = 'warning';
        $hasIssues = true;
    }

    echo "<tr>";
    echo "<td>{$sale['id']}</td>";
    echo "<td>" . htmlspecialchars($sale['title']) . "</td>";
    echo "<td>" . htmlspecialchars($sale['affiliate_name'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($fileName ?: 'NULL') . "</td>";
    echo "<td class='{$statusClass}'>{$status}</td>";

    if ($fileExists) {
        echo "<td><img src='{$webPath}' alt='Preview' onerror=\"this.src='assets/placeholder.jpg'\"></td>";
        echo "<td><a href='{$webPath}' target='_blank' class='btn'>Ver</a></td>";
    } else {
        echo "<td><img src='assets/placeholder.jpg' alt='Placeholder'></td>";
        echo "<td>-</td>";
    }

    echo "</tr>";
}

echo "</table>";

if ($hasIssues) {
    echo "<div style='margin-top: 20px; padding: 15px; background: #fff5f5; border-left: 4px solid #e53e3e; border-radius: 4px;'>";
    echo "<h3 style='color: #c53030; margin-top: 0;'>⚠️ Se encontraron problemas</h3>";
    echo "<p>Algunos espacios no tienen imagen o la imagen no existe físicamente.</p>";
    echo "<p><strong>Solución:</strong></p>";
    echo "<ul>";
    echo "<li>Para espacios sin imagen: Edita el espacio y sube una portada</li>";
    echo "<li>Para archivos que no existen: Vuelve a subir la imagen editando el espacio</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='margin-top: 20px; padding: 15px; background: #d4edda; border-left: 4px solid #38a169; border-radius: 4px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✓ Todo está correcto</h3>";
    echo "<p>Todas las imágenes están correctamente asignadas y existen físicamente.</p>";
    echo "</div>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<h3>📁 Archivos disponibles en uploads/affiliates/</h3>";
echo "<div style='background: #f7fafc; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;'>";

$uploadsPath = __DIR__ . '/uploads/affiliates/';
if (is_dir($uploadsPath)) {
    $files = array_diff(scandir($uploadsPath), ['.', '..']);

    // Filtrar solo imágenes de portada
    $coverFiles = array_filter($files, function($f) {
        return strpos($f, 'cover_') === 0;
    });

    if (count($coverFiles) > 0) {
        echo "<strong>Imágenes de portada encontradas: " . count($coverFiles) . "</strong><br><br>";
        foreach ($coverFiles as $file) {
            $fullPath = $uploadsPath . $file;
            $size = filesize($fullPath);
            $sizeKB = round($size / 1024, 1);
            echo "• {$file} ({$sizeKB} KB)<br>";
        }
    } else {
        echo "No se encontraron archivos de portada.";
    }
} else {
    echo "<span class='error'>El directorio uploads/affiliates/ no existe</span>";
}

echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='affiliate/sales.php' class='btn'>← Ir a Mis Espacios</a>";
echo "<a href='venta-garaje.php' class='btn'>Ver Página Pública</a>";
echo "</div>";

echo "</div>";
?>
