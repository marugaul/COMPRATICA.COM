<?php
require_once __DIR__ . '/../includes/config.php';
if (($_GET['key'] ?? '') !== ADMIN_PASS_PLAIN) { http_response_code(403); die('Acceso denegado'); }

header('Content-Type: text/plain; charset=utf-8');
$pdo = db();

$indexes = [
    'idx_job_listings_main' =>
        'CREATE INDEX IF NOT EXISTS idx_job_listings_main
         ON job_listings(listing_type, is_active, is_featured DESC, created_at DESC)',
    'idx_job_listings_import_source' =>
        'CREATE INDEX IF NOT EXISTS idx_job_listings_import_source
         ON job_listings(import_source, created_at)',
];

foreach ($indexes as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ $name creado\n";
    } catch (Exception $e) {
        echo "❌ $name: " . $e->getMessage() . "\n";
    }
}

// Verificar índices existentes
echo "\n=== Índices en job_listings ===\n";
$rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='job_listings' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $r) echo "  $r\n";
