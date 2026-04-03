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

$branch = 'main';
$repoDir = __DIR__ . '/..';
// Token stored in non-committed config file or env variable
$tokenFile = __DIR__ . '/../.git_deploy_token';
$ghToken = file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : (getenv('GH_DEPLOY_TOKEN') ?: '');
$remote = $ghToken
    ? "https://{$ghToken}@github.com/marugaul/COMPRATICA.COM.git"
    : 'origin';

// Cambiar al directorio del repo
chdir($repoDir);
echo "Directorio: " . getcwd() . "\n\n";

// git fetch with token
echo "--- git fetch ---\n";
$output = shell_exec("timeout 30 git fetch \"{$remote}\" 2>&1");
echo $output . "\n";

// git checkout branch
echo "--- git checkout $branch ---\n";
$output = shell_exec("timeout 10 git checkout $branch 2>&1");
echo $output . "\n";

// git pull with token
echo "--- git pull ---\n";
$output = shell_exec("timeout 30 git pull \"{$remote}\" $branch 2>&1");
echo $output . "\n";

echo "=== FIN ===\n";
echo "</pre>";
