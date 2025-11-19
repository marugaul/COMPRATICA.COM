<?php
// Dashboard de Email Marketing
$stats = $pdo->query("
    SELECT
        COUNT(*) as total_campaigns,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) as sending,
        SUM(total_recipients) as total_recipients,
        SUM(sent_count) as total_sent,
        SUM(failed_count) as total_failed,
        SUM(opened_count) as total_opened
    FROM email_campaigns
")->fetch(PDO::FETCH_ASSOC);

$recent_campaigns = $pdo->query("
    SELECT * FROM email_campaigns
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$open_rate = $stats['total_sent'] > 0
    ? round(($stats['total_opened'] / $stats['total_sent']) * 100, 1)
    : 0;
?>

<h1 class="mb-4"><i class="fas fa-chart-line"></i> Dashboard de Email Marketing</h1>

<!-- Estadísticas -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card primary">
            <i class="fas fa-envelope-open-text fa-2x mb-2"></i>
            <h3><?= number_format($stats['total_campaigns'] ?? 0) ?></h3>
            <p>Total Campañas</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="fas fa-paper-plane fa-2x mb-2"></i>
            <h3><?= number_format($stats['total_sent'] ?? 0) ?></h3>
            <p>Emails Enviados</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-eye fa-2x mb-2"></i>
            <h3><?= $open_rate ?>%</h3>
            <p>Tasa de Apertura</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
            <h3><?= number_format($stats['total_failed'] ?? 0) ?></h3>
            <p>Emails Fallidos</p>
        </div>
    </div>
</div>

<!-- Campañas Recientes -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-history"></i> Campañas Recientes
    </div>
    <div class="card-body">
        <?php if (empty($recent_campaigns)): ?>
            <div class="text-center py-5">
                <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hay campañas creadas</h5>
                <p class="text-muted">Crea tu primera campaña para empezar</p>
                <a href="?page=new-campaign" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Nueva Campaña
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Campaña</th>
                            <th>Estado</th>
                            <th>Destinatarios</th>
                            <th>Enviados</th>
                            <th>Abiertos</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_campaigns as $campaign): ?>
                            <tr>
                                <td>
                                    <strong><?= h($campaign['name']) ?></strong><br>
                                    <small class="text-muted"><?= h($campaign['subject']) ?></small>
                                </td>
                                <td>
                                    <span class="badge-campaign badge-<?= $campaign['status'] ?>">
                                        <?= ucfirst($campaign['status']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($campaign['total_recipients']) ?></td>
                                <td>
                                    <?= number_format($campaign['sent_count']) ?>
                                    <small class="text-muted">
                                        (<?= $campaign['total_recipients'] > 0
                                            ? round(($campaign['sent_count']/$campaign['total_recipients'])*100)
                                            : 0 ?>%)
                                    </small>
                                </td>
                                <td>
                                    <?= number_format($campaign['opened_count']) ?>
                                    <small class="text-muted">
                                        (<?= $campaign['sent_count'] > 0
                                            ? round(($campaign['opened_count']/$campaign['sent_count'])*100, 1)
                                            : 0 ?>%)
                                    </small>
                                </td>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($campaign['created_at'])) ?></small>
                                </td>
                                <td>
                                    <a href="?page=campaign-details&id=<?= $campaign['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Acciones Rápidas -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-bolt"></i> Acciones Rápidas
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <a href="?page=new-campaign" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-plus-circle"></i><br>
                    Nueva Campaña
                </a>
            </div>
            <div class="col-md-3">
                <a href="?page=templates" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-file-code"></i><br>
                    Ver Plantillas
                </a>
            </div>
            <div class="col-md-3">
                <a href="?page=smtp-config" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-cog"></i><br>
                    Configurar SMTP
                </a>
            </div>
            <div class="col-md-3">
                <a href="?page=reports" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-chart-bar"></i><br>
                    Ver Reportes
                </a>
            </div>
        </div>
    </div>
</div>
