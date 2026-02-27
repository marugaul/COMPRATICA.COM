<?php
/**
 * Script de verificación de configuración de PayPal
 * Verifica que las credenciales estén correctamente configuradas
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación PayPal</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .masked { font-family: monospace; }
    </style>
</head>
<body>
    <h1>🔍 Verificación de Configuración de PayPal</h1>

    <div class="info">
        <strong>ℹ️ Información:</strong> Este script verifica que las credenciales de PayPal estén correctamente configuradas.
        Las credenciales sensibles se muestran parcialmente enmascaradas por seguridad.
    </div>

    <table>
        <tr>
            <th>Configuración</th>
            <th>Estado</th>
            <th>Valor</th>
        </tr>
        <tr>
            <td>Modo PayPal</td>
            <td><?php echo defined('PAYPAL_MODE') && PAYPAL_MODE ? '<span class="success">✓ Configurado</span>' : '<span class="error">✗ No configurado</span>'; ?></td>
            <td><?php echo PAYPAL_MODE; ?></td>
        </tr>
        <tr>
            <td>Client ID</td>
            <td><?php echo defined('PAYPAL_CLIENT_ID') && !empty(PAYPAL_CLIENT_ID) ? '<span class="success">✓ Configurado</span>' : '<span class="error">✗ No configurado</span>'; ?></td>
            <td class="masked"><?php
                if (defined('PAYPAL_CLIENT_ID') && !empty(PAYPAL_CLIENT_ID)) {
                    echo substr(PAYPAL_CLIENT_ID, 0, 20) . '...' . substr(PAYPAL_CLIENT_ID, -10);
                } else {
                    echo '(vacío)';
                }
            ?></td>
        </tr>
        <tr>
            <td>Secret</td>
            <td><?php echo defined('PAYPAL_SECRET') && !empty(PAYPAL_SECRET) ? '<span class="success">✓ Configurado</span>' : '<span class="error">✗ No configurado</span>'; ?></td>
            <td class="masked"><?php
                if (defined('PAYPAL_SECRET') && !empty(PAYPAL_SECRET)) {
                    echo substr(PAYPAL_SECRET, 0, 20) . '...' . substr(PAYPAL_SECRET, -10);
                } else {
                    echo '(vacío)';
                }
            ?></td>
        </tr>
        <tr>
            <td>API URL</td>
            <td><?php echo defined('PAYPAL_API_URL') ? '<span class="success">✓ Configurado</span>' : '<span class="error">✗ No configurado</span>'; ?></td>
            <td><?php echo PAYPAL_API_URL ?? 'No definido'; ?></td>
        </tr>
    </table>

    <?php if (defined('PAYPAL_CLIENT_ID') && !empty(PAYPAL_CLIENT_ID) && defined('PAYPAL_SECRET') && !empty(PAYPAL_SECRET)): ?>
        <div class="info">
            <h3>✅ Estado General: Configuración Completa</h3>
            <p>Las credenciales de PayPal están correctamente configuradas.</p>
            <p><strong>Modo actual:</strong> <?php echo PAYPAL_MODE === 'sandbox' ? 'Sandbox (Desarrollo/Pruebas)' : 'Live (Producción)'; ?></p>
            <p><strong>URL de API:</strong> <?php echo PAYPAL_API_URL; ?></p>
        </div>

        <h2>🧪 Próximos Pasos</h2>
        <ol>
            <li>Verificar que las credenciales sean correctas probando un pago de prueba</li>
            <li>Si estás usando Sandbox, asegúrate de tener cuentas de prueba configuradas</li>
            <li>Cuando estés listo para producción, cambia <code>PAYPAL_MODE</code> a <code>'live'</code> y actualiza las credenciales</li>
        </ol>
    <?php else: ?>
        <div class="info" style="background: #fff3cd;">
            <h3>⚠️ Estado General: Configuración Incompleta</h3>
            <p>Faltan credenciales de PayPal. Por favor, configúralas en <code>/includes/config.local.php</code></p>
            <p>Ver instrucciones completas en <code>PAYPAL_CONFIG.md</code></p>
        </div>
    <?php endif; ?>

    <hr style="margin: 30px 0;">
    <p style="text-align: center; color: #666;">
        <a href="/admin/dashboard.php">← Volver al Dashboard</a>
    </p>
</body>
</html>
