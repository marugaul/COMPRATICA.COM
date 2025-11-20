<?php
// Reportes y Estadísticas
$stats = $pdo->query("
    SELECT
        COUNT(DISTINCT c.id) as total_campaigns,
        SUM(c.total_recipients) as total_recipients,
        SUM(c.sent_count) as total_sent,
        SUM(c.failed_count) as total_failed,
        SUM(c.opened_count) as total_opened,
        SUM(c.clicked_count) as total_clicked
    FROM email_campaigns c
    WHERE c.status != 'draft'
")->fetch(PDO::FETCH_ASSOC);

$open_rate = $stats['total_sent'] > 0 ? round(($stats['total_opened'] / $stats['total_sent']) * 100, 1) : 0;
$click_rate = $stats['total_sent'] > 0 ? round(($stats['total_clicked'] / $stats['total_sent']) * 100, 1) : 0;
$success_rate = $stats['total_sent'] > 0 ? round((($stats['total_sent'] - $stats['total_failed']) / $stats['total_sent']) * 100, 1) : 0;

// Campañas por estado
$by_status = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM email_campaigns
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Top campañas por tasa de apertura
$top_campaigns = $pdo->query("
    SELECT name, sent_count, opened_count,
           ROUND((opened_count / NULLIF(sent_count, 0)) * 100, 1) as open_rate
    FROM email_campaigns
    WHERE status = 'completed' AND sent_count > 0
    ORDER BY open_rate DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4"><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</h1>

<!-- KPIs Principales -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card primary">
            <i class="fas fa-paper-plane fa-2x mb-2"></i>
            <h3><?= number_format($stats['total_sent'] ?? 0) ?></h3>
            <p>Total Enviados</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="fas fa-eye fa-2x mb-2"></i>
            <h3><?= $open_rate ?>%</h3>
            <p>Tasa de Apertura</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-mouse-pointer fa-2x mb-2"></i>
            <h3><?= $click_rate ?>%</h3>
            <p>Tasa de Clicks</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="fas fa-check-circle fa-2x mb-2"></i>
            <h3><?= $success_rate ?>%</h3>
            <p>Tasa de Éxito</p>
        </div>
    </div>
</div>

<!-- Resumen General -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Campañas por Estado
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($by_status as $status): ?>
                            <tr>
                                <td>
                                    <span class="badge-campaign badge-<?= $status['status'] ?>">
                                        <?php
                                        $labels = [
                                            'draft' => 'Borradores',
                                            'sending' => 'Enviando',
                                            'completed' => 'Completadas',
                                            'failed' => 'Fallidas'
                                        ];
                                        echo $labels[$status['status']] ?? ucfirst($status['status']);
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format($status['count']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-trophy"></i> Top Campañas por Apertura
            </div>
            <div class="card-body">
                <?php if (empty($top_campaigns)): ?>
                    <p class="text-muted text-center py-3">No hay campañas completadas</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Campaña</th>
                                <th>Enviados</th>
                                <th>Tasa Apertura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_campaigns as $campaign): ?>
                                <tr>
                                    <td><?= h($campaign['name']) ?></td>
                                    <td><?= number_format($campaign['sent_count']) ?></td>
                                    <td>
                                        <strong class="text-success"><?= $campaign['open_rate'] ?>%</strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Benchmarks -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-chart-line"></i> Benchmarks de la Industria
    </div>
    <div class="card-body">
        <h6>¿Cómo se comparan tus resultados?</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Métrica</th>
                        <th>Tu Resultado</th>
                        <th>Promedio Industria</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Tasa de Apertura</td>
                        <td><strong><?= $open_rate ?>%</strong></td>
                        <td>15-25%</td>
                        <td>
                            <?php if ($open_rate >= 15): ?>
                                <span class="badge bg-success">✓ Bueno</span>
                            <?php else: ?>
                                <span class="badge bg-warning">⚠ Mejorable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Tasa de Clicks</td>
                        <td><strong><?= $click_rate ?>%</strong></td>
                        <td>2-5%</td>
                        <td>
                            <?php if ($click_rate >= 2): ?>
                                <span class="badge bg-success">✓ Bueno</span>
                            <?php else: ?>
                                <span class="badge bg-warning">⚠ Mejorable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Tasa de Éxito</td>
                        <td><strong><?= $success_rate ?>%</strong></td>
                        <td>95-98%</td>
                        <td>
                            <?php if ($success_rate >= 95): ?>
                                <span class="badge bg-success">✓ Excelente</span>
                            <?php elseif ($success_rate >= 90): ?>
                                <span class="badge bg-warning">⚠ Bueno</span>
                            <?php else: ?>
                                <span class="badge bg-danger">✗ Revisar</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="alert alert-info mt-3">
            <h6><i class="fas fa-lightbulb"></i> Tips para Mejorar</h6>
            <ul class="mb-0">
                <li><strong>Apertura baja?</strong> Mejore el asunto del email, use personalización ({nombre})</li>
                <li><strong>Clicks bajos?</strong> Mejore el CTA (Call-to-Action), use botones llamativos</li>
                <li><strong>Muchos fallos?</strong> Limpie su lista de emails, valide direcciones antes</li>
                <li><strong>En SPAM?</strong> Configure SPF/DKIM, evite palabras spam, use opt-in confirmado</li>
            </ul>
        </div>
    </div>
</div>
