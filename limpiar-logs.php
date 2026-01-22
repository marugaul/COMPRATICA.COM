<?php
/**
 * ============================================
 * LIMPIADOR DE LOGS MYSQL - Versión Web v2
 * ============================================
 * Este script elimina todos los logs antiguos
 * generados por mysql-auto-executor.sh en TODAS
 * las ubicaciones.
 * ============================================
 */

// Establecer timeout generoso
set_time_limit(300);
ini_set('memory_limit', '256M');

// TODAS las rutas donde pueden estar los logs
$directorios = [
    __DIR__ . '/mysql-logs',
    '/home/comprati/public_html/mysql-logs',
    '/home/comprati/compratica_repo/mysql-logs',  // CORREGIDO: compratica_repo (con "a")
    dirname(__DIR__) . '/mysql-logs'
];

// Eliminar duplicados
$directorios = array_unique($directorios);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiador de Logs MySQL v2</title>
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
            padding: 2rem;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem;
            max-width: 900px;
            margin: 0 auto;
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

        .directory-section {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .directory-header {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .directory-path {
            font-family: 'Courier New', monospace;
            background: #2d3748;
            color: #48bb78;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            word-break: break-all;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #718096;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
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

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            transition: width 0.3s ease;
        }

        .log-item {
            padding: 0.5rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .log-item.deleted {
            background: #c6f6d5;
            border-color: #48bb78;
        }

        .log-item.error {
            background: #fed7d7;
            border-color: #f56565;
        }

        .logs-container {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
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

        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-top: 2rem;
        }

        .summary h2 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .summary-stat {
            text-align: center;
        }

        .summary-stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .summary-stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
            Limpiador de Logs MySQL v2
        </h1>

        <div class="info">
            <strong>📋 Información:</strong><br>
            Este script busca y elimina todos los logs antiguos en TODAS las ubicaciones posibles, excepto "ultimo-ejecutado.log".
        </div>

        <?php
        $totalLogsEncontrados = 0;
        $totalLogsEliminados = 0;
        $totalErrores = 0;
        $directoriosProcesados = 0;

        foreach ($directorios as $logsDir):
            // Normalizar path
            $logsDir = rtrim($logsDir, '/');

            // Verificar si el directorio existe
            if (!is_dir($logsDir)) {
                continue;
            }

            $directoriosProcesados++;

            echo '<div class="directory-section">';
            echo '<div class="directory-header">📁 Directorio ' . $directoriosProcesados . '</div>';
            echo '<div class="directory-path">' . htmlspecialchars($logsDir) . '</div>';

            // Verificar permisos
            if (!is_readable($logsDir)) {
                echo '<div class="error">❌ No se puede leer el directorio (sin permisos)</div>';
                echo '</div>';
                continue;
            }

            if (!is_writable($logsDir)) {
                echo '<div class="warning">⚠️ No se puede escribir en el directorio (sin permisos para eliminar)</div>';
                echo '</div>';
                continue;
            }

            // Buscar TODOS los archivos .log
            $archivos = glob($logsDir . '/*.log');

            if ($archivos === false) {
                echo '<div class="error">❌ Error al leer archivos</div>';
                echo '</div>';
                continue;
            }

            // Filtrar para excluir ultimo-ejecutado.log
            $archivosParaEliminar = array_filter($archivos, function($archivo) {
                return basename($archivo) !== 'ultimo-ejecutado.log';
            });

            $cantidadEnEsteDir = count($archivosParaEliminar);
            $totalLogsEncontrados += $cantidadEnEsteDir;

            if ($cantidadEnEsteDir === 0) {
                echo '<div class="success">✅ No hay logs antiguos en este directorio</div>';
                echo '</div>';
                continue;
            }

            echo '<div class="stats">';
            echo '<div class="stat-box">';
            echo '<div class="stat-label">Logs Encontrados</div>';
            echo '<div class="stat-value">' . $cantidadEnEsteDir . '</div>';
            echo '</div>';
            echo '</div>';

            // Eliminar archivos UNO POR UNO
            $eliminadosAqui = 0;
            $erroresAqui = 0;

            echo '<div class="logs-container">';

            foreach ($archivosParaEliminar as $archivo) {
                $nombreArchivo = basename($archivo);

                // Intentar eliminar
                $eliminado = @unlink($archivo);

                if ($eliminado) {
                    // Verificar que realmente se eliminó
                    if (!file_exists($archivo)) {
                        $eliminadosAqui++;
                        $totalLogsEliminados++;
                        echo '<div class="log-item deleted">✓ ' . htmlspecialchars($nombreArchivo) . '</div>';
                    } else {
                        $erroresAqui++;
                        $totalErrores++;
                        echo '<div class="log-item error">✗ No se pudo verificar eliminación: ' . htmlspecialchars($nombreArchivo) . '</div>';
                    }
                } else {
                    $erroresAqui++;
                    $totalErrores++;
                    $permisos = substr(sprintf('%o', fileperms($archivo)), -4);
                    echo '<div class="log-item error">✗ Error (permisos: ' . $permisos . '): ' . htmlspecialchars($nombreArchivo) . '</div>';
                }
            }

            echo '</div>';

            echo '<div class="stats" style="margin-top: 1rem;">';
            echo '<div class="stat-box">';
            echo '<div class="stat-label">Eliminados</div>';
            echo '<div class="stat-value" style="color: #48bb78;">' . $eliminadosAqui . '</div>';
            echo '</div>';

            if ($erroresAqui > 0) {
                echo '<div class="stat-box">';
                echo '<div class="stat-label">Errores</div>';
                echo '<div class="stat-value" style="color: #f56565;">' . $erroresAqui . '</div>';
                echo '</div>';
            }
            echo '</div>';

            echo '</div>';

        endforeach;

        // Resumen final
        if ($directoriosProcesados === 0) {
            echo '<div class="error">❌ No se encontraron directorios mysql-logs/</div>';
        } else {
            echo '<div class="summary">';
            echo '<h2>📊 Resumen Final</h2>';
            echo '<div class="summary-stats">';

            echo '<div class="summary-stat">';
            echo '<div class="summary-stat-value">' . $directoriosProcesados . '</div>';
            echo '<div class="summary-stat-label">Directorios Procesados</div>';
            echo '</div>';

            echo '<div class="summary-stat">';
            echo '<div class="summary-stat-value">' . $totalLogsEncontrados . '</div>';
            echo '<div class="summary-stat-label">Logs Encontrados</div>';
            echo '</div>';

            echo '<div class="summary-stat">';
            echo '<div class="summary-stat-value">' . $totalLogsEliminados . '</div>';
            echo '<div class="summary-stat-label">Logs Eliminados</div>';
            echo '</div>';

            if ($totalErrores > 0) {
                echo '<div class="summary-stat">';
                echo '<div class="summary-stat-value">' . $totalErrores . '</div>';
                echo '<div class="summary-stat-label">Errores</div>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';

            if ($totalLogsEliminados > 0) {
                echo '<div class="success">✅ Limpieza completada exitosamente</div>';
            } elseif ($totalLogsEncontrados === 0) {
                echo '<div class="success">✅ No hay logs antiguos para eliminar</div>';
            } else {
                echo '<div class="error">❌ No se pudieron eliminar archivos (verifica permisos)</div>';
            }
        }
        ?>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0; text-align: center;">
            <a href="javascript:location.reload()" class="btn">🔄 Ejecutar de Nuevo</a>
            <a href="/" class="btn" style="background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);">🏠 Volver al Inicio</a>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
            <p style="color: #718096; font-size: 0.875rem; text-align: center;">
                <strong>💡 Nota:</strong> Si ves que los archivos no se eliminan, puede ser un problema de permisos. Contacta a soporte técnico del hosting.
            </p>
        </div>
    </div>
</body>
</html>
