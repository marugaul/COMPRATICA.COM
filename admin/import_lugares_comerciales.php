<?php
/**
 * Importador de Lugares Comerciales desde OpenStreetMap
 * Extrae hoteles, restaurantes y bares de Costa Rica
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

set_time_limit(300); // 5 minutos

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Acción
$action = $_POST['action'] ?? 'form';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Lugares Comerciales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/fontawesome-css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .log { background: #1e293b; color: #f1f5f9; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }
        .success { color: #51cf66; }
        .error { color: #ff6b6b; }
        .warning { color: #ffd43b; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-download"></i> Importador de Lugares Comerciales</h1>
        <p class="text-muted">Descarga datos de hoteles, restaurantes y bares desde OpenStreetMap</p>

        <?php if ($action === 'form'): ?>
            <!-- Paso 1: Crear tabla -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Paso 1: Crear Tabla en Base de Datos</h5>
                </div>
                <div class="card-body">
                    <p>Primero necesitamos crear la tabla <code>lugares_comerciales</code> en la base de datos.</p>
                    <button onclick="createTable()" class="btn btn-primary">
                        <i class="fas fa-database"></i> Crear Tabla
                    </button>
                    <div id="createTableResult" class="mt-3"></div>
                </div>
            </div>

            <!-- Paso 2: Descargar datos -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Paso 2: Descargar Datos de OpenStreetMap</h5>
                </div>
                <div class="card-body">
                    <p>Descarga datos de lugares comerciales de Costa Rica usando Overpass API.</p>
                    <button onclick="downloadData()" class="btn btn-success">
                        <i class="fas fa-cloud-download-alt"></i> Descargar Datos
                    </button>
                    <div id="downloadResult" class="mt-3"></div>
                </div>
            </div>

            <!-- Paso 3: Estadísticas -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Paso 3: Estadísticas</h5>
                </div>
                <div class="card-body">
                    <button onclick="showStats()" class="btn btn-info">
                        <i class="fas fa-chart-bar"></i> Ver Estadísticas
                    </button>
                    <div id="statsResult" class="mt-3"></div>
                </div>
            </div>

        <?php elseif ($action === 'create_table'): ?>
            <?php
            header('Content-Type: application/json');

            try {
                // Verificar si existe
                $exists = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

                if ($exists) {
                    echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
                    exit;
                }

                // Crear tabla con estructura expandida
                $sql = "CREATE TABLE lugares_comerciales (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre VARCHAR(255) NOT NULL,
                    tipo VARCHAR(100),
                    categoria VARCHAR(100),
                    subtipo VARCHAR(100),
                    descripcion TEXT,
                    direccion VARCHAR(500),
                    ciudad VARCHAR(100),
                    provincia VARCHAR(100),
                    codigo_postal VARCHAR(20),
                    telefono VARCHAR(50),
                    email VARCHAR(255),
                    website VARCHAR(500),
                    facebook VARCHAR(255),
                    instagram VARCHAR(255),
                    horario TEXT,
                    latitud DECIMAL(10, 8),
                    longitud DECIMAL(11, 8),
                    osm_id BIGINT,
                    osm_type VARCHAR(10),
                    capacidad INT,
                    estrellas TINYINT,
                    wifi BOOLEAN DEFAULT FALSE,
                    parking BOOLEAN DEFAULT FALSE,
                    discapacidad_acceso BOOLEAN DEFAULT FALSE,
                    tarjetas_credito BOOLEAN DEFAULT FALSE,
                    delivery BOOLEAN DEFAULT FALSE,
                    takeaway BOOLEAN DEFAULT FALSE,
                    tags_json TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_tipo (tipo),
                    INDEX idx_categoria (categoria),
                    INDEX idx_ciudad (ciudad),
                    INDEX idx_provincia (provincia),
                    INDEX idx_email (email),
                    INDEX idx_osm_id (osm_id),
                    FULLTEXT idx_nombre (nombre, descripcion)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                $pdo->exec($sql);

                echo json_encode(['success' => true, 'message' => 'Tabla creada exitosamente']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            ?>

        <?php elseif ($action === 'download'): ?>
            <?php
            header('Content-Type: application/json');

            try {
                // Query expandida para Overpass API - TODAS las categorías comerciales
                $overpass_query = '[out:json][timeout:180];
area["name"="Costa Rica"]["type"="boundary"]->.a;
(
  // GASTRONOMÍA
  node["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);
  way["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);

  // ALOJAMIENTO
  node["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);
  way["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);

  // TIENDAS
  node["shop"](area.a);
  way["shop"](area.a);

  // SERVICIOS
  node["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);
  way["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);

  // ENTRETENIMIENTO
  node["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  way["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  node["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);
  way["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);

  // TURISMO
  node["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);
  way["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);

  // OFICINAS Y SERVICIOS PROFESIONALES
  node["office"](area.a);
  way["office"](area.a);

  // EDUCACIÓN
  node["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);
  way["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);

  // BELLEZA Y BIENESTAR
  node["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  way["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  node["amenity"~"spa"](area.a);
  way["amenity"~"spa"](area.a);
);
out center;';

                $overpass_url = "http://overpass-api.de/api/interpreter";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $overpass_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode($overpass_query));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200) {
                    throw new Exception("Error en Overpass API: HTTP $http_code");
                }

                $data = json_decode($response, true);

                if (!isset($data['elements'])) {
                    throw new Exception("Respuesta inválida de Overpass API");
                }

                $elements = $data['elements'];
                $imported = 0;
                $errors = 0;

                foreach ($elements as $element) {
                    $tags = $element['tags'] ?? [];

                    $nombre = $tags['name'] ?? ($tags['brand'] ?? 'Sin nombre');

                    // Determinar tipo y categoría
                    $tipo = $tags['amenity'] ?? $tags['tourism'] ?? $tags['shop'] ?? $tags['office'] ?? $tags['leisure'] ?? 'other';
                    $categoria = '';

                    if (isset($tags['amenity'])) $categoria = 'amenity';
                    elseif (isset($tags['tourism'])) $categoria = 'tourism';
                    elseif (isset($tags['shop'])) $categoria = 'shop';
                    elseif (isset($tags['office'])) $categoria = 'office';
                    elseif (isset($tags['leisure'])) $categoria = 'leisure';

                    // Subtipo más específico
                    $subtipo = $tags['cuisine'] ?? $tags['shop_type'] ?? $tags['office_type'] ?? '';

                    // Obtener coordenadas
                    $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
                    $lon = $element['lon'] ?? ($element['center']['lon'] ?? null);

                    // Horario
                    $horario = $tags['opening_hours'] ?? '';

                    // Características booleanas
                    $wifi = in_array($tags['internet_access'] ?? '', ['yes', 'wlan', 'wifi']) ? 1 : 0;
                    $parking = in_array($tags['parking'] ?? '', ['yes', 'surface', 'underground']) ? 1 : 0;
                    $discapacidad = ($tags['wheelchair'] ?? '') === 'yes' ? 1 : 0;
                    $tarjetas = in_array($tags['payment:credit_cards'] ?? '', ['yes']) ? 1 : 0;
                    $delivery = ($tags['delivery'] ?? '') === 'yes' ? 1 : 0;
                    $takeaway = ($tags['takeaway'] ?? '') === 'yes' ? 1 : 0;

                    // Capacidad y estrellas
                    $capacidad = is_numeric($tags['capacity'] ?? '') ? intval($tags['capacity']) : null;
                    $estrellas = is_numeric($tags['stars'] ?? '') ? intval($tags['stars']) : null;

                    // Redes sociales
                    $facebook = $tags['contact:facebook'] ?? $tags['facebook'] ?? '';
                    $instagram = $tags['contact:instagram'] ?? $tags['instagram'] ?? '';

                    // Guardar todos los tags como JSON para referencia
                    $tags_json = json_encode($tags, JSON_UNESCAPED_UNICODE);

                    // Insertar
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO lugares_comerciales
                            (nombre, tipo, categoria, subtipo, descripcion, direccion, ciudad, provincia, codigo_postal,
                             telefono, email, website, facebook, instagram, horario, latitud, longitud, osm_id, osm_type,
                             capacidad, estrellas, wifi, parking, discapacidad_acceso, tarjetas_credito, delivery, takeaway, tags_json)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            nombre = VALUES(nombre),
                            tipo = VALUES(tipo),
                            categoria = VALUES(categoria),
                            direccion = VALUES(direccion),
                            telefono = VALUES(telefono),
                            email = VALUES(email),
                            website = VALUES(website),
                            facebook = VALUES(facebook),
                            instagram = VALUES(instagram),
                            horario = VALUES(horario),
                            tags_json = VALUES(tags_json)
                        ");

                        $direccion = trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? ''));

                        $stmt->execute([
                            $nombre,
                            $tipo,
                            $categoria,
                            $subtipo,
                            $tags['description'] ?? '',
                            $direccion,
                            $tags['addr:city'] ?? '',
                            $tags['addr:province'] ?? $tags['addr:state'] ?? '',
                            $tags['addr:postcode'] ?? '',
                            $tags['phone'] ?? $tags['contact:phone'] ?? '',
                            $tags['email'] ?? $tags['contact:email'] ?? '',
                            $tags['website'] ?? $tags['url'] ?? $tags['contact:website'] ?? '',
                            $facebook,
                            $instagram,
                            $horario,
                            $lat,
                            $lon,
                            $element['id'] ?? null,
                            $element['type'] ?? 'node',
                            $capacidad,
                            $estrellas,
                            $wifi,
                            $parking,
                            $discapacidad,
                            $tarjetas,
                            $delivery,
                            $takeaway,
                            $tags_json
                        ]);

                        $imported++;
                    } catch (Exception $e) {
                        $errors++;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'total' => count($elements),
                    'imported' => $imported,
                    'errors' => $errors
                ]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            ?>

        <?php elseif ($action === 'stats'): ?>
            <?php
            header('Content-Type: application/json');

            try {
                // Verificar si tabla existe
                $exists = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();

                if (!$exists) {
                    echo json_encode(['success' => false, 'error' => 'La tabla no existe']);
                    exit;
                }

                $stats = [];

                // Total
                $stats['total'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();

                // Por categoría
                $byCategoria = $pdo->query("
                    SELECT categoria, COUNT(*) as count
                    FROM lugares_comerciales
                    GROUP BY categoria
                    ORDER BY count DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                $stats['by_categoria'] = $byCategoria;

                // Por tipo (top 20)
                $byType = $pdo->query("
                    SELECT tipo, COUNT(*) as count
                    FROM lugares_comerciales
                    GROUP BY tipo
                    ORDER BY count DESC
                    LIMIT 20
                ")->fetchAll(PDO::FETCH_ASSOC);
                $stats['by_type'] = $byType;

                // Con email
                $stats['with_email'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != ''")->fetchColumn();

                // Con teléfono
                $stats['with_phone'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE telefono != ''")->fetchColumn();

                // Con website
                $stats['with_website'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website != ''")->fetchColumn();

                // Con redes sociales
                $stats['with_facebook'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE facebook != ''")->fetchColumn();
                $stats['with_instagram'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE instagram != ''")->fetchColumn();

                // Con características
                $stats['with_wifi'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE wifi = 1")->fetchColumn();
                $stats['with_parking'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE parking = 1")->fetchColumn();
                $stats['with_delivery'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE delivery = 1")->fetchColumn();

                // Por ciudad (top 10)
                $byCiudad = $pdo->query("
                    SELECT ciudad, COUNT(*) as count
                    FROM lugares_comerciales
                    WHERE ciudad != ''
                    GROUP BY ciudad
                    ORDER BY count DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                $stats['by_ciudad'] = $byCiudad;

                echo json_encode(['success' => true, 'stats' => $stats]);

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            ?>

        <?php endif; ?>

        <div class="mt-4">
            <a href="email_marketing.php?page=dashboard" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <script>
    async function createTable() {
        const resultDiv = document.getElementById('createTableResult');
        resultDiv.innerHTML = '<div class="alert alert-info">⏳ Creando tabla...</div>';

        try {
            const response = await fetch('import_lugares_comerciales.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=create_table'
            });

            const result = await response.json();

            if (result.success) {
                resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check"></i> ' + result.message + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + result.error + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        }
    }

    async function downloadData() {
        const resultDiv = document.getElementById('downloadResult');
        resultDiv.innerHTML = '<div class="alert alert-info">⏳ Descargando datos de OpenStreetMap...<br>Esto puede tomar 1-2 minutos, por favor espera...</div>';

        try {
            const response = await fetch('import_lugares_comerciales.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=download'
            });

            const result = await response.json();

            if (result.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle"></i> ¡Descarga Exitosa!</h5>
                        <ul>
                            <li>Total de lugares encontrados: <strong>${result.total}</strong></li>
                            <li>Importados a la BD: <strong>${result.imported}</strong></li>
                            <li>Errores: <strong>${result.errors}</strong></li>
                        </ul>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + result.error + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        }
    }

    async function showStats() {
        const resultDiv = document.getElementById('statsResult');
        resultDiv.innerHTML = '<div class="alert alert-info">⏳ Cargando estadísticas...</div>';

        try {
            const response = await fetch('import_lugares_comerciales.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=stats'
            });

            const result = await response.json();

            if (result.success) {
                const stats = result.stats;
                let html = '<div class="card"><div class="card-body">';
                html += '<h5>📊 Estadísticas Generales</h5>';
                html += '<table class="table table-bordered">';
                html += `<tr><td><strong>Total de lugares:</strong></td><td>${stats.total}</td></tr>`;
                html += `<tr><td><strong>Con email:</strong></td><td>${stats.with_email} (${(stats.with_email/stats.total*100).toFixed(1)}%)</td></tr>`;
                html += `<tr><td><strong>Con teléfono:</strong></td><td>${stats.with_phone} (${(stats.with_phone/stats.total*100).toFixed(1)}%)</td></tr>`;
                html += `<tr><td><strong>Con website:</strong></td><td>${stats.with_website} (${(stats.with_website/stats.total*100).toFixed(1)}%)</td></tr>`;
                html += '</table>';

                html += '<h5 class="mt-3">📂 Por Categoría</h5>';
                html += '<table class="table table-sm">';
                stats.by_categoria.forEach(item => {
                    html += `<tr><td><strong>${item.categoria}</strong></td><td>${item.count}</td></tr>`;
                });
                html += '</table>';

                html += '<h5 class="mt-3">🏷️ Top 20 Tipos</h5>';
                html += '<table class="table table-sm table-striped">';
                stats.by_type.forEach(item => {
                    html += `<tr><td>${item.tipo}</td><td><span class="badge bg-primary">${item.count}</span></td></tr>`;
                });
                html += '</table>';

                html += '<h5 class="mt-3">🌐 Redes Sociales</h5>';
                html += '<table class="table table-sm">';
                html += `<tr><td>📘 Facebook</td><td>${stats.with_facebook}</td></tr>`;
                html += `<tr><td>📸 Instagram</td><td>${stats.with_instagram}</td></tr>`;
                html += '</table>';

                html += '<h5 class="mt-3">✨ Características</h5>';
                html += '<table class="table table-sm">';
                html += `<tr><td>📶 WiFi</td><td>${stats.with_wifi}</td></tr>`;
                html += `<tr><td>🅿️ Parking</td><td>${stats.with_parking}</td></tr>`;
                html += `<tr><td>🚚 Delivery</td><td>${stats.with_delivery}</td></tr>`;
                html += '</table>';

                html += '<h5 class="mt-3">🏙️ Top 10 Ciudades</h5>';
                html += '<table class="table table-sm">';
                stats.by_ciudad.forEach(item => {
                    html += `<tr><td>${item.ciudad}</td><td>${item.count}</td></tr>`;
                });
                html += '</table>';

                html += '</div></div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + result.error + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        }
    }
    </script>
</body>
</html>
