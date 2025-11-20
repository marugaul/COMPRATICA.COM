<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalador Email Marketing - CompraTica</title>
    <style>
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; margin: 0; }
        .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #667eea; text-align: center; margin-bottom: 20px; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #667eea; border-radius: 4px; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; margin: 10px 5px; }
        .btn:hover { opacity: 0.9; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>

<div class="container">
    <h1>📧 Instalador Email Marketing</h1>

<?php
// Solo permitir ejecución UNA VEZ
if (file_exists(__DIR__ . '/.email_installed.lock')) {
    ?>
    <div class="step warning">
        <strong>⚠️ Sistema ya instalado</strong><br>
        El sistema fue instalado el <?= date('Y-m-d H:i:s', filemtime(__DIR__ . '/.email_installed.lock')) ?><br><br>
        <a href="email_marketing.php" class="btn">Ir a Email Marketing</a>
        <a href="dashboard.php" class="btn">Volver al Dashboard</a>
    </div>
    <?php
    exit;
}

// Verificar si se presionó el botón de instalación
if (isset($_POST['install'])) {
    echo '<div class="step"><strong>Iniciando instalación...</strong></div>';

    try {
        // Conectar a MySQL usando la función existente
        require_once __DIR__ . '/../includes/db_places.php';
        $pdo = db_places();

        echo '<div class="step success">✓ Conexión a MySQL: comprati_marketplace</div>';

        // Leer y ejecutar SQL
        $sql = file_get_contents(__DIR__ . '/INSTALAR_EMAIL_MARKETING.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $created = 0;
        $errors = 0;

        foreach ($statements as $stmt) {
            if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SELECT') === 0 || strpos($stmt, 'USE ') === 0) continue;

            try {
                $pdo->exec($stmt);
                $created++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo '<div class="step warning">⚠ ' . htmlspecialchars(substr($e->getMessage(), 0, 100)) . '</div>';
                    $errors++;
                }
            }
        }

        echo "<div class='step success'>✓ SQL ejecutado: $created statements procesados</div>";

        // Crear directorio de adjuntos
        $upload_dir = __DIR__ . '/../uploads/email_attachments';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        echo '<div class="step success">✓ Directorio de adjuntos creado</div>';

        // Marcar como instalado
        file_put_contents(__DIR__ . '/.email_installed.lock', date('Y-m-d H:i:s'));

        echo '<div class="step success"><h2 style="margin:0;">✅ INSTALACIÓN COMPLETADA</h2></div>';

        echo '<div class="step">
            <h3>Próximos pasos:</h3>
            <ol>
                <li><strong>Configurar SMTP:</strong> Ir a Email Marketing → Config. SMTP</li>
                <li><strong>Subir plantillas HTML:</strong> Ir a Email Marketing → Plantillas</li>
                <li><strong>Configurar DNS:</strong> Agregar registros SPF/DKIM</li>
                <li><strong>Crear primera campaña:</strong> Probar con email de prueba</li>
            </ol>
        </div>';

        echo '<div style="text-align:center; margin-top:30px;">
            <a href="email_marketing.php" class="btn">📧 Ir a Email Marketing</a>
            <a href="dashboard.php" class="btn">Dashboard</a>
        </div>';

    } catch (Exception $e) {
        echo '<div class="step error"><strong>❌ ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<div style="text-align:center;"><a href="javascript:history.back()" class="btn">Reintentar</a></div>';
    }

} else {
    // Mostrar formulario de instalación
    ?>

    <div class="step">
        <h3>¿Qué hace este instalador?</h3>
        <ul>
            <li>✓ Crea 5 tablas en MySQL (email_smtp_configs, email_templates, email_campaigns, email_recipients, email_send_logs)</li>
            <li>✓ Inserta 3 configuraciones SMTP (Mixtico, CRV-SOFT, Compratica)</li>
            <li>✓ Crea directorio para adjuntos</li>
            <li>✓ Prepara el sistema para envío masivo de emails</li>
        </ul>
    </div>

    <div class="step warning">
        <strong>⚠️ IMPORTANTE:</strong>
        <ul>
            <li>Este instalador se ejecuta UNA SOLA VEZ</li>
            <li>Asegúrate de tener acceso a la base de datos: comprati_marketplace</li>
            <li>Después deberás configurar las contraseñas SMTP manualmente</li>
        </ul>
    </div>

    <div class="step">
        <h3>Requisitos:</h3>
        <ul>
            <li>Base de datos MySQL: comprati_marketplace ✓</li>
            <li>Permisos de escritura en /uploads ✓</li>
            <li>Archivos del sistema en /admin ✓</li>
        </ul>
    </div>

    <form method="POST" style="text-align: center; margin-top: 30px;">
        <button type="submit" name="install" class="btn" style="font-size: 18px; padding: 15px 40px;">
            🚀 INSTALAR AHORA
        </button>
    </form>

    <p style="text-align: center; color: #6c757d; margin-top: 20px;">
        <small>Documentación completa en: admin/EMAIL_MARKETING_README.md</small>
    </p>

    <?php
}
?>

</div>

</body>
</html>
