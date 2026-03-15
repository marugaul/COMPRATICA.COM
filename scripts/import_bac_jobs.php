#!/usr/bin/env php
<?php
/**
 * Script experimental para importar empleos del BAC (Talento360)
 *
 * ADVERTENCIA: Este es un script experimental. Verifica que el uso
 * de estas APIs no viole los términos de servicio del sitio.
 *
 * Ejecutar: php scripts/import_bac_jobs.php
 */

require_once __DIR__ . '/../includes/db.php';

// Configuración
define('BAC_BASE_URL', 'https://talento360.csod.com');
define('BAC_COUNTRY', 'cr'); // Costa Rica
define('LOG_FILE', __DIR__ . '/../logs/import_bac.log');

// Asegurar que existe el directorio de logs
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

function log_msg($msg) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

/**
 * Intenta diferentes métodos para obtener los empleos del BAC
 */
function fetchBACJobs() {
    log_msg("=== Iniciando importación de empleos del BAC ===");

    // Método 1: Intentar API directa
    $jobs = tryAPIMethod();
    if (!empty($jobs)) {
        return $jobs;
    }

    // Método 2: Intentar scraping de la página principal
    $jobs = tryScrapingMethod();
    if (!empty($jobs)) {
        return $jobs;
    }

    log_msg("ERROR: No se pudo obtener empleos con ningún método");
    return [];
}

/**
 * Método 1: Intentar acceder a la API de Talento360
 */
function tryAPIMethod() {
    log_msg("Método 1: Intentando acceso a API de Talento360...");

    // Posibles endpoints de la API
    $endpoints = [
        '/ux/ats/careersite/4/requisitions?country=cr',
        '/api/rec-job-search/external?country=cr',
        '/ats/careersite/4/jobs?country=cr&lang=es-MX',
    ];

    foreach ($endpoints as $endpoint) {
        $url = BAC_BASE_URL . $endpoint;
        log_msg("  Probando: {$url}");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: es-MX,es;q=0.9',
                'Referer: ' . BAC_BASE_URL . '/ux/ats/careersite/4/home?c=talento360',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_msg("  ✗ Error CURL: {$error}");
            continue;
        }

        log_msg("  HTTP {$httpCode}");

        if ($httpCode == 200 && !empty($response)) {
            // Intentar parsear como JSON
            $data = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                log_msg("  ✓ Respuesta JSON válida recibida");

                // Buscar array de empleos en diferentes estructuras comunes
                $possibleKeys = ['jobs', 'requisitions', 'data', 'results', 'items', 'postings'];
                foreach ($possibleKeys as $key) {
                    if (isset($data[$key]) && is_array($data[$key])) {
                        log_msg("  ✓ Encontrados empleos en key '{$key}': " . count($data[$key]));
                        return parseAPIJobs($data[$key]);
                    }
                }

                // Si el data mismo es array de empleos
                if (isset($data[0]) && is_array($data[0])) {
                    log_msg("  ✓ Data es array directo: " . count($data));
                    return parseAPIJobs($data);
                }

                // Guardar respuesta para debug
                file_put_contents(__DIR__ . '/../logs/bac_api_response.json', json_encode($data, JSON_PRETTY_PRINT));
                log_msg("  ⚠ Estructura JSON no reconocida. Guardado en logs/bac_api_response.json");
            }
        }
    }

    return [];
}

/**
 * Método 2: Scraping de la página HTML (fallback)
 */
function tryScrapingMethod() {
    log_msg("Método 2: Intentando scraping de HTML...");

    $url = BAC_BASE_URL . '/ux/ats/careersite/4/home?c=talento360&country=cr&lang=es-MX';

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

    if ($httpCode != 200 || empty($html)) {
        log_msg("  ✗ Error obteniendo HTML (HTTP {$httpCode})");
        return [];
    }

    // Buscar datos JSON embebidos en el HTML
    // Muchos SPAs incluyen el estado inicial como JSON en <script> tags
    if (preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $html, $matches)) {
        foreach ($matches[1] as $script) {
            // Buscar JSON que contenga "jobs", "requisitions", etc.
            if (preg_match('/(\{.*(?:jobs|requisitions|postings).*\})/si', $script, $jsonMatch)) {
                $possibleJson = $jsonMatch[1];
                $data = json_decode($possibleJson, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    log_msg("  ✓ JSON encontrado en <script>");
                    file_put_contents(__DIR__ . '/../logs/bac_scrape_response.json', json_encode($data, JSON_PRETTY_PRINT));

                    // Buscar empleos en la estructura
                    foreach (['jobs', 'requisitions', 'data', 'results'] as $key) {
                        if (isset($data[$key]) && is_array($data[$key])) {
                            return parseAPIJobs($data[$key]);
                        }
                    }
                }
            }
        }
    }

    log_msg("  ✗ No se encontraron datos JSON en HTML");
    return [];
}

/**
 * Parsear empleos desde respuesta de API
 */
function parseAPIJobs($rawJobs) {
    log_msg("Parseando " . count($rawJobs) . " empleos...");

    $parsed = [];

    foreach ($rawJobs as $job) {
        if (!is_array($job)) continue;

        // Intentar extraer campos comunes (los nombres pueden variar)
        $title = $job['title'] ?? $job['jobTitle'] ?? $job['name'] ?? $job['position'] ?? null;
        $description = $job['description'] ?? $job['jobDescription'] ?? $job['details'] ?? '';
        $location = $job['location'] ?? $job['city'] ?? $job['locationName'] ?? 'Costa Rica';
        $url = $job['url'] ?? $job['applyUrl'] ?? $job['link'] ?? null;
        $id = $job['id'] ?? $job['requisitionId'] ?? $job['jobId'] ?? null;

        if (empty($title)) {
            continue; // Skip si no tiene título
        }

        $parsed[] = [
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'company' => 'BAC Credomatic',
            'source_url' => $url ?: (BAC_BASE_URL . '/ux/ats/careersite/4/home?c=talento360'),
            'external_id' => $id ? "BAC_{$id}" : null,
        ];
    }

    log_msg("✓ Parseados " . count($parsed) . " empleos válidos");
    return $parsed;
}

/**
 * Insertar empleos en la base de datos
 */
function importJobs($jobs) {
    if (empty($jobs)) {
        log_msg("No hay empleos para importar");
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

    log_msg("Iniciando importación a base de datos...");

    foreach ($jobs as $job) {
        try {
            // Verificar si ya existe (por título + compañía)
            $stmt = $pdo->prepare("
                SELECT id FROM job_listings
                WHERE title = ? AND company_name = ?
                LIMIT 1
            ");
            $stmt->execute([$job['title'], $job['company']]);

            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }

            // Insertar nuevo empleo
            $stmt = $pdo->prepare("
                INSERT INTO job_listings (
                    user_id, title, description, category, location,
                    company_name, listing_type, is_active, import_source, source_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $botId,
                $job['title'],
                $job['description'],
                'EMP:Finance', // BAC es banco, categoría Finance por defecto
                $job['location'],
                $job['company'],
                'job',
                1,
                'BAC_Talento360',
                $job['source_url']
            ]);

            $inserted++;
            log_msg("  ✓ Insertado: {$job['title']}");

        } catch (Exception $e) {
            log_msg("  ✗ Error insertando '{$job['title']}': " . $e->getMessage());
        }
    }

    log_msg("\n=== Resumen ===");
    log_msg("Total empleos procesados: " . count($jobs));
    log_msg("Insertados: {$inserted}");
    log_msg("Omitidos (duplicados): {$skipped}");
    log_msg("===============\n");
}

// Ejecutar importación
try {
    $jobs = fetchBACJobs();

    if (!empty($jobs)) {
        log_msg("\n--- Muestra de empleos encontrados ---");
        foreach (array_slice($jobs, 0, 3) as $i => $job) {
            log_msg(($i+1) . ". {$job['title']} - {$job['location']}");
        }
        log_msg("--- (mostrando 3 de " . count($jobs) . ") ---\n");

        importJobs($jobs);
    } else {
        log_msg("\n⚠ NOTA: Este script es experimental.");
        log_msg("Si no funcionó, puede ser porque:");
        log_msg("  1. El sitio requiere autenticación");
        log_msg("  2. Los empleos se cargan dinámicamente con JavaScript");
        log_msg("  3. La API tiene protección anti-bot");
        log_msg("\nRecomendaciones:");
        log_msg("  - Contactar a BAC para preguntar por RSS feed oficial");
        log_msg("  - Usar canales de Telegram de empleos de CR");
        log_msg("  - Agregar empleos manualmente desde el admin\n");
    }

} catch (Exception $e) {
    log_msg("ERROR FATAL: " . $e->getMessage());
    log_msg($e->getTraceAsString());
    exit(1);
}
