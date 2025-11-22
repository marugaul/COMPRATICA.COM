<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Debug - Template Upload</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .section { background: #252526; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #3e3e42; }
        h2 { color: #4ec9b0; margin-top: 0; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto; border: 1px solid #3e3e42; }
        .error { color: #f48771; }
        .success { color: #4ec9b0; }
        .warning { color: #dcdcaa; }
        code { color: #ce9178; }
    </style>
</head>
<body>
    <h1>🔍 Debug Template Upload API</h1>

    <div class="section">
        <h2>1. Test directo a templates_api.php</h2>
        <p>Llamando a <code>email_marketing/templates_api.php?action=upload_template</code> sin archivos...</p>

        <?php
        $url = 'http://' . $_SERVER['HTTP_HOST'] . '/admin/email_marketing/templates_api.php?action=upload_template';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'template_name' => 'Test',
            'template_company' => 'test-debug',
            'template_subject' => 'Test Subject'
        ]);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        echo "<p><strong>HTTP Code:</strong> <span class='warning'>$httpCode</span></p>";

        echo "<h3>Headers Recibidos:</h3>";
        echo "<pre>" . htmlspecialchars($headers) . "</pre>";

        echo "<h3>Body Recibido (primeros 2000 caracteres):</h3>";
        echo "<pre>" . htmlspecialchars(substr($body, 0, 2000)) . "</pre>";

        // Intentar decodificar como JSON
        $json = json_decode($body, true);
        if ($json !== null) {
            echo "<p class='success'>✓ Respuesta es JSON válido</p>";
            echo "<pre>" . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<p class='error'>✗ Respuesta NO es JSON válido</p>";
            echo "<p class='error'>Error JSON: " . json_last_error_msg() . "</p>";

            // Mostrar los primeros caracteres para identificar el problema
            echo "<h3>Primeros 500 caracteres de la respuesta:</h3>";
            echo "<pre class='error'>" . htmlspecialchars(substr($body, 0, 500)) . "</pre>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. Verificar archivos requeridos</h2>
        <?php
        $files = [
            '../../includes/config.php',
            '../../config/database.php',
            '../email_marketing/templates_api.php'
        ];

        foreach ($files as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (file_exists($fullPath)) {
                echo "<p class='success'>✓ Existe: <code>$file</code></p>";

                // Verificar si tiene BOM o espacios al inicio
                $content = file_get_contents($fullPath);
                if (substr($content, 0, 5) === '<?php') {
                    echo "<p class='success'>  → Comienza correctamente con &lt;?php</p>";
                } elseif (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    echo "<p class='error'>  → ⚠️ Tiene BOM UTF-8 al inicio</p>";
                } else {
                    $first20 = substr($content, 0, 20);
                    echo "<p class='warning'>  → Primeros caracteres: " . htmlspecialchars($first20) . "</p>";
                }
            } else {
                echo "<p class='error'>✗ NO existe: <code>$file</code></p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Test de conexión MySQL</h2>
        <?php
        try {
            require_once __DIR__ . '/../includes/config.php';
            echo "<p class='success'>✓ config.php cargado</p>";

            $config = require __DIR__ . '/../config/database.php';
            echo "<p class='success'>✓ database.php cargado</p>";

            $pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "<p class='success'>✓ Conexión MySQL exitosa</p>";

            // Verificar columnas de email_templates
            $stmt = $pdo->query("SHOW COLUMNS FROM email_templates");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $required = ['image_path', 'image_display', 'image_cid', 'is_default'];
            $missing = array_diff($required, $columns);

            if (empty($missing)) {
                echo "<p class='success'>✓ Todas las columnas requeridas existen</p>";
            } else {
                echo "<p class='error'>✗ Faltan columnas: " . implode(', ', $missing) . "</p>";
            }

        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>4. Verificar sesión admin</h2>
        <?php
        if (!isset($_SESSION['is_admin'])) {
            echo "<p class='error'>✗ $_SESSION['is_admin'] no está definida</p>";
        } elseif ($_SESSION['is_admin'] !== true) {
            echo "<p class='error'>✗ $_SESSION['is_admin'] = " . var_export($_SESSION['is_admin'], true) . "</p>";
        } else {
            echo "<p class='success'>✓ Sesión admin válida</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Verificar output buffering</h2>
        <?php
        $bufferLevel = ob_get_level();
        echo "<p>Nivel de buffering actual: <span class='warning'>$bufferLevel</span></p>";

        $bufferStatus = ob_get_status(true);
        echo "<pre>" . htmlspecialchars(print_r($bufferStatus, true)) . "</pre>";
        ?>
    </div>

    <p style="margin-top: 40px; text-align: center;">
        <a href="/admin/email_marketing.php" style="color: #4ec9b0;">← Volver a Email Marketing</a>
    </p>
</body>
</html>
