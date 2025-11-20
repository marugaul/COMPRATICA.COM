<?php
// Nueva Campaña de Email Marketing

// Obtener SMTPconfigs y templates
$smtp_configs = $pdo->query("SELECT * FROM email_smtp_configs WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$templates = $pdo->query("SELECT * FROM email_templates WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías únicas de places_cr
$categories = $pdo->query("
    SELECT DISTINCT category, COUNT(*) as count
    FROM places_cr
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY category
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4"><i class="fas fa-plus-circle"></i> Nueva Campaña de Email</h1>

<form action="email_marketing_api.php" method="POST" enctype="multipart/form-data" id="campaignForm">
    <input type="hidden" name="action" value="create_campaign">

    <!-- Información Básica -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Información de la Campaña
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre de la Campaña *</label>
                    <input type="text" name="campaign_name" class="form-control"
                           placeholder="Ej: Promoción Hoteles Enero 2025" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Asunto del Email *</label>
                    <input type="text" name="subject" class="form-control"
                           placeholder="Ej: Oferta Especial para Hoteles en Costa Rica" required>
                </div>
            </div>
        </div>
    </div>

    <!-- Selección de Origen de Datos -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-database"></i> Origen de Destinatarios
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fuente de Datos *</label>
                    <select name="source_type" id="sourceType" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <option value="excel">📄 Subir Excel (.xlsx, .csv)</option>
                        <option value="database">🗄️ Base de Datos (places_cr)</option>
                        <option value="manual">✍️ Ingresar Manualmente</option>
                    </select>
                </div>
            </div>

            <!-- Opción: Upload Excel -->
            <div id="excelOption" class="mt-4" style="display: none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Formato de Excel:</strong>
                    El archivo debe tener las columnas: <code>nombre</code>, <code>email</code>, <code>telefono</code> (opcional)
                </div>
                <div class="mb-3">
                    <label class="form-label">Archivo Excel/CSV</label>
                    <input type="file" name="excel_file" class="form-control"
                           accept=".xlsx,.xls,.csv">
                    <small class="text-muted">Formatos soportados: .xlsx, .xls, .csv</small>
                </div>
                <div class="text-end">
                    <a href="email_marketing_api.php?action=download_template" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download"></i> Descargar Plantilla Excel
                    </a>
                </div>
            </div>

            <!-- Opción: Base de Datos -->
            <div id="databaseOption" class="mt-4" style="display: none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Base de Datos OSM:</strong>
                    Seleccione las categorías de lugares que desea contactar. Total de lugares: <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM places_cr")->fetchColumn()) ?></strong>
                </div>

                <div class="mb-3">
                    <label class="form-label">Seleccionar Categorías</label>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllCategories()">
                                <i class="fas fa-check-double"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllCategories()">
                                <i class="fas fa-times"></i> Deseleccionar Todas
                            </button>
                        </div>
                    </div>

                    <div class="row mt-3" style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $category_groups = [
                            'accommodation' => 'Alojamiento',
                            'food' => 'Comida & Bebida',
                            'shopping' => 'Comercio',
                            'transport' => 'Transporte',
                            'healthcare' => 'Salud',
                            'education' => 'Educación',
                            'government' => 'Gobierno',
                            'culture' => 'Cultura',
                            'sports' => 'Deportes',
                            'nature' => 'Naturaleza',
                            'religion' => 'Religión',
                            'services' => 'Servicios',
                            'professional' => 'Profesional',
                            'emergency' => 'Emergencia',
                            'places' => 'Lugares'
                        ];

                        foreach ($category_groups as $group_key => $group_name):
                            $group_categories = array_filter($categories, function($cat) use ($group_key) {
                                return $cat['category'] === $group_key;
                            });

                            if (!empty($group_categories)):
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="card" style="background-color: #f8f9fa;">
                                    <div class="card-body">
                                        <h6 class="text-primary"><i class="fas fa-folder"></i> <?= $group_name ?></h6>
                                        <?php
                                        // Obtener tipos únicos de esta categoría
                                        $types = $pdo->prepare("
                                            SELECT DISTINCT type, COUNT(*) as count
                                            FROM places_cr
                                            WHERE category = ?
                                            GROUP BY type
                                            ORDER BY type
                                        ");
                                        $types->execute([$group_key]);

                                        while ($type = $types->fetch(PDO::FETCH_ASSOC)):
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input category-checkbox"
                                                       type="checkbox"
                                                       name="categories[]"
                                                       value="<?= h($type['type']) ?>"
                                                       id="cat_<?= h($type['type']) ?>">
                                                <label class="form-check-label" for="cat_<?= h($type['type']) ?>">
                                                    <?= ucfirst(h($type['type'])) ?>
                                                    <span class="badge bg-secondary"><?= number_format($type['count']) ?></span>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong>
                    Solo se enviarán emails a lugares que tengan un email registrado en el campo <code>tags</code>.
                    Actualmente hay aproximadamente <strong>192 lugares con email</strong> en la base de datos.
                </div>
            </div>

            <!-- Opción: Manual -->
            <div id="manualOption" class="mt-4" style="display: none;">
                <div class="mb-3">
                    <label class="form-label">Destinatarios (uno por línea)</label>
                    <textarea name="manual_recipients" class="form-control" rows="10"
                              placeholder="nombre@ejemplo.com, Nombre Completo, Teléfono&#10;otro@ejemplo.com, Otro Nombre, +506-xxxx-xxxx"></textarea>
                    <small class="text-muted">Formato: email, nombre, teléfono (separados por comas)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Selección de Plantilla -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-file-code"></i> Plantilla de Email
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <div class="alert alert-warning">
                    No hay plantillas disponibles. <a href="?page=templates">Crear plantilla</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($templates as $template): ?>
                        <div class="col-md-4 mb-3">
                            <div class="template-preview" onclick="selectTemplate(<?= $template['id'] ?>)">
                                <input type="radio" name="template_id" value="<?= $template['id'] ?>"
                                       id="template_<?= $template['id'] ?>" required style="display: none;">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-envelope fa-3x mb-3" style="color: var(--<?= $template['company'] === 'mixtico' ? 'secondary' : ($template['company'] === 'crv-soft' ? 'secondary' : 'primary') ?>);"></i>
                                        <h6><?= h($template['name']) ?></h6>
                                        <span class="badge" style="background-color: <?= $template['company'] === 'mixtico' ? '#3b82f6' : ($template['company'] === 'crv-soft' ? '#06b6d4' : '#dc2626') ?>;">
                                            <?= ucfirst(h($template['company'])) ?>
                                        </span>
                                        <p class="small text-muted mt-2 mb-0"><?= h($template['subject']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Configuración SMTP -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-server"></i> Configuración de Envío (SMTP)
        </div>
        <div class="card-body">
            <?php if (empty($smtp_configs)): ?>
                <div class="alert alert-warning">
                    No hay configuraciones SMTP disponibles. <a href="?page=smtp-config">Configurar SMTP</a>
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <label class="form-label">Cuenta de Envío *</label>
                    <select name="smtp_config_id" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($smtp_configs as $config): ?>
                            <option value="<?= $config['id'] ?>">
                                <?= h($config['name']) ?> - <?= h($config['from_email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Archivo Adjunto (Opcional) -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-paperclip"></i> Archivo Adjunto (Opcional)
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Adjuntar Archivo</label>
                <input type="file" name="attachment" class="form-control"
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <small class="text-muted">Archivos permitidos: PDF, imágenes, Word. Tamaño máximo: 5MB</small>
            </div>
        </div>
    </div>

    <!-- Botones de Acción -->
    <div class="card">
        <div class="card-body text-end">
            <a href="?page=dashboard" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="previewCampaign()">
                <i class="fas fa-eye"></i> Vista Previa
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Crear y Programar Campaña
            </button>
        </div>
    </div>
</form>

<script>
// Manejo de selección de origen de datos
document.getElementById('sourceType').addEventListener('change', function() {
    document.getElementById('excelOption').style.display = 'none';
    document.getElementById('databaseOption').style.display = 'none';
    document.getElementById('manualOption').style.display = 'none';

    if (this.value === 'excel') {
        document.getElementById('excelOption').style.display = 'block';
    } else if (this.value === 'database') {
        document.getElementById('databaseOption').style.display = 'block';
    } else if (this.value === 'manual') {
        document.getElementById('manualOption').style.display = 'block';
    }
});

// Selección de template
function selectTemplate(id) {
    document.querySelectorAll('.template-preview').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('template_' + id).checked = true;
}

// Seleccionar/Deseleccionar todas las categorías
function selectAllCategories() {
    document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = true);
}

function deselectAllCategories() {
    document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);
}

// Vista previa
function previewCampaign() {
    alert('Vista previa en desarrollo. Esta función mostrará un preview del email antes de enviar.');
}

// Validación antes de enviar
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    const sourceType = document.getElementById('sourceType').value;

    if (sourceType === 'database') {
        const checked = document.querySelectorAll('.category-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Por favor seleccione al menos una categoría');
            return false;
        }
    }

    if (!confirm('¿Está seguro de crear esta campaña? Los emails se programarán para envío.')) {
        e.preventDefault();
        return false;
    }
});
</script>
