<?php
// test-real-estate-oauth.php
// Herramienta de diagnóstico para OAuth de Bienes Raíces

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';

$issues = [];
$warnings = [];
$success = [];

// 1. Verificar que las constantes de Google existan
if (!defined('GOOGLE_CLIENT_ID') || empty(GOOGLE_CLIENT_ID)) {
    $issues[] = 'GOOGLE_CLIENT_ID no está definida en config.local.php';
} else {
    $success[] = 'GOOGLE_CLIENT_ID configurada: ' . substr(GOOGLE_CLIENT_ID, 0, 30) . '...';
}

if (!defined('GOOGLE_CLIENT_SECRET') || empty(GOOGLE_CLIENT_SECRET)) {
    $issues[] = 'GOOGLE_CLIENT_SECRET no está definida en config.local.php';
} else {
    $success[] = 'GOOGLE_CLIENT_SECRET configurada (longitud: ' . strlen(GOOGLE_CLIENT_SECRET) . ' caracteres)';
}

// 2. Verificar que los archivos de OAuth existan
$requiredFiles = [
    '/real-estate/oauth-start.php',
    '/real-estate/oauth-callback.php',
    '/real-estate/login.php',
    '/real-estate/register.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . $file;
    if (!file_exists($fullPath)) {
        $issues[] = "Archivo faltante: $file";
    } else {
        $success[] = "Archivo encontrado: $file";
    }
}

// 3. Construir el redirect URI que se usará
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$redirectUri = $protocol . '://' . $host . '/real-estate/oauth-callback.php';

// 4. Verificar la base de datos
try {
    require_once __DIR__ . '/includes/db.php';
    $pdo = db();

    // Verificar que la tabla real_estate_agents exista
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='real_estate_agents'");
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        $issues[] = 'La tabla real_estate_agents NO existe. Ejecuta instalar-bienes-raices-agentes.php';
    } else {
        $success[] = 'Tabla real_estate_agents existe en la base de datos';

        // Verificar estructura de la tabla
        $stmt = $pdo->query("PRAGMA table_info(real_estate_agents)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        $requiredColumns = ['id', 'name', 'email', 'phone', 'password_hash', 'is_active', 'created_at'];
        $missingColumns = array_diff($requiredColumns, $columnNames);

        if (!empty($missingColumns)) {
            $issues[] = 'Columnas faltantes en real_estate_agents: ' . implode(', ', $missingColumns);
        } else {
            $success[] = 'Todas las columnas requeridas están presentes';
        }
    }
} catch (Exception $e) {
    $issues[] = 'Error de base de datos: ' . $e->getMessage();
}

// 5. Verificar configuración de sesión
if (session_status() === PHP_SESSION_DISABLED) {
    $issues[] = 'Las sesiones están DESHABILITADAS en PHP';
} else {
    $success[] = 'Las sesiones de PHP están habilitadas';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico OAuth - Bienes Raíces</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; text-align: center; }
        .header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; }
        .content { padding: 2rem; }
        .section { margin-bottom: 2rem; }
        .section h2 { font-size: 1.5rem; color: #333; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #667eea; }
        .item { padding: 1rem; margin-bottom: 0.5rem; border-radius: 8px; display: flex; align-items: flex-start; }
        .item-icon { font-size: 1.5rem; margin-right: 1rem; flex-shrink: 0; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .success .item-icon { color: #28a745; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .warning .item-icon { color: #ffc107; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .error .item-icon { color: #dc3545; }
        .info-box { background: #e7f3ff; border: 2px solid #2196F3; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; }
        .info-box h3 { color: #1976D2; margin-bottom: 1rem; }
        .info-box code { background: #fff; padding: 0.25rem 0.5rem; border-radius: 4px; font-family: 'Courier New', monospace; color: #d63384; }
        .info-box pre { background: #fff; padding: 1rem; border-radius: 4px; overflow-x: auto; margin: 1rem 0; }
        .btn { display: inline-block; padding: 1rem 2rem; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 0.5rem; transition: all 0.3s; }
        .btn:hover { background: #5568d3; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .actions { text-align: center; padding: 2rem; background: #f8f9fa; border-top: 1px solid #dee2e6; }
        .summary { display: flex; justify-content: space-around; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 2rem; }
        .summary-item { text-align: center; }
        .summary-number { font-size: 2.5rem; font-weight: bold; }
        .summary-label { color: #6c757d; margin-top: 0.5rem; }
        .summary-item.success .summary-number { color: #28a745; }
        .summary-item.error .summary-number { color: #dc3545; }
        .summary-item.warning .summary-number { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico OAuth para Bienes Raíces</h1>
            <p>Verificación completa del sistema de autenticación con Google</p>
        </div>

        <div class="content">
            <!-- Resumen -->
            <div class="summary">
                <div class="summary-item success">
                    <div class="summary-number"><?php echo count($success); ?></div>
                    <div class="summary-label">Correctos</div>
                </div>
                <div class="summary-item warning">
                    <div class="summary-number"><?php echo count($warnings); ?></div>
                    <div class="summary-label">Advertencias</div>
                </div>
                <div class="summary-item error">
                    <div class="summary-number"><?php echo count($issues); ?></div>
                    <div class="summary-label">Problemas</div>
                </div>
            </div>

            <!-- Problemas críticos -->
            <?php if (!empty($issues)): ?>
            <div class="section">
                <h2>❌ Problemas Críticos</h2>
                <?php foreach ($issues as $issue): ?>
                <div class="item error">
                    <div class="item-icon">❌</div>
                    <div><?php echo htmlspecialchars($issue); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Advertencias -->
            <?php if (!empty($warnings)): ?>
            <div class="section">
                <h2>⚠️ Advertencias</h2>
                <?php foreach ($warnings as $warning): ?>
                <div class="item warning">
                    <div class="item-icon">⚠️</div>
                    <div><?php echo htmlspecialchars($warning); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Configuración correcta -->
            <?php if (!empty($success)): ?>
            <div class="section">
                <h2>✅ Configuración Correcta</h2>
                <?php foreach ($success as $item): ?>
                <div class="item success">
                    <div class="item-icon">✅</div>
                    <div><?php echo htmlspecialchars($item); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Configuración de Google Cloud Console -->
            <div class="info-box">
                <h3>🔧 Configuración Requerida en Google Cloud Console</h3>
                <p><strong>Para que el OAuth de Bienes Raíces funcione, debes configurar EXACTAMENTE estas URIs de redirección autorizadas:</strong></p>

                <p style="margin-top: 1rem;"><strong>Client ID actual:</strong></p>
                <pre><?php echo htmlspecialchars(GOOGLE_CLIENT_ID ?? 'No configurado'); ?></pre>

                <p style="margin-top: 1rem;"><strong>URIs de redirección que debes agregar:</strong></p>
                <pre>https://compratica.com/real-estate/oauth-callback.php
https://www.compratica.com/real-estate/oauth-callback.php</pre>

                <p style="margin-top: 1rem;"><strong>URI que se usará en este servidor:</strong></p>
                <pre><?php echo htmlspecialchars($redirectUri); ?></pre>

                <p style="margin-top: 1rem;"><strong>Pasos para configurar:</strong></p>
                <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Ve a <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: #667eea;">Google Cloud Console → Credenciales</a></li>
                    <li>Busca el Client ID: <code><?php echo htmlspecialchars(substr(GOOGLE_CLIENT_ID ?? '', 0, 30)); ?>...</code></li>
                    <li>Haz clic en editar (ícono de lápiz)</li>
                    <li>En "URIs de redirección autorizadas", agrega AMBAS URIs de arriba</li>
                    <li>Guarda los cambios</li>
                    <li>Espera 5 minutos para que se propaguen los cambios</li>
                </ol>
            </div>

            <!-- Información adicional -->
            <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                <h3 style="color: #856404;">⚠️ IMPORTANTE: Diferencia entre OAuth principal y OAuth de Bienes Raíces</h3>
                <p>Este sitio tiene DOS flujos de OAuth separados:</p>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li><strong>OAuth Principal:</strong> <code>/login.php?oauth=google</code> (para usuarios regulares)</li>
                    <li><strong>OAuth Bienes Raíces:</strong> <code>/real-estate/oauth-callback.php</code> (para agentes inmobiliarios)</li>
                </ul>
                <p style="margin-top: 1rem;"><strong>Ambas URIs deben estar configuradas en Google Cloud Console</strong> para que ambos sistemas funcionen.</p>
            </div>
        </div>

        <div class="actions">
            <a href="/real-estate/login.php" class="btn">Ir a Login de Bienes Raíces</a>
            <a href="/real-estate/register.php" class="btn btn-secondary">Ir a Registro</a>
        </div>
    </div>
</body>
</html>
