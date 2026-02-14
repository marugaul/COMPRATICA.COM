<?php
/**
 * Diagnóstico completo de Google OAuth
 * Este archivo verifica la configuración y genera la URL de prueba correcta
 */

require_once __DIR__ . '/includes/config.php';

// Detectar el dominio actual
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
           || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$baseUrl = $protocol . $host;

// URLs de redirección que deberían estar configuradas
$redirectUris = [
    'Bienes Raíces' => $baseUrl . '/real-estate/oauth-callback.php',
    'Afiliados Login' => $baseUrl . '/affiliate/login.php',
    'Afiliados Registro' => $baseUrl . '/affiliate/register.php',
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico OAuth - Compratica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2.5rem;
        }
        h1 {
            color: #1a202c;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        h2 {
            color: #2d3748;
            font-size: 1.25rem;
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 2rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-ok { background: #c6f6d5; color: #22543d; }
        .status-error { background: #fed7d7; color: #742a2a; }
        .status-warning { background: #feebc8; color: #7c2d12; }

        .info-box {
            background: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        .info-box strong { color: #2c5282; }

        .credentials {
            background: #1a202c;
            color: #e2e8f0;
            padding: 1.25rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
        }
        .credentials code { color: #48bb78; }

        .redirect-uris {
            background: #fffaf0;
            border: 2px solid #ed8936;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .redirect-uris h3 {
            color: #c05621;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .redirect-uris ul {
            list-style: none;
            padding: 0;
        }
        .redirect-uris li {
            padding: 0.5rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .test-button {
            display: inline-block;
            background: #4299e1;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 1rem 0;
            transition: background 0.2s;
        }
        .test-button:hover { background: #3182ce; }

        .instructions {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: 4px;
        }
        .instructions h3 {
            color: #2c5282;
            margin-bottom: 1rem;
        }
        .instructions ol {
            margin-left: 1.5rem;
        }
        .instructions li {
            margin: 0.5rem 0;
            color: #2d3748;
        }

        .icon { font-size: 1.5rem; }
        .icon-ok { color: #48bb78; }
        .icon-error { color: #f56565; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            🔐 Diagnóstico de Google OAuth
        </h1>
        <p class="subtitle">Verificación de credenciales y configuración</p>

        <!-- Estado de Credenciales -->
        <h2>📋 Estado de Credenciales</h2>
        <?php
        $clientIdConfigured = defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID);
        $clientSecretConfigured = defined('GOOGLE_CLIENT_SECRET') && !empty(GOOGLE_CLIENT_SECRET);
        ?>

        <div class="info-box">
            <p>
                <strong>Client ID:</strong>
                <span class="status-badge <?php echo $clientIdConfigured ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $clientIdConfigured ? '✅ Configurado' : '❌ No configurado'; ?>
                </span>
            </p>
            <?php if ($clientIdConfigured): ?>
                <div class="credentials">
                    <code><?php echo htmlspecialchars(GOOGLE_CLIENT_ID); ?></code>
                </div>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <p>
                <strong>Client Secret:</strong>
                <span class="status-badge <?php echo $clientSecretConfigured ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $clientSecretConfigured ? '✅ Configurado' : '❌ No configurado'; ?>
                </span>
            </p>
            <?php if ($clientSecretConfigured): ?>
                <div class="credentials">
                    <code><?php echo substr(GOOGLE_CLIENT_SECRET, 0, 15) . '...' . substr(GOOGLE_CLIENT_SECRET, -5); ?></code>
                </div>
            <?php endif; ?>
        </div>

        <!-- URIs de Redirección -->
        <h2>🔗 URIs de Redirección</h2>
        <div class="redirect-uris">
            <h3>⚠️ IMPORTANTE: Estos URIs deben estar configurados en Google Cloud Console</h3>
            <p style="margin-bottom: 1rem; color: #744210;">
                Ve a: <strong>Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs</strong>
            </p>
            <ul>
                <?php foreach ($redirectUris as $name => $uri): ?>
                    <li><strong><?php echo $name; ?>:</strong><br><?php echo htmlspecialchars($uri); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Instrucciones -->
        <h2>📖 Cómo Configurar Google Cloud Console</h2>
        <div class="instructions">
            <h3>Pasos para configurar los Redirect URIs:</h3>
            <ol>
                <li>Ve a <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
                <li>Selecciona tu proyecto</li>
                <li>En "Credentials", busca tu OAuth 2.0 Client ID</li>
                <li>Haz clic en el nombre del Client ID para editarlo</li>
                <li>En "Authorized redirect URIs", agrega cada uno de los URIs mostrados arriba</li>
                <li>Guarda los cambios</li>
                <li>Espera 5-10 minutos para que los cambios se propaguen</li>
            </ol>
        </div>

        <?php if ($clientIdConfigured && $clientSecretConfigured): ?>
            <!-- Prueba OAuth -->
            <h2>🧪 Probar OAuth</h2>
            <div class="info-box">
                <p>Haz clic en el botón para probar el flujo completo de OAuth con Google:</p>
                <a href="/real-estate/oauth-start.php" class="test-button">
                    🚀 Probar Login con Google
                </a>
            </div>
        <?php else: ?>
            <div class="info-box" style="border-left-color: #f56565; background: #fff5f5;">
                <strong style="color: #c53030;">⚠️ Configuración Incompleta</strong>
                <p style="margin-top: 0.5rem;">Las credenciales de Google OAuth no están configuradas correctamente.</p>
            </div>
        <?php endif; ?>

        <!-- Información del Sistema -->
        <h2>ℹ️ Información del Sistema</h2>
        <div class="info-box">
            <p><strong>Protocolo:</strong> <?php echo $isHttps ? 'HTTPS ✅' : 'HTTP ⚠️'; ?></p>
            <p><strong>Host:</strong> <?php echo htmlspecialchars($host); ?></p>
            <p><strong>Base URL:</strong> <?php echo htmlspecialchars($baseUrl); ?></p>
        </div>
    </div>
</body>
</html>
