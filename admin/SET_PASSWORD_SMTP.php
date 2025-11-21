<?php
// ============================================
// AGREGAR CONTRASEÑA SMTP - SIMPLE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bypass temporal de auth
$_SESSION['is_admin'] = true;

// Si viene del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    try {
        require __DIR__ . '/../config/database.php';

        $pdo = new PDO(
            "mysql:host={$host};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $smtp_id = (int)$_POST['smtp_id'];
        $new_password = $_POST['password'];

        $stmt = $pdo->prepare("UPDATE email_smtp_configs SET smtp_password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_password, $smtp_id]);

        $success = "✓ Contraseña actualizada correctamente";

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener configs
try {
    require __DIR__ . '/../config/database.php';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $configs = $pdo->query("SELECT * FROM email_smtp_configs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error BD: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Configurar Contraseña SMTP</title>
    <style>
        body {
            font-family: Arial;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 20px;
            max-width: 700px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        h1 { color: #dc2626; }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #10b981;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ef4444;
        }
        .config {
            background: #f9fafb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #0891b2;
        }
        input[type="password"], input[type="text"], select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        button {
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover { background: #b91c1c; }
        .show-password {
            cursor: pointer;
            color: #0891b2;
            text-decoration: underline;
            font-size: 12px;
        }
        .info { color: #666; font-size: 13px; }
        a {
            color: #0891b2;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <h1>🔑 Configurar Contraseña SMTP</h1>

    <?php if (isset($success)): ?>
        <div class="success">
            <strong><?= $success ?></strong><br>
            <a href="SEND_EMAIL_NOW.php">→ Probar envío de email ahora</a>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="error">
            <strong><?= $error ?></strong>
        </div>
    <?php endif; ?>

    <?php if (count($configs) === 0): ?>
        <div class="error">
            No hay configuraciones SMTP.<br>
            <a href="update_smtp_mixtico.php">Crear configuración de Mixtico</a>
        </div>
    <?php else: ?>

        <h3>Configuraciones SMTP Disponibles:</h3>

        <?php foreach ($configs as $cfg): ?>
            <div class="config">
                <strong><?= htmlspecialchars($cfg['config_name']) ?></strong><br>
                <small>
                    Host: <?= $cfg['smtp_host'] ?>:<?= $cfg['smtp_port'] ?> |
                    Usuario: <?= $cfg['smtp_username'] ?> |
                    Encriptación: <?= strtoupper($cfg['smtp_encryption']) ?>
                </small><br>
                <strong>Contraseña:
                    <?php if (empty($cfg['smtp_password'])): ?>
                        <span style="color:#dc2626">❌ NO CONFIGURADA</span>
                    <?php else: ?>
                        <span style="color:#10b981">✓ Configurada (••••••••)</span>
                    <?php endif; ?>
                </strong>
            </div>
        <?php endforeach; ?>

        <hr>

        <h3>Actualizar Contraseña:</h3>

        <form method="POST">
            <label><strong>Seleccionar Configuración:</strong></label>
            <select name="smtp_id" required>
                <?php foreach ($configs as $cfg): ?>
                    <option value="<?= $cfg['id'] ?>">
                        <?= htmlspecialchars($cfg['config_name']) ?> (<?= $cfg['smtp_host'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label><strong>Nueva Contraseña SMTP:</strong></label>
            <input type="password" id="password" name="password" placeholder="Ingresa la contraseña de info@mixtico.net" required>

            <p class="info">
                <span class="show-password" onclick="togglePassword()">👁️ Mostrar contraseña</span>
            </p>

            <button type="submit">💾 Guardar Contraseña</button>
        </form>

        <hr>

        <h3>📋 Información:</h3>
        <ul>
            <li><strong>Para Mixtico:</strong> Usa la contraseña del email <code>info@mixtico.net</code></li>
            <li><strong>Host:</strong> mail.mixtico.net</li>
            <li><strong>Puerto:</strong> 465 (SSL) o 587 (TLS)</li>
        </ul>

    <?php endif; ?>

    <hr>
    <p>
        <a href="SEND_EMAIL_NOW.php">→ Probar envío de email</a> |
        <a href="email_marketing.php">→ Email Marketing</a>
    </p>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
