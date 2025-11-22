<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Último Error de Email</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #dc2626; padding-bottom: 10px; }
        .error { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 15px; margin: 10px 0; border-radius: 4px; }
        pre { background: #f9fafb; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico de Errores de Email</h1>

        <?php
        require_once __DIR__ . '/../includes/config.php';

        try {
            $config = require __DIR__ . '/../config/database.php';
            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            echo '<div class="success">✓ Conectado a la base de datos</div>';

            // Última campaña
            echo '<h2>📧 Última Campaña</h2>';
            $stmt = $pdo->query("
                SELECT c.*, t.name as template_name, s.smtp_username, s.smtp_host
                FROM email_campaigns c
                LEFT JOIN email_templates t ON c.template_id = t.id
                LEFT JOIN email_smtp_configs s ON c.smtp_config_id = s.id
                ORDER BY c.id DESC
                LIMIT 1
            ");
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($campaign) {
                echo '<table>';
                echo '<tr><th>Campo</th><th>Valor</th></tr>';
                echo '<tr><td>ID</td><td>' . $campaign['id'] . '</td></tr>';
                echo '<tr><td>Nombre</td><td>' . htmlspecialchars($campaign['campaign_name']) . '</td></tr>';
                echo '<tr><td>Estado</td><td><strong>' . $campaign['status'] . '</strong></td></tr>';
                echo '<tr><td>Plantilla</td><td>' . htmlspecialchars($campaign['template_name']) . '</td></tr>';
                echo '<tr><td>SMTP Host</td><td>' . $campaign['smtp_host'] . '</td></tr>';
                echo '<tr><td>SMTP User</td><td>' . $campaign['smtp_username'] . '</td></tr>';
                echo '<tr><td>Total Destinatarios</td><td>' . $campaign['total_recipients'] . '</td></tr>';
                echo '<tr><td>Enviados</td><td style="color: green;">' . $campaign['sent_count'] . '</td></tr>';
                echo '<tr><td>Fallidos</td><td style="color: red;"><strong>' . $campaign['failed_count'] . '</strong></td></tr>';
                echo '<tr><td>Creada</td><td>' . $campaign['created_at'] . '</td></tr>';
                echo '</table>';

                // Destinatarios fallidos
                if ($campaign['failed_count'] > 0) {
                    echo '<h2>❌ Destinatarios Fallidos</h2>';
                    $stmt = $pdo->prepare("
                        SELECT * FROM email_recipients
                        WHERE campaign_id = ? AND status = 'failed'
                        ORDER BY id DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$campaign['id']]);

                    echo '<table>';
                    echo '<tr><th>Email</th><th>Nombre</th><th>Error</th></tr>';
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                        echo '<td class="error"><strong>' . htmlspecialchars($row['error_message']) . '</strong></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }

                // Logs de envío
                echo '<h2>📝 Logs de Envío (últimos 10)</h2>';
                $stmt = $pdo->prepare("
                    SELECT * FROM email_send_logs
                    WHERE campaign_id = ?
                    ORDER BY id DESC
                    LIMIT 10
                ");
                $stmt->execute([$campaign['id']]);

                if ($stmt->rowCount() > 0) {
                    echo '<table>';
                    echo '<tr><th>Email</th><th>Estado</th><th>Error/Mensaje</th><th>Respuesta SMTP</th></tr>';
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $statusColor = $row['status'] === 'success' ? 'green' : 'red';
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                        echo '<td style="color: ' . $statusColor . ';"><strong>' . $row['status'] . '</strong></td>';
                        echo '<td>' . htmlspecialchars($row['error_message']) . '</td>';
                        echo '<td><small>' . htmlspecialchars($row['smtp_response']) . '</small></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="info">No hay logs de envío para esta campaña</div>';
                }

            } else {
                echo '<div class="info">No se encontraron campañas</div>';
            }

        } catch (Exception $e) {
            echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        ?>
    </div>

    <script>
    // Auto-refresh cada 10 segundos si está en modo "enviando"
    setTimeout(function() {
        location.reload();
    }, 10000);
    </script>
</body>
</html>
