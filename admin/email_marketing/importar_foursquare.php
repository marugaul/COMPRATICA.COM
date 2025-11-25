<?php
/**
 * Importar Lugares desde Foursquare Places API
 * 50,000 llamadas gratis al mes
 * Interfaz web para administración
 */

// Verificar tabla y estadísticas
$table_exists = false;
$total_lugares = 0;
$with_email = 0;
$with_phone = 0;
$with_website = 0;
$last_update = null;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
    $table_exists = (bool)$check;

    if ($table_exists) {
        $total_lugares = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_foursquare WHERE website IS NOT NULL AND website != ''")->fetchColumn();
        $last_update_row = $pdo->query("SELECT MAX(updated_at) as last_update FROM lugares_foursquare")->fetch();
        $last_update = $last_update_row['last_update'];
    }
} catch (Exception $e) {
    $table_exists = false;
}

// Categorías disponibles para Foursquare
$categorias_foursquare = [
    '13065' => 'Restaurantes',
    '13034,13035' => 'Cafés',
    '13003,13004' => 'Bares y Vida Nocturna',
    '19014' => 'Hoteles y Hospedaje',
    '17000' => 'Tiendas y Comercios',
    '12000' => 'Servicios',
    '10000' => 'Entretenimiento',
    '15000' => 'Salud y Medicina',
    '18000' => 'Deportes y Recreación'
];

// Ciudades principales de Costa Rica
$ciudades_cr = [
    'San José, Costa Rica',
    'Alajuela, Costa Rica',
    'Cartago, Costa Rica',
    'Heredia, Costa Rica',
    'Liberia, Costa Rica',
    'Puntarenas, Costa Rica',
    'Limón, Costa Rica',
    'Escazú, Costa Rica',
    'Santa Ana, Costa Rica',
    'Guanacaste, Costa Rica',
    'Jacó, Costa Rica',
    'Manuel Antonio, Costa Rica',
    'La Fortuna, Costa Rica',
    'Tamarindo, Costa Rica',
    'Puerto Viejo, Costa Rica'
];
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-map-marker-alt"></i> Importar desde Foursquare</h2>
        <p class="text-muted">Obtén datos de negocios desde Foursquare Places API (50,000 llamadas/mes gratis)</p>

        <!-- Navegación de pestañas Foursquare -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" href="?page=importar-foursquare">
                    <i class="fas fa-cloud-download-alt"></i> Importar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=lugares-foursquare">
                    <i class="fas fa-database"></i> Ver Base de Datos
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Estadísticas actuales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card <?= $table_exists ? 'success' : 'warning' ?>">
            <i class="fas fa-database fa-2x"></i>
            <h3><?= $table_exists ? '✓' : '✗' ?></h3>
            <p><?= $table_exists ? 'Tabla Creada' : 'Tabla NO Existe' ?></p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card primary">
            <i class="fas fa-store fa-2x"></i>
            <h3><?= number_format($total_lugares) ?></h3>
            <p>Lugares Foursquare</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-envelope fa-2x"></i>
            <h3><?= number_format($with_email) ?></h3>
            <p>Con Email</p>
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

<!-- Estadísticas adicionales si hay datos -->
<?php if ($total_lugares > 0): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <i class="fas fa-phone fa-2x"></i>
            <h3><?= number_format($with_phone) ?></h3>
            <p>Con Teléfono (<?= $total_lugares > 0 ? round($with_phone/$total_lugares*100, 1) : 0 ?>%)</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
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
                    Debes crear la tabla <code>lugares_foursquare</code> antes de importar datos.
                </div>

                <button id="btnCrearTabla" class="btn btn-primary btn-lg">
                    <i class="fas fa-database"></i> Crear Tabla lugares_foursquare
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
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download"></i> Importar Lugares desde Foursquare</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> ¿Qué datos obtendrás de Foursquare?</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Nombre</strong> del negocio verificado</li>
                                <li><strong>Dirección</strong> completa y geolocalización</li>
                                <li><strong>Teléfono</strong> de contacto</li>
                                <li><strong>Email</strong> (cuando disponible)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Website</strong> oficial</li>
                                <li><strong>Categoría</strong> del negocio</li>
                                <li><strong>Rating</strong> y popularidad</li>
                                <li><strong>Estado de verificación</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Opciones de importación -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><strong><i class="fas fa-tags"></i> Categorías a importar:</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="catTodas" checked>
                            <label class="form-check-label" for="catTodas"><strong>Todas las categorías</strong></label>
                        </div>
                        <hr>
                        <div id="listaCategorias">
                            <?php foreach ($categorias_foursquare as $id => $nombre): ?>
                            <div class="form-check">
                                <input class="form-check-input cat-individual" type="checkbox"
                                       id="cat_<?= str_replace(',', '_', $id) ?>" value="<?= $id ?>" checked>
                                <label class="form-check-label" for="cat_<?= str_replace(',', '_', $id) ?>">
                                    <?= h($nombre) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong><i class="fas fa-map"></i> Ciudades a buscar:</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ciudadTodas" checked>
                            <label class="form-check-label" for="ciudadTodas"><strong>Todas las ciudades principales</strong></label>
                        </div>
                        <hr>
                        <div id="listaCiudades" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($ciudades_cr as $ciudad): ?>
                            <div class="form-check">
                                <input class="form-check-input ciudad-individual" type="checkbox"
                                       id="ciudad_<?= md5($ciudad) ?>" value="<?= h($ciudad) ?>" checked>
                                <label class="form-check-label" for="ciudad_<?= md5($ciudad) ?>">
                                    <?= h($ciudad) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="text-center my-4">
                    <button id="btnImportar" class="btn btn-success btn-lg"
                            style="padding: 20px 50px; font-size: 20px;"
                            <?= !$table_exists ? 'disabled' : '' ?>>
                        <i class="fas fa-cloud-download-alt fa-2x"></i><br>
                        <span style="font-size: 24px; font-weight: bold;">IMPORTAR DESDE FOURSQUARE</span><br>
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
                                 role="progressbar" style="width: 0%; font-size: 16px; font-weight: bold;">0%</div>
                        </div>
                        <p id="progressMessage" class="mt-3 mb-0">Iniciando conexión con Foursquare API...</p>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="resultArea" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Top categorías si hay datos -->
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
                    FROM lugares_foursquare
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
                    FROM lugares_foursquare
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

// Manejar checkbox "Todas las ciudades"
document.getElementById('ciudadTodas').addEventListener('change', function() {
    document.querySelectorAll('.ciudad-individual').forEach(cb => cb.checked = this.checked);
});

document.querySelectorAll('.ciudad-individual').forEach(cb => {
    cb.addEventListener('change', function() {
        const todas = document.querySelectorAll('.ciudad-individual');
        const marcadas = document.querySelectorAll('.ciudad-individual:checked');
        document.getElementById('ciudadTodas').checked = todas.length === marcadas.length;
    });
});

// Crear tabla
document.getElementById('btnCrearTabla')?.addEventListener('click', async function() {
    const btn = this;
    const resultDiv = document.getElementById('resultCrearTabla');

    btn.disabled = true;
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Creando tabla...</div>';

    try {
        const response = await fetch('/admin/email_marketing/importar_foursquare_api.php', {
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

// Importar lugares
document.getElementById('btnImportar').addEventListener('click', async function() {
    const btn = this;
    const progressArea = document.getElementById('progressArea');
    const resultArea = document.getElementById('resultArea');

    // Recoger categorías seleccionadas
    const categoriasSeleccionadas = [];
    document.querySelectorAll('.cat-individual:checked').forEach(cb => {
        categoriasSeleccionadas.push(cb.value);
    });

    // Recoger ciudades seleccionadas
    const ciudadesSeleccionadas = [];
    document.querySelectorAll('.ciudad-individual:checked').forEach(cb => {
        ciudadesSeleccionadas.push(cb.value);
    });

    if (categoriasSeleccionadas.length === 0) {
        alert('Selecciona al menos una categoría');
        return;
    }

    if (ciudadesSeleccionadas.length === 0) {
        alert('Selecciona al menos una ciudad');
        return;
    }

    const totalBusquedas = categoriasSeleccionadas.length * ciudadesSeleccionadas.length;
    if (!confirm(`¿Deseas importar lugares de Foursquare?\n\nSe realizarán aproximadamente ${totalBusquedas} búsquedas.\nEsto puede tomar varios minutos.`)) {
        return;
    }

    btn.disabled = true;
    progressArea.style.display = 'block';
    resultArea.style.display = 'none';

    updateProgress(5, 'Iniciando importación desde Foursquare...');

    // Polling de progreso
    let progressInterval = setInterval(async () => {
        try {
            const progressResponse = await fetch('/admin/email_marketing/foursquare_progreso_api.php');
            const progressData = await progressResponse.json();

            if (progressData.percent > 0) {
                let message = progressData.message;
                if (progressData.total > 0) {
                    message += ` (${progressData.imported.toLocaleString()} / ${progressData.total.toLocaleString()})`;
                }
                updateProgress(progressData.percent, message);
            }
        } catch (e) {
            // Ignorar errores de polling
        }
    }, 2000);

    try {
        const response = await fetch('/admin/email_marketing/importar_foursquare_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=importar&categorias=' + encodeURIComponent(JSON.stringify(categoriasSeleccionadas)) +
                  '&ciudades=' + encodeURIComponent(JSON.stringify(ciudadesSeleccionadas))
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
                        <h4><i class="fas fa-check-circle"></i> ¡Importación desde Foursquare Exitosa!</h4>
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
                            <strong>Con email:</strong> ${result.stats.with_email.toLocaleString()} (${result.stats.email_percent}%)<br>
                            <strong>Con teléfono:</strong> ${result.stats.with_phone.toLocaleString()} (${result.stats.phone_percent}%)<br>
                            <strong>Con website:</strong> ${result.stats.with_website.toLocaleString()} (${result.stats.website_percent}%)
                        </p>
                    </div>
                    <div class="text-center mt-3">
                        <a href="?page=lugares-foursquare" class="btn btn-primary">
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
