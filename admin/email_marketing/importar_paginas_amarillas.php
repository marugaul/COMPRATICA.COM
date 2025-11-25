<?php
/**
 * Importar Lugares desde Páginas Amarillas Costa Rica
 * Web scraping del directorio comercial
 */

// Verificar tabla y estadísticas
$table_exists = false;
$total_lugares = 0;
$with_email = 0;
$with_phone = 0;
$with_website = 0;
$last_update = null;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
    $table_exists = (bool)$check;

    if ($table_exists) {
        $total_lugares = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE website IS NOT NULL AND website != ''")->fetchColumn();
        $last_update_row = $pdo->query("SELECT MAX(updated_at) as last_update FROM lugares_paginas_amarillas")->fetch();
        $last_update = $last_update_row['last_update'];
    }
} catch (Exception $e) {
    $table_exists = false;
}

// Categorías de Páginas Amarillas
$categorias_pa = [
    'restaurantes' => 'Restaurantes',
    'hoteles' => 'Hoteles',
    'cafeterias' => 'Cafeterías',
    'bares' => 'Bares',
    'supermercados' => 'Supermercados',
    'ferreterias' => 'Ferreterías',
    'farmacias' => 'Farmacias',
    'clinicas' => 'Clínicas',
    'hospitales' => 'Hospitales',
    'abogados' => 'Abogados',
    'contadores' => 'Contadores',
    'talleres-mecanicos' => 'Talleres Mecánicos',
    'gimnasios' => 'Gimnasios',
    'salones-belleza' => 'Salones de Belleza',
    'veterinarias' => 'Veterinarias',
    'escuelas' => 'Escuelas',
    'universidades' => 'Universidades',
    'inmobiliarias' => 'Inmobiliarias',
    'constructoras' => 'Constructoras',
    'electrica' => 'Servicios Eléctricos'
];
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-book" style="color: #f5c518;"></i> Importar desde Páginas Amarillas CR</h2>
        <p class="text-muted">Obtén datos de negocios del directorio comercial de Costa Rica</p>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" href="?page=importar-paginas-amarillas">
                    <i class="fas fa-cloud-download-alt"></i> Importar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=lugares-paginas-amarillas">
                    <i class="fas fa-database"></i> Ver Base de Datos
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card <?= $table_exists ? 'success' : 'warning' ?>">
            <i class="fas fa-database fa-2x"></i>
            <h3><?= $table_exists ? '✓' : '✗' ?></h3>
            <p><?= $table_exists ? 'Tabla Creada' : 'Tabla NO Existe' ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #f5c518 0%, #d4a60e 100%);">
            <i class="fas fa-book fa-2x"></i>
            <h3><?= number_format($total_lugares) ?></h3>
            <p>Lugares PA</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-phone fa-2x"></i>
            <h3><?= number_format($with_phone) ?></h3>
            <p>Con Teléfono</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="fas fa-clock fa-2x"></i>
            <h3><?= $last_update ? date('d/m', strtotime($last_update)) : '-' ?></h3>
            <p>Última Actualización</p>
        </div>
    </div>
</div>

<?php if ($total_lugares > 0): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fas fa-envelope fa-2x"></i>
            <h3><?= number_format($with_email) ?></h3>
            <p>Con Email (<?= $total_lugares > 0 ? round($with_email/$total_lugares*100, 1) : 0 ?>%)</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
            <i class="fas fa-globe fa-2x"></i>
            <h3><?= number_format($with_website) ?></h3>
            <p>Con Website (<?= $total_lugares > 0 ? round($with_website/$total_lugares*100, 1) : 0 ?>%)</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Crear Tabla -->
<?php if (!$table_exists): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database"></i> Crear Tabla en Base de Datos</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-triangle"></i> Tabla no existe</strong><br>
                    Debes crear la tabla <code>lugares_paginas_amarillas</code> antes de importar datos.
                </div>

                <button id="btnCrearTabla" class="btn btn-primary btn-lg">
                    <i class="fas fa-database"></i> Crear Tabla
                </button>
                <div id="resultCrearTabla" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Panel de importación -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #f5c518 0%, #d4a60e 100%);">
                <h5 class="mb-0 text-dark"><i class="fas fa-download"></i> Importar desde Páginas Amarillas CR</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Acerca de Páginas Amarillas</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Directorio comercial</strong> de Costa Rica</li>
                                <li><strong>Teléfonos</strong> de negocios locales</li>
                                <li><strong>Direcciones</strong> verificadas</li>
                                <li><strong>Categorías</strong> organizadas</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Emails</strong> de contacto</li>
                                <li><strong>Sitios web</strong> de empresas</li>
                                <li><strong>Horarios</strong> de atención</li>
                                <li><strong>Datos</strong> de negocios locales</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong>
                    Esta función utiliza web scraping. Úsala de manera responsable y respetando los términos de uso del sitio.
                    El proceso puede tomar varios minutos.
                </div>

                <!-- Opciones de importación -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label"><strong><i class="fas fa-tags"></i> Categorías a importar:</strong></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="catTodas" checked>
                            <label class="form-check-label" for="catTodas"><strong>Todas las categorías</strong></label>
                        </div>
                        <hr>
                        <div class="row" id="listaCategorias">
                            <?php foreach ($categorias_pa as $id => $nombre): ?>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input cat-individual" type="checkbox"
                                           id="cat_<?= $id ?>" value="<?= $id ?>" checked>
                                    <label class="form-check-label" for="cat_<?= $id ?>">
                                        <?= h($nombre) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="text-center my-4">
                    <button id="btnImportar" class="btn btn-lg"
                            style="padding: 20px 50px; font-size: 20px; background: linear-gradient(135deg, #f5c518 0%, #d4a60e 100%); color: #1a1a1a; border: none;"
                            <?= !$table_exists ? 'disabled' : '' ?>>
                        <i class="fas fa-book fa-2x"></i><br>
                        <span style="font-size: 24px; font-weight: bold;">IMPORTAR DESDE PÁGINAS AMARILLAS</span><br>
                        <small style="font-size: 14px; opacity: 0.9;">(Puede tomar varios minutos)</small>
                    </button>
                </div>

                <?php if (!$table_exists): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle"></i>
                    Primero crea la tabla en la base de datos.
                </div>
                <?php endif; ?>

                <!-- Área de progreso -->
                <div id="progressArea" style="display: none;">
                    <div class="alert alert-info">
                        <h5 id="progressTitle"><i class="fas fa-spinner fa-spin"></i> Importando...</h5>
                        <div class="progress" style="height: 30px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%; font-size: 16px; font-weight: bold; background: #f5c518; color: #1a1a1a;">0%</div>
                        </div>
                        <p id="progressMessage" class="mt-3 mb-0">Conectando con Páginas Amarillas CR...</p>
                    </div>
                </div>

                <div id="resultArea" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<?php if ($total_lugares > 0): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Top Categorías</h5>
            </div>
            <div class="card-body">
                <?php
                $top_cats = $pdo->query("
                    SELECT categoria, COUNT(*) as total
                    FROM lugares_paginas_amarillas
                    GROUP BY categoria
                    ORDER BY total DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="table table-sm">
                    <?php foreach ($top_cats as $cat): ?>
                    <tr>
                        <td><strong><?= h($cat['categoria']) ?></strong></td>
                        <td class="text-end"><?= number_format($cat['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-city"></i> Top Ciudades</h5>
            </div>
            <div class="card-body">
                <?php
                $top_ciudades = $pdo->query("
                    SELECT ciudad, COUNT(*) as total
                    FROM lugares_paginas_amarillas
                    WHERE ciudad IS NOT NULL AND ciudad != ''
                    GROUP BY ciudad
                    ORDER BY total DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="table table-sm">
                    <?php foreach ($top_ciudades as $ciudad): ?>
                    <tr>
                        <td><strong><?= h($ciudad['ciudad']) ?></strong></td>
                        <td class="text-end"><?= number_format($ciudad['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Manejar checkbox "Todas las categorías"
document.getElementById('catTodas').addEventListener('change', function() {
    document.querySelectorAll('.cat-individual').forEach(cb => cb.checked = this.checked);
});

document.querySelectorAll('.cat-individual').forEach(cb => {
    cb.addEventListener('change', function() {
        const todas = document.querySelectorAll('.cat-individual');
        const marcadas = document.querySelectorAll('.cat-individual:checked');
        document.getElementById('catTodas').checked = todas.length === marcadas.length;
    });
});

// Crear tabla
document.getElementById('btnCrearTabla')?.addEventListener('click', async function() {
    const btn = this;
    const resultDiv = document.getElementById('resultCrearTabla');

    btn.disabled = true;
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Creando tabla...</div>';

    try {
        const response = await fetch('/admin/email_marketing/importar_paginas_amarillas_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=crear_tabla'
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + result.message + '<br><small>Recargando página...</small></div>';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ' + result.error + '</div>';
            btn.disabled = false;
        }
    } catch (error) {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Error: ' + error.message + '</div>';
        btn.disabled = false;
    }
});

// Importar
document.getElementById('btnImportar').addEventListener('click', async function() {
    const btn = this;
    const progressArea = document.getElementById('progressArea');
    const resultArea = document.getElementById('resultArea');

    const categoriasSeleccionadas = [];
    document.querySelectorAll('.cat-individual:checked').forEach(cb => {
        categoriasSeleccionadas.push(cb.value);
    });

    if (categoriasSeleccionadas.length === 0) {
        alert('Selecciona al menos una categoría');
        return;
    }

    if (!confirm(`¿Deseas importar lugares de Páginas Amarillas CR?\n\nSe buscarán ${categoriasSeleccionadas.length} categorías.\nEsto puede tomar varios minutos.`)) {
        return;
    }

    btn.disabled = true;
    progressArea.style.display = 'block';
    resultArea.style.display = 'none';

    updateProgress(5, 'Iniciando importación desde Páginas Amarillas...');

    let progressInterval = setInterval(async () => {
        try {
            const progressResponse = await fetch('/admin/email_marketing/paginas_amarillas_progreso_api.php');
            const progressData = await progressResponse.json();

            if (progressData.percent > 0) {
                let message = progressData.message;
                if (progressData.total > 0) {
                    message += ` (${progressData.imported.toLocaleString()} importados)`;
                }
                updateProgress(progressData.percent, message);
            }
        } catch (e) {}
    }, 3000);

    try {
        const response = await fetch('/admin/email_marketing/importar_paginas_amarillas_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=importar&categorias=' + encodeURIComponent(JSON.stringify(categoriasSeleccionadas))
        });

        clearInterval(progressInterval);

        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            throw new Error('Error del servidor: ' + responseText.substring(0, 200));
        }

        if (result.success) {
            updateProgress(100, '¡Importación completada!');

            setTimeout(() => {
                progressArea.style.display = 'none';
                resultArea.style.display = 'block';
                resultArea.innerHTML = `
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check-circle"></i> ¡Importación desde Páginas Amarillas Exitosa!</h4>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-success">${result.imported.toLocaleString()}</h2>
                                <small>Nuevos</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-info">${result.updated.toLocaleString()}</h2>
                                <small>Actualizados</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-danger">${result.errors.toLocaleString()}</h2>
                                <small>Errores</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0">${result.stats.total.toLocaleString()}</h2>
                                <small>Total en BD</small>
                            </div>
                        </div>
                        <hr>
                        <p class="mb-0">
                            <strong>Con teléfono:</strong> ${result.stats.with_phone.toLocaleString()} (${result.stats.phone_percent}%)<br>
                            <strong>Con email:</strong> ${result.stats.with_email.toLocaleString()} (${result.stats.email_percent}%)<br>
                            <strong>Con website:</strong> ${result.stats.with_website.toLocaleString()} (${result.stats.website_percent}%)
                        </p>
                    </div>
                    <div class="text-center mt-3">
                        <a href="?page=lugares-paginas-amarillas" class="btn btn-primary">
                            <i class="fas fa-list"></i> Ver Lugares Importados
                        </a>
                    </div>
                `;
                btn.disabled = false;
                setTimeout(() => window.location.reload(), 5000);
            }, 1000);
        } else {
            progressArea.style.display = 'none';
            resultArea.style.display = 'block';
            resultArea.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> <strong>Error:</strong> ' + result.error + '</div>';
            btn.disabled = false;
        }

    } catch (error) {
        clearInterval(progressInterval);
        progressArea.style.display = 'none';
        resultArea.style.display = 'block';
        resultArea.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
        btn.disabled = false;
    }
});

function updateProgress(percent, message) {
    const progressBar = document.getElementById('progressBar');
    const progressMessage = document.getElementById('progressMessage');

    progressBar.style.width = percent + '%';
    progressBar.textContent = Math.round(percent) + '%';
    progressMessage.textContent = message;
}
</script>
