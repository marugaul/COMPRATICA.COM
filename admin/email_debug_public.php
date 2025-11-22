<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Email - COMPRATICA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 25px; }
        .error {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            color: #991b1b;
            font-weight: 500;
        }
        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            color: #065f46;
            font-weight: 500;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            color: #1e40af;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            color: #92400e;
        }
        h2 {
            color: #1f2937;
            margin: 25px 0 15px;
            font-size: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .stat.success .stat-value { color: #10b981; }
        .stat.error .stat-value { color: #dc2626; }
        .stat.pending .stat-value { color: #f59e0b; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover { background: #f9fafb; }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
            color: #dc2626;
            font-family: 'Courier New', monospace;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.error { background: #fee2e2; color: #991b1b; }
        .badge.pending { background: #fef3c7; color: #92400e; }
        .loader {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #dc2626;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .timestamp {
            color: #6b7280;
            font-size: 12px;
            text-align: center;
            padding: 15px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico Email Marketing</h1>
            <p>Estado en tiempo real de campañas</p>
        </div>

        <div class="content">
            <?php
            // NO requerir autenticación para debug
            $debugMode = true;

            try {
                // Intentar cargar config
                if (file_exists(__DIR__ . '/../config/database.php')) {
                    $config = require __DIR__ . '/../config/database.php';
                } else {
                    throw new Exception('Archivo de configuración no encontrado');
                }

                $pdo = new PDO(
                    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                    $config['username'],
                    $config['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                echo '<div class="success">✓ Conectado a la base de datos</div>';

                // Última campaña
                echo '<h2>📧 Última Campaña Enviada</h2>';
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
                    // Stats
                    echo '<div class="stat-grid">';
                    echo '<div class="stat success">';
                    echo '<div class="stat-value">' . $campaign['sent_count'] . '</div>';
                    echo '<div class="stat-label">Enviados</div>';
                    echo '</div>';

                    echo '<div class="stat error">';
                    echo '<div class="stat-value">' . $campaign['failed_count'] . '</div>';
                    echo '<div class="stat-label">Fallidos</div>';
                    echo '</div>';

                    $pending = $campaign['total_recipients'] - $campaign['sent_count'] - $campaign['failed_count'];
                    echo '<div class="stat pending">';
                    echo '<div class="stat-value">' . $pending . '</div>';
                    echo '<div class="stat-label">Pendientes</div>';
                    echo '</div>';

                    echo '<div class="stat">';
                    echo '<div class="stat-value" style="color: #6b7280;">' . $campaign['total_recipients'] . '</div>';
                    echo '<div class="stat-label">Total</div>';
                    echo '</div>';
                    echo '</div>';

                    // Info de campaña
                    echo '<div class="info">';
                    echo '<strong>Campaña:</strong> ' . htmlspecialchars($campaign['campaign_name']) . '<br>';
                    echo '<strong>Estado:</strong> <span class="badge ' . $campaign['status'] . '">' . strtoupper($campaign['status']) . '</span><br>';
                    echo '<strong>Plantilla:</strong> ' . htmlspecialchars($campaign['template_name']) . '<br>';
                    echo '<strong>SMTP:</strong> ' . htmlspecialchars($campaign['smtp_username']) . ' @ ' . htmlspecialchars($campaign['smtp_host']);
                    echo '</div>';

                    // Si hay fallidos, mostrar detalles
                    if ($campaign['failed_count'] > 0) {
                        echo '<h2>❌ Errores Detectados</h2>';

                        // Primero buscar en email_send_logs
                        $stmt = $pdo->prepare("
                            SELECT * FROM email_send_logs
                            WHERE campaign_id = ? AND status = 'failed'
                            ORDER BY id DESC
                            LIMIT 10
                        ");
                        $stmt->execute([$campaign['id']]);

                        if ($stmt->rowCount() > 0) {
                            echo '<table>';
                            echo '<tr><th>Email</th><th>Error</th><th>SMTP Respuesta</th></tr>';
                            while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($log['email']) . '</td>';
                                echo '<td><span class="badge error">' . htmlspecialchars($log['error_message']) . '</span></td>';
                                echo '<td><code>' . htmlspecialchars(substr($log['smtp_response'], 0, 100)) . '</code></td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        } else {
                            // Si no hay logs, buscar en email_recipients
                            $stmt = $pdo->prepare("
                                SELECT * FROM email_recipients
                                WHERE campaign_id = ? AND status = 'failed'
                                LIMIT 10
                            ");
                            $stmt->execute([$campaign['id']]);

                            if ($stmt->rowCount() > 0) {
                                echo '<table>';
                                echo '<tr><th>Email</th><th>Nombre</th><th>Error</th></tr>';
                                while ($rec = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($rec['email']) . '</td>';
                                    echo '<td>' . htmlspecialchars($rec['name']) . '</td>';
                                    echo '<td><span class="badge error">' . htmlspecialchars($rec['error_message']) . '</span></td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                        }
                    } else if ($campaign['sent_count'] > 0) {
                        echo '<div class="success">✓ ¡Todos los emails se enviaron correctamente!</div>';
                    }

                    // Si está enviando, mostrar loader
                    if ($campaign['status'] === 'sending') {
                        echo '<div class="warning">';
                        echo '⏳ Campaña en proceso de envío...';
                        echo '<div class="loader"></div>';
                        echo '</div>';
                    }

                } else {
                    echo '<div class="info">No se encontraron campañas en el sistema</div>';
                }

            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error de conexión:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';

                echo '<div class="info">';
                echo '<strong>💡 Posibles causas:</strong><br>';
                echo '• La base de datos no está disponible<br>';
                echo '• Credenciales incorrectas en config/database.php<br>';
                echo '• El servidor MySQL está detenido';
                echo '</div>';
            }
            ?>
        </div>

        <div class="timestamp">
            Última actualización: <?php echo date('d/m/Y H:i:s'); ?>
        </div>
    </div>

    <script>
    // Auto-refresh cada 5 segundos
    setTimeout(function() {
        location.reload();
    }, 5000);
    </script>
</body>
</html>
