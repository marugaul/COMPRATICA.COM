<?php
// Gestión de Plantillas
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY company, name")->fetchAll(PDO::FETCH_ASSOC);

$template_files = [
    'mixtico' => __DIR__ . '/../email_templates/mixtico_template.html',
    'crv-soft' => __DIR__ . '/../email_templates/crv_soft_template.html',
    'compratica' => __DIR__ . '/../email_templates/compratica_template.html'
];
?>

<h1 class="mb-4"><i class="fas fa-file-code"></i> Plantillas de Email</h1>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> <strong>Variables disponibles:</strong>
    <code>{nombre}</code>, <code>{email}</code>, <code>{telefono}</code>, <code>{empresa}</code>,
    <code>{campaign_id}</code>, <code>{tracking_pixel}</code>, <code>{unsubscribe_link}</code>
</div>

<div class="row">
    <?php foreach ($templates as $template): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header" style="background: <?= $template['company'] === 'mixtico' ? '#3b82f6' : ($template['company'] === 'crv-soft' ? '#06b6d4' : '#dc2626') ?>; color: white;">
                    <strong><?= h($template['name']) ?></strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge" style="background-color: <?= $template['company'] === 'mixtico' ? '#3b82f6' : ($template['company'] === 'crv-soft' ? '#06b6d4' : '#dc2626') ?>;">
                            <?= ucfirst(h($template['company'])) ?>
                        </span>
                    </div>

                    <h6>Asunto:</h6>
                    <p class="small text-muted"><?= h($template['subject']) ?></p>

                    <h6>Variables:</h6>
                    <p class="small">
                        <?php
                        $vars = json_decode($template['variables'], true) ?? [];
                        foreach ($vars as $var) {
                            echo "<code>{" . h($var) . "}</code> ";
                        }
                        ?>
                    </p>

                    <div class="d-flex gap-2 mt-3">
                        <?php if (isset($template_files[$template['company']]) && file_exists($template_files[$template['company']])): ?>
                            <a href="<?= 'email_templates/' . $template['company'] . '_template.html' ?>"
                               target="_blank"
                               class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="fas fa-eye"></i> Preview
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick="editTemplate(<?= $template['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-question-circle"></i> Ayuda sobre Plantillas
    </div>
    <div class="card-body">
        <h6>Las plantillas incluidas están optimizadas para:</h6>
        <ul>
            <li><strong>Deliverability:</strong> HTML limpio que no es marcado como spam</li>
            <li><strong>Responsive:</strong> Se ven bien en desktop y móvil</li>
            <li><strong>Tracking:</strong> Incluyen pixel de apertura y tracking de clicks</li>
            <li><strong>Personalización:</strong> Variables que se reemplazan automáticamente</li>
        </ul>

        <h6 class="mt-3">Para personalizar una plantilla:</h6>
        <ol>
            <li>Los archivos de plantillas están en: <code>/admin/email_templates/</code></li>
            <li>Puede modificar el HTML directamente</li>
            <li>Use las variables entre llaves: <code>{nombre}</code>, <code>{email}</code></li>
            <li>Mantenga los links de <code>{tracking_pixel}</code> y <code>{unsubscribe_link}</code></li>
        </ol>
    </div>
</div>

<script>
function editTemplate(id) {
    alert('Para editar, modifique el archivo HTML en /admin/email_templates/\nLuego actualice el registro en la base de datos.');
}
</script>
