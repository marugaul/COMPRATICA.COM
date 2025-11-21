<?php
// Lista de Campañas
$campaigns = $pdo->query("
    SELECT c.*, s.name as smtp_name, t.name as template_name
    FROM email_campaigns c
    LEFT JOIN email_smtp_configs s ON c.smtp_config_id = s.id
    LEFT JOIN email_templates t ON c.template_id = t.id
    ORDER BY c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4"><i class="fas fa-envelope-open-text"></i> Campañas de Email</h1>

<div class="mb-3">
    <a href="?page=new-campaign" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i> Nueva Campaña
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($campaigns)): ?>
            <div class="text-center py-5">
                <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hay campañas creadas</h5>
                <p class="text-muted">Crea tu primera campaña de email marketing</p>
                <a href="?page=new-campaign" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Nueva Campaña
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Campaña</th>
                            <th>Estado</th>
                            <th>Config SMTP</th>
                            <th>Plantilla</th>
                            <th>Destinatarios</th>
                            <th>Enviados</th>
                            <th>Abiertos</th>
                            <th>Clicks</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr>
                                <td><?= $campaign['id'] ?></td>
                                <td>
                                    <strong><?= h($campaign['name']) ?></strong><br>
                                    <small class="text-muted"><?= h($campaign['subject']) ?></small>
                                </td>
                                <td>
                                    <span class="badge-campaign badge-<?= $campaign['status'] ?>">
                                        <?php
                                        $status_labels = [
                                            'draft' => 'Borrador',
                                            'scheduled' => 'Programada',
                                            'sending' => 'Enviando...',
                                            'completed' => 'Completada',
                                            'failed' => 'Fallida'
                                        ];
                                        echo $status_labels[$campaign['status']] ?? ucfirst($campaign['status']);
                                        ?>
                                    </span>
                                </td>
                                <td><?= h($campaign['smtp_name']) ?></td>
                                <td><?= h($campaign['template_name']) ?></td>
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
                                    <?= number_format($campaign['clicked_count']) ?>
                                    <small class="text-muted">
                                        (<?= $campaign['sent_count'] > 0
                                            ? round(($campaign['clicked_count']/$campaign['sent_count'])*100, 1)
                                            : 0 ?>%)
                                    </small>
                                </td>
                                <td>
                                    <small><?= date('d/m/Y', strtotime($campaign['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'sending'): ?>
                                            <a href="email_marketing_send.php?campaign_id=<?= $campaign['id'] ?>"
                                               class="btn btn-sm btn-success"
                                               title="Enviar/Continuar">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </a>
                                        <?php elseif ($campaign['status'] === 'completed'): ?>
                                            <a href="email_marketing_send.php?campaign_id=<?= $campaign['id'] ?>&resend=1"
                                               class="btn btn-sm btn-success"
                                               title="Reenviar campaña">
                                                <i class="fas fa-redo"></i> Reenviar
                                            </a>
                                        <?php endif; ?>
                                        <a href="?page=campaign-details&id=<?= $campaign['id'] ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
