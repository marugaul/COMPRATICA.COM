<?php
/**
 * Trigger Deploy Manual
 * Ejecuta el mismo comando que el CRON para sincronizar GitHub → Producción
 */

// Clave de seguridad simple
if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== 'si') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Deploy Manual</title>
        <style>
            body { font-family: Arial; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
            h1 { color: #667eea; text-align: center; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; }
            .info { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0; color: #0c5460; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚀 Deploy Manual</h1>

            <div class="info">
                <strong>¿Qué hace este script?</strong><br>
                Ejecuta el mismo comando que tu CRON para sincronizar GitHub con producción:
                <ol>
                    <li>Hace pull de la rama <code>main</code> de GitHub</li>
                    <li>Copia archivos a <code>/home/comprati/public_html/</code></li>
                    <li>Excluye archivos importantes (data.sqlite, sessions/, etc.)</li>
                </ol>
            </div>

            <div class="warning">
                <strong>⚠️ IMPORTANTE:</strong><br>
                • Solo ejecutar si necesitás sincronizar AHORA<br>
                • El CRON lo hace automáticamente según tu configuración<br>
                • Los archivos locales serán reemplazados por los de GitHub
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="?confirmar=si" class="btn">✓ CONFIRMAR DEPLOY</a>
            </div>

            <p style="text-align: center; color: #6c757d; margin-top: 20px;">
                <a href="dashboard.php" style="color: #667eea;">← Cancelar y volver</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Ejecutar deploy
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ejecutando Deploy...</title>
    <style>
        body { font-family: Arial; background: #000; color: #0f0; padding: 20px; }
        pre { font-size: 13px; line-height: 1.5; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .info { color: #0ff; }
        h1 { color: #fff; }
    </style>
</head>
<body>
    <h1>🚀 Ejecutando Deploy...</h1>
    <pre><?php

echo date('Y-m-d H:i:s') . " - Iniciando sincronización...\n";
echo str_repeat('=', 80) . "\n\n";

// Comando completo
$command = "cd /home/comprati/compratica_repo && git fetch --all 2>&1 && git reset --hard origin/main 2>&1 && git clean -fd 2>&1 && rsync -av --delete --exclude='.git' --exclude='.gitignore' --exclude='data.sqlite' --exclude='sessions/' --exclude='logs/' --exclude='php_error.log' --exclude='includes/config.local.php' --exclude='verificar_archivos.php' --exclude='diagnostico.php' --exclude='reset_repo.php' /home/comprati/compratica_repo/ /home/comprati/public_html/ 2>&1";

echo "Comando:\n$command\n\n";
echo str_repeat('=', 80) . "\n\n";

// Ejecutar
$output = shell_exec($command);

if ($output === null) {
    echo "<span class='error'>❌ ERROR: No se pudo ejecutar el comando.</span>\n";
    echo "Posibles causas:\n";
    echo "- Permisos insuficientes\n";
    echo "- shell_exec() deshabilitado en PHP\n";
    echo "- Ruta incorrecta\n\n";
    echo "Solución: Esperar a que el CRON se ejecute automáticamente.\n";
} else {
    echo $output;
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "<span class='success'>✅ Deploy completado en: " . date('Y-m-d H:i:s') . "</span>\n";

    // Log
    $log_msg = date('Y-m-d H:i:s') . " - Deploy manual desde ejecutar_deploy.php\n";
    @file_put_contents('/home/comprati/deploy.log', $log_msg, FILE_APPEND);
}

?></pre>

    <div style="background: white; color: #000; padding: 20px; margin-top: 30px; border-radius: 8px;">
        <h2>Próximos pasos:</h2>
        <ul>
            <li><a href="/admin/instalar_ahora.php" style="color: #667eea; font-weight: bold;">▶ Ejecutar Instalador Email Marketing</a></li>
            <li><a href="/admin/email_marketing.php" style="color: #667eea;">Ir a Email Marketing</a></li>
            <li><a href="/admin/dashboard.php" style="color: #667eea;">Volver al Dashboard</a></li>
        </ul>
    </div>

</body>
</html>
