<?php
/**
 * Importar Lugares Comerciales desde OpenStreetMap
 * Interfaz web con un solo click
 */

// Verificar tabla
$table_exists = false;
$total_lugares = 0;
$with_email = 0;
$last_update = null;

try {
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
    $table_exists = (bool)$check;

    if ($table_exists) {
        $total_lugares = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $last_update_row = $pdo->query("SELECT MAX(updated_at) as last_update FROM lugares_comerciales")->fetch();
        $last_update = $last_update_row['last_update'];
    }
} catch (Exception $e) {
    $table_exists = false;
}
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-cloud-download-alt"></i> Importar Lugares Comerciales</h2>
        <p class="text-muted">Descarga TODOS los negocios de Costa Rica desde OpenStreetMap (GRATIS)</p>
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
            <i class="fas fa-map-marker-alt fa-2x"></i>
            <h3><?= number_format($total_lugares) ?></h3>
            <p>Lugares en BD</p>
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

<!-- Panel de importación -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download"></i> Importación desde OpenStreetMap</h5>
            </div>
            <div class="card-body">
                <?php if (!$table_exists): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Tabla no existe</strong><br>
                        Primero debes crear la tabla. Haz clic en el botón "Crear Tabla" abajo.
                    </div>

                    <button id="btnCrearTabla" class="btn btn-primary btn-lg">
                        <i class="fas fa-database"></i> Crear Tabla lugares_comerciales
                    </button>
                    <div id="resultCrearTabla" class="mt-3"></div>

                    <hr class="my-4">
                <?php endif; ?>

                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> ¿Qué se importará?</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Alimentos:</strong> Restaurantes, bares, cafés, comida rápida</li>
                                <li><strong>Turismo:</strong> Hoteles, hostales, apartamentos</li>
                                <li><strong>Comercios:</strong> Tiendas, supermercados, boutiques</li>
                                <li><strong>Servicios:</strong> Bancos, farmacias, clínicas, gasolineras</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li><strong>Entretenimiento:</strong> Cines, teatros, discotecas</li>
                                <li><strong>Deportes:</strong> Gimnasios, piscinas, estadios</li>
                                <li><strong>Educación:</strong> Escuelas, universidades, academias</li>
                                <li><strong>Belleza:</strong> Salones, spas, peluquerías</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="text-center my-4">
                    <button id="btnImportar" class="btn btn-success btn-lg" style="padding: 20px 50px; font-size: 20px;" <?= !$table_exists ? 'disabled' : '' ?>>
                        <i class="fas fa-cloud-download-alt fa-2x"></i><br>
                        <span style="font-size: 24px; font-weight: bold;">IMPORTAR LUGARES</span><br>
                        <small style="font-size: 14px; opacity: 0.9;">(Toma 2-3 minutos)</small>
                    </button>
                </div>

                <!-- Área de progreso -->
                <div id="progressArea" style="display: none;">
                    <div class="alert alert-info">
                        <h5 id="progressTitle">⏳ Importando...</h5>
                        <div class="progress" style="height: 30px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%; font-size: 16px; font-weight: bold;">0%</div>
                        </div>
                        <p id="progressMessage" class="mt-3 mb-0">Iniciando descarga desde OpenStreetMap...</p>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="resultArea" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas después de importar -->
<div id="statsArea" style="display: none;">
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Top 10 Categorías</h5>
                </div>
                <div class="card-body">
                    <div id="topCategorias"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Top 10 Tipos</h5>
                </div>
                <div class="card-body">
                    <div id="topTipos"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Crear tabla
document.getElementById('btnCrearTabla')?.addEventListener('click', async function() {
    const btn = this;
    const resultDiv = document.getElementById('resultCrearTabla');

    btn.disabled = true;
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Creando tabla...</div>';

    try {
        const response = await fetch('/admin/email_marketing/importar_lugares_api.php', {
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
    const statsArea = document.getElementById('statsArea');

    // Confirmar
    if (!confirm('¿Deseas importar/actualizar todos los lugares de Costa Rica desde OpenStreetMap?\n\nEsto puede tomar 2-3 minutos.')) {
        return;
    }

    btn.disabled = true;
    progressArea.style.display = 'block';
    resultArea.style.display = 'none';
    statsArea.style.display = 'none';

    updateProgress(5, 'Iniciando importación...');

    // Iniciar polling de progreso
    let progressInterval = setInterval(async () => {
        try {
            const progressResponse = await fetch('/admin/email_marketing/importar_progreso_api.php');
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
    }, 2000); // Cada 2 segundos

    try {
        const response = await fetch('/admin/email_marketing/importar_lugares_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=importar'
        });

        // Detener polling
        clearInterval(progressInterval);

        const result = await response.json();

        if (result.success) {
            updateProgress(100, '¡Importación completada!');

            // Mostrar resultados
            setTimeout(() => {
                progressArea.style.display = 'none';
                resultArea.style.display = 'block';
                resultArea.innerHTML = `
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check-circle"></i> ¡Importación Exitosa!</h4>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0">${result.total.toLocaleString()}</h2>
                                <small>Encontrados</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-success">${result.imported.toLocaleString()}</h2>
                                <small>Importados</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-info">${result.updated.toLocaleString()}</h2>
                                <small>Actualizados</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h2 class="mb-0 text-danger">${result.errors.toLocaleString()}</h2>
                                <small>Errores</small>
                            </div>
                        </div>
                        <hr>
                        <p class="mb-0">
                            <strong>Total en BD:</strong> ${result.stats.total.toLocaleString()} lugares<br>
                            <strong>Con email:</strong> ${result.stats.with_email.toLocaleString()} (${result.stats.email_percent}%)<br>
                            <strong>Con teléfono:</strong> ${result.stats.with_phone.toLocaleString()} (${result.stats.phone_percent}%)
                        </p>
                    </div>
                `;

                // Mostrar estadísticas
                statsArea.style.display = 'block';

                let categoriasHtml = '<table class="table table-sm">';
                result.stats.top_categorias.forEach(cat => {
                    categoriasHtml += `<tr><td><strong>${cat.categoria}</strong></td><td class="text-end">${cat.count.toLocaleString()}</td></tr>`;
                });
                categoriasHtml += '</table>';
                document.getElementById('topCategorias').innerHTML = categoriasHtml;

                let tiposHtml = '<table class="table table-sm">';
                result.stats.top_tipos.forEach(tipo => {
                    tiposHtml += `<tr><td><strong>${tipo.tipo}</strong></td><td class="text-end">${tipo.count.toLocaleString()}</td></tr>`;
                });
                tiposHtml += '</table>';
                document.getElementById('topTipos').innerHTML = tiposHtml;

                btn.disabled = false;

                // Actualizar estadísticas de arriba
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
    progressBar.textContent = percent + '%';
    progressMessage.textContent = message;
}
</script>
