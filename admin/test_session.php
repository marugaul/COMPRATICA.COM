<?php
/**
 * Test de sesión para diagnóstico
 * Ayuda a verificar que las sesiones funcionen correctamente
 */

// Iniciar sesión
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Si hay un parámetro para setear algo
if (isset($_GET['set'])) {
    $_SESSION['test_value'] = $_GET['set'];
    $_SESSION['test_time'] = time();
}

// Si hay un parámetro para limpiar
if (isset($_GET['clear'])) {
    session_destroy();
    header('Location: test_session.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test de Sesión</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .box { background: #0f172a; padding: 20px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #60a5fa; }
        .success { border-left-color: #10b981; }
        .error { border-left-color: #ef4444; }
        .warning { border-left-color: #f59e0b; }
        h1 { color: #60a5fa; }
        h2 { color: #94a3b8; font-size: 18px; margin-top: 20px; }
        code { background: #334155; padding: 2px 6px; border-radius: 4px; }
        pre { background: #334155; padding: 12px; border-radius: 6px; overflow-x: auto; }
        .btn {
            display: inline-block;
            background: #60a5fa;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px;
        }
        .btn:hover { background: #3b82f6; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #60a5fa; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Sesión PHP</h1>

    <div class="box <?php echo session_status() === PHP_SESSION_ACTIVE ? 'success' : 'error'; ?>">
        <h2>Estado de Sesión</h2>
        <table>
            <tr>
                <th>Parámetro</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>Estado de Sesión</td>
                <td><strong><?php
                    switch(session_status()) {
                        case PHP_SESSION_DISABLED: echo '❌ DESHABILITADA'; break;
                        case PHP_SESSION_NONE: echo '⚠️ NO INICIADA'; break;
                        case PHP_SESSION_ACTIVE: echo '✅ ACTIVA'; break;
                    }
                ?></strong></td>
            </tr>
            <tr>
                <td>Session ID</td>
                <td><code><?php echo session_id() ?: 'N/A'; ?></code></td>
            </tr>
            <tr>
                <td>Nombre de Sesión</td>
                <td><code><?php echo session_name(); ?></code></td>
            </tr>
            <tr>
                <td>Cookie Enviada</td>
                <td><?php echo isset($_COOKIE[session_name()]) ? '✅ SÍ' : '❌ NO'; ?></td>
            </tr>
            <tr>
                <td>Cookie Value (primeros 10 chars)</td>
                <td><code><?php echo isset($_COOKIE[session_name()]) ? substr($_COOKIE[session_name()], 0, 10) . '...' : 'N/A'; ?></code></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h2>Variables de Sesión</h2>
        <?php if (empty($_SESSION)): ?>
            <p>⚠️ No hay variables en <code>$_SESSION</code></p>
        <?php else: ?>
            <pre><?php print_r($_SESSION); ?></pre>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Cookies Recibidas</h2>
        <?php if (empty($_COOKIE)): ?>
            <p>⚠️ No se recibieron cookies</p>
        <?php else: ?>
            <pre><?php print_r($_COOKIE); ?></pre>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Configuración de Cookie de Sesión</h2>
        <pre><?php
        $params = session_get_cookie_params();
        print_r($params);
        ?></pre>
    </div>

    <div class="box">
        <h2>Test de Persistencia</h2>
        <p>Usa estos enlaces para probar si la sesión persiste entre páginas:</p>
        <a href="?set=<?php echo time(); ?>" class="btn">Guardar Valor (timestamp)</a>
        <a href="test_session.php" class="btn">Recargar Página</a>
        <a href="?clear=1" class="btn" style="background:#ef4444;">Destruir Sesión</a>

        <?php if (isset($_SESSION['test_value'])): ?>
            <div class="box success" style="margin-top:15px;">
                <strong>✅ Valor guardado:</strong> <?php echo htmlspecialchars($_SESSION['test_value']); ?><br>
                <strong>Guardado hace:</strong> <?php echo time() - ($_SESSION['test_time'] ?? time()); ?> segundos
            </div>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Información del Servidor</h2>
        <table>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td>Session Save Path</td>
                <td><code><?php echo session_save_path() ?: ini_get('session.save_path'); ?></code></td>
            </tr>
            <tr>
                <td>Session Handler</td>
                <td><?php echo ini_get('session.save_handler'); ?></td>
            </tr>
            <tr>
                <td>Cookie Lifetime</td>
                <td><?php echo ini_get('session.cookie_lifetime'); ?> segundos</td>
            </tr>
            <tr>
                <td>GC Max Lifetime</td>
                <td><?php echo ini_get('session.gc_maxlifetime'); ?> segundos</td>
            </tr>
        </table>
    </div>

    <div class="box warning">
        <h2>⚠️ Problemas Comunes</h2>
        <ul>
            <li>Si la cookie no se envía: verifica que no haya espacios/output antes de session_start()</li>
            <li>Si el Session ID cambia en cada recarga: problema con permisos de session.save_path</li>
            <li>Si $_SESSION está vacía después de guardar: verifica que session_commit() o session_write_close() se llame</li>
            <li>Si todo parece correcto pero no funciona en el admin: verifica la configuración de dominio de la cookie</li>
        </ul>
    </div>

    <p style="margin-top:30px;"><a href="login.php" class="btn">← Volver al Login</a></p>
</body>
</html>
