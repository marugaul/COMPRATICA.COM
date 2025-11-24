<?php
/**
 * Enriquecer Emails desde Sitios Web
 * Extrae emails de páginas de contacto automáticamente
 */

// Obtener estadísticas directamente de BD
$stats = ['total_with_website' => 0, 'with_email' => 0, 'without_email' => 0];
$stats_error = null;

try {
    if (!isset($pdo)) {
        throw new Exception("PDO no está disponible");
    }

    $stats['total_with_website'] = (int)$pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website IS NOT NULL AND website != ''")->fetchColumn();
    $stats['with_email'] = (int)$pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website IS NOT NULL AND website != '' AND email IS NOT NULL AND email != ''")->fetchColumn();
    $stats['without_email'] = $stats['total_with_website'] - $stats['with_email'];

    // Debug: mostrar totales
    $total_lugares = (int)$pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
    $stats['total_lugares'] = $total_lugares;
} catch (Exception $e) {
    $stats_error = $e->getMessage();
}
?>

<div class="row">
    <div class="col-12">
        <h2><i class="fas fa-envelope"></i> Enriquecer Emails desde Sitios Web</h2>
        <p class="text-muted">Extrae emails automáticamente visitando las páginas de contacto de los negocios</p>

        <!-- Navegación de pestañas -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="?page=importar-lugares">
                    <i class="fas fa-cloud-download-alt"></i> Importar desde OSM
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="?page=enriquecer-emails">
                    <i class="fas fa-envelope"></i> Enriquecer Emails
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=lugares-comerciales">
                    <i class="fas fa-list"></i> Ver Base de Datos
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Estadísticas actuales -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <i class="fas fa-globe fa-2x"></i>
            <h3><?= number_format($stats['total_with_website']) ?></h3>
            <p>Lugares con Website</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card success">
            <i class="fas fa-check-circle fa-2x"></i>
            <h3><?= number_format($stats['with_email']) ?></h3>
            <p>Ya tienen Email</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card warning">
            <i class="fas fa-exclamation-circle fa-2x"></i>
            <h3><?= number_format($stats['without_email']) ?></h3>
            <p>Sin Email (procesar)</p>
        </div>
    </div>
</div>

<?php if ($stats_error): ?>
<div class="alert alert-danger">
    <strong>Error al obtener estadísticas:</strong> <?= htmlspecialchars($stats_error) ?>
</div>
<?php endif; ?>

<?php if (isset($stats['total_lugares']) && $stats['total_lugares'] > 0 && $stats['total_with_website'] === 0): ?>
<div class="alert alert-warning">
    <strong>⚠️ Problema detectado:</strong> Tienes <?= number_format($stats['total_lugares']) ?> lugares en la base de datos,
    pero <strong>NINGUNO tiene website</strong> en OpenStreetMap.<br>
    <br>
    <strong>Solución:</strong> OpenStreetMap no tiene muchos websites para Costa Rica.
    Te recomiendo usar <strong>Foursquare API</strong> (gratis) o <strong>Google Places API</strong> para obtener websites primero.
</div>
<?php endif; ?>

<!-- Panel de enriquecimiento -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-robot"></i> Crawler Automático de Emails</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h5>
                    <ol class="mb-0">
                        <li>Busca lugares que tienen <strong>website</strong> pero NO tienen <strong>email</strong></li>
                        <li>Visita la página principal del sitio web</li>
                        <li>Si no encuentra email, busca en páginas de contacto: /contacto, /contact, /acerca-de, etc.</li>
                        <li>Extrae emails usando expresiones regulares</li>
                        <li>Actualiza automáticamente la base de datos</li>
                    </ol>
                </div>

                <div class="alert alert-warning">
                    <strong>⚠️ Importante:</strong>
                    <ul class="mb-0">
                        <li>El proceso puede tomar <strong>varios minutos</strong> dependiendo de cuántos sitios procesar</li>
                        <li>Cada sitio tarda ~1 segundo en procesarse (para no saturar servidores)</li>
                        <li>Recomendado: Empezar con 50-100 sitios y luego aumentar</li>
                    </ul>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="limitSitios" class="form-label"><strong>¿Cuántos sitios procesar?</strong></label>
                        <select id="limitSitios" class="form-control form-control-lg">
                            <option value="50">50 sitios (~1 minuto)</option>
                            <option value="100" selected>100 sitios (~2 minutos)</option>
                            <option value="200">200 sitios (~3-4 minutos)</option>
                            <option value="500">500 sitios (~8-10 minutos)</option>
                            <option value="1000">1000 sitios (~15-20 minutos)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button id="btnEnriquecer" class="btn btn-success btn-lg w-100" <?= $stats['without_email'] == 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-play-circle fa-2x"></i><br>
                            <span style="font-size: 20px; font-weight: bold;">INICIAR CRAWLER</span>
                        </button>
                    </div>
                </div>

                <!-- Área de progreso -->
                <div id="progressArea" style="display: none;">
                    <div class="alert alert-info">
                        <h5 id="progressTitle">⏳ Procesando sitios web...</h5>
                        <div class="progress" style="height: 30px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%; font-size: 16px; font-weight: bold;">0%</div>
                        </div>
                        <p id="progressMessage" class="mt-3 mb-0">Iniciando crawler...</p>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="resultArea" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btnEnriquecer').addEventListener('click', async function() {
    const btn = this;
    const limit = document.getElementById('limitSitios').value;
    const progressArea = document.getElementById('progressArea');
    const resultArea = document.getElementById('resultArea');

    // Confirmar
    if (!confirm(`¿Deseas procesar ${limit} sitios web para extraer emails?\n\nEsto puede tomar varios minutos.`)) {
        return;
    }

    btn.disabled = true;
    progressArea.style.display = 'block';
    resultArea.style.display = 'none';

    updateProgress(5, 'Iniciando crawler de emails...');

    // Iniciar polling de progreso
    let progressInterval = setInterval(async () => {
        try {
            const progressResponse = await fetch('/admin/email_marketing/enriquecer_progreso_api.php');
            const progressData = await progressResponse.json();

            if (progressData.percent > 0) {
                let message = progressData.message;
                updateProgress(progressData.percent, message);
            }
        } catch (e) {
            // Ignorar errores de polling
        }
    }, 2000); // Cada 2 segundos

    try {
        const response = await fetch('/admin/email_marketing/enriquecer_emails_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=enrich&limit=${limit}`
        });

        // Detener polling
        clearInterval(progressInterval);

        const result = await response.json();

        if (result.success) {
            updateProgress(100, '¡Enriquecimiento completado!');

            // Mostrar resultados
            setTimeout(() => {
                progressArea.style.display = 'none';
                resultArea.style.display = 'block';
                resultArea.innerHTML = `
                    <div class="alert alert-success">
                        <h4><i class="fas fa-check-circle"></i> ¡Proceso Completado!</h4>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h2 class="mb-0">${result.processed.toLocaleString()}</h2>
                                <small>Sitios Procesados</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="mb-0 text-success">${result.found.toLocaleString()}</h2>
                                <small>Emails Encontrados</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="mb-0 text-info">${result.success_rate}%</h2>
                                <small>Tasa de Éxito</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="mb-0 text-primary">${result.total_emails_now.toLocaleString()}</h2>
                                <small>Total Emails en BD</small>
                            </div>
                        </div>
                        <hr>
                        <p class="mb-0 text-center">
                            <strong>¡Excelente!</strong> Se encontraron ${result.found} emails nuevos.<br>
                            <small>Recargando página en 3 segundos...</small>
                        </p>
                    </div>
                `;

                btn.disabled = false;

                // Recargar página
                setTimeout(() => window.location.reload(), 3000);

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
