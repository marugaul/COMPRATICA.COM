<?php
// Configuración SMTP
$smtp_configs = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4"><i class="fas fa-cog"></i> Configuración SMTP</h1>

<div class="row">
    <?php foreach ($smtp_configs as $config): ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header" style="background: <?= $config['name'] === 'Mixtico' ? '#3b82f6' : ($config['name'] === 'CRV-SOFT' ? '#06b6d4' : '#dc2626') ?>; color: white;">
                <strong><?= h($config['name']) ?></strong>
                <?php if ($config['is_active']): ?>
                    <span class="badge bg-success float-end">Activo</span>
                <?php else: ?>
                    <span class="badge bg-secondary float-end">Inactivo</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form action="email_marketing_api.php" method="POST">
                    <input type="hidden" name="action" value="save_smtp_config">
                    <input type="hidden" name="config_id" value="<?= $config['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Email Remitente</label>
                        <input type="email" name="from_email" class="form-control" value="<?= h($config['from_email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nombre Remitente</label>
                        <input type="text" name="from_name" class="form-control" value="<?= h($config['from_name']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Servidor SMTP</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= h($config['smtp_host']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= h($config['smtp_port']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Usuario SMTP</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= h($config['smtp_username']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña SMTP</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="••••••••">
                        <small class="text-muted">Dejar en blanco para mantener la actual</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Encriptación</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="tls" <?= $config['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
                            <option value="ssl" <?= $config['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $config['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Ninguna</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="testSMTP(<?= $config['id'] ?>)">
                            <i class="fas fa-vial"></i> Probar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Información Importante
    </div>
    <div class="card-body">
        <h6>Para evitar que los correos vayan a SPAM:</h6>
        <ul>
            <li><strong>Configurar SPF:</strong> Agregue un registro TXT en su DNS con el valor: <code>v=spf1 include:_spf.google.com ~all</code> (ajustar según su proveedor)</li>
            <li><strong>Configurar DKIM:</strong> Configure claves DKIM en su servidor de correo</li>
            <li><strong>Verificar Dominio:</strong> Asegúrese de que su dominio esté verificado</li>
            <li><strong>Rate Limiting:</strong> El sistema envía con retraso de 2 segundos entre emails</li>
            <li><strong>Contenido:</strong> Evite palabras spam como "GRATIS", "URGENTE", exceso de mayúsculas</li>
            <li><strong>Lista Limpia:</strong> Solo envíe a contactos que hayan dado permiso</li>
        </ul>

        <h6 class="mt-4">Proveedores SMTP Recomendados:</h6>
        <ul>
            <li><strong>Gmail/Google Workspace:</strong> smtp.gmail.com:587 (TLS)</li>
            <li><strong>SendGrid:</strong> smtp.sendgrid.net:587 (TLS)</li>
            <li><strong>Mailgun:</strong> smtp.mailgun.org:587 (TLS)</li>
            <li><strong>cPanel:</strong> mail.sudominio.com:587 (TLS)</li>
        </ul>
    </div>
</div>

<script>
function testSMTP(configId) {
    if (!confirm('¿Desea probar la conexión SMTP?')) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'email_marketing_api.php';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'test_smtp';

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'smtp_id';
    idInput.value = configId;

    form.appendChild(actionInput);
    form.appendChild(idInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
