<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación OSM - Progreso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2rem;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 2rem;
            font-size: 2rem;
            text-align: center;
        }
        .progress-container {
            background: #f7fafc;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
        }
        .progress-bar-wrapper {
            background: #e2e8f0;
            border-radius: 10px;
            height: 40px;
            overflow: hidden;
            position: relative;
            margin: 1rem 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        .stat-card .label {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 0.5rem;
        }
        .status-message {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .status-message.success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .status-message.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .current-category {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 1rem 0;
            padding: 0.75rem;
            background: white;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        .log-container {
            background: #1a202c;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
            margin: 1rem 0;
        }
        .log-line {
            padding: 0.25rem 0;
            border-bottom: 1px solid #2d3748;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌍 Importación de Lugares desde OpenStreetMap</h1>

        <div class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar">0%</div>
            </div>

            <div class="current-category" id="currentCategory">
                Esperando inicio...
            </div>

            <div class="stats">
                <div class="stat-card">
                    <div class="number" id="totalPlaces">0</div>
                    <div class="label">Lugares Importados</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="currentCategoryNum">0/8</div>
                    <div class="label">Categoría Actual</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="elapsedTime">0:00</div>
                    <div class="label">Tiempo Transcurrido</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="estimatedTime">--</div>
                    <div class="label">Tiempo Estimado</div>
                </div>
            </div>

            <div id="statusMessage" class="status-message" style="display: none;">
                Iniciando importación...
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" id="startBtn" onclick="startImport()">
                <span id="startBtnText">🚀 Iniciar Importación</span>
            </button>
            <button class="btn btn-danger" id="stopBtn" onclick="stopImport()" style="display: none;">
                ⏸️ Pausar
            </button>
            <a href="shuttle_search.php" class="btn btn-success" style="display: none;" id="testBtn">
                🧪 Probar Buscador
            </a>
        </div>

        <div class="log-container" id="logContainer">
            <div class="log-line">📋 Log de importación aparecerá aquí...</div>
        </div>
    </div>

    <script>
        let updateInterval = null;
        let startTime = null;

        function addLog(message) {
            const logContainer = document.getElementById('logContainer');
            const timestamp = new Date().toLocaleTimeString();
            const logLine = document.createElement('div');
            logLine.className = 'log-line';
            logLine.textContent = `[${timestamp}] ${message}`;
            logContainer.appendChild(logLine);
            logContainer.scrollTop = logContainer.scrollHeight;
        }

        async function updateProgress() {
            try {
                const response = await fetch('api/import_status.php');
                const data = await response.json();

                // Actualizar barra de progreso
                const progressBar = document.getElementById('progressBar');
                progressBar.style.width = data.progress + '%';
                progressBar.textContent = Math.round(data.progress) + '%';

                // Actualizar estadísticas
                document.getElementById('totalPlaces').textContent = data.total_imported.toLocaleString();
                document.getElementById('currentCategoryNum').textContent = `${data.current_category_index}/${data.total_categories}`;

                // Actualizar categoría actual
                const categoryEl = document.getElementById('currentCategory');
                if (data.status === 'running') {
                    categoryEl.innerHTML = `<span class="spinner"></span> Importando: ${data.current_category}`;
                } else if (data.status === 'completed') {
                    categoryEl.textContent = '✅ Importación Completada';
                } else if (data.status === 'error') {
                    categoryEl.textContent = '❌ Error en la importación';
                } else {
                    categoryEl.textContent = '⏸️ Pausado / No iniciado';
                }

                // Calcular tiempo transcurrido
                if (startTime) {
                    const elapsed = Math.floor((Date.now() - startTime) / 1000);
                    const minutes = Math.floor(elapsed / 60);
                    const seconds = elapsed % 60;
                    document.getElementById('elapsedTime').textContent =
                        `${minutes}:${seconds.toString().padStart(2, '0')}`;

                    // Estimar tiempo restante
                    if (data.progress > 5) {
                        const estimatedTotal = (elapsed / data.progress) * 100;
                        const remaining = Math.floor(estimatedTotal - elapsed);
                        const remMin = Math.floor(remaining / 60);
                        const remSec = remaining % 60;
                        document.getElementById('estimatedTime').textContent =
                            `${remMin}:${remSec.toString().padStart(2, '0')}`;
                    }
                }

                // Actualizar mensaje de estado
                const statusMsg = document.getElementById('statusMessage');
                if (data.message) {
                    statusMsg.style.display = 'block';
                    statusMsg.textContent = data.message;
                    statusMsg.className = 'status-message';
                    if (data.status === 'completed') {
                        statusMsg.className += ' success';
                    } else if (data.status === 'error') {
                        statusMsg.className += ' error';
                    }
                }

                // Actualizar log si hay nuevos mensajes
                if (data.last_log) {
                    addLog(data.last_log);
                }

                // Si completó, mostrar botón de prueba
                if (data.status === 'completed') {
                    document.getElementById('testBtn').style.display = 'inline-block';
                    document.getElementById('stopBtn').style.display = 'none';
                    document.getElementById('startBtn').style.display = 'none';
                    if (updateInterval) {
                        clearInterval(updateInterval);
                    }
                }

            } catch (error) {
                console.error('Error actualizando progreso:', error);
            }
        }

        let isImporting = false;

        async function startImport() {
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');

            if (isImporting) {
                return; // Prevenir múltiples llamadas
            }

            isImporting = true;
            startBtn.disabled = true;
            startBtn.innerHTML = '<span class="spinner"></span> Iniciando...';

            startTime = Date.now();
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';

            addLog('✅ Importación iniciada correctamente');

            // Iniciar actualización de progreso cada 2 segundos
            updateInterval = setInterval(updateProgress, 2000);

            // Ejecutar importación por lotes
            await runImportBatch();
        }

        async function runImportBatch() {
            try {
                const response = await fetch('api/start_import.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (!data.success) {
                    addLog('❌ Error: ' + (data.error || 'Error desconocido'));
                    isImporting = false;
                    return;
                }

                if (data.paused) {
                    addLog('⏸️ Importación pausada');
                    isImporting = false;
                    return;
                }

                if (data.completed) {
                    addLog('🎉 ¡Importación completada! Total: ' + data.total_imported + ' lugares');
                    document.getElementById('testBtn').style.display = 'inline-block';
                    document.getElementById('stopBtn').style.display = 'none';
                    isImporting = false;
                    if (updateInterval) {
                        clearInterval(updateInterval);
                    }
                    updateProgress(); // Actualización final
                    return;
                }

                if (data.continue) {
                    let logMsg = `✓ ${data.category}: ${data.imported} lugares importados`;

                    // Agregar debug info si está disponible
                    if (data.debug) {
                        logMsg += ` (recibidos: ${data.debug.received}`;
                        if (data.debug.details) {
                            logMsg += `, sin nombre: ${data.debug.details.no_name}, sin coords: ${data.debug.details.no_coords}, errores: ${data.debug.details.errors}`;
                        }
                        logMsg += ')';
                    }

                    addLog(logMsg);

                    // Actualizar progreso inmediatamente
                    updateProgress();

                    // Esperar 2 segundos antes del siguiente lote (rate limit de Overpass)
                    setTimeout(() => {
                        if (isImporting) {
                            runImportBatch(); // Continuar con el siguiente lote
                        }
                    }, 2000);
                }

            } catch (error) {
                addLog('❌ Error de conexión: ' + error.message);
                isImporting = false;

                // Reintentar en 5 segundos
                addLog('⏳ Reintentando en 5 segundos...');
                setTimeout(() => {
                    if (isImporting !== false) { // Si no fue pausado manualmente
                        runImportBatch();
                    }
                }, 5000);
            }
        }

        async function stopImport() {
            if (!confirm('¿Detener la importación? Podrás reanudarla después.')) {
                return;
            }

            try {
                isImporting = false; // Detener el loop

                const response = await fetch('api/stop_import.php', { method: 'POST' });
                const data = await response.json();

                if (data.success) {
                    addLog('⏸️ Importación pausada');
                    document.getElementById('stopBtn').style.display = 'none';
                    document.getElementById('startBtn').style.display = 'inline-block';
                    document.getElementById('startBtn').innerHTML = '▶️ Reanudar Importación';
                    document.getElementById('startBtn').disabled = false;

                    if (updateInterval) {
                        clearInterval(updateInterval);
                    }
                }
            } catch (error) {
                alert('Error al pausar: ' + error.message);
            }
        }

        // Al cargar, verificar estado y reanudar si es necesario
        window.addEventListener('load', async function() {
            // Obtener estado actual
            const response = await fetch('api/import_status.php');
            const data = await response.json();

            if (data.success) {
                if (data.status === 'running' && data.current_category_index < data.total_categories) {
                    // Hay una importación en curso, reanudarla automáticamente
                    addLog('🔄 Reanudando importación en curso...');

                    const startBtn = document.getElementById('startBtn');
                    const stopBtn = document.getElementById('stopBtn');

                    isImporting = true;
                    startTime = Date.now() - ((data.current_category_index / data.total_categories) * 60000); // Estimar tiempo
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'inline-block';

                    // Iniciar actualización
                    updateInterval = setInterval(updateProgress, 2000);
                    updateProgress(); // Actualización inmediata

                    // Continuar importación
                    setTimeout(() => runImportBatch(), 1000);
                } else if (data.status === 'paused') {
                    // Está pausado, mostrar botón de reanudar
                    addLog('⏸️ Importación pausada. Click en Reanudar para continuar.');
                    document.getElementById('startBtn').innerHTML = '▶️ Reanudar Importación';
                    document.getElementById('startBtn').style.display = 'inline-block';
                    updateProgress();
                } else if (data.status === 'completed') {
                    // Ya completado
                    addLog('✅ Importación previamente completada: ' + data.total_imported + ' lugares');
                    document.getElementById('testBtn').style.display = 'inline-block';
                    document.getElementById('startBtn').style.display = 'none';
                    updateProgress();
                } else {
                    // Estado idle, listo para iniciar
                    updateProgress();
                }
            } else {
                // Error obteniendo estado, solo actualizar
                updateProgress();
            }

            // Mantener actualización de progreso
            if (!updateInterval) {
                updateInterval = setInterval(updateProgress, 2000);
            }
        });
    </script>
</body>
</html>
