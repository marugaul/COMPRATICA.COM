<?php
/**
 * API para importar lugares desde Páginas Amarillas Costa Rica
 * Web scraping de https://www.paginasamarillas.cr
 */

// Cargar configuración
require_once __DIR__ . '/../../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Función para actualizar progreso
function updatePAProgress($percent, $message, $imported = 0, $total = 0) {
    $progress_file = __DIR__ . '/../../logs/paginas_amarillas_progress.json';
    $progress_dir = dirname($progress_file);
    if (!is_dir($progress_dir)) {
        @mkdir($progress_dir, 0755, true);
    }

    $data = [
        'percent' => $percent,
        'message' => $message,
        'imported' => $imported,
        'total' => $total,
        'timestamp' => time()
    ];

    file_put_contents($progress_file, json_encode($data));
}

header('Content-Type: application/json');

// Configuración de BD
$config = require __DIR__ . '/../../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? '';

// ============================================
// CREAR TABLA
// ============================================
if ($action === 'crear_tabla') {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
        if ($check) {
            echo json_encode(['success' => false, 'error' => 'La tabla ya existe']);
            exit;
        }

        $sql_file = __DIR__ . '/../../mysql-pendientes/005-crear-tabla-paginas-amarillas.sql';
        if (!file_exists($sql_file)) {
            echo json_encode(['success' => false, 'error' => 'Archivo SQL no encontrado']);
            exit;
        }

        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);

        echo json_encode([
            'success' => true,
            'message' => 'Tabla lugares_paginas_amarillas creada exitosamente'
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================
// IMPORTAR DESDE PAGINAS AMARILLAS
// ============================================
if ($action === 'importar') {
    updatePAProgress(5, 'Iniciando importación desde Páginas Amarillas...', 0, 0);
    set_time_limit(3600); // 1 hora

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'La tabla no existe. Créala primero.']);
            exit;
        }

        updatePAProgress(10, 'Conectando con Páginas Amarillas CR...', 0, 0);

        // Obtener categorías del request
        $categorias_json = $_POST['categorias'] ?? '[]';
        $categorias_seleccionadas = json_decode($categorias_json, true) ?: [];

        // Categorías de Páginas Amarillas CR (URLs slug)
        $todas_categorias = [
            'restaurantes' => 'Restaurantes',
            'hoteles' => 'Hoteles',
            'cafeterias' => 'Cafeterías',
            'bares' => 'Bares',
            'supermercados' => 'Supermercados',
            'ferreterias' => 'Ferreterías',
            'farmacias' => 'Farmacias',
            'clinicas' => 'Clínicas',
            'hospitales' => 'Hospitales',
            'abogados' => 'Abogados',
            'contadores' => 'Contadores',
            'talleres-mecanicos' => 'Talleres Mecánicos',
            'gimnasios' => 'Gimnasios',
            'salones-belleza' => 'Salones de Belleza',
            'veterinarias' => 'Veterinarias',
            'escuelas' => 'Escuelas',
            'universidades' => 'Universidades',
            'inmobiliarias' => 'Inmobiliarias',
            'constructoras' => 'Constructoras',
            'electrica' => 'Servicios Eléctricos'
        ];

        if (empty($categorias_seleccionadas)) {
            $categorias_seleccionadas = array_keys($todas_categorias);
        }

        $categorias = [];
        foreach ($categorias_seleccionadas as $cat_id) {
            if (isset($todas_categorias[$cat_id])) {
                $categorias[$cat_id] = $todas_categorias[$cat_id];
            }
        }

        if (empty($categorias)) {
            $categorias = $todas_categorias;
        }

        $total_imported = 0;
        $total_updated = 0;
        $total_errors = 0;
        $total_categorias = count($categorias);
        $categoria_actual = 0;

        // Preparar statement
        $stmt = $pdo->prepare("
            INSERT INTO lugares_paginas_amarillas (
                pa_id, nombre, categoria, subcategoria, telefono, telefono2, email,
                website, direccion, ciudad, provincia, codigo_postal,
                latitud, longitud, horario, descripcion, data_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre),
                telefono = VALUES(telefono),
                telefono2 = VALUES(telefono2),
                email = VALUES(email),
                website = VALUES(website),
                direccion = VALUES(direccion),
                ciudad = VALUES(ciudad),
                provincia = VALUES(provincia),
                horario = VALUES(horario),
                descripcion = VALUES(descripcion),
                data_json = VALUES(data_json),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($categorias as $cat_slug => $cat_nombre) {
            $categoria_actual++;
            $progreso = 15 + (($categoria_actual / $total_categorias) * 75);

            updatePAProgress(
                $progreso,
                "Buscando $cat_nombre...",
                $total_imported + $total_updated,
                $total_categorias * 50
            );

            // Intentar scraping de páginas amarillas
            // Nota: Este es un ejemplo, la estructura real puede variar
            $base_url = "https://www.paginasamarillas.cr/buscar/$cat_slug";

            for ($page = 1; $page <= 5; $page++) { // Máximo 5 páginas por categoría
                $url = $page === 1 ? $base_url : "$base_url?page=$page";

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: text/html,application/xhtml+xml',
                    'Accept-Language: es-CR,es;q=0.9'
                ]);

                $html = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code !== 200 || empty($html)) {
                    $total_errors++;
                    continue;
                }

                // Parsear HTML para extraer datos
                // Esta es una implementación genérica - ajustar según estructura real del sitio
                $lugares = parsePA_HTML($html, $cat_nombre);

                if (empty($lugares)) {
                    break; // No más resultados en esta categoría
                }

                foreach ($lugares as $lugar) {
                    try {
                        $pa_id = md5($lugar['nombre'] . $lugar['telefono'] . $lugar['direccion']);

                        $stmt->execute([
                            $pa_id,
                            $lugar['nombre'] ?? 'Sin nombre',
                            $cat_nombre,
                            $lugar['subcategoria'] ?? '',
                            $lugar['telefono'] ?? '',
                            $lugar['telefono2'] ?? '',
                            $lugar['email'] ?? '',
                            $lugar['website'] ?? '',
                            $lugar['direccion'] ?? '',
                            $lugar['ciudad'] ?? '',
                            $lugar['provincia'] ?? '',
                            $lugar['codigo_postal'] ?? '',
                            $lugar['latitud'] ?? null,
                            $lugar['longitud'] ?? null,
                            $lugar['horario'] ?? '',
                            $lugar['descripcion'] ?? '',
                            json_encode($lugar, JSON_UNESCAPED_UNICODE)
                        ]);

                        if ($stmt->rowCount() > 0) {
                            $total_imported++;
                        }
                    } catch (PDOException $e) {
                        error_log("PA DB Error: " . $e->getMessage());
                        $total_errors++;
                    }
                }

                // Rate limiting - ser respetuoso con el servidor
                sleep(2);
            }
        }

        updatePAProgress(95, 'Generando estadísticas finales...', $total_imported, $total_categorias * 50);

        // Estadísticas finales
        $total_db = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE website IS NOT NULL AND website != ''")->fetchColumn();

        updatePAProgress(100, '¡Importación completada!', $total_imported, $total_db);

        echo json_encode([
            'success' => true,
            'imported' => $total_imported,
            'updated' => $total_updated,
            'errors' => $total_errors,
            'stats' => [
                'total' => $total_db,
                'with_email' => $with_email,
                'with_phone' => $with_phone,
                'with_website' => $with_website,
                'email_percent' => $total_db > 0 ? round($with_email/$total_db*100, 1) : 0,
                'phone_percent' => $total_db > 0 ? round($with_phone/$total_db*100, 1) : 0,
                'website_percent' => $total_db > 0 ? round($with_website/$total_db*100, 1) : 0
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// ============================================
// FUNCIÓN PARA PARSEAR HTML
// ============================================
function parsePA_HTML($html, $categoria) {
    $lugares = [];

    // Crear DOM parser
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    // Buscar patrones comunes de listados de empresas
    // Estos selectores deben ajustarse según la estructura real del sitio
    $patterns = [
        // Patrón 1: divs con clase listing o business
        '//div[contains(@class, "listing") or contains(@class, "business") or contains(@class, "result")]',
        // Patrón 2: artículos
        '//article[contains(@class, "listing") or contains(@class, "empresa")]',
        // Patrón 3: li items
        '//li[contains(@class, "listing") or contains(@class, "result-item")]'
    ];

    $items = null;
    foreach ($patterns as $pattern) {
        $items = $xpath->query($pattern);
        if ($items && $items->length > 0) {
            break;
        }
    }

    if (!$items || $items->length === 0) {
        return $lugares;
    }

    foreach ($items as $item) {
        $lugar = [
            'nombre' => '',
            'telefono' => '',
            'telefono2' => '',
            'email' => '',
            'website' => '',
            'direccion' => '',
            'ciudad' => '',
            'provincia' => '',
            'descripcion' => '',
            'horario' => ''
        ];

        // Extraer nombre
        $nombre_nodes = $xpath->query('.//h2|.//h3|.//*[contains(@class, "name") or contains(@class, "title")]', $item);
        if ($nombre_nodes->length > 0) {
            $lugar['nombre'] = trim($nombre_nodes->item(0)->textContent);
        }

        // Extraer teléfono
        $tel_patterns = [
            './/*[contains(@class, "phone") or contains(@class, "tel")]',
            './/a[starts-with(@href, "tel:")]',
            './/*[contains(text(), "2") and string-length(.) < 20]' // Números CR empiezan con 2
        ];
        foreach ($tel_patterns as $pattern) {
            $tel_nodes = $xpath->query($pattern, $item);
            if ($tel_nodes->length > 0) {
                $tel = preg_replace('/[^0-9\-\+\s]/', '', $tel_nodes->item(0)->textContent);
                if (strlen($tel) >= 8) {
                    $lugar['telefono'] = trim($tel);
                    break;
                }
            }
        }

        // Extraer email
        $email_nodes = $xpath->query('.//a[contains(@href, "mailto:")]', $item);
        if ($email_nodes->length > 0) {
            $href = $email_nodes->item(0)->getAttribute('href');
            $lugar['email'] = str_replace('mailto:', '', $href);
        }

        // Extraer website
        $web_nodes = $xpath->query('.//a[contains(@class, "website") or contains(@class, "web")]/@href', $item);
        if ($web_nodes->length > 0) {
            $lugar['website'] = $web_nodes->item(0)->nodeValue;
        }

        // Extraer dirección
        $addr_nodes = $xpath->query('.//*[contains(@class, "address") or contains(@class, "direccion")]', $item);
        if ($addr_nodes->length > 0) {
            $lugar['direccion'] = trim($addr_nodes->item(0)->textContent);
        }

        // Solo agregar si tiene nombre y al menos teléfono o email
        if (!empty($lugar['nombre']) && (!empty($lugar['telefono']) || !empty($lugar['email']))) {
            $lugares[] = $lugar;
        }
    }

    return $lugares;
}

// ============================================
// OBTENER ESTADÍSTICAS
// ============================================
if ($action === 'estadisticas') {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'lugares_paginas_amarillas'")->fetch();
        if (!$check) {
            echo json_encode(['success' => false, 'error' => 'Tabla no existe']);
            exit;
        }

        $total = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas")->fetchColumn();
        $with_email = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $with_phone = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE telefono IS NOT NULL AND telefono != ''")->fetchColumn();
        $with_website = $pdo->query("SELECT COUNT(*) FROM lugares_paginas_amarillas WHERE website IS NOT NULL AND website != ''")->fetchColumn();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'with_email' => $with_email,
                'with_phone' => $with_phone,
                'with_website' => $with_website,
                'email_percent' => $total > 0 ? round($with_email/$total*100, 1) : 0,
                'phone_percent' => $total > 0 ? round($with_phone/$total*100, 1) : 0,
                'website_percent' => $total > 0 ? round($with_website/$total*100, 1) : 0
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
