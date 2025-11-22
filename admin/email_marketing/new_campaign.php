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

                <!-- Botón para ver lugares específicos -->
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary" id="loadPlacesBtn" onclick="loadPlacesByCategories()" disabled>
                        <i class="fas fa-eye"></i> Ver Lugares Específicos para Seleccionar
                    </button>
                    <div id="placesLoadingMsg" style="display: none;" class="mt-2">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Cargando lugares...
                    </div>
                </div>

                <!-- Tabla de lugares específicos -->
                <div id="placesTable" style="display: none;" class="mt-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-map-marker-alt"></i> Seleccionar Lugares Específicos (<span id="placesCount">0</span> encontrados)</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-light" onclick="selectAllPlaces()">
                                    <i class="fas fa-check-square"></i> Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="deselectAllPlaces()">
                                    <i class="fas fa-square"></i> Ninguno
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAllPlaces(this)"></th>
                                            <th>Nombre del Lugar</th>
                                            <th>Email</th>
                                            <th>Dueño/Contacto</th>
                                            <th>Teléfono</th>
                                            <th>Dirección</th>
                                            <th>Ciudad</th>
                                            <th>Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="placesTableBody">
                                        <!-- Se llena dinámicamente con JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <strong>Seleccionados: <span id="selectedCount" class="text-primary">0</span></strong>
                        </div>
                    </div>
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
                                        <span class="badge" style="background-color: <?= $template['company'] === 'mixtico' ? '#f97316' : ($template['company'] === 'crv-soft' ? '#06b6d4' : '#dc2626') ?>;">
                                            <?= ucfirst(h($template['company'])) ?>
                                        </span>
                                        <p class="small text-muted mt-2 mb-0"><?= h($template['subject_default']) ?></p>
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

    <!-- Programación de Envío -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-clock"></i> Programación de Envío
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">¿Cuándo enviar los emails? *</label>
                <select name="send_type" id="sendType" class="form-control" required>
                    <option value="">Seleccione...</option>
                    <option value="draft">💾 Guardar como Borrador (enviar después)</option>
                    <option value="now">🚀 Enviar Inmediatamente</option>
                    <option value="scheduled">📅 Programar para Fecha/Hora</option>
                </select>
            </div>

            <div id="scheduledOption" class="mt-3" style="display: none;">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Fecha y Hora de Envío</label>
                        <input type="datetime-local" name="scheduled_datetime" id="scheduledDatetime" class="form-control">
                        <small class="text-muted">Los emails se enviarán automáticamente en esta fecha/hora</small>
                    </div>
                </div>
            </div>

            <div id="nowOption" class="mt-3 alert alert-warning" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong>
                Los emails comenzarán a enviarse inmediatamente después de crear la campaña.
            </div>

            <div id="draftOption" class="mt-3 alert alert-info" style="display: none;">
                <i class="fas fa-info-circle"></i> <strong>Nota:</strong>
                La campaña se guardará como borrador. Podrá enviarla más tarde desde el panel de campañas.
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
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save"></i> Crear Campaña
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

// Habilitar/deshabilitar botón de cargar lugares según categorías seleccionadas
document.addEventListener('DOMContentLoaded', function() {
    const updateLoadPlacesButton = () => {
        const checkedCount = document.querySelectorAll('.category-checkbox:checked').length;
        const btn = document.getElementById('loadPlacesBtn');
        if (btn) {
            btn.disabled = (checkedCount === 0);
        }
    };

    // Escuchar cambios en todos los checkboxes de categorías
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('category-checkbox')) {
            updateLoadPlacesButton();
        }
    });

    // Actualizar al cargar la página
    updateLoadPlacesButton();
});

// Manejo de tipo de envío
document.getElementById('sendType').addEventListener('change', function() {
    const submitBtn = document.getElementById('submitBtn');
    document.getElementById('scheduledOption').style.display = 'none';
    document.getElementById('nowOption').style.display = 'none';
    document.getElementById('draftOption').style.display = 'none';

    if (this.value === 'scheduled') {
        document.getElementById('scheduledOption').style.display = 'block';
        document.getElementById('scheduledDatetime').required = true;
        submitBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Crear y Programar';
    } else if (this.value === 'now') {
        document.getElementById('nowOption').style.display = 'block';
        document.getElementById('scheduledDatetime').required = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Crear y Enviar Ahora';
    } else if (this.value === 'draft') {
        document.getElementById('draftOption').style.display = 'block';
        document.getElementById('scheduledDatetime').required = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar como Borrador';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Crear Campaña';
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
    updateLoadPlacesButton();
}

function deselectAllCategories() {
    document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);
    updateLoadPlacesButton();
    document.getElementById('placesTable').style.display = 'none';
}

// Actualizar botón de cargar lugares según checkboxes
document.querySelectorAll('.category-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateLoadPlacesButton);
});

function updateLoadPlacesButton() {
    const checked = document.querySelectorAll('.category-checkbox:checked').length;
    const loadBtn = document.getElementById('loadPlacesBtn');
    if (checked > 0) {
        loadBtn.disabled = false;
        loadBtn.classList.add('btn-pulse');
    } else {
        loadBtn.disabled = true;
        loadBtn.classList.remove('btn-pulse');
    }
}

// Cargar lugares por categorías seleccionadas via AJAX
function loadPlacesByCategories() {
    const selectedCategories = Array.from(document.querySelectorAll('.category-checkbox:checked'))
        .map(cb => cb.value);

    if (selectedCategories.length === 0) {
        alert('Seleccione al menos una categoría');
        return;
    }

    // Mostrar loading
    document.getElementById('loadPlacesBtn').disabled = true;
    document.getElementById('placesLoadingMsg').style.display = 'block';
    document.getElementById('placesTable').style.display = 'none';

    // AJAX request
    const formData = new FormData();
    selectedCategories.forEach(cat => formData.append('categories[]', cat));

    fetch('/admin/get_places_by_categories.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPlaces(data.places);
            document.getElementById('placesCount').textContent = data.count;
            document.getElementById('placesTable').style.display = 'block';
        } else {
            // Mostrar error detallado del servidor
            const errorMsg = data.error || 'Error desconocido';
            const errorDetails = data.file && data.line ? `\n\nArchivo: ${data.file}\nLínea: ${data.line}` : '';
            alert('Error al cargar lugares:\n' + errorMsg + errorDetails);
            console.error('Error completo:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al cargar lugares:\n' + error.message);
    })
    .finally(() => {
        document.getElementById('loadPlacesBtn').disabled = false;
        document.getElementById('placesLoadingMsg').style.display = 'none';
    });
}

// Mostrar lugares en la tabla
function displayPlaces(places) {
    const tbody = document.getElementById('placesTableBody');
    tbody.innerHTML = '';

    if (places.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No se encontraron lugares con email en estas categorías</td></tr>';
        return;
    }

    places.forEach(place => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="place-checkbox" value="${place.id}" data-place='${JSON.stringify(place)}' onchange="updateSelectedCount()"></td>
            <td><strong>${escapeHtml(place.name)}</strong></td>
            <td><small>${escapeHtml(place.email)}</small></td>
            <td>${escapeHtml(place.owner)}</td>
            <td>${escapeHtml(place.phone)}</td>
            <td><small>${escapeHtml(place.address)}</small></td>
            <td>${escapeHtml(place.city)}</td>
            <td><span class="badge bg-secondary">${escapeHtml(place.type)}</span></td>
        `;
        tbody.appendChild(row);
    });

    updateSelectedCount();
}

// Escape HTML para prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Seleccionar/Deseleccionar todos los lugares
function toggleAllPlaces(checkbox) {
    document.querySelectorAll('.place-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

function selectAllPlaces() {
    document.getElementById('selectAllCheckbox').checked = true;
    toggleAllPlaces(document.getElementById('selectAllCheckbox'));
}

function deselectAllPlaces() {
    document.getElementById('selectAllCheckbox').checked = false;
    toggleAllPlaces(document.getElementById('selectAllCheckbox'));
}

// Actualizar contador de seleccionados
function updateSelectedCount() {
    const count = document.querySelectorAll('.place-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Vista previa
function previewCampaign() {
    alert('Vista previa en desarrollo. Esta función mostrará un preview del email antes de enviar.');
}

// Validación antes de enviar
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    const sourceType = document.getElementById('sourceType').value;
    const sendType = document.getElementById('sendType').value;

    if (sourceType === 'database') {
        const checked = document.querySelectorAll('.category-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Por favor seleccione al menos una categoría');
            return false;
        }

        // Si hay lugares específicos seleccionados, agregarlos al formulario
        const selectedPlaces = document.querySelectorAll('.place-checkbox:checked');
        if (selectedPlaces.length > 0) {
            // Limpiar campos previos
            document.querySelectorAll('input[name="selected_places[]"]').forEach(el => el.remove());

            // Agregar los lugares seleccionados como campos hidden
            selectedPlaces.forEach(checkbox => {
                const placeData = JSON.parse(checkbox.getAttribute('data-place'));
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_places[]';
                input.value = JSON.stringify(placeData);
                this.appendChild(input);
            });
        }
    }

    // Mensaje de confirmación según el tipo de envío
    let confirmMessage = '';
    if (sendType === 'now') {
        confirmMessage = '¿Está seguro? Los emails comenzarán a enviarse INMEDIATAMENTE.';
    } else if (sendType === 'scheduled') {
        const datetime = document.getElementById('scheduledDatetime').value;
        if (!datetime) {
            e.preventDefault();
            alert('Por favor seleccione una fecha y hora para el envío programado');
            return false;
        }
        confirmMessage = '¿Está seguro de programar esta campaña para: ' + datetime + '?';
    } else if (sendType === 'draft') {
        confirmMessage = '¿Crear esta campaña como borrador?';
    } else {
        confirmMessage = '¿Está seguro de crear esta campaña?';
    }

    if (!confirm(confirmMessage)) {
        e.preventDefault();
        return false;
    }
});
</script>
