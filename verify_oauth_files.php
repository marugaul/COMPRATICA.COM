<?php
/**
 * Verificador de Archivos OAuth
 * Muestra si config.local.php existe y qué credenciales tiene
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificador de Archivos OAuth - CompraTica</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #0d1117;
            color: #c9d1d9;
            max-width: 1000px;
            margin: 0 auto;
        }
        .box {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 20px;
            margin: 15px 0;
        }
        .success { border-left: 4px solid #238636; background: #0d1e13; }
        .error { border-left: 4px solid #da3633; background: #2c0b0e; }
        .warning { border-left: 4px solid #d29922; background: #1c1710; }
        .info { border-left: 4px solid #58a6ff; background: #0c1d30; }
        h1 { color: #58a6ff; border-bottom: 1px solid #21262d; padding-bottom: 10px; }
        h3 { color: #8b949e; margin-top: 0; }
        pre {
            background: #0d1117;
            border: 1px solid #30363d;
            padding: 15px;
            overflow-x: auto;
            border-radius: 6px;
            color: #79c0ff;
        }
        .path { color: #79c0ff; font-weight: bold; }
        .exists { color: #3fb950; }
        .notexists { color: #f85149; }
        .masked { color: #ffa657; }
        code {
            background: #0d1117;
            padding: 3px 6px;
            border-radius: 3px;
            color: #79c0ff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #21262d;
        }
        th {
            color: #58a6ff;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #238636;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px;
        }
        .btn:hover {
            background: #2ea043;
        }
        .btn-secondary {
            background: #21262d;
        }
        .btn-secondary:hover {
            background: #30363d;
        }
    </style>
</head>
<body>
    <h1>🔍 Verificador de Archivos OAuth</h1>
    <p style="color: #8b949e;">Servidor: <code><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'desconocido') ?></code></p>
    <p style="color: #8b949e;">Fecha: <code><?= date('Y-m-d H:i:s') ?></code></p>

    <?php
    // Ruta del archivo config.local.php
    $configLocalPath = __DIR__ . '/includes/config.local.php';
    $configLocalExists = file_exists($configLocalPath);

    echo "<div class='box " . ($configLocalExists ? 'success' : 'error') . "'>";
    echo "<h3>📄 Archivo config.local.php</h3>";
    echo "<p><strong>Ruta:</strong> <span class='path'>" . htmlspecialchars($configLocalPath) . "</span></p>";

    if ($configLocalExists) {
        echo "<p class='exists'>✅ <strong>EXISTE</strong></p>";

        $fileSize = filesize($configLocalPath);
        $filePerms = substr(sprintf('%o', fileperms($configLocalPath)), -4);
        $fileModified = date('Y-m-d H:i:s', filemtime($configLocalPath));

        echo "<table>";
        echo "<tr><th>Propiedad</th><th>Valor</th></tr>";
        echo "<tr><td>Tamaño</td><td>" . $fileSize . " bytes</td></tr>";
        echo "<tr><td>Permisos</td><td>" . $filePerms . "</td></tr>";
        echo "<tr><td>Última modificación</td><td>" . $fileModified . "</td></tr>";
        echo "</table>";

        // Intentar cargar y verificar constantes
        require_once $configLocalPath;

        $hasGoogleId = defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '';
        $hasGoogleSecret = defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '';
        $hasFacebookId = defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '';
        $hasFacebookSecret = defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== '';

        echo "<h4 style='color: #8b949e; margin-top: 20px;'>Credenciales Definidas:</h4>";
        echo "<table>";
        echo "<tr><th>Constante</th><th>Estado</th><th>Valor (enmascarado)</th></tr>";

        if ($hasGoogleId) {
            $masked = substr(GOOGLE_CLIENT_ID, 0, 20) . '...' . substr(GOOGLE_CLIENT_ID, -10);
            echo "<tr><td>GOOGLE_CLIENT_ID</td><td class='exists'>✅ Configurado</td><td class='masked'>$masked</td></tr>";
        } else {
            echo "<tr><td>GOOGLE_CLIENT_ID</td><td class='notexists'>❌ Vacío/No definido</td><td>-</td></tr>";
        }

        if ($hasGoogleSecret) {
            $secret = GOOGLE_CLIENT_SECRET;
            $masked = substr($secret, 0, 10) . '...' . substr($secret, -5);
            echo "<tr><td>GOOGLE_CLIENT_SECRET</td><td class='exists'>✅ Configurado</td><td class='masked'>$masked</td></tr>";
        } else {
            echo "<tr><td>GOOGLE_CLIENT_SECRET</td><td class='notexists'>❌ Vacío/No definido</td><td>-</td></tr>";
        }

        if ($hasFacebookId) {
            echo "<tr><td>FACEBOOK_APP_ID</td><td class='exists'>✅ Configurado</td><td class='masked'>" . FACEBOOK_APP_ID . "</td></tr>";
        } else {
            echo "<tr><td>FACEBOOK_APP_ID</td><td class='notexists'>❌ Vacío/No definido</td><td>-</td></tr>";
        }

        if ($hasFacebookSecret) {
            $secret = FACEBOOK_APP_SECRET;
            $masked = substr($secret, 0, 8) . '...' . substr($secret, -4);
            echo "<tr><td>FACEBOOK_APP_SECRET</td><td class='exists'>✅ Configurado</td><td class='masked'>$masked</td></tr>";
        } else {
            echo "<tr><td>FACEBOOK_APP_SECRET</td><td class='notexists'>❌ Vacío/No definido</td><td>-</td></tr>";
        }

        echo "</table>";

        // Leer contenido del archivo (censurado)
        echo "<h4 style='color: #8b949e; margin-top: 20px;'>Contenido del Archivo (censurado):</h4>";
        $content = file_get_contents($configLocalPath);

        // Censurar valores sensibles
        $content = preg_replace("/define\('GOOGLE_CLIENT_ID',\s*'([^']+)'\)/", "define('GOOGLE_CLIENT_ID', '***CENSURADO***')", $content);
        $content = preg_replace("/define\('GOOGLE_CLIENT_SECRET',\s*'([^']+)'\)/", "define('GOOGLE_CLIENT_SECRET', '***CENSURADO***')", $content);
        $content = preg_replace("/define\('FACEBOOK_APP_ID',\s*'([^']+)'\)/", "define('FACEBOOK_APP_ID', '***CENSURADO***')", $content);
        $content = preg_replace("/define\('FACEBOOK_APP_SECRET',\s*'([^']+)'\)/", "define('FACEBOOK_APP_SECRET', '***CENSURADO***')", $content);

        echo "<pre>" . htmlspecialchars($content) . "</pre>";

    } else {
        echo "<p class='notexists'>❌ <strong>NO EXISTE</strong></p>";
        echo "<p>Este archivo debería existir en: <code>" . htmlspecialchars($configLocalPath) . "</code></p>";
        echo "<p>Sin este archivo, los botones de Google y Facebook NO aparecerán en el login.</p>";
    }
    echo "</div>";

    // Verificar includes/config.php
    $configPath = __DIR__ . '/includes/config.php';
    echo "<div class='box info'>";
    echo "<h3>📄 Archivo config.php (principal)</h3>";
    echo "<p><strong>Ruta:</strong> <span class='path'>" . htmlspecialchars($configPath) . "</span></p>";

    if (file_exists($configPath)) {
        echo "<p class='exists'>✅ Existe</p>";

        // Buscar la línea que carga config.local.php
        $configContent = file_get_contents($configPath);
        if (strpos($configContent, 'config.local.php') !== false) {
            echo "<p class='exists'>✅ Contiene llamada a config.local.php</p>";

            // Extraer las líneas relevantes
            preg_match('/if \(file_exists.*config\.local\.php.*\}.*require.*config\.local\.php/s', $configContent, $matches);
            if ($matches) {
                echo "<h4 style='color: #8b949e;'>Código de carga:</h4>";
                echo "<pre>" . htmlspecialchars(trim($matches[0])) . "</pre>";
            }
        } else {
            echo "<p class='notexists'>❌ NO contiene llamada a config.local.php</p>";
        }
    } else {
        echo "<p class='notexists'>❌ No existe</p>";
    }
    echo "</div>";

    // Verificar login.php
    $loginPath = __DIR__ . '/login.php';
    echo "<div class='box info'>";
    echo "<h3>📄 Archivo login.php</h3>";
    echo "<p><strong>Ruta:</strong> <span class='path'>" . htmlspecialchars($loginPath) . "</span></p>";

    if (file_exists($loginPath)) {
        echo "<p class='exists'>✅ Existe</p>";

        $loginContent = file_get_contents($loginPath);
        $hasGoogleCode = strpos($loginContent, 'handleGoogleOAuth') !== false;
        $hasFacebookCode = strpos($loginContent, 'handleFacebookOAuth') !== false;
        $hasGoogleButton = strpos($loginContent, 'Continuar con Google') !== false;

        echo "<table>";
        echo "<tr><th>Componente</th><th>Estado</th></tr>";
        echo "<tr><td>Función handleGoogleOAuth()</td><td class='" . ($hasGoogleCode ? 'exists' : 'notexists') . "'>" . ($hasGoogleCode ? '✅ Presente' : '❌ Ausente') . "</td></tr>";
        echo "<tr><td>Función handleFacebookOAuth()</td><td class='" . ($hasFacebookCode ? 'exists' : 'notexists') . "'>" . ($hasFacebookCode ? '✅ Presente' : '❌ Ausente') . "</td></tr>";
        echo "<tr><td>Botón 'Continuar con Google'</td><td class='" . ($hasGoogleButton ? 'exists' : 'notexists') . "'>" . ($hasGoogleButton ? '✅ Presente' : '❌ Ausente') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='notexists'>❌ No existe</p>";
    }
    echo "</div>";

    // Resumen final
    echo "<div class='box " . ($configLocalExists ? 'success' : 'warning') . "'>";
    echo "<h3>📊 Resumen y Diagnóstico</h3>";

    if ($configLocalExists) {
        $allConfigured = $hasGoogleId && $hasGoogleSecret && $hasFacebookId && $hasFacebookSecret;

        if ($allConfigured) {
            echo "<p class='exists'><strong>✅ OAuth COMPLETAMENTE CONFIGURADO</strong></p>";
            echo "<p>Los botones de Google y Facebook deberían aparecer en <a href='/login.php' style='color: #58a6ff;'>/login.php</a></p>";
        } else {
            echo "<p class='warning' style='color: #d29922;'><strong>⚠️ OAuth PARCIALMENTE CONFIGURADO</strong></p>";
            echo "<p>El archivo existe pero faltan algunas credenciales:</p>";
            echo "<ul>";
            if (!$hasGoogleId || !$hasGoogleSecret) echo "<li>Google: " . (!$hasGoogleId ? "Falta CLIENT_ID" : "") . (!$hasGoogleSecret ? " Falta CLIENT_SECRET" : "") . "</li>";
            if (!$hasFacebookId || !$hasFacebookSecret) echo "<li>Facebook: " . (!$hasFacebookId ? "Falta APP_ID" : "") . (!$hasFacebookSecret ? " Falta APP_SECRET" : "") . "</li>";
            echo "</ul>";
        }
    } else {
        echo "<p class='notexists'><strong>❌ OAuth NO CONFIGURADO</strong></p>";
        echo "<p>El archivo <code>config.local.php</code> no existe.</p>";
        echo "<p><strong>Acción requerida:</strong></p>";
        echo "<ol>";
        echo "<li>Crea el archivo: <code>/includes/config.local.php</code></li>";
        echo "<li>Agrega las credenciales de Google y Facebook</li>";
        echo "<li>Verifica siguiendo la guía en <a href='/OAUTH_SETUP.md' style='color: #58a6ff;'>OAUTH_SETUP.md</a></li>";
        echo "</ol>";
    }
    echo "</div>";
    ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="/login.php" class="btn">Ir a Login</a>
        <a href="/check_oauth.php" class="btn btn-secondary">Check OAuth</a>
        <a href="/index.php" class="btn btn-secondary">Inicio</a>
    </div>

    <div style="text-align: center; margin-top: 20px; color: #8b949e; font-size: 12px;">
        <p>⚠️ IMPORTANTE: Elimina este archivo después de verificar (contiene información sensible)</p>
        <p>Archivo: verify_oauth_files.php</p>
    </div>
</body>
</html>
