<?php
/**
 * Test Simple de PHPMailer
 * Verifica que PHPMailer carga correctamente
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test PHPMailer</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;max-width:800px;margin:0 auto}";
echo ".ok{color:green;font-weight:bold}.error{color:red;font-weight:bold}";
echo ".step{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #0891b2}";
echo "pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto;font-size:12px}</style></head><body>";

echo "<h1>🧪 Test de PHPMailer</h1>";

// Test 1: Autoloader
echo "<div class='step'><h3>1. Verificar Autoloader</h3>";
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    echo "<p><span class='ok'>✓ Archivo encontrado:</span> $autoload_path</p>";
    try {
        require_once $autoload_path;
        echo "<p><span class='ok'>✓ Autoloader cargado exitosamente</span></p>";
    } catch (Exception $e) {
        echo "<p><span class='error'>✗ Error al cargar:</span> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><span class='error'>✗ Archivo NO encontrado:</span> $autoload_path</p>";
}
echo "</div>";

// Test 2: Clase PHPMailer
echo "<div class='step'><h3>2. Verificar Clase PHPMailer</h3>";
try {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<p><span class='ok'>✓ Clase PHPMailer\PHPMailer\PHPMailer existe</span></p>";

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $version = $mail::VERSION;
        echo "<p><span class='ok'>✓ PHPMailer instanciado correctamente</span></p>";
        echo "<p><strong>Versión:</strong> $version</p>";

        echo "<h4>Métodos disponibles:</h4>";
        $methods = get_class_methods($mail);
        $important_methods = ['isSMTP', 'send', 'addAddress', 'setFrom', 'isHTML'];
        echo "<ul>";
        foreach ($important_methods as $method) {
            if (method_exists($mail, $method)) {
                echo "<li><span class='ok'>✓ $method()</span></li>";
            } else {
                echo "<li><span class='error'>✗ $method()</span></li>";
            }
        }
        echo "</ul>";

    } else {
        echo "<p><span class='error'>✗ Clase PHPMailer NO encontrada</span></p>";
        echo "<p>Clases disponibles con 'PHPMailer' en el nombre:</p><ul>";
        foreach (get_declared_classes() as $class) {
            if (stripos($class, 'phpmailer') !== false) {
                echo "<li>$class</li>";
            }
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error:</span> " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 3: EmailSender class
echo "<div class='step'><h3>3. Verificar Clase EmailSender</h3>";
$email_sender_path = __DIR__ . '/email_sender.php';
if (file_exists($email_sender_path)) {
    echo "<p><span class='ok'>✓ Archivo encontrado:</span> $email_sender_path</p>";
    try {
        require_once $email_sender_path;
        if (class_exists('EmailSender')) {
            echo "<p><span class='ok'>✓ Clase EmailSender cargada</span></p>";

            $methods = get_class_methods('EmailSender');
            echo "<h4>Métodos de EmailSender:</h4><ul>";
            foreach ($methods as $method) {
                echo "<li>$method()</li>";
            }
            echo "</ul>";
        } else {
            echo "<p><span class='error'>✗ Clase EmailSender NO encontrada</span></p>";
        }
    } catch (Exception $e) {
        echo "<p><span class='error'>✗ Error al cargar EmailSender:</span> " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p><span class='error'>✗ Archivo NO encontrado:</span> $email_sender_path</p>";
}
echo "</div>";

// Test 4: Database & SMTP Config
echo "<div class='step'><h3>4. Verificar Configuración SMTP en BD</h3>";
try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p><span class='ok'>✓ Conexión a BD exitosa</span></p>";

    $stmt = $pdo->query("SELECT * FROM email_smtp_configs LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp) {
        echo "<p><span class='ok'>✓ Configuración SMTP encontrada</span></p>";
        echo "<ul>";
        echo "<li><strong>Nombre:</strong> " . htmlspecialchars($smtp['config_name']) . "</li>";
        echo "<li><strong>Host:</strong> " . htmlspecialchars($smtp['smtp_host']) . "</li>";
        echo "<li><strong>Puerto:</strong> " . htmlspecialchars($smtp['smtp_port']) . "</li>";
        echo "<li><strong>Usuario:</strong> " . htmlspecialchars($smtp['smtp_username']) . "</li>";
        echo "<li><strong>Encriptación:</strong> " . htmlspecialchars($smtp['smtp_encryption']) . "</li>";
        echo "</ul>";
    } else {
        echo "<p><span class='error'>✗ No hay configuración SMTP en la BD</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error BD:</span> " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 5: Campaña de prueba
echo "<div class='step'><h3>5. Verificar Campaña ID=1</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM email_campaigns WHERE id = 1");
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($campaign) {
        echo "<p><span class='ok'>✓ Campaña encontrada</span></p>";
        echo "<pre>" . print_r($campaign, true) . "</pre>";

        // Verificar destinatarios
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM email_recipients WHERE campaign_id = 1");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Destinatarios:</strong> {$count['total']}</p>";

        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM email_recipients WHERE campaign_id = 1 GROUP BY status");
        echo "<h4>Por Estado:</h4><ul>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<li>{$row['status']}: {$row['count']}</li>";
        }
        echo "</ul>";

    } else {
        echo "<p><span class='error'>✗ Campaña ID=1 no encontrada</span></p>";
    }
} catch (Exception $e) {
    echo "<p><span class='error'>✗ Error:</span> " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<hr><p><a href='email_marketing.php' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px'>Volver a Email Marketing</a></p>";
echo "</body></html>";
?>
