<?php
/**
 * ============================================
 * EJECUTOR MANUAL - ETAPA 1 VENTA DE GARAJE
 * ============================================
 * Este script ejecuta el SQL de Etapa 1 directamente
 * desde el navegador sin depender del cron.
 *
 * USO: Visita https://compratica.com/ejecutar-etapa1.php
 * ============================================
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejecutar Etapa 1 - Venta de Garaje</title>
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
            max-width: 800px;
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

        .result-box {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
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

        .sql-query {
            background: #2d3748;
            color: #48bb78;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            margin: 1rem 0;
            overflow-x: auto;
            white-space: pre-wrap;
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
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #edf2f7;
            font-weight: 600;
            color: #2d3748;
        }

        tr:hover {
            background: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="icon">⚡</span>
            Ejecutar Etapa 1 - Venta de Garaje
        </h1>

        <div class="info">
            <strong>📋 Información:</strong><br>
            Este script agregará 3 columnas nuevas a la tabla <code>sales</code> para habilitar las nuevas funcionalidades de búsqueda y filtros.
        </div>

        <?php
        $pdo = db();
        $errores = [];
        $exitos = [];

        try {
            // 1. Agregar cover_image2
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN cover_image2 TEXT DEFAULT NULL");
                $exitos[] = "✅ Columna 'cover_image2' agregada correctamente";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                    $exitos[] = "ℹ️ Columna 'cover_image2' ya existe";
                } else {
                    $errores[] = "❌ Error al agregar 'cover_image2': " . $e->getMessage();
                }
            }

            // 2. Agregar description
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN description TEXT DEFAULT NULL");
                $exitos[] = "✅ Columna 'description' agregada correctamente";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                    $exitos[] = "ℹ️ Columna 'description' ya existe";
                } else {
                    $errores[] = "❌ Error al agregar 'description': " . $e->getMessage();
                }
            }

            // 3. Agregar tags
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN tags TEXT DEFAULT NULL");
                $exitos[] = "✅ Columna 'tags' agregada correctamente";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'duplicate column name') !== false) {
                    $exitos[] = "ℹ️ Columna 'tags' ya existe";
                } else {
                    $errores[] = "❌ Error al agregar 'tags': " . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $errores[] = "❌ Error general: " . $e->getMessage();
        }

        // Mostrar resultados
        echo '<div class="result-box">';
        echo '<strong>📊 Resultados de la ejecución:</strong>';

        foreach ($exitos as $msg) {
            echo '<div class="success">' . htmlspecialchars($msg) . '</div>';
        }

        foreach ($errores as $msg) {
            echo '<div class="error">' . htmlspecialchars($msg) . '</div>';
        }

        echo '</div>';

        // Mostrar estructura actual de la tabla
        echo '<div class="result-box">';
        echo '<strong>🔍 Estructura actual de la tabla sales:</strong>';

        try {
            $stmt = $pdo->query("PRAGMA table_info(sales)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Nullable</th><th>Default</th></tr></thead>';
            echo '<tbody>';

            foreach ($columns as $col) {
                $highlight = in_array($col['name'], ['cover_image2', 'description', 'tags']) ? 'style="background: #c6f6d5; font-weight: 600;"' : '';
                echo '<tr ' . $highlight . '>';
                echo '<td>' . htmlspecialchars($col['cid']) . '</td>';
                echo '<td>' . htmlspecialchars($col['name']) . '</td>';
                echo '<td>' . htmlspecialchars($col['type']) . '</td>';
                echo '<td>' . ($col['notnull'] ? 'No' : 'Sí') . '</td>';
                echo '<td>' . htmlspecialchars($col['dflt_value'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } catch (Exception $e) {
            echo '<div class="error">Error al obtener estructura: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';

        if (empty($errores)) {
            echo '<div class="success" style="margin-top: 2rem; padding: 1.5rem;">';
            echo '<strong>🎉 ¡Etapa 1 completada exitosamente!</strong><br><br>';
            echo 'Ahora puedes activar la nueva versión:';
            echo '<ol style="margin-left: 1.5rem; margin-top: 1rem;">';
            echo '<li>Renombrar: <code>venta-garaje.php</code> → <code>venta-garaje-backup.php</code></li>';
            echo '<li>Renombrar: <code>venta-garaje-etapa1.php</code> → <code>venta-garaje.php</code></li>';
            echo '<li>Visitar: <a href="venta-garaje.php" style="color: #22543d; font-weight: 700;">venta-garaje.php</a></li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0; text-align: center;">
            <a href="/" class="btn">🏠 Volver al Inicio</a>
            <?php if (empty($errores)): ?>
            <a href="venta-garaje.php" class="btn" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">
                ✨ Ver Nueva Venta de Garaje
            </a>
            <?php endif; ?>
        </div>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
            <p style="color: #718096; font-size: 0.875rem; text-align: center;">
                <strong>🔒 Seguridad:</strong> Después de ejecutar este script, elimínalo del servidor por seguridad.
            </p>
        </div>
    </div>
</body>
</html>
