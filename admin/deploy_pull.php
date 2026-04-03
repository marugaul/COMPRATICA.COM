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

// Token: via POST param, o archivo local, o env var
$ghToken = '';
if (!empty($_POST['gh_token'])) {
    $ghToken = trim($_POST['gh_token']);
    // Guardar para futuras ejecuciones
    $tokenFile = __DIR__ . '/../.git_deploy_token';
    file_put_contents($tokenFile, $ghToken);
    echo "Token guardado en .git_deploy_token\n\n";
} else {
    $tokenFile = __DIR__ . '/../.git_deploy_token';
    $ghToken = file_exists($tokenFile) ? trim(file_get_contents($tokenFile)) : (getenv('GH_DEPLOY_TOKEN') ?: '');
}

if (!$ghToken) {
    echo "ERROR: No hay token de GitHub. Envíalo via POST[gh_token].\n";
    echo "</pre>";

    // Mostrar formulario
    echo '<form method="post">
<input type="hidden" name="token_get" value="' . htmlspecialchars($token) . '">
<label>GitHub Token: <input type="text" name="gh_token" size="50" placeholder="ghp_..."></label>
<button type="submit">Deploy</button>
</form>';
    // Re-agregar token al action
    echo '<script>document.querySelector("form").action = "?" + new URLSearchParams(window.location.search);</script>';
    exit;
}

$remote = "https://{$ghToken}@github.com/marugaul/COMPRATICA.COM.git";

// Cambiar al directorio del repo
chdir($repoDir);
echo "Directorio: " . getcwd() . "\n\n";

// git fetch with token
echo "--- git fetch ---\n";
$output = shell_exec("timeout 30 git fetch \"{$remote}\" 2>&1");
echo htmlspecialchars($output) . "\n";

// git checkout branch
echo "--- git checkout $branch ---\n";
$output = shell_exec("timeout 10 git checkout $branch 2>&1");
echo htmlspecialchars($output) . "\n";

// git pull with token
echo "--- git pull ---\n";
$output = shell_exec("timeout 30 git pull \"{$remote}\" $branch 2>&1");
echo htmlspecialchars($output) . "\n";

echo "=== FIN ===\n";
echo "</pre>";

