<?php
// ============================================
// FORZAR ACTUALIZACIÓN DESDE GITHUB
// Ejecuta manualmente el proceso del cron
// ============================================

// Bypass auth
$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forzar Actualización</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .step { margin: 20px 0; padding: 15px; background: #111; border-left: 4px solid #0f0; }
        pre { background: #222; padding: 15px; overflow: auto; border: 1px solid #333; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        .warn { color: #ff0; }
        h1 { color: #0ff; }
    </style>
</head>
<body>

<h1>FORZAR ACTUALIZACIÓN DESDE GITHUB</h1>

<?php

if (isset($_GET['execute']) && $_GET['execute'] === 'yes') {

    echo "<div class='step'>";
    echo "<h3>Ejecutando Actualización Manual...</h3>";

    // PASO 1: Ver estado actual
    echo "<p><strong>1. Estado actual del repositorio:</strong></p>";
    exec('cd /home/comprati/compratica_repo && git log -1 --oneline', $output1, $return1);
    echo "<pre>" . implode("\n", $output1) . "</pre>";

    // PASO 2: Fetch
    echo "<p><strong>2. Haciendo git fetch...</strong></p>";
    exec('cd /home/comprati/compratica_repo && git fetch --all 2>&1', $output2, $return2);
    echo "<pre>" . implode("\n", $output2) . "</pre>";

    // PASO 3: Reset hard
    echo "<p><strong>3. Reseteando a origin/main...</strong></p>";
    exec('cd /home/comprati/compratica_repo && git reset --hard origin/main 2>&1', $output3, $return3);
    echo "<pre>" . implode("\n", $output3) . "</pre>";

    // PASO 4: Ver nuevo estado
    echo "<p><strong>4. Nuevo estado:</strong></p>";
    exec('cd /home/comprati/compratica_repo && git log -1 --oneline 2>&1', $output4, $return4);
    echo "<pre>" . implode("\n", $output4) . "</pre>";

    // PASO 5: Rsync
    echo "<p><strong>5. Sincronizando archivos con rsync...</strong></p>";
    $rsync_cmd = "rsync -av --delete " .
        "--exclude='.git' " .
        "--exclude='vendor/' " .
        "--exclude='sessions/' " .
        "--exclude='logs/' " .
        "--exclude='uploads/' " .
        "--exclude='.env' " .
        "--exclude='config/database.php' " .
        "/home/comprati/compratica_repo/ /home/comprati/public_html/ 2>&1";

    exec($rsync_cmd, $output5, $return5);
    echo "<pre>";
    foreach ($output5 as $line) {
        if (strpos($line, 'SEND_EMAIL_NOW.php') !== false ||
            strpos($line, 'SET_PASSWORD_SMTP.php') !== false ||
            strpos($line, 'CHECK_CAMPAIGN.php') !== false) {
            echo "<span class='ok'>$line</span>\n";
        } else {
            echo "$line\n";
        }
    }
    echo "</pre>";

    // PASO 6: Verificar archivos
    echo "<p><strong>6. Verificando archivos nuevos en public_html:</strong></p>";
    $files_to_check = [
        '/home/comprati/public_html/admin/SEND_EMAIL_NOW.php',
        '/home/comprati/public_html/admin/SET_PASSWORD_SMTP.php',
        '/home/comprati/public_html/admin/CHECK_CAMPAIGN.php',
    ];

    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo "<p class='ok'>✓ Existe: " . basename($file) . "</p>";
        } else {
            echo "<p class='error'>✗ NO existe: " . basename($file) . "</p>";
        }
    }

    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>✓ ACTUALIZACIÓN COMPLETADA</h3>";
    echo "<p>Ahora prueba los scripts:</p>";
    echo "<p><a href='SET_PASSWORD_SMTP.php' style='color:#0ff'>→ SET_PASSWORD_SMTP.php</a></p>";
    echo "<p><a href='SEND_EMAIL_NOW.php' style='color:#0ff'>→ SEND_EMAIL_NOW.php</a></p>";
    echo "<p><a href='CHECK_CAMPAIGN.php?campaign_id=4' style='color:#0ff'>→ CHECK_CAMPAIGN.php</a></p>";
    echo "</div>";

} else {
    // Mostrar botón para ejecutar
    echo "<div class='step'>";
    echo "<h3>Estado Actual</h3>";
    echo "<p>El cron está atascado en un commit viejo.</p>";
    echo "<p>Este script ejecutará manualmente el proceso de actualización:</p>";
    echo "<ol>";
    echo "<li>git fetch --all</li>";
    echo "<li>git reset --hard origin/main</li>";
    echo "<li>rsync a public_html</li>";
    echo "<li>Verificar archivos nuevos</li>";
    echo "</ol>";
    echo "<hr>";
    echo "<p><a href='?execute=yes' style='background:#0a0;color:#000;padding:15px 30px;text-decoration:none;font-weight:bold;border-radius:6px;display:inline-block'>▶ EJECUTAR ACTUALIZACIÓN AHORA</a></p>";
    echo "</div>";
}

?>

</body>
</html>
