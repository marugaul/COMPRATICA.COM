<?php
/**
 * Script para agregar el campo generic_greeting a la tabla email_campaigns
 */

require_once __DIR__ . '/../includes/config.php';

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Verificando estructura de email_campaigns...\n\n";

    // Verificar si el campo ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM email_campaigns LIKE 'generic_greeting'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "✓ El campo 'generic_greeting' YA existe en la tabla email_campaigns\n";
    } else {
        echo "⚠ El campo 'generic_greeting' NO existe. Agregándolo...\n";

        $pdo->exec("
            ALTER TABLE email_campaigns
            ADD COLUMN generic_greeting VARCHAR(255) DEFAULT 'Estimado propietario'
            AFTER subject
        ");

        echo "✓ Campo 'generic_greeting' agregado exitosamente\n";
    }

    echo "\nEstructura actualizada de email_campaigns:\n";
    $stmt = $pdo->query("DESCRIBE email_campaigns");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }

    echo "\n✅ Proceso completado\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
