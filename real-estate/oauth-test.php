<?php
/**
 * Página de diagnóstico de Google OAuth
 * Para verificar la configuración de OAuth antes de ir a producción
 */

require_once __DIR__ . '/../includes/config.php';

// Construir URL de redirección
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'];
$redirectUri = $protocol . $host . '/real-estate/oauth-callback.php';

$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$GOOGLE_CLIENT_SECRET = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Diagnóstico OAuth — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #002b7f;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-300: #cbd5e0;
      --bg: #f8f9fa;
      --success: #27ae60;
      --danger: #e74c3c;
      --warning: #f39c12;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--dark);
      padding: 2rem;
    }
    .container {
      max-width: 800px;
      margin: 0 auto;
      background: var(--white);
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h1 {
      color: var(--primary);
      margin-bottom: 1.5rem;
      border-bottom: 2px solid var(--gray-300);
      padding-bottom: 1rem;
    }
    h2 {
      color: var(--gray-700);
      margin: 1.5rem 0 1rem;
      font-size: 1.25rem;
    }
    .status-box {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      font-weight: 500;
    }
    .status-box.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .status-box.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .status-box.warning {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }
    .config-item {
      margin: 1rem 0;
      padding: 1rem;
      background: var(--bg);
      border-radius: 8px;
      font-family: monospace;
    }
    .config-label {
      font-weight: 600;
      color: var(--gray-700);
      margin-bottom: 0.5rem;
    }
    .config-value {
      word-break: break-all;
      color: var(--dark);
      background: var(--white);
      padding: 0.5rem;
      border-radius: 4px;
      border: 1px solid var(--gray-300);
    }
    .hidden-value {
      color: #999;
      font-style: italic;
    }
    .instructions {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 1rem;
      margin: 1.5rem 0;
      border-radius: 4px;
    }
    .instructions h3 {
      margin: 0 0 0.5rem 0;
      color: #1976d2;
    }
    .instructions ol {
      margin-left: 1.5rem;
    }
    .instructions li {
      margin: 0.5rem 0;
    }
    code {
      background: var(--gray-300);
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.9rem;
    }
    .btn {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      background: var(--primary);
      color: var(--white);
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      margin-top: 1rem;
    }
    .btn:hover {
      background: #001d5c;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔍 Diagnóstico de Google OAuth</h1>

    <h2>Estado de Configuración</h2>

    <?php if (empty($GOOGLE_CLIENT_ID) || empty($GOOGLE_CLIENT_SECRET)): ?>
      <div class="status-box error">
        <strong>❌ Error:</strong> Las credenciales de Google OAuth no están configuradas.
      </div>
    <?php else: ?>
      <div class="status-box success">
        <strong>✅ Correcto:</strong> Las credenciales de Google OAuth están configuradas.
      </div>
    <?php endif; ?>

    <h2>Configuración Actual</h2>

    <div class="config-item">
      <div class="config-label">Google Client ID:</div>
      <div class="config-value">
        <?php if (!empty($GOOGLE_CLIENT_ID)): ?>
          <?php echo htmlspecialchars($GOOGLE_CLIENT_ID); ?>
        <?php else: ?>
          <span class="hidden-value">(No configurado)</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="config-item">
      <div class="config-label">Google Client Secret:</div>
      <div class="config-value">
        <?php if (!empty($GOOGLE_CLIENT_SECRET)): ?>
          <span class="hidden-value">••••••••••••••••••••••••</span> (Configurado)
        <?php else: ?>
          <span class="hidden-value">(No configurado)</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="config-item">
      <div class="config-label">Redirect URI (DEBE COINCIDIR CON GOOGLE CLOUD CONSOLE):</div>
      <div class="config-value" style="background: #fffacd; border-color: var(--warning);">
        <strong><?php echo htmlspecialchars($redirectUri); ?></strong>
      </div>
    </div>

    <div class="instructions">
      <h3>📝 Instrucciones de Configuración</h3>
      <ol>
        <li>Andá a <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></li>
        <li>Seleccioná tu proyecto o creá uno nuevo</li>
        <li>Habilitá la API de Google+ (People API)</li>
        <li>Creá credenciales OAuth 2.0:
          <ul>
            <li>Tipo de aplicación: <code>Aplicación web</code></li>
            <li><strong>URI de redirección autorizados:</strong> <code><?php echo htmlspecialchars($redirectUri); ?></code></li>
          </ul>
        </li>
        <li>Copiá el Client ID y Client Secret</li>
        <li>Pegálos en <code>/includes/config.local.php</code></li>
      </ol>
    </div>

    <h2>Errores Comunes</h2>

    <div class="status-box warning">
      <strong>⚠️ Redirect URI Mismatch</strong><br>
      Si ves este error, el URI configurado en Google Cloud Console NO coincide con:<br>
      <code><?php echo htmlspecialchars($redirectUri); ?></code><br>
      <small>Nota: Debe coincidir EXACTAMENTE (incluido http/https, con o sin www, con o sin barra al final)</small>
    </div>

    <div class="status-box warning">
      <strong>⚠️ Invalid Client</strong><br>
      El Client ID o Client Secret son incorrectos. Verificá que estén bien copiados.
    </div>

    <div class="status-box warning">
      <strong>⚠️ Access Denied</strong><br>
      El usuario canceló la autorización o no dio permiso a la aplicación.
    </div>

    <h2>Verificación de Ambiente</h2>

    <div class="config-item">
      <div class="config-label">Protocolo:</div>
      <div class="config-value"><?php echo $protocol === 'https://' ? '✅ HTTPS (Recomendado)' : '⚠️ HTTP (Cambiar a HTTPS en producción)'; ?></div>
    </div>

    <div class="config-item">
      <div class="config-label">Host:</div>
      <div class="config-value"><?php echo htmlspecialchars($host); ?></div>
    </div>

    <div class="config-item">
      <div class="config-label">cURL disponible:</div>
      <div class="config-value"><?php echo function_exists('curl_init') ? '✅ Sí' : '❌ No (requerido)'; ?></div>
    </div>

    <div class="config-item">
      <div class="config-label">Sesiones funcionando:</div>
      <div class="config-value"><?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ Sí' : '⚠️ No'; ?></div>
    </div>

    <a href="register.php" class="btn">← Volver a Registro</a>
    <a href="oauth-start.php" class="btn" style="background: #27ae60;">Probar OAuth</a>
  </div>
</body>
</html>
