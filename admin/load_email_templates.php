<?php
/**
 * Cargar Plantillas de Email en la Base de Datos
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Cargar Plantillas</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
.btn{display:inline-block;padding:12px 24px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;margin:10px 5px}
</style></head><body>";

echo "<h1>🎨 Cargar Plantillas de Email Marketing</h1>";

try {
    // Conectar a BD
    echo "<div class='step'><h3>1. Conectando a Base de Datos</h3>";
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<span class='ok'>✓ Conexión exitosa</span></div>";

    // Definir plantillas
    $templates = [
        [
            'name' => 'Mixtico - Transporte Privado',
            'company' => 'mixtico',
            'subject' => 'Transporte Privado de Calidad en Costa Rica 🚐',
            'file' => 'mixtico_template.html',
            'variables' => json_encode(['nombre', 'email', 'telefono', 'empresa', 'campaign_id', 'tracking_pixel', 'unsubscribe_link'])
        ],
        [
            'name' => 'CRV-SOFT - Soluciones Tecnológicas',
            'company' => 'crv-soft',
            'subject' => 'Transforme su Negocio con Tecnología 💻',
            'file' => 'crv_soft_template.html',
            'variables' => json_encode(['nombre', 'email', 'telefono', 'empresa', 'campaign_id', 'tracking_pixel', 'unsubscribe_link'])
        ],
        [
            'name' => 'CompraTica - Marketplace Costarricense',
            'company' => 'compratica',
            'subject' => 'Descubrí el Marketplace 100% Tico 🇨🇷',
            'file' => 'compratica_template.html',
            'variables' => json_encode(['nombre', 'email', 'telefono', 'empresa', 'campaign_id', 'tracking_pixel', 'unsubscribe_link'])
        ]
    ];

    echo "<div class='step'><h3>2. Cargando Plantillas HTML</h3>";

    foreach ($templates as $template) {
        $filePath = __DIR__ . '/email_templates/' . $template['file'];

        echo "<p><strong>{$template['name']}</strong><br>";

        if (!file_exists($filePath)) {
            echo "<span class='error'>✗ Archivo no encontrado: {$template['file']}</span></p>";
            continue;
        }

        $htmlContent = file_get_contents($filePath);

        if (empty($htmlContent)) {
            echo "<span class='error'>✗ Archivo vacío</span></p>";
            continue;
        }

        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id FROM email_templates WHERE company = ?");
        $stmt->execute([$template['company']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Actualizar
            $stmt = $pdo->prepare("
                UPDATE email_templates
                SET name=?, subject=?, html_content=?, variables=?, is_active=1, updated_at=NOW()
                WHERE company=?
            ");
            $stmt->execute([
                $template['name'],
                $template['subject'],
                $htmlContent,
                $template['variables'],
                $template['company']
            ]);
            echo "<span class='ok'>✓ Actualizada</span> (" . strlen($htmlContent) . " bytes)</p>";
        } else {
            // Insertar nueva
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (name, company, subject, html_content, variables, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $template['name'],
                $template['company'],
                $template['subject'],
                $htmlContent,
                $template['variables']
            ]);
            echo "<span class='ok'>✓ Creada</span> (" . strlen($htmlContent) . " bytes)</p>";
        }
    }
    echo "</div>";

    // Verificar resultados
    echo "<div class='step'><h3>3. Plantillas en Base de Datos</h3>";
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY company");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($templates) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
        echo "<tr style='background:#f0f9ff'><th>ID</th><th>Nombre</th><th>Empresa</th><th>Asunto</th><th>Tamaño HTML</th><th>Activa</th></tr>";
        foreach ($templates as $t) {
            echo "<tr>";
            echo "<td>{$t['id']}</td>";
            echo "<td>{$t['name']}</td>";
            echo "<td><strong>{$t['company']}</strong></td>";
            echo "<td>{$t['subject']}</td>";
            echo "<td>" . number_format(strlen($t['html_content'])) . " bytes</td>";
            echo "<td>" . ($t['is_active'] ? '<span class="ok">✓ Sí</span>' : '✗ No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
    echo "<h3 style='color:#065f46'>✓ Plantillas Cargadas Exitosamente</h3>";
    echo "<p>Ahora puedes crear campañas usando estas plantillas.</p>";
    echo "<p>";
    echo "<a href='email_marketing.php?page=templates' class='btn'>Ver Plantillas</a>";
    echo "<a href='email_marketing.php?page=new-campaign' class='btn' style='background:#16a34a'>Nueva Campaña</a>";
    echo "<a href='preview_template.php' class='btn' style='background:#0891b2'>Vista Previa</a>";
    echo "</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='background:#fee2e2;border-left-color:#dc2626'>";
    echo "<h3 style='color:#991b1b'>✗ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
