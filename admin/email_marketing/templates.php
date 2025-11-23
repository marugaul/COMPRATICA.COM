<?php
// Gestión de Plantillas
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY is_default DESC, company, name")->fetchAll(PDO::FETCH_ASSOC);

$template_files = [
    'mixtico' => __DIR__ . '/../email_templates/mixtico_template.html',
    'crv-soft' => __DIR__ . '/../email_templates/crv_soft_template.html',
    'compratica' => __DIR__ . '/../email_templates/compratica_template.html'
];
?>

<style>
.template-card {
    transition: all 0.3s;
    height: 100%;
}
.template-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.template-card.is-default {
    border: 3px solid #fbbf24;
    position: relative;
}
.default-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #fbbf24;
    color: #000;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 11px;
    z-index: 10;
}
.upload-zone {
    border: 3px dashed #cbd5e1;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.3s;
}
.upload-zone:hover {
    border-color: #0891b2;
    background: #ecfeff;
}
.upload-zone.dragover {
    border-color: #dc2626;
    background: #fee;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
}
.modal-content {
    background: white;
    margin: 2% auto;
    padding: 0;
    width: 95%;
    max-width: 1200px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.modal-header {
    padding: 20px 30px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-body {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}
.close {
    font-size: 32px;
    font-weight: bold;
    color: #64748b;
    cursor: pointer;
    line-height: 1;
}
.close:hover {
    color: #dc2626;
}
</style>

<h1 class="mb-4"><i class="fas fa-file-code"></i> Gestión de Plantillas de Email</h1>

<!-- Botón para subir nueva plantilla -->
<div class="mb-4">
    <button class="btn btn-success" onclick="showUploadModal()">
        <i class="fas fa-upload"></i> Subir Nueva Plantilla
    </button>
    <a href="../preview_template.php" class="btn btn-info" target="_blank">
        <i class="fas fa-eye"></i> Vista Previa de Plantillas
    </a>
    <a href="../load_email_templates.php" class="btn btn-secondary">
        <i class="fas fa-sync"></i> Recargar Plantillas Predeterminadas
    </a>
</div>

<!-- Alert de Variables Disponibles -->
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> <strong>Variables disponibles:</strong>
    <code>{nombre}</code>, <code>{email}</code>, <code>{telefono}</code>, <code>{empresa}</code>,
    <code>{campaign_id}</code>, <code>{tracking_pixel}</code>, <code>{unsubscribe_link}</code>
</div>

<!-- Grid de Plantillas -->
<div class="row">
    <?php foreach ($templates as $template): ?>
        <div class="col-md-4 mb-4">
            <div class="card template-card <?= $template['is_default'] ? 'is-default' : '' ?>">
                <?php if ($template['is_default']): ?>
                    <div class="default-badge">★ POR DEFECTO</div>
                <?php endif; ?>

                <div class="card-header" style="background: <?= $template['company'] === 'mixtico' ? '#3b82f6' : ($template['company'] === 'crv-soft' ? '#06b6d4' : ($template['company'] === 'compratica' ? '#dc2626' : '#16a34a')) ?>; color: white;">
                    <strong><?= h($template['name']) ?></strong>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="badge" style="background-color: <?= $template['company'] === 'mixtico' ? '#3b82f6' : ($template['company'] === 'crv-soft' ? '#06b6d4' : ($template['company'] === 'compratica' ? '#dc2626' : '#16a34a')) ?>;">
                            <?= ucfirst(h($template['company'])) ?>
                        </span>
                        <?php if ($template['is_active']): ?>
                            <span class="badge bg-success">Activa</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactiva</span>
                        <?php endif; ?>
                    </div>

                    <h6>Asunto:</h6>
                    <p class="small text-muted"><?= h($template['subject_default'] ?? $template['subject'] ?? 'Sin asunto') ?></p>

                    <h6>Variables:</h6>
                    <p class="small">
                        <?php
                        $vars = json_decode($template['variables'], true) ?? [];
                        foreach ($vars as $var) {
                            echo "<code>{" . h($var) . "}</code> ";
                        }
                        ?>
                    </p>

                    <p class="small text-muted">
                        <i class="fas fa-file-code"></i> <?= number_format(strlen($template['html_content'])) ?> bytes
                    </p>

                    <?php if (!empty($template['image_path'])): ?>
                        <div class="alert alert-info p-2 mb-2" style="font-size:12px;">
                            <i class="fas fa-image"></i>
                            <strong>Imagen:</strong>
                            <?php if ($template['image_display'] === 'inline'): ?>
                                <span class="badge bg-primary">Inline</span>
                            <?php elseif ($template['image_display'] === 'attachment'): ?>
                                <span class="badge bg-secondary">Adjunto</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Botones de acción -->
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="previewTemplate(<?= $template['id'] ?>)">
                            <i class="fas fa-eye"></i> Vista Previa
                        </button>

                        <button class="btn btn-sm btn-outline-success" onclick="testEmail(<?= $template['id'] ?>)">
                            <i class="fas fa-paper-plane"></i> Test
                        </button>

                        <?php if (!$template['is_default']): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick="setDefault(<?= $template['id'] ?>)" title="Marcar como predeterminada">
                                <i class="fas fa-star"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($template['is_active']): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleActive(<?= $template['id'] ?>, 0)" title="Desactivar">
                                <i class="fas fa-toggle-on"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleActive(<?= $template['id'] ?>, 1)" title="Activar">
                                <i class="fas fa-toggle-off"></i>
                            </button>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-danger"
                                onclick="deleteTemplate(<?= $template['id'] ?>, '<?= h($template['company']) ?>', '<?= h($template['name']) ?>')"
                                title="Eliminar plantilla">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal para subir nueva plantilla -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-upload"></i> Subir Nueva Plantilla</h2>
            <span class="close" onclick="closeUploadModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_template">

                <div class="mb-3">
                    <label class="form-label"><strong>Nombre de la Plantilla</strong></label>
                    <input type="text" name="template_name" class="form-control" placeholder="Ej: Promoción Verano 2024" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Identificador (slug)</strong></label>
                    <input type="text" name="template_company" class="form-control" placeholder="ej: promo-verano" required pattern="[a-z0-9-]+">
                    <small class="text-muted">Solo letras minúsculas, números y guiones</small>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Asunto Predeterminado</strong></label>
                    <input type="text" name="template_subject" class="form-control" placeholder="Ej: ¡Ofertas especiales de verano! 🌞" required>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Archivo HTML</strong></label>
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color:#0891b2;"></i>
                        <h4>Arrastra tu archivo HTML aquí</h4>
                        <p class="text-muted">o haz clic para seleccionar</p>
                        <p id="fileName" class="text-success mt-2" style="display:none;"></p>
                    </div>
                    <input type="file" id="fileInput" name="template_file" accept=".html,.htm" style="display:none;" required onchange="showFileName(this)">
                </div>

                <!-- Sección de Imagen -->
                <div class="mb-3">
                    <label class="form-label"><strong>🖼️ Imagen (Opcional)</strong></label>
                    <input type="file" name="template_image" class="form-control" accept="image/*" id="imageInput" onchange="previewImage(this)">
                    <small class="text-muted">JPG, PNG, GIF - Máx 5MB</small>

                    <!-- Preview de imagen -->
                    <div id="imagePreview" style="display:none;margin-top:10px;">
                        <img id="imagePreviewImg" src="" style="max-width:200px;max-height:200px;border:2px solid #e2e8f0;border-radius:8px;padding:5px;">
                    </div>
                </div>

                <div class="mb-3" id="imageDisplayOptions" style="display:none;">
                    <label class="form-label"><strong>¿Cómo mostrar la imagen?</strong></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="image_display" id="imageInline" value="inline" checked>
                        <label class="form-check-label" for="imageInline">
                            <strong>Dentro del cuerpo del email</strong> (inline)
                            <small class="text-muted d-block">La imagen aparece embebida en el HTML usando {template_image}</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="image_display" id="imageAttachment" value="attachment">
                        <label class="form-check-label" for="imageAttachment">
                            <strong>Como archivo adjunto</strong>
                            <small class="text-muted d-block">La imagen se envía como adjunto del email</small>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="set_as_default" id="setAsDefault">
                        <label class="form-check-label" for="setAsDefault">
                            <strong>Marcar como plantilla predeterminada</strong>
                        </label>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle"></i> Importante:</strong><br>
                    Asegúrate de incluir las variables entre llaves en tu HTML: <code>{nombre}</code>, <code>{email}</code>, etc.<br>
                    También incluye: <code>{tracking_pixel}</code> y <code>{unsubscribe_link}</code><br>
                    <strong>Para la imagen inline:</strong> Usa <code>{template_image}</code> en tu HTML
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1">
                        <i class="fas fa-upload"></i> Subir Plantilla
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Vista Previa -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Vista Previa de Plantilla</h2>
            <span class="close" onclick="closePreviewModal()">&times;</span>
        </div>
        <div class="modal-body">
            <iframe id="previewFrame" style="width:100%;height:700px;border:1px solid #e2e8f0;border-radius:8px;"></iframe>
        </div>
    </div>
</div>

<!-- Modal para Test de Email -->
<div id="testModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h2><i class="fas fa-paper-plane"></i> Enviar Email de Prueba</h2>
            <span class="close" onclick="closeTestModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="testForm">
                <input type="hidden" name="action" value="test_template">
                <input type="hidden" name="template_id" id="testTemplateId">

                <div class="mb-3">
                    <label class="form-label"><strong>Email de Destino</strong></label>
                    <input type="email" name="test_email" class="form-control" placeholder="tu@email.com" required>
                    <small class="text-muted">Enviaremos un email de prueba a esta dirección</small>
                </div>

                <div class="mb-3">
                    <label class="form-label"><strong>Configuración SMTP</strong></label>
                    <select name="smtp_config_id" class="form-control" required>
                        <?php
                        $smtp_configs = $pdo->query("SELECT * FROM email_smtp_configs WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($smtp_configs as $smtp) {
                            echo "<option value='{$smtp['id']}'>" . h($smtp['name']) . " ({$smtp['from_email']})</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> El email se enviará con datos de ejemplo para que puedas verificar el formato y diseño.
                </div>

                <div id="testResult"></div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1">
                        <i class="fas fa-paper-plane"></i> Enviar Prueba
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeTestModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-question-circle"></i> Ayuda sobre Plantillas
    </div>
    <div class="card-body">
        <h6>Características del Sistema de Plantillas:</h6>
        <ul>
            <li><strong>Vista Previa:</strong> Visualiza cómo se verá el email antes de enviarlo</li>
            <li><strong>Test de Envío:</strong> Envía un email de prueba a tu correo para verificar</li>
            <li><strong>Plantilla por Defecto:</strong> Marca una plantilla como predeterminada (★)</li>
            <li><strong>Subir Plantillas:</strong> Sube tus propios diseños HTML</li>
            <li><strong>Variables Dinámicas:</strong> Usa {nombre}, {email}, etc. para personalizar</li>
        </ul>

        <h6 class="mt-3">Para crear una plantilla HTML personalizada:</h6>
        <ol>
            <li>Diseña tu email en HTML (responsive recomendado)</li>
            <li>Incluye las variables entre llaves: <code>{nombre}</code>, <code>{email}</code>, etc.</li>
            <li>Agrega el tracking pixel: <code>&lt;img src="{tracking_pixel}" width="1" height="1"&gt;</code></li>
            <li>Incluye link de desuscripción: <code>&lt;a href="{unsubscribe_link}"&gt;Desuscribirse&lt;/a&gt;</code></li>
            <li>Sube el archivo usando el botón "Subir Nueva Plantilla"</li>
        </ol>
    </div>
</div>

<script>
// Upload Modal
function showUploadModal() {
    document.getElementById('uploadModal').style.display = 'block';
}

function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    document.getElementById('uploadForm').reset();
    document.getElementById('fileName').style.display = 'none';
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('imageDisplayOptions').style.display = 'none';
}

function showFileName(input) {
    const fileName = document.getElementById('fileName');
    if (input.files.length > 0) {
        fileName.textContent = '✓ ' + input.files[0].name;
        fileName.style.display = 'block';
    }
}

// Preview de imagen
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('imagePreviewImg');
    const displayOptions = document.getElementById('imageDisplayOptions');

    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validar tamaño (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('La imagen es muy grande. Máximo 5MB.');
            input.value = '';
            preview.style.display = 'none';
            displayOptions.style.display = 'none';
            return;
        }

        // Mostrar preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
            displayOptions.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
        displayOptions.style.display = 'none';
    }
}

// Drag & Drop
const uploadZone = document.getElementById('uploadZone');
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});
uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});
uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('fileInput').files = files;
        showFileName(document.getElementById('fileInput'));
    }
});

// Upload Form Submit
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';

    try {
        const response = await fetch('/admin/email_marketing/templates_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ Plantilla subida exitosamente');
            closeUploadModal();
            location.reload();
        } else {
            alert('Error: ' + result.error);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        alert('Error al subir plantilla: ' + error);
        console.error('Error completo:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Preview Modal
function previewTemplate(templateId) {
    document.getElementById('previewModal').style.display = 'block';
    document.getElementById('previewFrame').src = '/admin/preview_template.php?template_id=' + templateId + '&render=1';
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewFrame').src = '';
}

// Test Modal
function testEmail(templateId) {
    document.getElementById('testModal').style.display = 'block';
    document.getElementById('testTemplateId').value = templateId;
    document.getElementById('testResult').innerHTML = '';
}

function closeTestModal() {
    document.getElementById('testModal').style.display = 'none';
    document.getElementById('testForm').reset();
}

// Test Form Submit
document.getElementById('testForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    const resultDiv = document.getElementById('testResult');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    resultDiv.innerHTML = '';

    try {
        const response = await fetch('/admin/email_marketing/templates_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '</div>';
            setTimeout(() => {
                closeTestModal();
            }, 2000);
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + result.error + '</div>';
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Set Default
async function setDefault(templateId) {
    if (!confirm('¿Marcar esta plantilla como predeterminada?')) return;

    try {
        const formData = new FormData();
        formData.append('action', 'set_default');
        formData.append('template_id', templateId);

        const response = await fetch('/admin/email_marketing/templates_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error: ' + error);
    }
}

// Toggle Active
async function toggleActive(templateId, active) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('template_id', templateId);
        formData.append('is_active', active);

        const response = await fetch('/admin/email_marketing/templates_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error: ' + error);
    }
}

// Delete Template
async function deleteTemplate(templateId, company, name) {
    // Mensajes específicos según el tipo de plantilla
    const systemTemplates = ['mixtico', 'crv-soft', 'compratica'];
    let confirmMessage = '';

    if (systemTemplates.includes(company)) {
        confirmMessage = '⚠️ ADVERTENCIA: Está a punto de eliminar una plantilla del SISTEMA.\n\n' +
                        'Plantilla: ' + name + '\n' +
                        'Tipo: ' + company.toUpperCase() + '\n\n' +
                        '❌ Esta acción NO SE PUEDE DESHACER.\n' +
                        '❌ Perderá todos los cambios realizados.\n\n' +
                        '¿Está COMPLETAMENTE SEGURO de eliminar esta plantilla?';
    } else {
        confirmMessage = '¿Está seguro de eliminar la plantilla "' + name + '"?\n\n' +
                        'Esta acción no se puede deshacer.';
    }

    if (!confirm(confirmMessage)) return;

    // Confirmación adicional para plantillas del sistema
    if (systemTemplates.includes(company)) {
        if (!confirm('🔴 ÚLTIMA CONFIRMACIÓN 🔴\n\nEscriba OK mentalmente y confirme para eliminar permanentemente.')) {
            return;
        }
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_template');
        formData.append('template_id', templateId);

        const response = await fetch('/admin/email_marketing/templates_api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ Plantilla eliminada exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch (error) {
        alert('❌ Error: ' + error);
    }
}

// Close modals on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>
