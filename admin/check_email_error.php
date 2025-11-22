<?php
require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== ÚLTIMAS CAMPAÑAS ===\n\n";
$stmt = $pdo->query("
    SELECT id, campaign_name, status, created_at, sent_at,
           total_recipients, sent_count, failed_count
    FROM email_campaigns
    ORDER BY id DESC
    LIMIT 5
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}\n";
    echo "Nombre: {$row['campaign_name']}\n";
    echo "Estado: {$row['status']}\n";
    echo "Total destinatarios: {$row['total_recipients']}\n";
    echo "Enviados: {$row['sent_count']}\n";
    echo "Fallidos: {$row['failed_count']}\n";
    echo "Creada: {$row['created_at']}\n";
    echo "---\n\n";
}

echo "\n=== ÚLTIMOS LOGS DE EMAIL (con errores) ===\n\n";
$stmt = $pdo->query("
    SELECT el.*, ec.campaign_name
    FROM email_logs el
    LEFT JOIN email_campaigns ec ON el.campaign_id = ec.id
    WHERE el.status = 'failed'
    ORDER BY el.id DESC
    LIMIT 10
");

if ($stmt->rowCount() > 0) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Log ID: {$row['id']}\n";
        echo "Campaña: {$row['campaign_name']} (ID: {$row['campaign_id']})\n";
        echo "Email destinatario: {$row['recipient_email']}\n";
        echo "Estado: {$row['status']}\n";
        echo "ERROR: {$row['error_message']}\n";
        echo "Fecha: {$row['sent_at']}\n";
        echo "---\n\n";
    }
} else {
    echo "No se encontraron logs con errores\n";
}
