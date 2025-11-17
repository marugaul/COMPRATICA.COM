<?php
/**
 * Script para actualizar fechas de inicio/fin de espacios (sales)
 * Ejecutar desde navegador o CLI
 *
 * USO:
 * - Sin parámetros: Muestra listado de espacios
 * - Con parámetros: Actualiza fecha
 *
 * Ejemplos:
 * update_sale_dates.php?sale_id=5&start_at=2025-11-20 08:00:00
 * update_sale_dates.php?sale_id=5&end_at=2025-11-27 18:00:00
 * update_sale_dates.php?sale_id=5&start_at=2025-11-20 08:00:00&end_at=2025-11-27 18:00:00
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();

// Procesar actualización si se envió formulario
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $start_at = trim($_POST['start_at'] ?? '');
    $end_at = trim($_POST['end_at'] ?? '');

    if ($sale_id > 0 && ($start_at !== '' || $end_at !== '')) {
        try {
            $updates = [];
            $params = [':id' => $sale_id];

            if ($start_at !== '') {
                $updates[] = "start_at = :start";
                $params[':start'] = $start_at;
            }

            if ($end_at !== '') {
                $updates[] = "end_at = :end";
                $params[':end'] = $end_at;
            }

            $updates[] = "updated_at = datetime('now', 'localtime')";

            $sql = "UPDATE sales SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $message = "✓ Espacio actualizado correctamente";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "✗ Error: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "⚠ Debes proporcionar sale_id y al menos una fecha";
        $messageType = 'warning';
    }
}

// Obtener todos los espacios
$sales = $pdo->query("
    SELECT s.*, a.name AS affiliate_name, a.email AS affiliate_email
    FROM sales s
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Fechas de Espacios</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
            padding: 2rem;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .subtitle {
            color: #718096;
            margin-bottom: 2rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .sale-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .sale-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
        }

        .sale-id {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .sale-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #718096;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #2d3748;
            font-weight: 500;
        }

        .date-value {
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: 'Courier New', monospace;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-private {
            background: #fff3cd;
            color: #856404;
        }

        .no-sales {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .sale-header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Actualizar Fechas de Espacios</h1>
        <p class="subtitle">Modifica las fechas de inicio y fin de los espacios de venta</p>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($sales)): ?>
            <div class="no-sales">
                <p>No hay espacios registrados en el sistema.</p>
            </div>
        <?php else: ?>
            <?php foreach ($sales as $sale): ?>
                <div class="card">
                    <div class="sale-header">
                        <div>
                            <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                            <div style="margin-top: 0.5rem;">
                                <span class="sale-id">ID: <?= $sale['id'] ?></span>
                                <?php if ($sale['is_active']): ?>
                                    <span class="status-badge status-active">Activo</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactivo</span>
                                <?php endif; ?>
                                <?php if (!empty($sale['is_private'])): ?>
                                    <span class="status-badge status-private">🔒 Privado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="sale-info">
                        <div class="info-item">
                            <span class="info-label">Afiliado</span>
                            <span class="info-value"><?= htmlspecialchars($sale['affiliate_name'] ?? 'N/A') ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">📅 Fecha de Inicio Actual</span>
                            <span class="info-value date-value"><?= htmlspecialchars($sale['start_at']) ?></span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">📅 Fecha de Fin Actual</span>
                            <span class="info-value date-value"><?= htmlspecialchars($sale['end_at']) ?></span>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Nueva Fecha de Inicio</label>
                                <input
                                    type="datetime-local"
                                    name="start_at"
                                    value="<?= date('Y-m-d\TH:i', strtotime($sale['start_at'])) ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label>Nueva Fecha de Fin</label>
                                <input
                                    type="datetime-local"
                                    name="end_at"
                                    value="<?= date('Y-m-d\TH:i', strtotime($sale['end_at'])) ?>"
                                >
                            </div>

                            <button type="submit" class="btn">Actualizar</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
