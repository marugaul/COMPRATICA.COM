<?php
/**
 * API para enriquecer emails desde sitios web existentes
 * Extrae emails de páginas de contacto de los negocios
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
function updateEnrichProgress($percent, $message, $processed = 0, $total = 0, $found = 0) {
    $progress_file = __DIR__ . '/../../logs/enrich_progress.json';
    $progress_dir = dirname($progress_file);
    if (!is_dir($progress_dir)) {
        @mkdir($progress_dir, 0755, true);
    }

    $data = [
        'percent' => $percent,
        'message' => $message,
        'processed' => $processed,
        'total' => $total,
        'found' => $found,
        'timestamp' => time()
    ];

    file_put_contents($progress_file, json_encode($data));
}

// Función para extraer emails de HTML
function extractEmails($html) {
    $emails = [];

    // Regex para emails
    $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    preg_match_all($pattern, $html, $matches);

    if (!empty($matches[0])) {
        foreach ($matches[0] as $email) {
            $email = strtolower(trim($email));

            // Filtrar emails no deseados
            $skip = ['example.com', 'domain.com', 'email.com', 'test.com',
                     'yoursite.com', 'yourdomain.com', 'placeholder',
                     'noreply@', 'no-reply@', 'image', '.png', '.jpg', '.gif'];

            $isValid = true;
            foreach ($skip as $pattern) {
                if (strpos($email, $pattern) !== false) {
                    $isValid = false;
                    break;
                }
            }

            if ($isValid && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
    }

    return array_unique($emails);
}

// Función para obtener contenido de una URL
function fetchURL($url, $timeout = 10) {
    // Normalizar URL
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400 && $content) {
        return $content;
    }

    return false;
}

// Función para buscar emails en un sitio web
function findEmailsInWebsite($baseUrl) {
    $emails = [];

    // Primero buscar en la página principal
    $homeContent = fetchURL($baseUrl, 8);
    if ($homeContent) {
        $emails = array_merge($emails, extractEmails($homeContent));
    }

    // Si no encontró emails, buscar en páginas de contacto comunes
    if (empty($emails)) {
        $contactPages = [
            '/contacto', '/contact', '/contactenos', '/contactenos.html', '/contact.html',
            '/acerca-de', '/about', '/nosotros', '/acerca', '/sobre-nosotros',
            '/contacto.php', '/contact.php', '/contacto.html'
        ];

        foreach ($contactPages as $page) {
            $url = rtrim($baseUrl, '/') . $page;
            $content = fetchURL($url, 8);

            if ($content) {
                $foundEmails = extractEmails($content);
                if (!empty($foundEmails)) {
                    $emails = array_merge($emails, $foundEmails);
                    break; // Ya encontró, no seguir buscando
                }
            }
        }
    }

    return array_unique($emails);
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
// OBTENER ESTADÍSTICAS
// ============================================
if ($action === 'stats') {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website IS NOT NULL AND website != ''")->fetchColumn();
        $withEmail = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE website IS NOT NULL AND website != '' AND email IS NOT NULL AND email != ''")->fetchColumn();
        $withoutEmail = $total - $withEmail;

        echo json_encode([
            'success' => true,
            'total_with_website' => $total,
            'with_email' => $withEmail,
            'without_email' => $withoutEmail
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ============================================
// ENRIQUECER EMAILS
// ============================================
if ($action === 'enrich') {
    updateEnrichProgress(5, 'Iniciando enriquecimiento...', 0, 0, 0);
    set_time_limit(600); // 10 minutos

    try {
        // Obtener lugares con website pero sin email
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100; // Por defecto 100

        $stmt = $pdo->query("
            SELECT id, nombre, website
            FROM lugares_comerciales
            WHERE website IS NOT NULL
            AND website != ''
            AND (email IS NULL OR email = '')
            LIMIT $limit
        ");

        $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($lugares);

        if ($total === 0) {
            echo json_encode([
                'success' => true,
                'message' => 'No hay lugares para procesar',
                'processed' => 0,
                'found' => 0
            ]);
            exit;
        }

        updateEnrichProgress(10, "Procesando $total sitios web...", 0, $total, 0);

        $processed = 0;
        $found = 0;
        $updateStmt = $pdo->prepare("UPDATE lugares_comerciales SET email = ? WHERE id = ?");

        foreach ($lugares as $lugar) {
            $processed++;

            // Buscar emails en el sitio web
            $emails = findEmailsInWebsite($lugar['website']);

            if (!empty($emails)) {
                $email = $emails[0]; // Tomar el primero encontrado
                $updateStmt->execute([$email, $lugar['id']]);
                $found++;
            }

            // Actualizar progreso cada 5 sitios
            if ($processed % 5 === 0 || $processed === $total) {
                $percent = 10 + ($processed / $total * 85);
                updateEnrichProgress(
                    round($percent),
                    "Procesados: $processed / $total - Emails encontrados: $found",
                    $processed,
                    $total,
                    $found
                );
            }

            // Pequeña pausa para no saturar servidores
            usleep(500000); // 0.5 segundos
        }

        updateEnrichProgress(100, '¡Enriquecimiento completado!', $processed, $total, $found);

        // Estadísticas finales
        $totalEmails = $pdo->query("SELECT COUNT(*) FROM lugares_comerciales WHERE email IS NOT NULL AND email != ''")->fetchColumn();

        echo json_encode([
            'success' => true,
            'processed' => $processed,
            'found' => $found,
            'success_rate' => $processed > 0 ? round($found/$processed*100, 1) : 0,
            'total_emails_now' => $totalEmails
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// Acción no reconocida
echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
