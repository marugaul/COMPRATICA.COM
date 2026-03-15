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

function log_msg($msg, $stdout = true) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    if ($stdout) echo $line;
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
    log_msg("Obteniendo mensajes de @{$channelUsername}...", false);

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
        log_msg("  ✗ Error obteniendo canal (HTTP {$httpCode})", false);
        return [];
    }

    return parseChannelHTML($html, $channelUsername);
}

/**
 * Parsear HTML del canal de Telegram
 */
function parseChannelHTML($html, $channelUsername) {
    $messages = [];

    // Buscar todos los mensajes completos (incluyendo fecha)
    if (preg_match_all('/<div class="tgme_widget_message[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $messageMatches, PREG_SET_ORDER)) {

        foreach ($messageMatches as $idx => $match) {
            $fullMessageHTML = $match[1];

            // Extraer fecha del mensaje
            $publishDate = null;
            if (preg_match('/<time[^>]+datetime="([^"]+)"[^>]*>/i', $fullMessageHTML, $dateMatch)) {
                $publishDate = $dateMatch[1];
            }

            // Extraer texto del mensaje
            if (!preg_match('/<div class="tgme_widget_message_text[^"]*"[^>]*>(.*?)<\/div>/s', $fullMessageHTML, $textMatch)) {
                continue;
            }

            $messageHTML = $textMatch[0];
            $text = $textMatch[1];

            // Limpiar HTML: convertir <br/> a saltos de línea, quitar tags
            $text = str_replace(['<br/>', '<br>', '<br />'], "\n", $text);
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);

            // Filtro más flexible para detectar empleos
            $isJob = false;

            // Palabras clave en español
            $keywordsEs = ['empleo', 'trabajo', 'vacante', 'plaza', 'puesto', 'empresa:', 'ubicación:',
                          'ingeniero', 'desarrollador', 'analista', 'contador', 'gerente', 'asistente',
                          'coordinador', 'director', 'técnico', 'supervisor'];

            // Palabras clave en inglés
            $keywordsEn = ['job', 'position', 'hiring', 'vacancy', 'engineer', 'developer', 'designer',
                          'analyst', 'manager', 'director', 'coordinator', 'specialist', 'consultant',
                          'architect', 'administrator', 'technician'];

            // Emojis comunes en empleos
            $emojis = ['🧑‍💼', '💼', '📢', '🔎', '🔍', '👔', '💻', '🏢'];

            // Verificar palabras clave
            $textLower = mb_strtolower($text, 'UTF-8');
            foreach (array_merge($keywordsEs, $keywordsEn) as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    $isJob = true;
                    break;
                }
            }

            // Verificar emojis
            if (!$isJob) {
                foreach ($emojis as $emoji) {
                    if (strpos($text, $emoji) !== false) {
                        $isJob = true;
                        break;
                    }
                }
            }

            if (!$isJob) {
                continue;
            }

            // Extraer link del mensaje
            $link = null;
            if (preg_match('/<a[^>]+href="(https?:\/\/(?!t\.me)[^"]+)"[^>]*>/i', $messageHTML, $linkMatch)) {
                $link = $linkMatch[1];
            }

            // Generar un ID único para este mensaje
            $messageId = $channelUsername . '/' . md5($text);

            $messages[] = [
                'id' => $messageId,
                'text' => $text,
                'link' => $link,
                'channel' => $channelUsername,
                'publish_date' => $publishDate,
            ];
        }
    }

    log_msg("  ✓ Encontrados " . count($messages) . " mensajes de empleos", false);
    return $messages;
}

/**
 * Parsear mensaje de empleo formato STEMJobsCR
 *
 * Formato esperado:
 * 🧑‍💼 | [Título del puesto]
 *
 * Empresa: [Nombre]
 * Ubicación: [Lugar] (Tipo)
 *
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

    // Buscar "Empresa:" en cualquier línea
    $company = 'No especificada';
    foreach ($lines as $line) {
        if (preg_match('/^Empresa:\s*(.+)$/i', $line, $match)) {
            $company = trim($match[1]);
            break;
        }
    }

    // Buscar "Ubicación:" en cualquier línea
    $location = 'Costa Rica';
    $jobType = 'onsite';
    foreach ($lines as $line) {
        if (preg_match('/^Ubicación:\s*(.+)$/i', $line, $match)) {
            $location = trim($match[1]);

            // Extraer tipo de trabajo del paréntesis
            if (preg_match('/\((Remote|On-site|Hybrid|Remoto|Presencial|Híbrido)\)/i', $location, $typeMatch)) {
                $type = strtolower($typeMatch[1]);
                if (in_array($type, ['remote', 'remoto'])) {
                    $jobType = 'remote';
                } elseif (in_array($type, ['hybrid', 'híbrido'])) {
                    $jobType = 'hybrid';
                }
                // Limpiar el tipo del location
                $location = trim(str_replace($typeMatch[0], '', $location));
            }
            break;
        }
    }

    // Extraer URL de aplicación de la descripción o del mensaje
    $applicationUrl = null;

    // Buscar URL en el link del mensaje (tiene prioridad)
    if (!empty($message['link'])) {
        $applicationUrl = $message['link'];
    } else {
        // Buscar URL en el texto (común: https://www.example.com/jobs/view/123456)
        if (preg_match('/(https?:\/\/[^\s<>"\']+)/i', $text, $urlMatch)) {
            $applicationUrl = $urlMatch[1];
        }
    }

    // Descripción: todo el texto completo
    $description = $text;

    // Crear descripción limpia sin el URL (ya lo tenemos en application_url)
    $cleanDescription = $description;
    if ($applicationUrl) {
        // Remover el URL de la descripción para que no se duplique
        $cleanDescription = str_replace($applicationUrl, '', $cleanDescription);
        $cleanDescription = trim(preg_replace('/\s+/', ' ', $cleanDescription));
    }

    // Categoría basada en palabras clave del título
    $category = categorizeJob($title);

    return [
        'title' => $title,
        'company' => $company,
        'location' => $location,
        'description' => $cleanDescription,
        'category' => $category,
        'job_type' => $jobType,
        'source_url' => "https://t.me/{$message['channel']}",
        'application_url' => $applicationUrl,
        'external_id' => "TG_{$message['id']}",
        'publish_date' => $message['publish_date'] ?? null,
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
        log_msg("No hay empleos nuevos para importar", false);
        return [0, 0];
    }

    $pdo = db();

    // Obtener ID del bot
    $stmt = $pdo->query("SELECT id FROM users WHERE email = 'bot@compratica.com' LIMIT 1");
    $bot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bot) {
        log_msg("ERROR: Usuario bot no encontrado");
        return [0, 0];
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

            // Preparar descripción con info de la empresa
            $fullDescription = "**Empresa:** " . $job['company'] . "\n\n" . $job['description'];

            // Insertar
            $stmt = $pdo->prepare("
                INSERT INTO job_listings (
                    employer_id, title, description, category, location,
                    job_type, listing_type, is_active,
                    import_source, source_url, application_url, start_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $botId,
                $job['title'],
                $fullDescription,
                $job['category'],
                $job['location'],
                $job['job_type'],
                'job',
                1,
                'Telegram_' . $job['external_id'],
                $job['source_url'],
                $job['application_url'],
                $job['publish_date'] ?? null
            ]);

            $inserted++;
            log_msg("  ✓ {$job['title']} - {$job['company']}", false);

        } catch (Exception $e) {
            log_msg("  ✗ Error: " . $e->getMessage(), false);
        }
    }

    return [$inserted, $skipped];
}

// ===== EJECUCIÓN PRINCIPAL =====
try {
    log_msg("=== Importación desde Telegram iniciada ===\n", false);

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

    log_msg("\nTotal mensajes nuevos: " . count($allMessages) . "\n", false);

    // Parsear empleos
    $jobs = [];
    foreach ($allMessages as $msg) {
        $job = parseJobMessage($msg);
        if ($job) {
            $jobs[] = $job;
        }
    }

    log_msg("Total empleos parseados: " . count($jobs) . "\n", false);

    if (!empty($jobs)) {
        // Mostrar muestra
        log_msg("--- Muestra de empleos ---", false);
        foreach (array_slice($jobs, 0, 5) as $i => $job) {
            log_msg(($i+1) . ". {$job['title']} | {$job['company']} | {$job['location']}", false);
        }
        log_msg("--- (mostrando " . min(5, count($jobs)) . " de " . count($jobs) . ") ---\n", false);

        list($inserted, $skipped) = importJobs($jobs);
        log_msg("  Telegram (STEMJobsCR + STEMJobsLATAM): +{$inserted} nuevos, {$skipped} duplicados");
    } else {
        log_msg("  Telegram (STEMJobsCR + STEMJobsLATAM): +0 nuevos, 0 duplicados");
    }

    // Guardar estado
    // Mantener solo los últimos 1000 IDs procesados
    if (count($state['processed']) > 1000) {
        $state['processed'] = array_slice($state['processed'], -1000);
    }
    saveState($state);

    log_msg("=== Importación completada ===", false);

} catch (Exception $e) {
    log_msg("ERROR FATAL: " . $e->getMessage());
    log_msg($e->getTraceAsString(), false);
    exit(1);
}
