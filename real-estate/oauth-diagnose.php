<?php
// real-estate/oauth-diagnose.php
// Página de diagnóstico para verificar la configuración de Google OAuth

session_start();
require_once __DIR__ . '/../includes/config.php';

$issues = [];
$warnings = [];
$success = [];

// 1. Verificar que las credenciales estén definidas
if (!defined('GOOGLE_CLIENT_ID') || empty(GOOGLE_CLIENT_ID)) {
    $issues[] = 'GOOGLE_CLIENT_ID no está configurado';
} else {
    $success[] = 'GOOGLE_CLIENT_ID configurado: ' . substr(GOOGLE_CLIENT_ID, 0, 30) . '...';
}

if (!defined('GOOGLE_CLIENT_SECRET') || empty(GOOGLE_CLIENT_SECRET)) {
    $issues[] = 'GOOGLE_CLIENT_SECRET no está configurado';
} else {
    $success[] = 'GOOGLE_CLIENT_SECRET configurado: ' . substr(GOOGLE_CLIENT_SECRET, 0, 15) . '...';
}

// 2. Verificar que el archivo config.local.php existe
if (!file_exists(__DIR__ . '/../includes/config.local.php')) {
    $issues[] = 'El archivo includes/config.local.php no existe';
} else {
    $success[] = 'Archivo config.local.php existe';
}

// 3. Verificar HTTPS
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (!$isHttps) {
    $warnings[] = 'No se detectó HTTPS - Google OAuth requiere HTTPS en producción';
} else {
    $success[] = 'HTTPS detectado correctamente';
}

// 4. Construir y verificar redirect URI
$baseUrl = ($isHttps ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$redirectUri = $baseUrl . '/real-estate/oauth-callback.php';
$success[] = 'Redirect URI que se usará: ' . $redirectUri;

// 5. Verificar que los archivos necesarios existen
$requiredFiles = [
    'oauth-start.php' => __DIR__ . '/oauth-start.php',
    'oauth-callback.php' => __DIR__ . '/oauth-callback.php',
    'login.php' => __DIR__ . '/login.php',
    'register.php' => __DIR__ . '/register.php'
];

foreach ($requiredFiles as $name => $path) {
    if (!file_exists($path)) {
        $issues[] = "Archivo faltante: $name";
    } else {
        $success[] = "Archivo presente: $name";
    }
}

// 6. Verificar tabla de base de datos
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    $stmt = $pdo->query("SELECT COUNT(*) FROM real_estate_agents");
    $count = $stmt->fetchColumn();
    $success[] = "Tabla real_estate_agents existe (agentes registrados: $count)";
} catch (Exception $e) {
    $issues[] = 'Error al acceder a la tabla real_estate_agents: ' . $e->getMessage();
}

// 7. Verificar sesiones
if (session_status() === PHP_SESSION_ACTIVE) {
    $success[] = 'Sesiones PHP funcionando correctamente';
} else {
    $issues[] = 'Problema con las sesiones PHP';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico OAuth - Bienes Raíces</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #002b7f, #0041b8);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #002b7f;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section {
            margin: 2rem 0;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        .section h2 {
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        .item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .icon { font-size: 1.25rem; }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #002b7f;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #001d5c;
        }
        .status-summary {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 2rem;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-issues {
            background: #f8d7da;
            color: #721c24;
        }
        code {
            background: #f5f5f5;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span style="font-size: 2rem;">🔍</span>
            Diagnóstico Google OAuth - Bienes Raíces
        </h1>

        <?php if (empty($issues)): ?>
            <div class="status-summary status-ok">
                ✅ Todo configurado correctamente
            </div>
        <?php else: ?>
            <div class="status-summary status-issues">
                ⚠️ Se encontraron <?php echo count($issues); ?> problema(s)
            </div>
        <?php endif; ?>

        <!-- Problemas críticos -->
        <?php if (!empty($issues)): ?>
            <div class="section">
                <h2>❌ Problemas que deben resolverse</h2>
                <?php foreach ($issues as $issue): ?>
                    <div class="item error">
                        <span class="icon">✖</span>
                        <span><?php echo htmlspecialchars($issue); ?></span>
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
                        <span class="icon">⚠</span>
                        <span><?php echo htmlspecialchars($warning); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Verificaciones exitosas -->
        <?php if (!empty($success)): ?>
            <div class="section">
                <h2>✅ Configuración correcta</h2>
                <?php foreach ($success as $item): ?>
                    <div class="item success">
                        <span class="icon">✓</span>
                        <span><?php echo htmlspecialchars($item); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Instrucciones -->
        <div class="section">
            <h2>📋 URIs que deben estar en Google Cloud Console</h2>
            <p style="margin-bottom: 1rem;">
                Asegurate de que estos URIs estén configurados en:
                <br><code>Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs</code>
            </p>
            <div class="item success">
                <span class="icon">🔗</span>
                <code><?php echo htmlspecialchars($redirectUri); ?></code>
            </div>
        </div>

        <!-- Acciones -->
        <div style="text-align: center; margin-top: 2rem;">
            <?php if (empty($issues)): ?>
                <a href="/real-estate/oauth-start.php" class="btn">
                    🚀 Probar Login con Google
                </a>
            <?php endif; ?>
            <a href="/real-estate/login.php" class="btn" style="background: #6c757d;">
                ← Volver al Login
            </a>
        </div>
    </div>
</body>
</html>
