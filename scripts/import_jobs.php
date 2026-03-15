<?php
/**
 * scripts/import_jobs.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Importador automático de empleos gratuito para CompraTica.
 *
 * Fuentes soportadas (todas GRATIS, sin API key):
 *   1. Indeed Costa Rica  — RSS por categoría
 *   2. Arbeitnow          — API JSON de empleos remotos
 *   3. Remotive           — API JSON de empleos remotos
 *   4. Jobicy             — API JSON de empleos remotos
 *
 * Uso:
 *   php scripts/import_jobs.php              # importa todo
 *   php scripts/import_jobs.php --source=indeed
 *   php scripts/import_jobs.php --source=arbeitnow
 *   php scripts/import_jobs.php --dry-run    # solo muestra, no inserta
 *
 * Cron (cPanel → Cron Jobs):
 *   0 6 * * *  php /home/TUUSUARIO/public_html/scripts/import_jobs.php
 */

// ── Bootstrapping ────────────────────────────────────────────────────────────
define('IS_CLI', PHP_SAPI === 'cli');
define('DRY_RUN', in_array('--dry-run', $argv ?? []));

$scriptArg = '';
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--source=')) $scriptArg = substr($a, 9);
}

if (IS_CLI) {
    chdir(dirname(__DIR__));
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// ── Obtener ID del bot ───────────────────────────────────────────────────────
$botId = (int)$pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
if (!$botId) {
    die("[ERROR] Usuario bot no encontrado. Ejecuta la app una vez para inicializarlo.\n");
}

// ── Configuración de fuentes ─────────────────────────────────────────────────
//
// FUENTES PRINCIPALES: Indeed Costa Rica (gratis, sin API key)
// URL patrón: https://cr.indeed.com/jobs?q=TÉRMINO&l=Costa+Rica&rss=1
//
// FUENTES OPCIONALES (--source=remote): empleos remotos internacionales
// Solo se activan con --source=remote para no mezclar con los nacionales.

$indeedQueries = [
    // Tecnología
    'programacion'           => 'EMP:Technology',
    'desarrollador'          => 'EMP:Technology',
    'soporte tecnico'        => 'EMP:Technology',
    'sistemas'               => 'EMP:Technology',
    // Administración
    'administracion'         => 'EMP:Administration',
    'asistente administrativo'=> 'EMP:Administration',
    'recursos humanos'       => 'EMP:Administration',
    'contabilidad'           => 'EMP:Administration',
    'finanzas'               => 'EMP:Administration',
    // Ventas y Marketing
    'ventas'                 => 'EMP:Sales',
    'ejecutivo de ventas'    => 'EMP:Sales',
    'marketing'              => 'EMP:Sales',
    // Salud
    'salud'                  => 'EMP:Health',
    'enfermeria'             => 'EMP:Health',
    'medico'                 => 'EMP:Health',
    // Educación
    'educacion'              => 'EMP:Education',
    'maestro'                => 'EMP:Education',
    'profesor'               => 'EMP:Education',
    // Construcción / Manufactura
    'construccion'           => 'EMP:Construction',
    'operario'               => 'EMP:Construction',
    // Turismo / Hotelería
    'turismo'                => 'EMP:Hospitality',
    'mesero'                 => 'EMP:Hospitality',
    'recepcionista'          => 'EMP:Hospitality',
    // Transporte / Logística
    'conductor'              => 'EMP:Transport',
    'transporte'             => 'EMP:Transport',
    'logistica'              => 'EMP:Transport',
    // Atención al cliente
    'atencion al cliente'    => 'EMP:Customer Service',
    'call center'            => 'EMP:Customer Service',
    'servicio al cliente'    => 'EMP:Customer Service',
    // Legal / Jurídico
    'abogado'                => 'EMP:Legal',
    'derecho'                => 'EMP:Legal',
];

$sources = [];

// Indeed Costa Rica — DESHABILITADO: devuelve 403 (Cloudflare)
// if ($scriptArg === '' || $scriptArg === 'indeed') { ... }

// Empleos remotos internacionales — activo por defecto o con --source=remote
if ($scriptArg === '' || $scriptArg === 'remote') {
    $sources[] = [
        'name'     => 'arbeitnow',
        'label'    => 'Arbeitnow — Remote Jobs',
        'type'     => 'arbeitnow_api',
        'url'      => 'https://www.arbeitnow.com/api/job-board-api',
        'category' => 'EMP:Technology',
    ];
    $sources[] = [
        'name'     => 'remotive',
        'label'    => 'Remotive — Remote Jobs',
        'type'     => 'remotive_api',
        'url'      => 'https://remotive.com/api/remote-jobs?limit=50',
        'category' => 'EMP:Technology',
    ];
    $sources[] = [
        'name'     => 'jobicy',
        'label'    => 'Jobicy — Remote Jobs',
        'type'     => 'jobicy_api',
        'url'      => 'https://jobicy.com/api/v2/remote-jobs?count=50',
        'category' => 'EMP:Technology',
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function log_msg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    if (function_exists('ob_get_level') && ob_get_level() === 0) flush();
    $logFile = __DIR__ . '/../logs/import_jobs.log';
    $logDir  = dirname($logFile);
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function fetch_url(string $url, bool $json = false): string|false {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; CompraTicaBot/1.0; +https://compratica.com)',
            CURLOPT_HTTPHEADER     => [
                'Accept: ' . ($json ? 'application/json' : 'application/rss+xml, application/xml, text/xml'),
            ],
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($err) { log_msg("  cURL error: $err"); return false; }
        return $result ?: false;
    }
    // Fallback file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 20,
            'header'  => 'User-Agent: Mozilla/5.0 (compatible; CompraTicaBot/1.0; +https://compratica.com)',
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function clean_html(string $s): string {
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $s));
}

function truncate(string $s, int $max): string {
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 3) . '...' : $s;
}

function guess_province(string $location): string {
    $map = [
        'san josé'    => 'San José',
        'san jose'    => 'San José',
        'alajuela'    => 'Alajuela',
        'heredia'     => 'Heredia',
        'cartago'     => 'Cartago',
        'guanacaste'  => 'Guanacaste',
        'puntarenas'  => 'Puntarenas',
        'limón'       => 'Limón',
        'limon'       => 'Limón',
        'escazú'      => 'San José',
        'escazu'      => 'San José',
        'desamparados'=> 'San José',
        'santa ana'   => 'San José',
        'la sabana'   => 'San José',
    ];
    $lower = mb_strtolower($location);
    foreach ($map as $k => $v) {
        if (str_contains($lower, $k)) return $v;
    }
    return '';
}

function insert_job(PDO $pdo, int $botId, array $job): string {
    // Dedup por source_url
    $exists = $pdo->prepare("SELECT id FROM job_listings WHERE source_url = ? LIMIT 1");
    $exists->execute([$job['source_url']]);
    if ($exists->fetchColumn()) return 'skipped';

    $now      = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d', strtotime('+30 days'));

    // Retry logic para errores de SQLite (disk I/O, database locked, etc)
    $maxRetries = 3;
    $attempt = 0;
    $lastError = null;

    while ($attempt < $maxRetries) {
        try {
            $pdo->prepare("
                INSERT INTO job_listings (
                    employer_id, listing_type, title, description, category,
                    job_type, location, province, remote_allowed,
                    contact_name, contact_email, application_url,
                    is_active, is_featured, payment_status,
                    start_date, end_date,
                    import_source, source_url,
                    company_name,
                    created_at, updated_at
                ) VALUES (
                    ?, 'job', ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    1, 0, 'free',
                    datetime('now'), ?,
                    ?, ?,
                    ?,
                    ?, ?
                )
            ")->execute([
                $botId,
                truncate($job['title'], 200),
                $job['description'] ?: 'Consultar en la fuente original.',
                $job['category'],
                $job['job_type'] ?? 'full-time',
                truncate($job['location'] ?? '', 200),
                $job['province'] ?? '',
                $job['remote'] ? 1 : 0,
                truncate($job['company'] ?? '', 200),
                $job['email'] ?? '',
                $job['url'] ?? '',
                $end_date,
                $job['source'],
                $job['source_url'],
                truncate($job['company'] ?? '', 200),
                $now,
                $now,
            ]);
            return 'inserted';
        } catch (PDOException $e) {
            $lastError = $e;
            $errorMsg = $e->getMessage();

            // Si es error de disco o DB bloqueada, reintentar
            if (stripos($errorMsg, 'disk I/O') !== false ||
                stripos($errorMsg, 'database is locked') !== false ||
                stripos($errorMsg, 'SQLITE_BUSY') !== false) {
                $attempt++;
                if ($attempt < $maxRetries) {
                    usleep(100000 * $attempt); // 100ms, 200ms, 300ms
                    continue;
                }
            }
            // Otro error, lanzar inmediatamente
            throw $e;
        }
    }

    // Si llegamos aquí, fallaron todos los reintentos
    if ($lastError) throw $lastError;
    return 'error';
}

// ── Parsers ──────────────────────────────────────────────────────────────────

function parse_indeed_rss(string $xml, string $category, string $source): array {
    $jobs = [];
    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if (!$feed) return [];

    foreach ($feed->channel->item ?? [] as $item) {
        $title   = clean_html((string)$item->title);
        $desc    = clean_html((string)$item->description);
        $link    = (string)$item->link;
        $company = '';
        // Indeed pone "Título - Empresa" en el title
        if (str_contains($title, ' - ')) {
            $parts   = explode(' - ', $title, 2);
            $title   = trim($parts[0]);
            $company = trim($parts[1] ?? '');
        }
        $location = clean_html((string)($item->children('georss', true)->point ?? ''));
        $location = $location ?: clean_html((string)($item->children('indeed', true)->company ?? ''));
        // Extract location from description
        if (preg_match('/location:\s*([^\n<]+)/i', $desc, $m)) {
            $location = trim($m[1]);
        }
        if (!$title || !$link) continue;
        $jobs[] = [
            'title'       => $title,
            'description' => truncate($desc, 2000),
            'category'    => $category,
            'company'     => $company,
            'location'    => $location ?: 'Costa Rica',
            'province'    => guess_province($location),
            'url'         => $link,
            'source_url'  => $link,
            'source'      => $source,
            'remote'      => false,
            'job_type'    => 'full-time',
        ];
    }
    return $jobs;
}

function parse_arbeitnow(string $json, string $source): array {
    $data = json_decode($json, true);
    $jobs = [];
    foreach ($data['data'] ?? [] as $item) {
        if (!($item['title'] ?? '') || !($item['url'] ?? '')) continue;
        $jobs[] = [
            'title'       => truncate($item['title'], 200),
            'description' => truncate(clean_html($item['description'] ?? ''), 2000),
            'category'    => 'EMP:Technology',
            'company'     => $item['company_name'] ?? '',
            'location'    => $item['location'] ?? 'Remoto',
            'province'    => '',
            'url'         => $item['url'],
            'source_url'  => $item['url'],
            'source'      => $source,
            'remote'      => true,
            'job_type'    => 'full-time',
        ];
    }
    return $jobs;
}

function parse_remotive(string $json, string $source): array {
    $data = json_decode($json, true);
    $jobs = [];
    foreach ($data['jobs'] ?? [] as $item) {
        if (!($item['title'] ?? '') || !($item['url'] ?? '')) continue;
        $jobs[] = [
            'title'       => truncate($item['title'], 200),
            'description' => truncate(clean_html($item['description'] ?? ''), 2000),
            'category'    => 'EMP:Technology',
            'company'     => $item['company_name'] ?? '',
            'location'    => $item['candidate_required_location'] ?? 'Remoto',
            'province'    => '',
            'url'         => $item['url'],
            'source_url'  => $item['url'],
            'source'      => $source,
            'remote'      => true,
            'job_type'    => match(strtolower($item['job_type'] ?? '')) {
                'contract' => 'contract',
                'part_time', 'part-time' => 'part-time',
                'freelance' => 'freelance',
                default => 'full-time',
            },
        ];
    }
    return $jobs;
}

function parse_jobicy(string $json, string $source): array {
    $data = json_decode($json, true);
    $jobs = [];
    foreach ($data['jobs'] ?? [] as $item) {
        if (!($item['jobTitle'] ?? '') || !($item['url'] ?? '')) continue;
        $jobs[] = [
            'title'       => truncate($item['jobTitle'], 200),
            'description' => truncate(clean_html($item['jobDescription'] ?? ''), 2000),
            'category'    => 'EMP:Technology',
            'company'     => $item['companyName'] ?? '',
            'location'    => $item['jobGeo'] ?? 'Remoto',
            'province'    => '',
            'url'         => $item['url'],
            'source_url'  => $item['url'],
            'source'      => $source,
            'remote'      => true,
            'job_type'    => 'full-time',
        ];
    }
    return $jobs;
}

// ── Auto-expirar importados viejos (>30 días) ────────────────────────────────
$expired = $pdo->exec("
    UPDATE job_listings
    SET is_active = 0
    WHERE import_source IS NOT NULL
      AND end_date < date('now')
      AND is_active = 1
");
if ($expired > 0) log_msg("Expirados: {$expired} empleos importados antiguos");

// ── Procesar fuentes ─────────────────────────────────────────────────────────
$totalInserted = 0;
$totalSkipped  = 0;
$totalErrors   = 0;

foreach ($sources as $src) {
    log_msg("Iniciando: {$src['label']}");

    // Insertar log de inicio
    $pdo->prepare("INSERT INTO job_import_log (source, started_at) VALUES (?, datetime('now'))")
        ->execute([$src['label']]);
    $logId = (int)$pdo->lastInsertId();

    $raw = fetch_url($src['url'], str_contains($src['type'], 'api'));
    if ($raw === false) {
        log_msg("  ERROR: No se pudo descargar {$src['url']}");
        $pdo->prepare("UPDATE job_import_log SET finished_at=datetime('now'), errors=1, message=? WHERE id=?")
            ->execute(['Fallo al descargar URL', $logId]);
        $totalErrors++;
        continue;
    }

    $jobs = match($src['type']) {
        'indeed_rss'   => parse_indeed_rss($raw,  $src['category'], $src['name']),
        'arbeitnow_api'=> parse_arbeitnow($raw,   $src['name']),
        'remotive_api' => parse_remotive($raw,    $src['name']),
        'jobicy_api'   => parse_jobicy($raw,      $src['name']),
        default        => [],
    };

    $ins = 0; $skip = 0;

    // Usar transacciones para mejorar performance y reducir locks
    $pdo->beginTransaction();
    $batchCount = 0;

    foreach ($jobs as $job) {
        if (DRY_RUN) {
            log_msg("  [DRY] " . $job['title'] . ' — ' . $job['company']);
            continue;
        }

        try {
            $result = insert_job($pdo, $botId, $job);
            if ($result === 'inserted') { $ins++;  $totalInserted++; }
            else                        { $skip++; $totalSkipped++;  }

            $batchCount++;

            // Commit cada 25 empleos para evitar transacciones muy largas
            if ($batchCount >= 25) {
                $pdo->commit();
                usleep(50000); // 50ms de pausa
                $pdo->beginTransaction();
                $batchCount = 0;
            }
        } catch (Exception $e) {
            log_msg("  ERROR insertando: " . $e->getMessage());
            $totalErrors++;
        }
    }

    // Commit final
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    log_msg("  {$src['label']}: +{$ins} nuevos, {$skip} duplicados");
    $pdo->prepare("UPDATE job_import_log SET finished_at=datetime('now'), inserted=?, skipped=? WHERE id=?")
        ->execute([$ins, $skip, $logId]);

    // Pausa cortés entre requests
    sleep(1);
}

log_msg("=== TOTAL: +{$totalInserted} insertados | {$totalSkipped} duplicados | {$totalErrors} errores ===");

// Auto-categorizar empleos importados
if ($totalInserted > 0) {
    log_msg("Categorizando empleos importados...");

    // Ejecutar categorización inline
    $categorizeScript = __DIR__ . '/categorize_imported_jobs.php';
    if (file_exists($categorizeScript)) {
        ob_start();
        include $categorizeScript;
        $output = ob_get_clean();
        log_msg("Categorización completada");
    }
}
