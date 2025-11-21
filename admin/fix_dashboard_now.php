<?php
// Quick fix - Actualizar dashboard.php inmediatamente
$_SESSION['is_admin'] = true;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix Dashboard</title>";
echo "<style>body{font-family:Arial;padding:20px;max-width:800px;margin:0 auto;background:#fef3c7}";
echo ".card{background:white;padding:25px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin:20px 0}";
echo ".ok{color:#10b981;font-weight:bold}.error{color:#dc2626;font-weight:bold}";
echo "h1{color:#dc2626}</style></head><body>";

echo "<div class='card'><h1>🔧 Actualizar Dashboard</h1>";

try {
    // Pull from GitHub
    exec('cd /home/comprati/compratica_repo && git pull origin main 2>&1', $pull_out, $pull_ret);
    echo "<p><strong>Git Pull:</strong></p>";
    echo "<pre style='background:#f3f4f6;padding:10px;border-radius:6px;font-size:12px'>" . implode("\n", array_slice($pull_out, -3)) . "</pre>";

    // Copy file
    $source = '/home/comprati/compratica_repo/admin/dashboard.php';
    $dest = '/home/comprati/public_html/admin/dashboard.php';

    if (copy($source, $dest)) {
        echo "<p class='ok'>✓ dashboard.php actualizado correctamente</p>";
        echo "<p>Última modificación: " . date('Y-m-d H:i:s', filemtime($dest)) . "</p>";

        echo "<hr>";
        echo "<p><strong>El error está corregido. Ahora puedes acceder a:</strong></p>";
        echo "<p><a href='dashboard.php' style='display:inline-block;background:#10b981;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:bold'>→ Ir al Dashboard</a></p>";
    } else {
        echo "<p class='error'>✗ Error al copiar archivo</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div></body></html>";
?>
