<?php
// Script temporal para actualizar configuración de Mixtico directamente

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=comprati_marketplace',
    'comprati_places_user',
    'Marden7i/',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== CONFIGURACIÓN ACTUAL DE MIXTICO ===\n";
$smtp = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'")->fetch(PDO::FETCH_ASSOC);

if ($smtp) {
    echo "ID: {$smtp['id']}\n";
    echo "Host: {$smtp['smtp_host']}\n";
    echo "Puerto: {$smtp['smtp_port']}\n";
    echo "Encriptación: {$smtp['smtp_encryption']}\n";
    echo "Contraseña: " . (empty($smtp['smtp_password']) ? 'VACÍA' : 'Configurada') . "\n";

    // Verificar si necesita corrección
    if ($smtp['smtp_port'] == 465 && $smtp['smtp_encryption'] != 'ssl') {
        echo "\n❌ PROBLEMA: Puerto 465 con {$smtp['smtp_encryption']} (debe ser SSL)\n";
        echo "Corrigiendo...\n";
        $pdo->exec("UPDATE email_smtp_configs SET smtp_encryption = 'ssl' WHERE id = {$smtp['id']}");
        echo "✓ CORREGIDO: Cambiado a SSL\n";
    } elseif ($smtp['smtp_port'] == 465 && $smtp['smtp_encryption'] == 'ssl') {
        echo "\n✓ CORRECTO: Puerto 465 con SSL\n";
    }

    // Mostrar configuración final
    $final = $pdo->query("SELECT * FROM email_smtp_configs WHERE smtp_username = 'info@mixtico.net'")->fetch(PDO::FETCH_ASSOC);
    echo "\n=== CONFIGURACIÓN FINAL ===\n";
    echo "Puerto: {$final['smtp_port']} | Encriptación: " . strtoupper($final['smtp_encryption']) . "\n";

    if ($final['smtp_port'] == 465 && $final['smtp_encryption'] == 'ssl') {
        echo "✓✓✓ CONFIGURACIÓN CORRECTA - LISTA PARA USAR ✓✓✓\n";
    }
} else {
    echo "ERROR: No se encontró configuración para info@mixtico.net\n";
}
?>
