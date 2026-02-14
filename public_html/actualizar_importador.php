<?php
/**
 * Script de actualización automática
 * Crea/actualiza los archivos del importador de lugares
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$updates = [];
$errors = [];

// ============================================
// ARCHIVO 1: email_marketing.php (actualizar menú)
// ============================================
$file1 = __DIR__ . '/../admin/email_marketing.php';
if (file_exists($file1)) {
    $content = file_get_contents($file1);

    // Verificar si ya tiene la opción
    if (strpos($content, 'importar-lugares') === false) {
        // Agregar opción en el menú (antes del HR)
        $content = str_replace(
            '<li><hr style="border-color: #334155; margin: 20px 15px;"></li>',
            '<li><a href="?page=importar-lugares" class="<?= $page === \'importar-lugares\' ? \'active\' : \'\' ?>">
                        <i class="fas fa-cloud-download-alt"></i> Importar Lugares
                    </a></li>
                    <li><hr style="border-color: #334155; margin: 20px 15px;"></li>',
            $content
        );

        // Agregar case en el router
        $content = str_replace(
            "case 'blacklist':\n                        include __DIR__ . '/email_marketing/blacklist.php';\n                        break;\n                    default:",
            "case 'blacklist':\n                        include __DIR__ . '/email_marketing/blacklist.php';\n                        break;\n                    case 'importar-lugares':\n                        include __DIR__ . '/email_marketing/importar_lugares.php';\n                        break;\n                    default:",
            $content
        );

        if (file_put_contents($file1, $content)) {
            $updates[] = '✓ email_marketing.php actualizado (menú agregado)';
        } else {
            $errors[] = '✗ No se pudo actualizar email_marketing.php';
        }
    } else {
        $updates[] = '✓ email_marketing.php ya tiene la opción (sin cambios)';
    }
} else {
    $errors[] = '✗ No se encontró email_marketing.php';
}

// ============================================
// ARCHIVO 2: importar_lugares.php
// ============================================
$file2 = __DIR__ . '/../admin/email_marketing/importar_lugares.php';
$content2 = <<<'PHP'
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
        const response = await fetch('importar_lugares_api.php', {
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

    updateProgress(10, 'Descargando datos desde OpenStreetMap...');

    try {
        const response = await fetch('importar_lugares_api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=importar'
        });

        updateProgress(50, 'Procesando datos recibidos...');

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
PHP;

if (file_put_contents($file2, $content2)) {
    $updates[] = '✓ importar_lugares.php creado/actualizado';
} else {
    $errors[] = '✗ No se pudo crear importar_lugares.php';
}

// ============================================
// ARCHIVO 3: importar_lugares_api.php
// ============================================
$file3 = __DIR__ . '/../admin/email_marketing/importar_lugares_api.php';
$content3 = file_get_contents('/home/user/COMPRATICA/admin/email_marketing/importar_lugares_api.php');

if ($content3 && file_put_contents($file3, $content3)) {
    $updates[] = '✓ importar_lugares_api.php creado/actualizado';
} else {
    $errors[] = '✗ No se pudo crear importar_lugares_api.php';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/fontawesome-css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="fas fa-sync-alt"></i> Actualización del Sistema</h3>
            </div>
            <div class="card-body">
                <h5>Resultados de la actualización:</h5>

                <?php if (!empty($updates)): ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Actualizaciones exitosas:</h6>
                        <ul class="mb-0">
                            <?php foreach ($updates as $update): ?>
                                <li><?= $update ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle"></i> Errores:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($errors)): ?>
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-info-circle"></i> ¡Actualización Completada!</h5>
                        <p>Ahora puedes:</p>
                        <ol>
                            <li>Ir a <strong>Email Marketing</strong> en tu panel admin</li>
                            <li>Verás la nueva opción <strong>"Importar Lugares"</strong> en el menú lateral</li>
                            <li>Haz clic ahí para crear la tabla e importar datos</li>
                        </ol>
                        <hr>
                        <a href="../admin/email_marketing.php?page=importar-lugares" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i> Ir a Importar Lugares
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        <strong>⚠️ IMPORTANTE:</strong> Después de usar esta herramienta, elimina este archivo por seguridad:
                        <code>/public_html/actualizar_importador.php</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
