#!/usr/bin/env php
<?php
/**
 * Importador de empleos desde canales de Telegram
 *
 * Canales: @STEMJobsCR y @STEMJobsLATAM
 *
 * CONFIGURACIÓN INICIAL:
 * 1. Habla con @BotFather en Telegram
 * 2. Envía: /newbot
 * 3. Elige nombre (ej: CompraTica Jobs Bot)
 * 4. Elige username (ej: compratica_jobs_bot)
 * 5. Copia el TOKEN que te da
 * 6. Pégalo en config.php (ver abajo)
 *
 * Ejecutar: php scripts/import_telegram_jobs.php
 */

require_once __DIR__ . '/../includes/db.php';

// ===== CONFIGURACIÓN =====
// Crea este archivo: /includes/telegram_config.php
$configFile = __DIR__ . '/../includes/telegram_config.php';

if (!file_exists($configFile)) {
    echo "❌ ERROR: No existe el archivo de configuración.\n\n";
    echo "Crea el archivo: includes/telegram_config.php\n";
    echo "Con este contenido:\n\n";
    echo "<?php\n";
    echo "// Token del bot de Telegram (obtenlo de @BotFather)\n";
    echo "define('TELEGRAM_BOT_TOKEN', 'TU_TOKEN_AQUI');\n\n";
    echo "// Canales a importar (sin @)\n";
    echo "define('TELEGRAM_CHANNELS', [\n";
    echo "    'STEMJobsCR',      // Empleos CR\n";
    echo "    'STEMJobsLATAM',   // Empleos remotos LATAM\n";
    echo "]);\n";
    echo "?>\n\n";
    echo "Luego ejecuta este script de nuevo.\n";
    exit(1);
}

require_once $configFile;

// Verificar configuración
if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'TU_TOKEN_AQUI') {
    echo "❌ ERROR: Debes configurar TELEGRAM_BOT_TOKEN en includes/telegram_config.php\n";
    echo "Ve a @BotFather en Telegram para crear tu bot y obtener el token.\n";
    exit(1);
}

define('LOG_FILE', __DIR__ . '/../logs/import_telegram.log');

// Asegurar directorio de logs
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Base de datos para tracking de mensajes ya procesados
$stateFile = __DIR__ . '/../logs/telegram_state.json';

function log_msg($msg) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

/**
 * Llamada a la API de Telegram
 */
function telegramAPI($method, $params = []) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/{$method}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        log_msg("API Error: HTTP {$httpCode}");
        return null;
    }

    $result = json_decode($response, true);

    if (!$result || !$result['ok']) {
        $error = $result['description'] ?? 'Unknown error';
        log_msg("Telegram API Error: {$error}");
        return null;
    }

    return $result['result'];
}

/**
 * Obtener mensajes recientes de un canal
 * Nota: Para canales públicos usamos la web preview
 */
function getChannelMessages($channelUsername) {
    log_msg("Obteniendo mensajes de @{$channelUsername}...");

    // Para canales públicos, scrapeamos la versión web de Telegram
    $url = "https://t.me/s/{$channelUsername}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($html)) {
        log_msg("  ✗ Error obteniendo canal (HTTP {$httpCode})");
        return [];
    }

    return parseChannelHTML($html, $channelUsername);
}

/**
 * Parsear HTML del canal de Telegram
 */
function parseChannelHTML($html, $channelUsername) {
    $messages = [];

    // Buscar todos los mensajes (divs con class="tgme_widget_message")
    if (preg_match_all('/<div class="tgme_widget_message.*?" data-post="([^"]+)".*?>(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $matches, PREG_SET_ORDER)) {

        foreach ($matches as $match) {
            $messageId = $match[1]; // channel/messageId
            $messageHTML = $match[2];

            // Extraer texto del mensaje
            if (preg_match('/<div class="tgme_widget_message_text[^"]*"[^>]*>(.*?)<\/div>/s', $messageHTML, $textMatch)) {
                $text = strip_tags($textMatch[1]);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = trim($text);

                // Extraer link si existe
                $link = null;
                if (preg_match('/<a[^>]+href="([^"]+)"[^>]*>/i', $messageHTML, $linkMatch)) {
                    $link = $linkMatch[1];
                    // Limpiar links de Telegram redirect
                    if (strpos($link, 't.me/') !== false) {
                        // Es un link interno, buscar el link externo
                        if (preg_match('/href="(https?:\/\/(?!t\.me)[^"]+)"/i', $messageHTML, $externalLink)) {
                            $link = $externalLink[1];
                        }
                    }
                }

                // Solo procesar si parece un empleo (tiene emoji 🧑‍💼 o keywords)
                if (strpos($text, '🧑‍💼') !== false ||
                    preg_match('/\b(developer|engineer|designer|analyst|manager)\b/i', $text)) {

                    $messages[] = [
                        'id' => $messageId,
                        'text' => $text,
                        'link' => $link,
                        'channel' => $channelUsername,
                    ];
                }
            }
        }
    }

    log_msg("  ✓ Encontrados " . count($messages) . " mensajes de empleos");
    return $messages;
}

/**
 * Parsear mensaje de empleo formato STEMJobsCR
 *
 * Formato esperado:
 * 🧑‍💼 | [Título del puesto]
 * [Empresa]
 * [Ubicación]
 * [Link opcional]
 */
function parseJobMessage($message) {
    $text = $message['text'];
    $lines = array_filter(array_map('trim', explode("\n", $text)));

    if (count($lines) < 2) {
        return null; // No tiene suficiente info
    }

    // Primera línea: título (puede tener 🧑‍💼 |)
    $title = $lines[0];
    $title = preg_replace('/^🧑‍💼\s*\|\s*/', '', $title);
    $title = trim($title);

    if (empty($title)) {
        return null;
    }

    // Segunda línea: empresa
    $company = $lines[1] ?? 'No especificada';

    // Tercera línea: ubicación
    $location = $lines[2] ?? 'Costa Rica';

    // Resto: descripción
    $description = '';
    if (count($lines) > 3) {
        $description = implode("\n", array_slice($lines, 3));
    }

    // Categoría basada en palabras clave del título
    $category = categorizeJob($title);

    // Determinar tipo de trabajo (remoto/híbrido/presencial)
    $jobType = 'onsite';
    if (preg_match('/remote|remoto|virtual/i', $location)) {
        $jobType = 'remote';
    } elseif (preg_match('/hybrid|híbrido/i', $location)) {
        $jobType = 'hybrid';
    }

    return [
        'title' => $title,
        'company' => $company,
        'location' => $location,
        'description' => $description,
        'category' => $category,
        'job_type' => $jobType,
        'source_url' => $message['link'] ?? "https://t.me/{$message['channel']}",
        'external_id' => "TG_{$message['id']}",
    ];
}

/**
 * Categorizar empleo basado en título
 */
function categorizeJob($title) {
    $title = strtolower($title);

    $categories = [
        'EMP:Technology' => ['developer', 'engineer', 'software', 'programmer', 'devops', 'cloud', 'data', 'qa', 'tester', 'frontend', 'backend', 'fullstack', 'mobile', 'ios', 'android', 'architect'],
        'EMP:Design' => ['designer', 'ux', 'ui', 'graphic', 'creative'],
        'EMP:Marketing' => ['marketing', 'seo', 'social media', 'content', 'digital marketing'],
        'EMP:Sales' => ['sales', 'business development', 'account'],
        'EMP:Management' => ['manager', 'director', 'lead', 'head', 'chief', 'ceo', 'cto'],
        'EMP:Customer Service' => ['customer service', 'support', 'help desk'],
        'EMP:Finance' => ['accountant', 'finance', 'financial', 'analyst'],
    ];

    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($title, $keyword) !== false) {
                return $category;
            }
        }
    }

    return 'EMP:Technology'; // Default para STEM jobs
}

/**
 * Cargar estado (IDs ya procesados)
 */
function loadState() {
    global $stateFile;
    if (file_exists($stateFile)) {
        $data = json_decode(file_get_contents($stateFile), true);
        return $data ?: ['processed' => []];
    }
    return ['processed' => []];
}

/**
 * Guardar estado
 */
function saveState($state) {
    global $stateFile;
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
}

/**
 * Importar empleos a la base de datos
 */
function importJobs($jobs) {
    if (empty($jobs)) {
        log_msg("No hay empleos nuevos para importar");
        return;
    }

    $pdo = db();

    // Obtener ID del bot
    $stmt = $pdo->query("SELECT id FROM users WHERE email = 'bot@compratica.com' LIMIT 1");
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bot) {
        log_msg("ERROR: Usuario bot no encontrado");
        return;
    }

    $botId = $bot['id'];
    $inserted = 0;
    $skipped = 0;

    foreach ($jobs as $job) {
        try {
            // Verificar si ya existe (por external_id)
            $stmt = $pdo->prepare("SELECT id FROM job_listings WHERE import_source = ? LIMIT 1");
            $stmt->execute(['Telegram_' . $job['external_id']]);

            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }

            // Insertar
            $stmt = $pdo->prepare("
                INSERT INTO job_listings (
                    user_id, title, description, category, location,
                    company_name, job_type, listing_type, is_active,
                    import_source, source_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $botId,
                $job['title'],
                $job['description'],
                $job['category'],
                $job['location'],
                $job['company'],
                $job['job_type'],
                'job',
                1,
                'Telegram_' . $job['external_id'],
                $job['source_url']
            ]);

            $inserted++;
            log_msg("  ✓ {$job['title']} - {$job['company']}");

        } catch (Exception $e) {
            log_msg("  ✗ Error: " . $e->getMessage());
        }
    }

    log_msg("\n=== Resumen ===");
    log_msg("Insertados: {$inserted}");
    log_msg("Omitidos: {$skipped}");
    log_msg("===============\n");
}

// ===== EJECUCIÓN PRINCIPAL =====
try {
    log_msg("=== Importación desde Telegram iniciada ===\n");

    $state = loadState();
    $allMessages = [];

    // Procesar cada canal
    foreach (TELEGRAM_CHANNELS as $channel) {
        $messages = getChannelMessages($channel);

        foreach ($messages as $msg) {
            // Verificar si ya fue procesado
            if (in_array($msg['id'], $state['processed'])) {
                continue;
            }

            $allMessages[] = $msg;
            $state['processed'][] = $msg['id'];
        }
    }

    log_msg("\nTotal mensajes nuevos: " . count($allMessages) . "\n");

    // Parsear empleos
    $jobs = [];
    foreach ($allMessages as $msg) {
        $job = parseJobMessage($msg);
        if ($job) {
            $jobs[] = $job;
        }
    }

    log_msg("Total empleos parseados: " . count($jobs) . "\n");

    if (!empty($jobs)) {
        // Mostrar muestra
        log_msg("--- Muestra de empleos ---");
        foreach (array_slice($jobs, 0, 5) as $i => $job) {
            log_msg(($i+1) . ". {$job['title']} | {$job['company']} | {$job['location']}");
        }
        log_msg("--- (mostrando " . min(5, count($jobs)) . " de " . count($jobs) . ") ---\n");

        importJobs($jobs);
    }

    // Guardar estado
    // Mantener solo los últimos 1000 IDs procesados
    if (count($state['processed']) > 1000) {
        $state['processed'] = array_slice($state['processed'], -1000);
    }
    saveState($state);

    log_msg("=== Importación completada ===");

} catch (Exception $e) {
    log_msg("ERROR FATAL: " . $e->getMessage());
    log_msg($e->getTraceAsString());
    exit(1);
}
