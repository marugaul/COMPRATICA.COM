<?php
/**
 * ============================================
 * LIMPIADOR DE LOGS MYSQL - Versión Web
 * ============================================
 * Este script elimina todos los logs antiguos
 * generados por mysql-auto-executor.sh
 *
 * USO:
 * 1. Sube este archivo al servidor
 * 2. Visita: https://compratica.com/limpiar-logs.php
 * 3. Los logs se eliminarán automáticamente
 * ============================================
 */

// Establecer timeout generoso
set_time_limit(300);

$logsDir = __DIR__ . '/mysql-logs';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiador de Logs MySQL</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .icon {
            font-size: 2rem;
        }

        .info {
            background: #edf2f7;
            border-left: 4px solid #4299e1;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            color: #2d3748;
        }

        .result {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .result-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: 600;
            color: #4a5568;
        }

        .value {
            color: #2d3748;
            font-weight: 700;
        }

        .success {
            background: #c6f6d5;
            border-left: 4px solid #48bb78;
            color: #22543d;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            font-weight: 600;
        }

        .error {
            background: #fed7d7;
            border-left: 4px solid #f56565;
            color: #742a2a;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            font-weight: 600;
        }

        .warning {
            background: #feebc8;
            border-left: 4px solid #ed8936;
            color: #7c2d12;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            font-weight: 600;
        }

        .file-list {
            max-height: 300px;
            overflow-y: auto;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .file-item {
            padding: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-item.deleted {
            text-decoration: line-through;
            color: #a0aec0;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .container {
                padding: 2rem 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="icon">🧹</span>
            Limpiador de Logs MySQL
        </h1>

        <div class="info">
            <strong>📋 Información:</strong><br>
            Este script elimina todos los logs antiguos generados por el auto-executor MySQL, excepto el archivo "ultimo-ejecutado.log".
        </div>

        <?php
        $ejecutado = false;
        $totalLogs = 0;
        $logsEliminados = 0;
        $errores = [];
        $archivosEliminados = [];

        // Verificar que el directorio existe
        if (!is_dir($logsDir)) {
            echo '<div class="error">❌ Error: El directorio mysql-logs no existe.</div>';
        } else {
            // Buscar todos los archivos .log excepto ultimo-ejecutado.log
            $archivos = glob($logsDir . '/*.log');

            if ($archivos === false) {
                echo '<div class="error">❌ Error: No se pudo leer el directorio.</div>';
            } else {
                $totalLogs = count($archivos);

                // Filtrar para excluir ultimo-ejecutado.log
                $archivosParaEliminar = array_filter($archivos, function($archivo) {
                    return basename($archivo) !== 'ultimo-ejecutado.log';
                });

                if (empty($archivosParaEliminar)) {
                    echo '<div class="warning">⚠️ No se encontraron logs antiguos para eliminar.</div>';
                } else {
                    echo '<div class="result">';
                    echo '<div class="result-item">';
                    echo '<span class="label">📊 Logs encontrados:</span>';
                    echo '<span class="value">' . count($archivosParaEliminar) . '</span>';
                    echo '</div>';
                    echo '</div>';

                    // Eliminar archivos
                    foreach ($archivosParaEliminar as $archivo) {
                        $nombreArchivo = basename($archivo);

                        if (unlink($archivo)) {
                            $logsEliminados++;
                            $archivosEliminados[] = $nombreArchivo;
                        } else {
                            $errores[] = "No se pudo eliminar: $nombreArchivo";
                        }
                    }

                    $ejecutado = true;

                    // Mostrar lista de archivos eliminados
                    if (!empty($archivosEliminados)) {
                        echo '<div class="file-list">';
                        echo '<strong style="display: block; margin-bottom: 0.5rem;">✅ Archivos eliminados:</strong>';
                        foreach ($archivosEliminados as $archivo) {
                            echo '<div class="file-item deleted">' . htmlspecialchars($archivo) . '</div>';
                        }
                        echo '</div>';
                    }

                    // Mostrar errores si los hay
                    if (!empty($errores)) {
                        echo '<div class="error">';
                        foreach ($errores as $error) {
                            echo htmlspecialchars($error) . '<br>';
                        }
                        echo '</div>';
                    }

                    // Resultado final
                    echo '<div class="result" style="margin-top: 1.5rem;">';
                    echo '<div class="result-item">';
                    echo '<span class="label">🗑️ Logs eliminados:</span>';
                    echo '<span class="value">' . $logsEliminados . '</span>';
                    echo '</div>';

                    // Calcular espacio del directorio
                    $espacioBytes = 0;
                    $archivosRestantes = glob($logsDir . '/*');
                    foreach ($archivosRestantes as $archivo) {
                        if (is_file($archivo)) {
                            $espacioBytes += filesize($archivo);
                        }
                    }

                    $espacioKB = round($espacioBytes / 1024, 2);
                    $espacioMB = round($espacioBytes / (1024 * 1024), 2);

                    $espacioFormateado = $espacioMB > 1 ? $espacioMB . ' MB' : $espacioKB . ' KB';

                    echo '<div class="result-item">';
                    echo '<span class="label">📁 Espacio actual:</span>';
                    echo '<span class="value">' . $espacioFormateado . '</span>';
                    echo '</div>';
                    echo '</div>';

                    echo '<div class="success">✅ Limpieza completada exitosamente</div>';
                }
            }
        }
        ?>

        <?php if ($ejecutado): ?>
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0; text-align: center;">
                <p style="color: #4a5568; margin-bottom: 1rem;">
                    <strong>✅ El problema está resuelto.</strong><br>
                    A partir de ahora, el auto-executor solo mantendrá un único log.
                </p>
                <a href="/" class="btn">Volver al Inicio</a>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
            <p style="color: #718096; font-size: 0.875rem; text-align: center;">
                <strong>💡 Nota:</strong> Este script se puede ejecutar las veces que sea necesario. Es seguro ejecutarlo múltiples veces.
            </p>
        </div>
    </div>
</body>
</html>
