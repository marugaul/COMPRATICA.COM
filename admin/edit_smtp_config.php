<?php
/**
 * Editar Configuración SMTP
 * Permite actualizar la contraseña y otros datos de configuración SMTP
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$message = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_smtp'])) {
    try {
        $id = $_POST['id'];
        $smtp_password = $_POST['smtp_password'];

        // Solo actualizar la contraseña si no está vacía
        if (!empty($smtp_password)) {
            $stmt = $pdo->prepare("
                UPDATE email_smtp_configs
                SET smtp_password = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$smtp_password, $id]);
            $message = "✓ Contraseña actualizada correctamente";
        }

        // Actualizar otros campos si se proporcionaron
        if (isset($_POST['config_name']) && !empty($_POST['config_name'])) {
            $stmt = $pdo->prepare("
                UPDATE email_smtp_configs
                SET config_name = ?,
                    smtp_host = ?,
                    smtp_port = ?,
                    smtp_username = ?,
                    smtp_encryption = ?,
                    from_email = ?,
                    from_name = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['config_name'],
                $_POST['smtp_host'],
                $_POST['smtp_port'],
                $_POST['smtp_username'],
                $_POST['smtp_encryption'],
                $_POST['from_email'],
                $_POST['from_name'],
                $id
            ]);
            $message = "✓ Configuración SMTP actualizada completamente";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener configuración a editar
$smtp_id = $_GET['id'] ?? 0;
if ($smtp_id) {
    $stmt = $pdo->prepare("SELECT * FROM email_smtp_configs WHERE id = ?");
    $stmt->execute([$smtp_id]);
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Si no hay ID, mostrar la primera configuración
    $smtp = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Obtener todas las configuraciones
$all_configs = $pdo->query("SELECT id, config_name, smtp_host, smtp_port FROM email_smtp_configs ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Configuración SMTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            padding: 30px 15px;
        }
        .container {
            max-width: 900px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }
        .form-label {
            font-weight: 600;
            color: #374151;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 38px;
            cursor: pointer;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-cog"></i> Editar Configuración SMTP</h3>
            </div>
            <div class="card-body p-4">

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (count($all_configs) > 1): ?>
                    <div class="mb-4">
                        <label class="form-label">Seleccionar Configuración:</label>
                        <select class="form-select" onchange="window.location='?id='+this.value">
                            <?php foreach ($all_configs as $conf): ?>
                                <option value="<?= $conf['id'] ?>" <?= ($smtp && $conf['id'] == $smtp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($conf['config_name']) ?> (<?= $conf['smtp_host'] ?>:<?= $conf['smtp_port'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($smtp): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?= $smtp['id'] ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre de Configuración</label>
                                <input type="text" name="config_name" class="form-control"
                                       value="<?= htmlspecialchars($smtp['config_name']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Host SMTP</label>
                                <input type="text" name="smtp_host" class="form-control"
                                       value="<?= htmlspecialchars($smtp['smtp_host']) ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Puerto</label>
                                <input type="number" name="smtp_port" class="form-control"
                                       value="<?= $smtp['smtp_port'] ?>" required>
                                <small class="text-muted">465 (SSL) o 587 (TLS)</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Encriptación</label>
                                <select name="smtp_encryption" class="form-control" required>
                                    <option value="ssl" <?= $smtp['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="tls" <?= $smtp['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="none" <?= $smtp['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Ninguna</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Usuario SMTP</label>
                                <input type="text" name="smtp_username" class="form-control"
                                       value="<?= htmlspecialchars($smtp['smtp_username']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label class="form-label">Contraseña SMTP</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control"
                                   placeholder="<?= !empty($smtp['smtp_password']) ? '••••••••••' : 'Ingrese la contraseña' ?>">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                            <small class="text-muted">Deja vacío para mantener la contraseña actual</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Remitente (From)</label>
                                <input type="email" name="from_email" class="form-control"
                                       value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Remitente</label>
                                <input type="text" name="from_name" class="form-control"
                                       value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" name="update_smtp" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="email_marketing.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                            </div>
                            <div>
                                <a href="test_smtp_connection.php?id=<?= $smtp['id'] ?>" class="btn btn-success" target="_blank">
                                    <i class="fas fa-paper-plane"></i> Probar Conexión
                                </a>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No hay configuraciones SMTP disponibles.
                        <a href="update_smtp_mixtico.php" class="btn btn-sm btn-primary ms-2">Crear Configuración</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Información adicional -->
        <div class="card mt-3">
            <div class="card-body">
                <h5><i class="fas fa-info-circle text-info"></i> Información de Configuración</h5>
                <hr>
                <p><strong>Configuración Recomendada para Mixtico:</strong></p>
                <ul>
                    <li><strong>Host:</strong> mail.mixtico.net</li>
                    <li><strong>Puerto:</strong> 465 (SSL) o 587 (TLS)</li>
                    <li><strong>Usuario:</strong> info@mixtico.net</li>
                    <li><strong>Encriptación:</strong> SSL (para puerto 465) o TLS (para puerto 587)</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('smtp_password');
            const icon = event.target;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
