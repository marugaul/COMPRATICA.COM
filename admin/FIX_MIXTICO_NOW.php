<?php
// ============================================
// ARREGLAR MIXTICO AHORA - Actualización Directa BD
// Cambia TLS a SSL para puerto 465
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION['is_admin'] = true;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Mixtico</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; max-width: 900px; margin: 0 auto; }
        .step { margin: 20px 0; padding: 20px; background: #111; border-left: 4px solid #0f0; }
        .ok { color: #0f0; font-weight: bold; }
        .error { color: #f00; }
        .warn { color: #ff0; }
        pre { background: #222; padding: 15px; overflow: auto; }
        h1 { color: #0ff; text-align: center; }
        .big { font-size: 24px; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>

<h1>🔧 ARREGLAR CONFIGURACIÓN MIXTICO</h1>

<?php

try {
    // Conectar directamente
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=comprati_marketplace;charset=utf8mb4",
        "comprati_places_user",
        "Marden7i/",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<div class='step'>";
    echo "<h3>PASO 1: Buscar Config de Mixtico</h3>";

    $stmt = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'");
    $mixtico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mixtico) {
        throw new Exception("No se encontró configuración para info@mixtico.net");
    }

    echo "<p><strong>Config ANTES:</strong></p>";
    echo "<pre>";
    echo "ID: {$mixtico['id']}\n";
    echo "Host: {$mixtico['smtp_host']}\n";
    echo "Puerto: {$mixtico['smtp_port']}\n";
    echo "Usuario: {$mixtico['smtp_username']}\n";
    echo "Encriptación: {$mixtico['smtp_encryption']} ";

    if ($mixtico['smtp_port'] == 465 && $mixtico['smtp_encryption'] == 'tls') {
        echo "<span class='error'>← INCORRECTO (debe ser SSL)</span>\n";
    } else {
        echo "<span class='ok'>← CORRECTO</span>\n";
    }

    echo "</pre>";
    echo "</div>";

    // Verificar si necesita actualización
    if ($mixtico['smtp_port'] == 465 && $mixtico['smtp_encryption'] !== 'ssl') {
        echo "<div class='step'>";
        echo "<h3>PASO 2: Actualizar a SSL</h3>";

        $pdo->prepare("UPDATE email_smtp_configs SET smtp_encryption = 'ssl' WHERE id = ?")
            ->execute([$mixtico['id']]);

        echo "<p class='ok big'>✓✓✓ ACTUALIZADO A SSL ✓✓✓</p>";
        echo "</div>";

        // Verificar cambio
        $stmt = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'");
        $mixtico_updated = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div class='step'>";
        echo "<h3>PASO 3: Verificar Cambio</h3>";
        echo "<pre>";
        echo "ID: {$mixtico_updated['id']}\n";
        echo "Host: {$mixtico_updated['smtp_host']}\n";
        echo "Puerto: {$mixtico_updated['smtp_port']}\n";
        echo "Encriptación: <span class='ok'>{$mixtico_updated['smtp_encryption']} ← CORREGIDO</span>\n";
        echo "</pre>";
        echo "</div>";

    } elseif ($mixtico['smtp_port'] == 587 && $mixtico['smtp_encryption'] !== 'tls') {
        echo "<div class='step'>";
        echo "<h3>PASO 2: Actualizar a TLS</h3>";

        $pdo->prepare("UPDATE email_smtp_configs SET smtp_encryption = 'tls' WHERE id = ?")
            ->execute([$mixtico['id']]);

        echo "<p class='ok big'>✓✓✓ ACTUALIZADO A TLS ✓✓✓</p>";
        echo "</div>";

    } else {
        echo "<div class='step'>";
        echo "<h3>PASO 2: Sin Cambios Necesarios</h3>";
        echo "<p class='ok'>✓ La configuración ya es correcta</p>";
        echo "</div>";
    }

    // Resumen final
    echo "<div class='step' style='border-left-color:#0a5;background:#064e3b'>";
    echo "<h2 style='color:#6ee7b7'>✅ CONFIGURACIÓN FINAL DE MIXTICO</h2>";

    $final = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'")->fetch(PDO::FETCH_ASSOC);

    echo "<ul style='font-size:16px;line-height:2'>";
    echo "<li><strong>Host:</strong> {$final['smtp_host']}</li>";
    echo "<li><strong>Puerto:</strong> {$final['smtp_port']}</li>";
    echo "<li><strong>Encriptación:</strong> <span class='ok'>" . strtoupper($final['smtp_encryption']) . "</span></li>";
    echo "<li><strong>Usuario:</strong> {$final['smtp_username']}</li>";

    if ($final['smtp_port'] == 465 && $final['smtp_encryption'] == 'ssl') {
        echo "<li class='ok'>✓ Puerto 465 con SSL - CORRECTO</li>";
    } elseif ($final['smtp_port'] == 587 && $final['smtp_encryption'] == 'tls') {
        echo "<li class='ok'>✓ Puerto 587 con TLS - CORRECTO</li>";
    }

    echo "</ul>";

    echo "<p class='big' style='color:#fef3c7'>🎉 CONFIGURACIÓN CORREGIDA - LISTA PARA ENVIAR</p>";

    echo "<hr style='border-color:#10b981;margin:30px 0'>";
    echo "<p style='text-align:center;font-size:18px'>";
    echo "<a href='SEND_EMAIL_NOW.php' style='background:#0a5;color:#fff;padding:15px 30px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:bold'>📧 ENVIAR EMAIL DE PRUEBA AHORA</a>";
    echo "</p>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='border-left-color:#f00'>";
    echo "<h3 class='error'>ERROR</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

?>

<hr style="border-color:#333;margin:40px 0">
<p style="text-align:center">
    <a href="SEND_EMAIL_NOW.php" style="color:#0ff">→ Enviar Test Email</a> |
    <a href="email_marketing.php" style="color:#0ff">→ Email Marketing</a>
</p>

</body>
</html>
