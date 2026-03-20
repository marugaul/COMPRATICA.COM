<?php
/**
 * Panel de Configuración de Mooving - CompraTica
 * Configuración corporativa de credenciales y comisión
 */

// 🔧 LOGGING DETALLADO PARA DEBUG
$logFile = __DIR__ . '/../logs/mooving_debug.log';
@mkdir(dirname($logFile), 0777, true);

function logDebug($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

logDebug("=== INICIO mooving-config.php ===");

try {
    logDebug("Iniciando session...");
    session_start();
    logDebug("Session iniciada OK");

    logDebug("Cargando db.php...");
    require_once __DIR__ . '/../includes/db.php';
    logDebug("db.php cargado OK");

    logDebug("Cargando MovingAPI.php...");
    require_once __DIR__ . '/../mooving/MovingAPI.php';
    logDebug("MovingAPI.php cargado OK");

    logDebug("Verificando sesión admin...");
    // Verificar que es admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        logDebug("Usuario no autorizado - redirigiendo a login");
        header('Location: login.php');
        exit;
    }
    logDebug("Usuario autorizado OK");

} catch (Exception $e) {
    logDebug("ERROR FATAL: " . $e->getMessage());
    logDebug("Stack trace: " . $e->getTraceAsString());
    die("Error al cargar Mooving Config. Ver logs/mooving_debug.log para detalles.");
}

logDebug("Obteniendo conexión PDO...");
$pdo = db();
logDebug("PDO obtenido OK");

$success = '';
$error = '';

// Crear tabla si no existe
try {
    logDebug("Creando instancia MovingAPI...");
    $api = new MovingAPI($pdo);
    logDebug("Instancia MovingAPI creada OK");

    logDebug("Inicializando tabla de configuración...");
    $api->initConfigTable();
    logDebug("Tabla de configuración inicializada OK");
} catch (Exception $e) {
    logDebug("Error al inicializar tabla: " . $e->getMessage());
    // Tabla ya existe o se creará en el primer save
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_config') {
        $apiKey = trim($_POST['api_key'] ?? '');
        $apiSecret = trim($_POST['api_secret'] ?? '');
        $merchantId = trim($_POST['merchant_id'] ?? '');
        $isSandbox = isset($_POST['is_sandbox']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $commission = (float)($_POST['commission_percentage'] ?? 15.0);

        if (empty($apiKey) || empty($apiSecret) || empty($merchantId)) {
            $error = 'Todos los campos de credenciales son requeridos.';
        } else {
            try {
                // Verificar si ya existe configuración global
                $stmt = $pdo->prepare("SELECT id FROM mooving_config WHERE entrepreneur_id IS NULL LIMIT 1");
                $stmt->execute();
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // UPDATE
                    $pdo->prepare("
                        UPDATE mooving_config SET
                            api_key = ?,
                            api_secret = ?,
                            merchant_id = ?,
                            is_sandbox = ?,
                            is_active = ?,
                            commission_percentage = ?,
                            updated_at = datetime('now','localtime')
                        WHERE entrepreneur_id IS NULL
                    ")->execute([$apiKey, $apiSecret, $merchantId, $isSandbox, $isActive, $commission]);
                } else {
                    // INSERT
                    $pdo->prepare("
                        INSERT INTO mooving_config
                        (entrepreneur_id, api_key, api_secret, merchant_id, is_sandbox, is_active, commission_percentage)
                        VALUES (NULL, ?, ?, ?, ?, ?, ?)
                    ")->execute([$apiKey, $apiSecret, $merchantId, $isSandbox, $isActive, $commission]);
                }

                $success = '✅ Configuración guardada exitosamente.';
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'test_connection') {
        try {
            $api = new MovingAPI($pdo);
            if ($api->isConfigured()) {
                $success = '✅ Conexión exitosa. Las credenciales están configuradas correctamente.';
            } else {
                $error = '⚠️ No hay credenciales configuradas. Por favor configúralas primero.';
            }
        } catch (Exception $e) {
            $error = '❌ Error de conexión: ' . $e->getMessage();
        }
    }
}

// Cargar configuración actual
$config = null;
try {
    logDebug("Cargando configuración actual...");
    $stmt = $pdo->prepare("SELECT * FROM mooving_config WHERE entrepreneur_id IS NULL LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    logDebug("Configuración cargada: " . ($config ? "SI (config_id=" . ($config['id'] ?? 'N/A') . ")" : "NO"));
} catch (Exception $e) {
    logDebug("Error al cargar configuración: " . $e->getMessage());
    // No hay configuración aún
}

logDebug("Renderizando HTML...");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Mooving | Admin CompraTica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 24px 32px;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #1f2937;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header h1 i {
            color: #8b5cf6;
            font-size: 2rem;
        }
        .header .subtitle {
            color: #6b7280;
            margin-top: 8px;
            font-size: 0.95rem;
        }
        .content {
            background: white;
            padding: 32px;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 2px solid #3b82f6;
        }
        .info-box {
            background: #f3e8ff;
            border: 2px solid #8b5cf6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .info-box h3 {
            color: #6b21a8;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box p {
            color: #7c3aed;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .info-box ul {
            color: #7c3aed;
            margin-left: 20px;
            line-height: 1.8;
        }
        .form-section {
            margin-bottom: 32px;
        }
        .form-section h2 {
            color: #1f2937;
            font-size: 1.25rem;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row {
            margin-bottom: 20px;
        }
        .form-row label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .form-row label .required {
            color: #ef4444;
        }
        .form-row input[type="text"],
        .form-row input[type="password"],
        .form-row input[type="number"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .form-row input:focus {
            outline: none;
            border-color: #8b5cf6;
        }
        .form-row input:disabled {
            background: #f9fafb;
            color: #9ca3af;
        }
        .form-row .help-text {
            color: #6b7280;
            font-size: 0.85rem;
            margin-top: 6px;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .checkbox-row input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #8b5cf6;
        }
        .checkbox-row label {
            margin: 0;
            color: #374151;
            font-weight: 600;
        }
        .commission-preview {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
        }
        .commission-preview h4 {
            color: #92400e;
            margin-bottom: 10px;
        }
        .commission-preview .example {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            font-size: 0.9rem;
        }
        .commission-preview .example div {
            background: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .commission-preview .example div strong {
            display: block;
            color: #92400e;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        .commission-preview .example div span {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #6b21a8);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .btn-test {
            background: #3b82f6;
            color: white;
        }
        .btn-test:hover {
            background: #2563eb;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-bottom: 16px;
            font-weight: 600;
            padding: 10px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: background 0.2s;
        }
        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-left: 12px;
        }
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-sandbox {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>

        <div class="header">
            <h1>
                <i class="fas fa-motorcycle"></i>
                Configuración de Mooving
                <?php if ($config): ?>
                    <span class="status-badge <?= $config['is_active'] ? 'status-active' : 'status-inactive' ?>">
                        <i class="fas fa-circle"></i>
                        <?= $config['is_active'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                    <?php if ($config['is_sandbox']): ?>
                    <span class="status-badge status-sandbox">
                        <i class="fas fa-flask"></i> Sandbox
                    </span>
                    <?php endif; ?>
                <?php endif; ?>
            </h1>
            <p class="subtitle">Configuración corporativa de envíos con Mooving para todas las emprendedoras</p>
        </div>

        <div class="content">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size:1.5rem;"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle" style="font-size:1.5rem;"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$config): ?>
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> ¿Cómo obtener credenciales de Mooving?</h3>
                <p><strong>Paso 1:</strong> Regístrate en <a href="https://www.mooving.com/empresas" target="_blank" style="color:#8b5cf6;">Mooving para Empresas</a></p>
                <p><strong>Paso 2:</strong> Completa el proceso de verificación empresarial</p>
                <p><strong>Paso 3:</strong> Solicita acceso al API Developer Portal</p>
                <p><strong>Paso 4:</strong> Genera tus credenciales:</p>
                <ul>
                    <li><strong>API Key:</strong> Llave pública para identificar tu aplicación</li>
                    <li><strong>API Secret:</strong> Llave privada (¡mantenla segura!)</li>
                    <li><strong>Merchant ID:</strong> Identificador único de tu empresa</li>
                </ul>
                <p style="margin-top:12px;">💡 <strong>Tip:</strong> Comienza en modo Sandbox para probar sin cargos reales.</p>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="save_config">

                <div class="form-section">
                    <h2><i class="fas fa-key"></i> Credenciales de API</h2>

                    <div class="form-row">
                        <label>
                            API Key <span class="required">*</span>
                        </label>
                        <input type="text" name="api_key"
                               value="<?= htmlspecialchars($config['api_key'] ?? '') ?>"
                               placeholder="mooving_live_..." required>
                        <p class="help-text">Llave pública proporcionada por Mooving</p>
                    </div>

                    <div class="form-row">
                        <label>
                            API Secret <span class="required">*</span>
                        </label>
                        <input type="password" name="api_secret"
                               value="<?= htmlspecialchars($config['api_secret'] ?? '') ?>"
                               placeholder="••••••••••••" required>
                        <p class="help-text">Llave privada (nunca la compartas)</p>
                    </div>

                    <div class="form-row">
                        <label>
                            Merchant ID <span class="required">*</span>
                        </label>
                        <input type="text" name="merchant_id"
                               value="<?= htmlspecialchars($config['merchant_id'] ?? '') ?>"
                               placeholder="MERCH_..." required>
                        <p class="help-text">Identificador único de CompraTica en Mooving</p>
                    </div>
                </div>

                <div class="form-section">
                    <h2><i class="fas fa-percent"></i> Comisión de CompraTica</h2>

                    <div class="form-row">
                        <label>
                            Porcentaje de Comisión
                        </label>
                        <input type="number" name="commission_percentage"
                               value="<?= $config['commission_percentage'] ?? 15.0 ?>"
                               step="0.1" min="0" max="100"
                               id="commission-input"
                               oninput="updateCommissionPreview()">
                        <p class="help-text">% que CompraTica gana sobre cada envío</p>
                    </div>

                    <div class="commission-preview">
                        <h4><i class="fas fa-calculator"></i> Ejemplo de Cálculo</h4>
                        <div class="example">
                            <div>
                                <strong>Costo Mooving</strong>
                                <span>₡2,000</span>
                            </div>
                            <div>
                                <strong>Tu Comisión (<span id="preview-percent">15</span>%)</strong>
                                <span id="preview-commission">₡300</span>
                            </div>
                            <div>
                                <strong>Total Cliente</strong>
                                <span id="preview-total">₡2,300</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2><i class="fas fa-cog"></i> Configuración</h2>

                    <div class="checkbox-row">
                        <input type="checkbox" name="is_sandbox" id="is_sandbox"
                               <?= ($config['is_sandbox'] ?? true) ? 'checked' : '' ?>>
                        <label for="is_sandbox">
                            <i class="fas fa-flask"></i> Modo Sandbox (Testing)
                        </label>
                    </div>
                    <p class="help-text" style="margin-left:42px;margin-top:-8px;">
                        Usa el ambiente de pruebas de Mooving (sin cargos reales)
                    </p>

                    <div class="checkbox-row">
                        <input type="checkbox" name="is_active" id="is_active"
                               <?= ($config['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label for="is_active">
                            <i class="fas fa-power-off"></i> Servicio Activo
                        </label>
                    </div>
                    <p class="help-text" style="margin-left:42px;margin-top:-8px;">
                        Las emprendedoras podrán ofrecer envíos con Mooving
                    </p>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Configuración
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>

            <?php if ($config): ?>
            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-test">
                    <i class="fas fa-plug"></i>
                    Probar Conexión
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function updateCommissionPreview() {
        const baseCost = 2000;
        const percent = parseFloat(document.getElementById('commission-input').value) || 0;
        const commission = Math.round(baseCost * (percent / 100));
        const total = baseCost + commission;

        document.getElementById('preview-percent').textContent = percent.toFixed(1);
        document.getElementById('preview-commission').textContent = '₡' + commission.toLocaleString('es-CR');
        document.getElementById('preview-total').textContent = '₡' + total.toLocaleString('es-CR');
    }
    // Inicializar
    updateCommissionPreview();
    </script>
</body>
</html>
<?php
logDebug("=== HTML renderizado exitosamente ===");
?>
