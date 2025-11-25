<?php
// Nueva Campaña de Email Marketing

// Obtener SMTPconfigs y templates
$smtp_configs = $pdo->query("SELECT * FROM email_smtp_configs WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$templates = $pdo->query("SELECT * FROM email_templates WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

// Verificar si existe la tabla lugares_comerciales
$table_lugares_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
    $table_lugares_exists = (bool)$check;
} catch (Exception $e) {
    $table_lugares_exists = false;
}

// Verificar si existe la tabla lugares_foursquare
$table_foursquare_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
    $table_foursquare_exists = (bool)$check;
} catch (Exception $e) {
    $table_foursquare_exists = false;
}

// Verificar si existe la tabla lugares_yelp
$table_yelp_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
    $table_yelp_exists = (bool)$check;
} catch (Exception $e) {
    $table_yelp_exists = false;
}

// Verificar si existe la tabla lugares_paginas_amarillas
$table_pa_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
    $table_pa_exists = (bool)$check;
} catch (Exception $e) {
    $table_pa_exists = false;
}

// Obtener categorías de lugares_foursquare si existe la tabla
$categorias_foursquare = [];
if ($table_foursquare_exists) {
    try {
        $categorias_foursquare = $pdo->query("
            SELECT DISTINCT categoria, COUNT(*) as count
            FROM lugares_foursquare
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $categorias_foursquare = [];
    }
}

// Obtener categorías de lugares_yelp si existe la tabla
$categorias_yelp = [];
if ($table_yelp_exists) {
    try {
        $categorias_yelp = $pdo->query("
            SELECT DISTINCT categoria, COUNT(*) as count
            FROM lugares_yelp
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $categorias_yelp = [];
    }
}

// Obtener categorías de lugares_paginas_amarillas si existe la tabla
$categorias_pa = [];
if ($table_pa_exists) {
    try {
        $categorias_pa = $pdo->query("
            SELECT DISTINCT categoria, COUNT(*) as count
            FROM lugares_paginas_amarillas
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $categorias_pa = [];
    }
}

// Obtener categorías únicas de places_cr
$categories = $pdo->query("
    SELECT DISTINCT category, COUNT(*) as count
    FROM places_cr
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY category
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías de lugares_comerciales si existe la tabla
$categorias_lugares = [];
if ($table_lugares_exists) {
    try {
        $categorias_lugares = $pdo->query("
            SELECT DISTINCT categoria, COUNT(*) as count
            FROM lugares_comerciales
            WHERE categoria IS NOT NULL AND categoria != ''
            GROUP BY categoria
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $categorias_lugares = [];
    }
}
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
                        <?php if ($table_lugares_exists): ?>
                        <option value="lugares_comerciales">🏪 Lugares Comerciales (OpenStreetMap)</option>
                        <?php endif; ?>
                        <?php if ($table_foursquare_exists): ?>
                        <option value="lugares_foursquare">📍 Lugares Foursquare</option>
                        <?php endif; ?>
                        <?php if ($table_yelp_exists): ?>
                        <option value="lugares_yelp">⭐ Lugares Yelp (con teléfonos)</option>
                        <?php endif; ?>
                        <?php if ($table_pa_exists): ?>
                        <option value="lugares_paginas_amarillas">📒 Páginas Amarillas CR</option>
                        <?php endif; ?>
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

            <!-- Opción: Lugares Comerciales -->
            <div id="lugaresOption" class="mt-4" style="display: none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Base de Datos OpenStreetMap:</strong>
                    Lugares comerciales de Costa Rica importados desde OpenStreetMap.
                    Total de lugares: <strong><?= $table_lugares_exists ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn()) : 0 ?></strong>
                    <?php if ($table_lugares_exists): ?>
                    | Con email: <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != '' AND email IS NOT NULL")->fetchColumn()) ?></strong>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Seleccionar por Categoría</label>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllLugaresCategories()">
                                <i class="fas fa-check-double"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllLugaresCategories()">
                                <i class="fas fa-times"></i> Deseleccionar Todas
                            </button>
                        </div>
                    </div>

                    <div class="row mt-3" style="max-height: 400px; overflow-y: auto;">
                        <?php
                        if ($table_lugares_exists && !empty($categorias_lugares)):
                            foreach ($categorias_lugares as $cat):
                                $cat_name = $cat['categoria'];

                                // Obtener tipos únicos de esta categoría
                                $tipos_stmt = $pdo->prepare("
                                    SELECT DISTINCT tipo, COUNT(*) as count
                                    FROM lugares_comerciales
                                    WHERE categoria = ? AND tipo IS NOT NULL AND tipo != ''
                                    GROUP BY tipo
                                    ORDER BY count DESC
                                    LIMIT 20
                                ");
                                $tipos_stmt->execute([$cat_name]);
                                $tipos = $tipos_stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($tipos)):
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="card" style="background-color: #f8f9fa;">
                                    <div class="card-body">
                                        <h6 class="text-primary">
                                            <i class="fas fa-tag"></i> <?= ucfirst(h($cat_name)) ?>
                                            <span class="badge bg-secondary"><?= number_format($cat['count']) ?></span>
                                        </h6>
                                        <?php foreach ($tipos as $tipo): ?>
                                            <div class="form-check">
                                                <input class="form-check-input lugares-checkbox"
                                                       type="checkbox"
                                                       name="lugares_tipos[]"
                                                       value="<?= h($tipo['tipo']) ?>"
                                                       id="lugar_<?= h(str_replace(' ', '_', $tipo['tipo'])) ?>">
                                                <label class="form-check-label" for="lugar_<?= h(str_replace(' ', '_', $tipo['tipo'])) ?>">
                                                    <?= ucfirst(h($tipo['tipo'])) ?>
                                                    <span class="badge bg-info"><?= number_format($tipo['count']) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong>
                    Solo se enviarán emails a lugares que tengan un email registrado.
                    <?php if ($table_lugares_exists): ?>
                    Actualmente hay <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != '' AND email IS NOT NULL")->fetchColumn()) ?></strong> lugares con email en la base de datos.
                    <?php endif; ?>
                </div>

                <!-- Botón para ver lugares específicos -->
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary" id="loadLugaresBtn" onclick="loadLugaresByTipos()" disabled>
                        <i class="fas fa-eye"></i> Ver Lugares Específicos para Seleccionar
                    </button>
                    <div id="lugaresLoadingMsg" style="display: none;" class="mt-2">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Cargando lugares...
                    </div>
                </div>

                <!-- Tabla de lugares específicos -->
                <div id="lugaresTable" style="display: none;" class="mt-4">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-map-marker-alt"></i> Seleccionar Lugares Específicos (<span id="lugaresCount">0</span> encontrados)</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-light" onclick="selectAllLugares()">
                                    <i class="fas fa-check-square"></i> Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="deselectAllLugares()">
                                    <i class="fas fa-square"></i> Ninguno
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllLugaresCheckbox" onchange="toggleAllLugares(this)"></th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>Dirección</th>
                                            <th>Ciudad</th>
                                            <th>Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lugaresTableBody">
                                        <!-- Se llena dinámicamente con JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <strong>Seleccionados: <span id="selectedLugaresCount" class="text-primary">0</span></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opción: Foursquare -->
            <div id="foursquareOption" class="mt-4" style="display: none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Base de Datos Foursquare:</strong>
                    Lugares comerciales verificados desde Foursquare Places API.
                    Total de lugares: <strong><?= $table_foursquare_exists ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn()) : 0 ?></strong>
                    <?php if ($table_foursquare_exists): ?>
                    | Con email: <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email != '' AND email IS NOT NULL")->fetchColumn()) ?></strong>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Seleccionar por Categoría</label>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllFoursquareCategories()">
                                <i class="fas fa-check-double"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllFoursquareCategories()">
                                <i class="fas fa-times"></i> Deseleccionar Todas
                            </button>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <?php if ($table_foursquare_exists && !empty($categorias_foursquare)): ?>
                            <?php foreach ($categorias_foursquare as $cat): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input foursquare-checkbox"
                                           type="checkbox"
                                           name="foursquare_categorias[]"
                                           value="<?= h($cat['categoria']) ?>"
                                           id="fsq_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                    <label class="form-check-label" for="fsq_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                        <?= h($cat['categoria']) ?>
                                        <span class="badge bg-info"><?= number_format($cat['count']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong>
                    Solo se enviarán emails a lugares que tengan un email registrado.
                    <?php if ($table_foursquare_exists): ?>
                    Actualmente hay <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email != '' AND email IS NOT NULL")->fetchColumn()) ?></strong> lugares con email.
                    <?php endif; ?>
                </div>

                <!-- Botón para ver lugares específicos -->
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary" id="loadFoursquareBtn" onclick="loadFoursquareByCategorias()" disabled>
                        <i class="fas fa-eye"></i> Ver Lugares Específicos para Seleccionar
                    </button>
                    <div id="foursquareLoadingMsg" style="display: none;" class="mt-2">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Cargando lugares...
                    </div>
                </div>

                <!-- Tabla de lugares específicos -->
                <div id="foursquareTable" style="display: none;" class="mt-4">
                    <div class="card">
                        <div class="card-header bg-purple text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                            <span><i class="fas fa-map-marker-alt"></i> Seleccionar Lugares Foursquare (<span id="foursquareCount">0</span> encontrados)</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-light" onclick="selectAllFoursquare()">
                                    <i class="fas fa-check-square"></i> Todos
                                </button>
                                <button type="button" class="btn btn-sm btn-light" onclick="deselectAllFoursquare()">
                                    <i class="fas fa-square"></i> Ninguno
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllFoursquareCheckbox" onchange="toggleAllFoursquare(this)"></th>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>Ciudad</th>
                                            <th>Categoría</th>
                                        </tr>
                                    </thead>
                                    <tbody id="foursquareTableBody">
                                        <!-- Se llena dinámicamente con JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <strong>Seleccionados: <span id="selectedFoursquareCount" class="text-primary">0</span></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opción: Yelp -->
            <div id="yelpOption" class="mt-4" style="display: none;">
                <div class="alert alert-success">
                    <i class="fab fa-yelp"></i> <strong>Base de Datos Yelp:</strong>
                    Lugares con teléfonos verificados desde Yelp Fusion API.
                    Total de lugares: <strong><?= $table_yelp_exists ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_yelp")->fetchColumn()) : 0 ?></strong>
                    <?php if ($table_yelp_exists): ?>
                    | Con teléfono: <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_yelp WHERE telefono != '' AND telefono IS NOT NULL")->fetchColumn()) ?></strong>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Seleccionar por Categoría</label>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="selectAllYelpCategories()">
                                <i class="fas fa-check-double"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllYelpCategories()">
                                <i class="fas fa-times"></i> Deseleccionar Todas
                            </button>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <?php if ($table_yelp_exists && !empty($categorias_yelp)): ?>
                            <?php foreach ($categorias_yelp as $cat): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input yelp-checkbox"
                                           type="checkbox"
                                           name="yelp_categorias[]"
                                           value="<?= h($cat['categoria']) ?>"
                                           id="yelp_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                    <label class="form-check-label" for="yelp_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                        <?= h($cat['categoria']) ?>
                                        <span class="badge bg-danger"><?= number_format($cat['count']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-phone"></i> <strong>Ventaja:</strong>
                    Yelp incluye teléfonos verificados de los negocios.
                </div>

                <div class="text-center mb-3">
                    <button type="button" class="btn btn-danger" id="loadYelpBtn" onclick="loadYelpByCategorias()" disabled>
                        <i class="fas fa-eye"></i> Ver Lugares Específicos para Seleccionar
                    </button>
                </div>

                <div id="yelpTable" style="display: none;" class="mt-4">
                    <div class="card">
                        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background: #d32323;">
                            <span><i class="fab fa-yelp"></i> Seleccionar Lugares Yelp (<span id="yelpCount">0</span> encontrados)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllYelpCheckbox" onchange="toggleAllYelp(this)"></th>
                                            <th>Nombre</th>
                                            <th>Teléfono</th>
                                            <th>Ciudad</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yelpTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <strong>Seleccionados: <span id="selectedYelpCount" class="text-danger">0</span></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Opción: Páginas Amarillas -->
            <div id="paginasAmarillasOption" class="mt-4" style="display: none;">
                <div class="alert" style="background: #fff3cd; border-color: #f5c518;">
                    <i class="fas fa-book" style="color: #f5c518;"></i> <strong>Base de Datos Páginas Amarillas:</strong>
                    Directorio comercial de Costa Rica.
                    Total de lugares: <strong><?= $table_pa_exists ? number_format($pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas")->fetchColumn()) : 0 ?></strong>
                    <?php if ($table_pa_exists): ?>
                    | Con teléfono: <strong><?= number_format($pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE telefono != '' AND telefono IS NOT NULL")->fetchColumn()) ?></strong>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Seleccionar por Categoría</label>
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="selectAllPACategories()">
                                <i class="fas fa-check-double"></i> Seleccionar Todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPACategories()">
                                <i class="fas fa-times"></i> Deseleccionar Todas
                            </button>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <?php if ($table_pa_exists && !empty($categorias_pa)): ?>
                            <?php foreach ($categorias_pa as $cat): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input pa-checkbox"
                                           type="checkbox"
                                           name="pa_categorias[]"
                                           value="<?= h($cat['categoria']) ?>"
                                           id="pa_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                    <label class="form-check-label" for="pa_<?= h(str_replace(' ', '_', $cat['categoria'])) ?>">
                                        <?= h($cat['categoria']) ?>
                                        <span class="badge bg-warning text-dark"><?= number_format($cat['count']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mb-3">
                    <button type="button" class="btn btn-warning text-dark" id="loadPABtn" onclick="loadPAByCategorias()" disabled>
                        <i class="fas fa-eye"></i> Ver Lugares Específicos para Seleccionar
                    </button>
                </div>

                <div id="paTable" style="display: none;" class="mt-4">
                    <div class="card">
                        <div class="card-header text-dark d-flex justify-content-between align-items-center" style="background: #f5c518;">
                            <span><i class="fas fa-book"></i> Seleccionar Lugares Páginas Amarillas (<span id="paCount">0</span> encontrados)</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAllPACheckbox" onchange="toggleAllPA(this)"></th>
                                            <th>Nombre</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Ciudad</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <strong>Seleccionados: <span id="selectedPACount" class="text-warning">0</span></strong>
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
    const lugaresOption = document.getElementById('lugaresOption');
    if (lugaresOption) lugaresOption.style.display = 'none';
    const foursquareOption = document.getElementById('foursquareOption');
    if (foursquareOption) foursquareOption.style.display = 'none';
    const yelpOption = document.getElementById('yelpOption');
    if (yelpOption) yelpOption.style.display = 'none';
    const paginasAmarillasOption = document.getElementById('paginasAmarillasOption');
    if (paginasAmarillasOption) paginasAmarillasOption.style.display = 'none';
    document.getElementById('manualOption').style.display = 'none';

    if (this.value === 'excel') {
        document.getElementById('excelOption').style.display = 'block';
    } else if (this.value === 'database') {
        document.getElementById('databaseOption').style.display = 'block';
    } else if (this.value === 'lugares_comerciales') {
        if (lugaresOption) lugaresOption.style.display = 'block';
    } else if (this.value === 'lugares_foursquare') {
        if (foursquareOption) foursquareOption.style.display = 'block';
    } else if (this.value === 'lugares_yelp') {
        if (yelpOption) yelpOption.style.display = 'block';
    } else if (this.value === 'lugares_paginas_amarillas') {
        if (paginasAmarillasOption) paginasAmarillasOption.style.display = 'block';
    } else if (this.value === 'manual') {
        document.getElementById('manualOption').style.display = 'block';
    }
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

    fetch('get_places_by_categories.php', {
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
            alert('Error al cargar lugares');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al cargar lugares');
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
    const templateId = document.querySelector('input[name="template_id"]:checked')?.value;

    if (!templateId) {
        alert('Por favor selecciona una plantilla primero');
        return;
    }

    // Mostrar loading
    const modal = document.createElement('div');
    modal.id = 'previewModal';
    modal.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; width: 90%; max-width: 800px; max-height: 90vh; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 50px rgba(0,0,0,0.3);">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0;"><i class="fas fa-eye"></i> Vista Previa del Email</h5>
                    <button onclick="closePreviewModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                <div id="previewContent" style="padding: 0; overflow-y: auto; max-height: calc(90vh - 60px);">
                    <div style="text-align: center; padding: 50px;">
                        <i class="fas fa-spinner fa-spin fa-3x" style="color: #667eea;"></i>
                        <p style="margin-top: 15px; color: #666;">Cargando vista previa...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Cargar preview
    fetch('/admin/email_marketing/templates_api.php?action=get_template_preview&template_id=' + templateId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('previewContent').innerHTML = `
                    <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                        <strong>Asunto:</strong> ${data.subject || 'Sin asunto'}
                    </div>
                    <div style="padding: 0;">
                        <iframe id="previewFrame" style="width: 100%; height: 500px; border: none;"></iframe>
                    </div>
                `;
                // Escribir contenido en el iframe
                const iframe = document.getElementById('previewFrame');
                const iframeDoc = iframe.contentWindow.document;
                iframeDoc.open();
                iframeDoc.write(data.html);
                iframeDoc.close();
            } else {
                document.getElementById('previewContent').innerHTML = `
                    <div style="padding: 30px; text-align: center; color: #dc3545;">
                        <i class="fas fa-exclamation-circle fa-3x"></i>
                        <p style="margin-top: 15px;">${data.error || 'Error al cargar la vista previa'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('previewContent').innerHTML = `
                <div style="padding: 30px; text-align: center; color: #dc3545;">
                    <i class="fas fa-exclamation-circle fa-3x"></i>
                    <p style="margin-top: 15px;">Error de conexión: ${error.message}</p>
                </div>
            `;
        });
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    if (modal) modal.remove();
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
    } else if (sourceType === 'lugares_comerciales') {
        const checked = document.querySelectorAll('.lugares-checkbox:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Por favor seleccione al menos un tipo de lugar');
            return false;
        }

        // Si hay lugares específicos seleccionados, agregarlos al formulario
        const selectedLugares = document.querySelectorAll('.lugar-checkbox:checked');
        if (selectedLugares.length > 0) {
            // Limpiar campos previos
            document.querySelectorAll('input[name="selected_lugares[]"]').forEach(el => el.remove());

            // Agregar los lugares seleccionados como campos hidden
            selectedLugares.forEach(checkbox => {
                const lugarData = JSON.parse(checkbox.getAttribute('data-lugar'));
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_lugares[]';
                input.value = JSON.stringify(lugarData);
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

// ============================================
// Funciones para Lugares Comerciales
// ============================================

// Seleccionar/Deseleccionar todas las categorías de lugares
function selectAllLugaresCategories() {
    document.querySelectorAll('.lugares-checkbox').forEach(cb => cb.checked = true);
    updateLoadLugaresButton();
}

function deselectAllLugaresCategories() {
    document.querySelectorAll('.lugares-checkbox').forEach(cb => cb.checked = false);
    updateLoadLugaresButton();
    const lugaresTable = document.getElementById('lugaresTable');
    if (lugaresTable) lugaresTable.style.display = 'none';
}

// Actualizar botón de cargar lugares según checkboxes
function updateLoadLugaresButton() {
    const checked = document.querySelectorAll('.lugares-checkbox:checked').length;
    const loadBtn = document.getElementById('loadLugaresBtn');
    if (loadBtn) {
        if (checked > 0) {
            loadBtn.disabled = false;
            loadBtn.classList.add('btn-pulse');
        } else {
            loadBtn.disabled = true;
            loadBtn.classList.remove('btn-pulse');
        }
    }
}

// Escuchar cambios en checkboxes de lugares
document.addEventListener('DOMContentLoaded', function() {
    const lugaresCheckboxes = document.querySelectorAll('.lugares-checkbox');
    lugaresCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateLoadLugaresButton);
    });
});

// Cargar lugares por tipos seleccionados via AJAX
function loadLugaresByTipos() {
    const selectedTipos = Array.from(document.querySelectorAll('.lugares-checkbox:checked'))
        .map(cb => cb.value);

    if (selectedTipos.length === 0) {
        alert('Seleccione al menos un tipo');
        return;
    }

    // Mostrar loading
    const loadBtn = document.getElementById('loadLugaresBtn');
    const loadingMsg = document.getElementById('lugaresLoadingMsg');
    const lugaresTable = document.getElementById('lugaresTable');

    if (loadBtn) loadBtn.disabled = true;
    if (loadingMsg) loadingMsg.style.display = 'block';
    if (lugaresTable) lugaresTable.style.display = 'none';

    // AJAX request
    const formData = new FormData();
    selectedTipos.forEach(tipo => formData.append('tipos[]', tipo));

    fetch('/admin/get_lugares_by_tipos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayLugares(data.lugares);
            const lugaresCount = document.getElementById('lugaresCount');
            if (lugaresCount) lugaresCount.textContent = data.count;
            if (lugaresTable) lugaresTable.style.display = 'block';
        } else {
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
        if (loadBtn) loadBtn.disabled = false;
        if (loadingMsg) loadingMsg.style.display = 'none';
    });
}

// Mostrar lugares en la tabla
function displayLugares(lugares) {
    const tbody = document.getElementById('lugaresTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (lugares.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No se encontraron lugares con email en estos tipos</td></tr>';
        return;
    }

    lugares.forEach(lugar => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="lugar-checkbox" value="${lugar.id}" data-lugar='${JSON.stringify(lugar)}' onchange="updateSelectedLugaresCount()"></td>
            <td><strong>${escapeHtml(lugar.nombre || '')}</strong></td>
            <td><small>${escapeHtml(lugar.email || '')}</small></td>
            <td>${escapeHtml(lugar.telefono || '')}</td>
            <td><small>${escapeHtml(lugar.direccion || '')}</small></td>
            <td>${escapeHtml(lugar.ciudad || '')}</td>
            <td><span class="badge bg-secondary">${escapeHtml(lugar.tipo || '')}</span></td>
        `;
        tbody.appendChild(row);
    });

    updateSelectedLugaresCount();
}

// Escape HTML para prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Seleccionar/Deseleccionar todos los lugares
function toggleAllLugares(checkbox) {
    document.querySelectorAll('.lugar-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedLugaresCount();
}

function selectAllLugares() {
    const selectAllCheckbox = document.getElementById('selectAllLugaresCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        toggleAllLugares(selectAllCheckbox);
    }
}

function deselectAllLugares() {
    const selectAllCheckbox = document.getElementById('selectAllLugaresCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        toggleAllLugares(selectAllCheckbox);
    }
}

// Actualizar contador de seleccionados
function updateSelectedLugaresCount() {
    const count = document.querySelectorAll('.lugar-checkbox:checked').length;
    const selectedCount = document.getElementById('selectedLugaresCount');
    if (selectedCount) selectedCount.textContent = count;
}

// ============================================
// Funciones para Foursquare
// ============================================

// Seleccionar/Deseleccionar todas las categorías de Foursquare
function selectAllFoursquareCategories() {
    document.querySelectorAll('.foursquare-checkbox').forEach(cb => cb.checked = true);
    updateLoadFoursquareButton();
}

function deselectAllFoursquareCategories() {
    document.querySelectorAll('.foursquare-checkbox').forEach(cb => cb.checked = false);
    updateLoadFoursquareButton();
    const foursquareTable = document.getElementById('foursquareTable');
    if (foursquareTable) foursquareTable.style.display = 'none';
}

// Actualizar botón de cargar lugares Foursquare
function updateLoadFoursquareButton() {
    const checked = document.querySelectorAll('.foursquare-checkbox:checked').length;
    const loadBtn = document.getElementById('loadFoursquareBtn');
    if (loadBtn) {
        loadBtn.disabled = checked === 0;
    }
}

// Escuchar cambios en checkboxes de Foursquare
document.addEventListener('DOMContentLoaded', function() {
    const foursquareCheckboxes = document.querySelectorAll('.foursquare-checkbox');
    foursquareCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateLoadFoursquareButton);
    });
});

// Cargar lugares Foursquare por categorías
function loadFoursquareByCategorias() {
    const selectedCategorias = Array.from(document.querySelectorAll('.foursquare-checkbox:checked'))
        .map(cb => cb.value);

    if (selectedCategorias.length === 0) {
        alert('Seleccione al menos una categoría');
        return;
    }

    const loadBtn = document.getElementById('loadFoursquareBtn');
    const loadingMsg = document.getElementById('foursquareLoadingMsg');
    const foursquareTable = document.getElementById('foursquareTable');

    if (loadBtn) loadBtn.disabled = true;
    if (loadingMsg) loadingMsg.style.display = 'block';
    if (foursquareTable) foursquareTable.style.display = 'none';

    const formData = new FormData();
    selectedCategorias.forEach(cat => formData.append('categorias[]', cat));

    fetch('/admin/get_foursquare_by_categorias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayFoursquareLugares(data.lugares);
            const foursquareCount = document.getElementById('foursquareCount');
            if (foursquareCount) foursquareCount.textContent = data.count;
            if (foursquareTable) foursquareTable.style.display = 'block';
        } else {
            alert('Error al cargar lugares: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al cargar lugares');
    })
    .finally(() => {
        if (loadBtn) loadBtn.disabled = false;
        if (loadingMsg) loadingMsg.style.display = 'none';
    });
}

// Mostrar lugares Foursquare en la tabla
function displayFoursquareLugares(lugares) {
    const tbody = document.getElementById('foursquareTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (lugares.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">No se encontraron lugares con email en estas categorías</td></tr>';
        return;
    }

    lugares.forEach(lugar => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="foursquare-lugar-checkbox" value="${lugar.id}" data-foursquare='${JSON.stringify(lugar)}' onchange="updateSelectedFoursquareCount()"></td>
            <td><strong>${escapeHtml(lugar.nombre || '')}</strong></td>
            <td><small>${escapeHtml(lugar.email || '')}</small></td>
            <td>${escapeHtml(lugar.telefono || '')}</td>
            <td>${escapeHtml(lugar.ciudad || '')}</td>
            <td><span class="badge bg-purple" style="background: #8b5cf6;">${escapeHtml(lugar.categoria || '')}</span></td>
        `;
        tbody.appendChild(row);
    });

    updateSelectedFoursquareCount();
}

// Toggle all Foursquare lugares
function toggleAllFoursquare(checkbox) {
    document.querySelectorAll('.foursquare-lugar-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedFoursquareCount();
}

function selectAllFoursquare() {
    const selectAllCheckbox = document.getElementById('selectAllFoursquareCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
        toggleAllFoursquare(selectAllCheckbox);
    }
}

function deselectAllFoursquare() {
    const selectAllCheckbox = document.getElementById('selectAllFoursquareCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        toggleAllFoursquare(selectAllCheckbox);
    }
}

function updateSelectedFoursquareCount() {
    const count = document.querySelectorAll('.foursquare-lugar-checkbox:checked').length;
    const selectedCount = document.getElementById('selectedFoursquareCount');
    if (selectedCount) selectedCount.textContent = count;
}

// ============================================
// Funciones para Yelp
// ============================================

function selectAllYelpCategories() {
    document.querySelectorAll('.yelp-checkbox').forEach(cb => cb.checked = true);
    updateLoadYelpButton();
}

function deselectAllYelpCategories() {
    document.querySelectorAll('.yelp-checkbox').forEach(cb => cb.checked = false);
    updateLoadYelpButton();
    const yelpTable = document.getElementById('yelpTable');
    if (yelpTable) yelpTable.style.display = 'none';
}

function updateLoadYelpButton() {
    const checked = document.querySelectorAll('.yelp-checkbox:checked').length;
    const loadBtn = document.getElementById('loadYelpBtn');
    if (loadBtn) loadBtn.disabled = checked === 0;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.yelp-checkbox').forEach(cb => {
        cb.addEventListener('change', updateLoadYelpButton);
    });
});

function loadYelpByCategorias() {
    const selectedCategorias = Array.from(document.querySelectorAll('.yelp-checkbox:checked'))
        .map(cb => cb.value);

    if (selectedCategorias.length === 0) {
        alert('Seleccione al menos una categoría');
        return;
    }

    const loadBtn = document.getElementById('loadYelpBtn');
    const yelpTable = document.getElementById('yelpTable');

    if (loadBtn) loadBtn.disabled = true;
    if (yelpTable) yelpTable.style.display = 'none';

    const formData = new FormData();
    selectedCategorias.forEach(cat => formData.append('categorias[]', cat));

    fetch('/admin/get_yelp_by_categorias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayYelpLugares(data.lugares);
            const yelpCount = document.getElementById('yelpCount');
            if (yelpCount) yelpCount.textContent = data.count;
            if (yelpTable) yelpTable.style.display = 'block';
        } else {
            alert('Error al cargar lugares: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al cargar lugares');
    })
    .finally(() => {
        if (loadBtn) loadBtn.disabled = false;
    });
}

function displayYelpLugares(lugares) {
    const tbody = document.getElementById('yelpTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (lugares.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No se encontraron lugares</td></tr>';
        return;
    }

    lugares.forEach(lugar => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="yelp-lugar-checkbox" value="${lugar.id}" data-yelp='${JSON.stringify(lugar)}' onchange="updateSelectedYelpCount()"></td>
            <td><strong>${escapeHtml(lugar.nombre || '')}</strong></td>
            <td><i class="fas fa-phone text-success"></i> ${escapeHtml(lugar.telefono || '-')}</td>
            <td>${escapeHtml(lugar.ciudad || '')}</td>
            <td><span class="text-warning">${lugar.rating ? '★'.repeat(Math.floor(lugar.rating)) : '-'}</span></td>
        `;
        tbody.appendChild(row);
    });

    updateSelectedYelpCount();
}

function toggleAllYelp(checkbox) {
    document.querySelectorAll('.yelp-lugar-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateSelectedYelpCount();
}

function updateSelectedYelpCount() {
    const count = document.querySelectorAll('.yelp-lugar-checkbox:checked').length;
    const selectedCount = document.getElementById('selectedYelpCount');
    if (selectedCount) selectedCount.textContent = count;
}

// ============================================
// Funciones para Páginas Amarillas
// ============================================

function selectAllPACategories() {
    document.querySelectorAll('.pa-checkbox').forEach(cb => cb.checked = true);
    updateLoadPAButton();
}

function deselectAllPACategories() {
    document.querySelectorAll('.pa-checkbox').forEach(cb => cb.checked = false);
    updateLoadPAButton();
    const paTable = document.getElementById('paTable');
    if (paTable) paTable.style.display = 'none';
}

function updateLoadPAButton() {
    const checked = document.querySelectorAll('.pa-checkbox:checked').length;
    const loadBtn = document.getElementById('loadPABtn');
    if (loadBtn) loadBtn.disabled = checked === 0;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pa-checkbox').forEach(cb => {
        cb.addEventListener('change', updateLoadPAButton);
    });
});

function loadPAByCategorias() {
    const selectedCategorias = Array.from(document.querySelectorAll('.pa-checkbox:checked'))
        .map(cb => cb.value);

    if (selectedCategorias.length === 0) {
        alert('Seleccione al menos una categoría');
        return;
    }

    const loadBtn = document.getElementById('loadPABtn');
    const paTable = document.getElementById('paTable');

    if (loadBtn) loadBtn.disabled = true;
    if (paTable) paTable.style.display = 'none';

    const formData = new FormData();
    selectedCategorias.forEach(cat => formData.append('categorias[]', cat));

    fetch('/admin/get_paginas_amarillas_by_categorias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPALugares(data.lugares);
            const paCount = document.getElementById('paCount');
            if (paCount) paCount.textContent = data.count;
            if (paTable) paTable.style.display = 'block';
        } else {
            alert('Error al cargar lugares: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al cargar lugares');
    })
    .finally(() => {
        if (loadBtn) loadBtn.disabled = false;
    });
}

function displayPALugares(lugares) {
    const tbody = document.getElementById('paTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (lugares.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No se encontraron lugares</td></tr>';
        return;
    }

    lugares.forEach(lugar => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="pa-lugar-checkbox" value="${lugar.id}" data-pa='${JSON.stringify(lugar)}' onchange="updateSelectedPACount()"></td>
            <td><strong>${escapeHtml(lugar.nombre || '')}</strong></td>
            <td>${lugar.telefono ? '<i class="fas fa-phone text-success"></i> ' + escapeHtml(lugar.telefono) : '-'}</td>
            <td>${lugar.email ? '<i class="fas fa-envelope text-info"></i> ' + escapeHtml(lugar.email) : '-'}</td>
            <td>${escapeHtml(lugar.ciudad || '')}</td>
        `;
        tbody.appendChild(row);
    });

    updateSelectedPACount();
}

function toggleAllPA(checkbox) {
    document.querySelectorAll('.pa-lugar-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateSelectedPACount();
}

function updateSelectedPACount() {
    const count = document.querySelectorAll('.pa-lugar-checkbox:checked').length;
    const selectedCount = document.getElementById('selectedPACount');
    if (selectedCount) selectedCount.textContent = count;
}
</script>
