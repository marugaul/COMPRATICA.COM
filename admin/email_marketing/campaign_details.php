<?php
/**
 * Detalle de Campaña - Ver resultados, errores y logs
 */

$campaign_id = $_GET['id'] ?? 0;

if (!$campaign_id) {
    echo '<div class="alert alert-danger">ID de campaña no válido</div>';
    return;
}

// Obtener campaña
$stmt = $pdo->prepare("
    SELECT c.*, t.name as template_name, s.smtp_username, s.smtp_host
    FROM email_campaigns c
    LEFT JOIN email_templates t ON c.template_id = t.id
    LEFT JOIN email_smtp_configs s ON c.smtp_config_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    echo '<div class="alert alert-danger">Campaña no encontrada</div>';
    return;
}

// Calcular stats
$pending = $campaign['total_recipients'] - $campaign['sent_count'] - $campaign['failed_count'];
$success_rate = $campaign['total_recipients'] > 0
    ? round(($campaign['sent_count'] / $campaign['total_recipients']) * 100, 1)
    : 0;
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 25px 0;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #6b7280;
}
.stat-card.success { border-color: #10b981; }
.stat-card.error { border-color: #ef4444; }
.stat-card.pending { border-color: #f59e0b; }
.stat-card.total { border-color: #3b82f6; }

.stat-value {
    font-size: 36px;
    font-weight: bold;
    margin: 10px 0;
}
.stat-card.success .stat-value { color: #10b981; }
.stat-card.error .stat-value { color: #ef4444; }
.stat-card.pending .stat-value { color: #f59e0b; }
.stat-card.total .stat-value { color: #3b82f6; }

.stat-label {
    color: #6b7280;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.error-row {
    background: #fef2f2 !important;
}

.copy-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}
.copy-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}
.copy-btn.copied {
    background: #10b981;
}

.nav-tabs .nav-link {
    border-radius: 8px 8px 0 0;
}
.nav-tabs .nav-link.active {
    background: white;
    border-bottom: 3px solid var(--primary);
}

.badge-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.badge-status.sent { background: #d1fae5; color: #065f46; }
.badge-status.failed { background: #fee2e2; color: #991b1b; }
.badge-status.pending { background: #fef3c7; color: #92400e; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 10px;
}
.info-label {
    font-weight: 600;
    color: #374151;
}
.info-value {
    color: #6b7280;
}

.progress {
    height: 30px;
    border-radius: 15px;
    background: #e5e7eb;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    transition: width 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in {
    animation: fadeIn 0.3s ease-out;
}
</style>

<div class="animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="fas fa-chart-line"></i> Detalles de Campaña</h1>
            <p class="text-muted mb-0"><?= h($campaign['campaign_name']) ?></p>
        </div>
        <a href="?page=campaigns" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Info de Campaña -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-envelope" style="color: #3b82f6;"></i>
                    <div>
                        <div class="info-label">Asunto</div>
                        <div class="info-value"><?= h($campaign['subject']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-file-code" style="color: #8b5cf6;"></i>
                    <div>
                        <div class="info-label">Plantilla</div>
                        <div class="info-value"><?= h($campaign['template_name']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-server" style="color: #f59e0b;"></i>
                    <div>
                        <div class="info-label">SMTP</div>
                        <div class="info-value"><?= h($campaign['smtp_username']) ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar" style="color: #10b981;"></i>
                    <div>
                        <div class="info-label">Creada</div>
                        <div class="info-value"><?= date('d/m/Y H:i', strtotime($campaign['created_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-label">Enviados</div>
            <div class="stat-value"><?= $campaign['sent_count'] ?></div>
            <small><?= $success_rate ?>% de éxito</small>
        </div>
        <div class="stat-card error">
            <div class="stat-label">Fallidos</div>
            <div class="stat-value"><?= $campaign['failed_count'] ?></div>
            <?php if ($campaign['failed_count'] > 0): ?>
                <small><?= round(($campaign['failed_count'] / $campaign['total_recipients']) * 100, 1) ?>% fallaron</small>
            <?php endif; ?>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value"><?= $pending ?></div>
            <?php if ($pending > 0): ?>
                <small><?= round(($pending / $campaign['total_recipients']) * 100, 1) ?>% restante</small>
            <?php endif; ?>
        </div>
        <div class="stat-card total">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= $campaign['total_recipients'] ?></div>
            <small>destinatarios</small>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Progreso General</h6>
            <div class="progress">
                <?php
                $sent_pct = ($campaign['sent_count'] / $campaign['total_recipients']) * 100;
                $failed_pct = ($campaign['failed_count'] / $campaign['total_recipients']) * 100;
                $pending_pct = ($pending / $campaign['total_recipients']) * 100;
                ?>
                <div class="progress-bar" style="width: <?= $sent_pct ?>%; background: #10b981;">
                    <?php if ($sent_pct > 10): ?><?= round($sent_pct) ?>%<?php endif; ?>
                </div>
                <div class="progress-bar" style="width: <?= $failed_pct ?>%; background: #ef4444;">
                    <?php if ($failed_pct > 10): ?><?= round($failed_pct) ?>%<?php endif; ?>
                </div>
                <div class="progress-bar" style="width: <?= $pending_pct ?>%; background: #f59e0b;">
                    <?php if ($pending_pct > 10): ?><?= round($pending_pct) ?>%<?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#errors">
                <i class="fas fa-exclamation-triangle"></i> Errores (<?= $campaign['failed_count'] ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#success">
                <i class="fas fa-check-circle"></i> Exitosos (<?= $campaign['sent_count'] ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#pending">
                <i class="fas fa-clock"></i> Pendientes (<?= $pending ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Tab Errores -->
        <div class="tab-pane fade show active" id="errors">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-bug"></i> Destinatarios Fallidos
                </div>
                <div class="card-body p-0">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT r.*, l.error_message as log_error, l.smtp_response
                        FROM email_recipients r
                        LEFT JOIN email_send_logs l ON l.recipient_id = r.id
                        WHERE r.campaign_id = ? AND r.status = 'failed'
                        ORDER BY r.id DESC
                    ");
                    $stmt->execute([$campaign_id]);
                    $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($failed)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p>¡No hay errores! Todos los emails se enviaron correctamente.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Nombre</th>
                                        <th>Error</th>
                                        <th>Respuesta SMTP</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($failed as $rec): ?>
                                        <tr class="error-row">
                                            <td><?= h($rec['email']) ?></td>
                                            <td><?= h($rec['name']) ?></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?= h($rec['log_error'] ?: $rec['error_message']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= h(substr($rec['smtp_response'] ?? 'N/A', 0, 100)) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="copy-btn" onclick="copyError(this, <?= htmlspecialchars(json_encode([
                                                    'email' => $rec['email'],
                                                    'error' => $rec['log_error'] ?: $rec['error_message'],
                                                    'smtp' => $rec['smtp_response']
                                                ])) ?>)">
                                                    <i class="fas fa-copy"></i> Copiar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Exitosos -->
        <div class="tab-pane fade" id="success">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-check-circle"></i> Emails Enviados Correctamente
                </div>
                <div class="card-body p-0">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT * FROM email_recipients
                        WHERE campaign_id = ? AND status = 'sent'
                        ORDER BY sent_at DESC
                        LIMIT 100
                    ");
                    $stmt->execute([$campaign_id]);
                    $sent = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($sent)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Aún no se han enviado emails exitosamente.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Nombre</th>
                                        <th>Enviado</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sent as $rec): ?>
                                        <tr>
                                            <td><?= h($rec['email']) ?></td>
                                            <td><?= h($rec['name']) ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($rec['sent_at'])) ?></td>
                                            <td><span class="badge-status sent">✓ Enviado</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Pendientes -->
        <div class="tab-pane fade" id="pending">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-clock"></i> Emails Pendientes de Envío
                </div>
                <div class="card-body p-0">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT * FROM email_recipients
                        WHERE campaign_id = ? AND status = 'pending'
                        ORDER BY id ASC
                        LIMIT 100
                    ");
                    $stmt->execute([$campaign_id]);
                    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (empty($pending_list)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-check-double fa-3x mb-3"></i>
                            <p>No hay emails pendientes. La campaña está completa.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_list as $rec): ?>
                                        <tr>
                                            <td><?= h($rec['email']) ?></td>
                                            <td><?= h($rec['name']) ?></td>
                                            <td><span class="badge-status pending">⏳ Pendiente</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyError(btn, errorData) {
    const text = `
EMAIL: ${errorData.email}
ERROR: ${errorData.error}
SMTP: ${errorData.smtp}
    `.trim();

    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.classList.add('copied');

        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('copied');
        }, 2000);
    });
}

// Auto-refresh si está en proceso de envío
<?php if ($campaign['status'] === 'sending'): ?>
setTimeout(() => location.reload(), 5000);
<?php endif; ?>
</script>
