<?php
// Script de despliegue - ejecutar desde el servidor de producción
// Protección básica: solo desde localhost o con token
$secret = 'deploy_' . md5('compratica_deploy_2024');
$token = $_GET['token'] ?? '';
if ($token !== $secret && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Acceso denegado');
}

echo "<pre>\n";
echo "=== DEPLOY - " . date('Y-m-d H:i:s') . " ===\n\n";

$branch = 'claude/moovin-feature-l8dLk';
$repoDir = __DIR__ . '/..';

// Cambiar al directorio del repo
chdir($repoDir);
echo "Directorio: " . getcwd() . "\n\n";

// git fetch
echo "--- git fetch ---\n";
$output = shell_exec('git fetch origin 2>&1');
echo $output . "\n";

// git checkout branch
echo "--- git checkout $branch ---\n";
$output = shell_exec("git checkout $branch 2>&1");
echo $output . "\n";

// git pull
echo "--- git pull ---\n";
$output = shell_exec("git pull origin $branch 2>&1");
echo $output . "\n";

echo "=== FIN ===\n";
echo "</pre>";
