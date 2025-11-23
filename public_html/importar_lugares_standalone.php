<?php
/**
 * Importador de Lugares Comerciales - VERSIÓN STANDALONE
 * Sin dependencias de config.php
 */

// Configuración directa de base de datos
$db_config = [
    'host' => '127.0.0.1',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
];

// Intentar conectar
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['database']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "<br><br>Por favor actualiza las credenciales en la línea 9-13 del archivo.");
}

set_time_limit(300); // 5 minutos

$action = $_POST['action'] ?? 'form';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importador de Lugares Comerciales - Costa Rica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .standalone-badge { background: #ff6b6b; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="standalone-badge">
            <strong>⚠️ VERSIÓN STANDALONE:</strong> Este archivo es temporal y público. Eliminar después de usar.
        </div>

        <h1><i class="fas fa-download"></i> Importador de Lugares Comerciales</h1>
        <p class="text-muted">Descarga TODOS los negocios de Costa Rica desde OpenStreetMap (GRATIS)</p>

        <?php if ($action === 'form'): ?>
            <!-- Paso 1: Crear tabla -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Paso 1: Crear Tabla en Base de Datos</h5>
                </div>
                <div class="card-body">
                    <p>Crea la tabla <code>lugares_comerciales</code> con 28 campos de datos.</p>
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
                    <p>Descarga ~15,000 lugares: hoteles, restaurants, tiendas, servicios, etc.</p>
                    <button onclick="downloadData()" class="btn btn-success btn-lg">
                        <i class="fas fa-cloud-download-alt"></i> Descargar Datos (2-3 min)
                    </button>
                    <div id="downloadResult" class="mt-3"></div>
                </div>
            </div>

            <!-- Paso 3: Estadísticas -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Paso 3: Ver Resultados</h5>
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
                $exists = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
                if ($exists) {
                    echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
                    exit;
                }

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
                $overpass_query = '[out:json][timeout:180];
area["name"="Costa Rica"]["type"="boundary"]->.a;
(
  node["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);
  way["amenity"~"restaurant|bar|cafe|fast_food|pub|food_court|ice_cream|biergarten"](area.a);
  node["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);
  way["tourism"~"hotel|motel|guest_house|hostel|apartment|chalet|alpine_hut"](area.a);
  node["shop"](area.a);
  way["shop"](area.a);
  node["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);
  way["amenity"~"bank|pharmacy|clinic|dentist|doctors|hospital|veterinary|fuel|car_wash|car_rental|parking"](area.a);
  node["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  way["amenity"~"cinema|theatre|nightclub|casino|arts_centre|community_centre"](area.a);
  node["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);
  way["leisure"~"sports_centre|fitness_centre|swimming_pool|marina|golf_course|stadium"](area.a);
  node["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);
  way["tourism"~"attraction|museum|gallery|viewpoint|theme_park|zoo|aquarium"](area.a);
  node["office"](area.a);
  way["office"](area.a);
  node["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);
  way["amenity"~"school|kindergarten|college|university|language_school|driving_school"](area.a);
  node["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  way["shop"~"beauty|hairdresser|cosmetics|massage"](area.a);
  node["amenity"~"spa"](area.a);
  way["amenity"~"spa"](area.a);
);
out center;';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://overpass-api.de/api/interpreter");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode($overpass_query));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200) throw new Exception("Error en Overpass API: HTTP $http_code");

                $data = json_decode($response, true);
                if (!isset($data['elements'])) throw new Exception("Respuesta inválida de Overpass API");

                $elements = $data['elements'];
                $imported = 0;
                $errors = 0;

                foreach ($elements as $element) {
                    $tags = $element['tags'] ?? [];
                    $nombre = $tags['name'] ?? ($tags['brand'] ?? 'Sin nombre');
                    $tipo = $tags['amenity'] ?? $tags['tourism'] ?? $tags['shop'] ?? $tags['office'] ?? $tags['leisure'] ?? 'other';
                    $categoria = '';
                    if (isset($tags['amenity'])) $categoria = 'amenity';
                    elseif (isset($tags['tourism'])) $categoria = 'tourism';
                    elseif (isset($tags['shop'])) $categoria = 'shop';
                    elseif (isset($tags['office'])) $categoria = 'office';
                    elseif (isset($tags['leisure'])) $categoria = 'leisure';

                    $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
                    $lon = $element['lon'] ?? ($element['center']['lon'] ?? null);

                    try {
                        $stmt = $pdo->prepare("INSERT INTO lugares_comerciales (nombre, tipo, categoria, subtipo, descripcion, direccion, ciudad, provincia, codigo_postal, telefono, email, website, facebook, instagram, horario, latitud, longitud, osm_id, osm_type, capacidad, estrellas, wifi, parking, discapacidad_acceso, tarjetas_credito, delivery, takeaway, tags_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)");

                        $stmt->execute([
                            $nombre,
                            $tipo,
                            $categoria,
                            $tags['cuisine'] ?? '',
                            $tags['description'] ?? '',
                            trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? '')),
                            $tags['addr:city'] ?? '',
                            $tags['addr:province'] ?? '',
                            $tags['addr:postcode'] ?? '',
                            $tags['phone'] ?? $tags['contact:phone'] ?? '',
                            $tags['email'] ?? $tags['contact:email'] ?? '',
                            $tags['website'] ?? $tags['url'] ?? '',
                            $tags['contact:facebook'] ?? $tags['facebook'] ?? '',
                            $tags['contact:instagram'] ?? $tags['instagram'] ?? '',
                            $tags['opening_hours'] ?? '',
                            $lat,
                            $lon,
                            $element['id'] ?? null,
                            $element['type'] ?? 'node',
                            is_numeric($tags['capacity'] ?? '') ? intval($tags['capacity']) : null,
                            is_numeric($tags['stars'] ?? '') ? intval($tags['stars']) : null,
                            in_array($tags['internet_access'] ?? '', ['yes', 'wlan', 'wifi']) ? 1 : 0,
                            in_array($tags['parking'] ?? '', ['yes', 'surface']) ? 1 : 0,
                            ($tags['wheelchair'] ?? '') === 'yes' ? 1 : 0,
                            in_array($tags['payment:credit_cards'] ?? '', ['yes']) ? 1 : 0,
                            ($tags['delivery'] ?? '') === 'yes' ? 1 : 0,
                            ($tags['takeaway'] ?? '') === 'yes' ? 1 : 0,
                            json_encode($tags, JSON_UNESCAPED_UNICODE)
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors++;
                    }
                }

                echo json_encode(['success' => true, 'total' => count($elements), 'imported' => $imported, 'errors' => $errors]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            ?>

        <?php elseif ($action === 'stats'): ?>
            <?php
            header('Content-Type: application/json');
            try {
                $exists = $pdo->query("SHOW TABLES LIKE 'lugares_comerciales'")->fetch();
                if (!$exists) {
                    echo json_encode(['success' => false, 'error' => 'La tabla no existe']);
                    exit;
                }

                $stats = [];
                $stats['total'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales")->fetchColumn();
                $stats['by_categoria'] = $pdo->query("SELECT categoria, COUNT(*) as count FROM lugares_comerciales GROUP BY categoria ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
                $stats['by_type'] = $pdo->query("SELECT tipo, COUNT(*) as count FROM lugares_comerciales GROUP BY tipo ORDER BY count DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                $stats['with_email'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email != ''")->fetchColumn();
                $stats['with_phone'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE telefono != ''")->fetchColumn();
                $stats['with_website'] = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website != ''")->fetchColumn();
                $stats['by_ciudad'] = $pdo->query("SELECT ciudad, COUNT(*) as count FROM lugares_comerciales WHERE ciudad != '' GROUP BY ciudad ORDER BY count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'stats' => $stats]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            ?>

        <?php endif; ?>
    </div>

    <script>
    async function createTable() {
        const resultDiv = document.getElementById('createTableResult');
        resultDiv.innerHTML = '<div class="alert alert-info">⏳ Creando tabla...</div>';
        try {
            const response = await fetch('importar_lugares_standalone.php', {
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
        resultDiv.innerHTML = '<div class="alert alert-info"><strong>⏳ Descargando...</strong><br>Esto toma 2-3 minutos. NO cierres esta página.</div>';
        try {
            const response = await fetch('importar_lugares_standalone.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=download'
            });
            const result = await response.json();
            if (result.success) {
                resultDiv.innerHTML = `<div class="alert alert-success"><h5><i class="fas fa-check-circle"></i> ¡Descarga Exitosa!</h5><ul><li>Total encontrados: <strong>${result.total}</strong></li><li>Importados: <strong>${result.imported}</strong></li><li>Errores: <strong>${result.errors}</strong></li></ul></div>`;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + result.error + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        }
    }

    async function showStats() {
        const resultDiv = document.getElementById('statsResult');
        resultDiv.innerHTML = '<div class="alert alert-info">⏳ Cargando...</div>';
        try {
            const response = await fetch('importar_lugares_standalone.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=stats'
            });
            const result = await response.json();
            if (result.success) {
                const stats = result.stats;
                let html = '<div class="card"><div class="card-body">';
                html += '<h5>📊 Resumen</h5>';
                html += '<table class="table table-bordered"><tr><td><strong>Total:</strong></td><td>' + stats.total + '</td></tr>';
                html += '<tr><td><strong>Con email:</strong></td><td>' + stats.with_email + ' (' + (stats.with_email/stats.total*100).toFixed(1) + '%)</td></tr>';
                html += '<tr><td><strong>Con teléfono:</strong></td><td>' + stats.with_phone + '</td></tr></table>';

                html += '<h6 class="mt-3">Por Categoría</h6><table class="table table-sm">';
                stats.by_categoria.forEach(item => {
                    html += '<tr><td><strong>' + item.categoria + '</strong></td><td>' + item.count + '</td></tr>';
                });
                html += '</table></div></div>';
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' + result.error + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        }
    }
    </script>
</body>
</html>
