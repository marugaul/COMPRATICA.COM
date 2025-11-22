<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Generic Greeting - COMPRATICA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .success {
            background: #d1fae5;
            border-left: 5px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #065f46;
            font-weight: 500;
            font-size: 16px;
        }
        .error {
            background: #fee2e2;
            border-left: 5px solid #dc2626;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #991b1b;
            font-weight: 500;
        }
        .info {
            background: #dbeafe;
            border-left: 5px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #1e40af;
        }
        .field-list {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        .field-item {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }
        .field-item:last-child { border-bottom: none; }
        .field-name { color: #374151; font-weight: 600; }
        .field-type { color: #6b7280; }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 13px;
        }
        .icon { font-size: 48px; margin: 20px 0; }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Setup de Base de Datos</h1>
            <p>Agregar campo generic_greeting</p>
        </div>

        <div class="content">
            <?php
            try {
                // Cargar configuración
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

                echo '<div class="info">✓ Conectado a la base de datos</div>';

                // Verificar si el campo ya existe
                $stmt = $pdo->query("SHOW COLUMNS FROM email_campaigns LIKE 'generic_greeting'");
                $exists = $stmt->fetch();

                if ($exists) {
                    echo '<div class="success">';
                    echo '<div class="icon">✅</div>';
                    echo '<strong>¡Campo ya existe!</strong><br><br>';
                    echo 'El campo <code>generic_greeting</code> ya está configurado en la tabla email_campaigns.';
                    echo '</div>';
                } else {
                    echo '<div class="info">';
                    echo '⚠️ El campo <code>generic_greeting</code> NO existe.<br>';
                    echo 'Agregándolo ahora...';
                    echo '</div>';

                    // Agregar el campo
                    $pdo->exec("
                        ALTER TABLE email_campaigns
                        ADD COLUMN generic_greeting VARCHAR(255) DEFAULT 'Estimado propietario'
                        AFTER subject
                    ");

                    echo '<div class="success">';
                    echo '<div class="icon">🎉</div>';
                    echo '<strong>¡Campo agregado exitosamente!</strong><br><br>';
                    echo 'Se ha creado el campo <code>generic_greeting</code> con valor por defecto: "Estimado propietario"';
                    echo '</div>';
                }

                // Mostrar estructura actualizada
                echo '<h3 style="margin: 25px 0 15px; color: #374151;">📋 Estructura de email_campaigns:</h3>';
                echo '<div class="field-list">';

                $stmt = $pdo->query("DESCRIBE email_campaigns");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $highlight = ($row['Field'] === 'generic_greeting') ? ' style="background: #d1fae5;"' : '';
                    echo '<div class="field-item"' . $highlight . '>';
                    echo '<span class="field-name">' . $row['Field'] . '</span>';
                    echo '<span class="field-type">' . $row['Type'] . '</span>';
                    echo '</div>';
                }

                echo '</div>';

                echo '<div class="info" style="margin-top: 20px;">';
                echo '<strong>✅ Próximo paso:</strong><br>';
                echo 'Ahora puedes crear campañas con saludo genérico personalizado.';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error:</strong><br><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';

                echo '<div class="info">';
                echo '<strong>💡 Posibles causas:</strong><br>';
                echo '• La base de datos no está disponible<br>';
                echo '• Credenciales incorrectas en config/database.php<br>';
                echo '• Permisos insuficientes para modificar tabla';
                echo '</div>';
            }
            ?>

            <div style="text-align: center; margin-top: 25px;">
                <a href="email_debug_public.php" class="btn">Ver Estado de Emails →</a>
            </div>
        </div>
    </div>
</body>
</html>
