<?php
/**
 * Crear Tabla de Blacklist Global de Emails
 * Ejecutar solo UNA vez para crear la tabla
 */

require_once __DIR__ . '/../includes/config.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Crear Blacklist</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:900px;margin:0 auto;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:20px;margin:15px 0;border-radius:4px;}
.error{background:#fee;border-left:4px solid #dc2626;padding:20px;margin:15px 0;border-radius:4px;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:20px;margin:15px 0;border-radius:4px;}
pre{background:#1e293b;color:#f1f5f9;padding:15px;border-radius:5px;overflow:auto;}
h1{color:#dc2626;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;text-decoration:none;}
</style></head><body>";

echo "<h1>🔨 Crear Tabla de Blacklist Global</h1>";

try {
    // Verificar si la tabla ya existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'email_blacklist'")->fetch();

    if ($tableExists) {
        echo "<div class='info'>";
        echo "<strong>ℹ️ La tabla 'email_blacklist' ya existe</strong><br>";
        echo "No es necesario crearla de nuevo.";
        echo "</div>";

        // Mostrar estructura actual
        $structure = $pdo->query("DESCRIBE email_blacklist")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Estructura Actual:</h3>";
        echo "<pre>";
        foreach ($structure as $column) {
            echo "{$column['Field']}\t{$column['Type']}\t{$column['Null']}\t{$column['Key']}\t{$column['Default']}\t{$column['Extra']}\n";
        }
        echo "</pre>";

        // Mostrar registros
        $count = $pdo->query("SELECT COUNT(*) FROM email_blacklist")->fetchColumn();
        echo "<p><strong>Emails en blacklist:</strong> {$count}</p>";

        if ($count > 0) {
            $emails = $pdo->query("SELECT * FROM email_blacklist ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>Últimos 10 emails en blacklist:</h3>";
            echo "<pre>";
            foreach ($emails as $email) {
                echo "{$email['email']}\t{$email['reason']}\t{$email['created_at']}\n";
            }
            echo "</pre>";
        }

    } else {
        echo "<div class='info'>";
        echo "<strong>📋 Creando tabla 'email_blacklist'...</strong>";
        echo "</div>";

        // Crear tabla
        $sql = "CREATE TABLE email_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            reason VARCHAR(500) DEFAULT NULL,
            campaign_id INT DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'unsubscribe',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            UNIQUE KEY unique_email (email),
            INDEX idx_email (email),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);

        echo "<div class='success'>";
        echo "<strong>✅ Tabla creada exitosamente!</strong><br><br>";
        echo "<strong>Estructura de la tabla:</strong><br>";
        echo "<ul>";
        echo "<li><strong>id:</strong> ID autoincremental</li>";
        echo "<li><strong>email:</strong> Email en blacklist (ÚNICO)</li>";
        echo "<li><strong>reason:</strong> Razón de bloqueo</li>";
        echo "<li><strong>campaign_id:</strong> Campaña desde donde se desuscribió</li>";
        echo "<li><strong>source:</strong> Origen (unsubscribe, manual, bounce, spam_complaint)</li>";
        echo "<li><strong>ip_address:</strong> IP del usuario al desuscribirse</li>";
        echo "<li><strong>user_agent:</strong> Navegador del usuario</li>";
        echo "<li><strong>created_at:</strong> Fecha de bloqueo</li>";
        echo "<li><strong>created_by:</strong> Admin que lo agregó (si fue manual)</li>";
        echo "<li><strong>notes:</strong> Notas adicionales</li>";
        echo "</ul>";
        echo "</div>";

        // Migrar emails existentes marcados como bounced
        echo "<div class='info'>";
        echo "<strong>🔄 Migrando emails existentes marcados como 'bounced'...</strong><br><br>";

        $bouncedEmails = $pdo->query("
            SELECT DISTINCT email, campaign_id
            FROM email_recipients
            WHERE status = 'bounced'
        ")->fetchAll(PDO::FETCH_ASSOC);

        $migrated = 0;
        foreach ($bouncedEmails as $bounced) {
            try {
                $pdo->prepare("
                    INSERT INTO email_blacklist (email, reason, campaign_id, source)
                    VALUES (?, 'Migrado de email_recipients (bounced)', ?, 'migration')
                    ON DUPLICATE KEY UPDATE campaign_id = VALUES(campaign_id)
                ")->execute([$bounced['email'], $bounced['campaign_id']]);
                $migrated++;
            } catch (Exception $e) {
                // Ignorar duplicados
            }
        }

        echo "✓ Migrados {$migrated} emails de campañas anteriores<br>";
        echo "</div>";
    }

    echo "<div class='info'>";
    echo "<h3>📖 Próximos Pasos:</h3>";
    echo "<ol>";
    echo "<li>La tabla ya está creada y lista para usar</li>";
    echo "<li>El sistema de unsubscribe ahora agregará emails a esta blacklist</li>";
    echo "<li>Antes de enviar emails, el sistema verificará esta blacklist</li>";
    echo "<li>Puedes gestionar la blacklist desde el panel de administración</li>";
    echo "</ol>";
    echo "</div>";

    echo "<p style='text-align:center;'>";
    echo "<a href='email_marketing.php?page=blacklist' class='btn'>📋 Ver Blacklist</a> ";
    echo "<a href='email_marketing.php?page=dashboard' class='btn'>🏠 Dashboard</a>";
    echo "</p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "<br><br><strong>Archivo:</strong> {$e->getFile()}<br>";
    echo "<strong>Línea:</strong> {$e->getLine()}";
    echo "</div>";
}

echo "</body></html>";
