<?php
/**
 * Script de diagnóstico para la barra de progreso
 */
require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Barra de Progreso</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; max-width: 1200px; margin: 0 auto; }
        .success { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 10px 0; }
        .error { background: #fee; border-left: 4px solid #dc2626; padding: 15px; margin: 10px 0; }
        .info { background: #eff6ff; border-left: 4px solid #0891b2; padding: 15px; margin: 10px 0; }
        pre { background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 5px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3b82f6; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; margin: 5px; cursor: pointer; border: none; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Barra de Progreso</h1>

    <?php
    // Test 1: Verificar última campaña
    echo '<div class="test-section">';
    echo '<h2>Test 1: Última Campaña Creada</h2>';

    $last_campaign = $pdo->query("
        SELECT * FROM email_campaigns
        ORDER BY created_at DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($last_campaign) {
        echo '<div class="success">✅ Se encontró la última campaña</div>';
        echo '<table>';
        echo '<tr><th>ID</th><td>' . $last_campaign['id'] . '</td></tr>';
        echo '<tr><th>Nombre</th><td>' . htmlspecialchars($last_campaign['name']) . '</td></tr>';
        echo '<tr><th>Estado</th><td>' . $last_campaign['status'] . '</td></tr>';
        echo '<tr><th>Tipo Envío</th><td>' . $last_campaign['send_type'] . '</td></tr>';
        echo '<tr><th>Creada</th><td>' . $last_campaign['created_at'] . '</td></tr>';
        echo '</table>';

        // Test 2: Verificar destinatarios
        echo '<h2>Test 2: Destinatarios de la Campaña #' . $last_campaign['id'] . '</h2>';

        $stats = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM email_recipients
            WHERE campaign_id = ?
        ");
        $stats->execute([$last_campaign['id']]);
        $recipient_stats = $stats->fetch(PDO::FETCH_ASSOC);

        if ($recipient_stats['total'] > 0) {
            echo '<div class="success">✅ Se encontraron destinatarios</div>';
            echo '<table>';
            echo '<tr><th>Total</th><td>' . $recipient_stats['total'] . '</td></tr>';
            echo '<tr><th>Enviados</th><td>' . $recipient_stats['sent'] . '</td></tr>';
            echo '<tr><th>Fallidos</th><td>' . $recipient_stats['failed'] . '</td></tr>';
            echo '<tr><th>Pendientes</th><td>' . $recipient_stats['pending'] . '</td></tr>';
            echo '</table>';
        } else {
            echo '<div class="error">❌ No se encontraron destinatarios para esta campaña</div>';
        }

        // Test 3: Test del endpoint de progreso
        echo '<h2>Test 3: Endpoint de Progreso</h2>';
        echo '<button class="btn" onclick="testProgressEndpoint(' . $last_campaign['id'] . ')">Probar Endpoint</button>';
        echo '<div id="endpointResult"></div>';

        // Test 4: Logs recientes
        echo '<h2>Test 4: Logs de Envío</h2>';

        $logs = $pdo->prepare("
            SELECT r.email, r.status, l.error_message, l.created_at
            FROM email_recipients r
            LEFT JOIN email_send_logs l ON l.recipient_id = r.id
            WHERE r.campaign_id = ? AND r.status IN ('sent', 'failed')
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        $logs->execute([$last_campaign['id']]);
        $recent_logs = $logs->fetchAll(PDO::FETCH_ASSOC);

        if (count($recent_logs) > 0) {
            echo '<div class="success">✅ Se encontraron ' . count($recent_logs) . ' logs</div>';
            echo '<table>';
            echo '<tr><th>Email</th><th>Estado</th><th>Error</th><th>Fecha</th></tr>';
            foreach ($recent_logs as $log) {
                $icon = $log['status'] === 'sent' ? '✅' : '❌';
                echo '<tr>';
                echo '<td>' . $icon . ' ' . htmlspecialchars($log['email']) . '</td>';
                echo '<td>' . $log['status'] . '</td>';
                echo '<td>' . htmlspecialchars($log['error_message'] ?? '-') . '</td>';
                echo '<td>' . $log['created_at'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="info">ℹ️ No hay logs de envío todavía</div>';
        }

    } else {
        echo '<div class="error">❌ No se encontraron campañas en la base de datos</div>';
    }
    echo '</div>';

    // Test 5: Verificar estructura de tabla
    echo '<div class="test-section">';
    echo '<h2>Test 5: Estructura de Tablas</h2>';

    $tables_to_check = ['email_campaigns', 'email_recipients', 'email_send_logs'];
    foreach ($tables_to_check as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($exists) {
            echo '<div class="success">✅ Tabla <code>' . $table . '</code> existe</div>';
        } else {
            echo '<div class="error">❌ Tabla <code>' . $table . '</code> NO existe</div>';
        }
    }
    echo '</div>';
    ?>

    <script>
    async function testProgressEndpoint(campaignId) {
        const resultDiv = document.getElementById('endpointResult');
        resultDiv.innerHTML = '<div class="info">⏳ Probando endpoint...</div>';

        try {
            const response = await fetch(`email_marketing_api.php?action=get_campaign_progress&campaign_id=${campaignId}`);
            const data = await response.json();

            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="success">✅ Endpoint funcionando correctamente</div>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="error">❌ Error en el endpoint: ${data.error || 'Desconocido'}</div>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            }
        } catch (error) {
            resultDiv.innerHTML = `
                <div class="error">❌ Error de conexión: ${error.message}</div>
            `;
        }
    }
    </script>

    <div class="test-section">
        <h2>📝 Instrucciones</h2>
        <ol>
            <li>Este script verifica la configuración de la barra de progreso</li>
            <li>Revisa la última campaña creada y sus destinatarios</li>
            <li>Prueba el endpoint de progreso</li>
            <li>Muestra los logs de envío</li>
        </ol>
        <p><strong>Si algún test falla, ese es el problema que necesita ser corregido.</strong></p>
        <p><a href="email_marketing.php?page=new-campaign" class="btn">← Volver a Crear Campaña</a></p>
    </div>

</body>
</html>
