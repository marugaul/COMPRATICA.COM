<?php
// ============================================
// ACTUALIZAR ARCHIVO ESPECÍFICO AHORA
// Copia un archivo directamente del repo
// ============================================

$_SESSION['is_admin'] = true;

$file = $_GET['file'] ?? 'SET_PASSWORD_SMTP.php';
$source = "/home/comprati/compratica_repo/admin/$file";
$dest = "/home/comprati/public_html/admin/$file";

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Update File</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
        .step { margin: 20px 0; padding: 15px; background: #111; border-left: 4px solid #0f0; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        pre { background: #222; padding: 10px; }
        a { color: #0ff; }
    </style>
</head>
<body>

<h1>ACTUALIZAR ARCHIVO: <?= htmlspecialchars($file) ?></h1>

<?php

if (isset($_GET['go'])) {
    echo "<div class='step'>";
    echo "<h3>Actualizando...</h3>";

    // Primero hacer git pull en el repo
    exec('cd /home/comprati/compratica_repo && git pull origin main 2>&1', $pull_output);
    echo "<p><strong>Git Pull:</strong></p><pre>" . implode("\n", array_slice($pull_output, -5)) . "</pre>";

    // Copiar archivo
    if (file_exists($source)) {
        $result = copy($source, $dest);

        if ($result) {
            echo "<p class='ok'>✓ Archivo actualizado exitosamente</p>";
            echo "<p>De: $source</p>";
            echo "<p>A: $dest</p>";

            // Verificar
            $mod_time = date('Y-m-d H:i:s', filemtime($dest));
            echo "<p>Última modificación: $mod_time</p>";

            echo "<hr>";
            echo "<p><strong>Ahora accede a:</strong></p>";
            echo "<p><a href='$file'>→ $file</a></p>";
        } else {
            echo "<p class='error'>✗ Error al copiar archivo</p>";
        }
    } else {
        echo "<p class='error'>✗ Archivo fuente no existe: $source</p>";
    }

    echo "</div>";
} else {
    echo "<div class='step'>";
    echo "<h3>¿Actualizar archivo?</h3>";
    echo "<p>Esto copiará la versión más reciente de:</p>";
    echo "<p><code>$source</code></p>";
    echo "<p>A:</p>";
    echo "<p><code>$dest</code></p>";
    echo "<hr>";
    echo "<p><a href='?file=$file&go=1' style='background:#0a0;color:#000;padding:15px 30px;text-decoration:none;font-weight:bold;border-radius:6px;display:inline-block'>▶ ACTUALIZAR AHORA</a></p>";
    echo "</div>";
}

?>

<hr>
<p>Actualizar otros archivos:</p>
<p>
    <a href="?file=SET_PASSWORD_SMTP.php">SET_PASSWORD_SMTP.php</a> |
    <a href="?file=SEND_EMAIL_NOW.php">SEND_EMAIL_NOW.php</a> |
    <a href="?file=CHECK_CAMPAIGN.php">CHECK_CAMPAIGN.php</a>
</p>

</body>
</html>
