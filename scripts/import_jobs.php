#!/usr/bin/env php
<?php
/**
 * Script de Importación de Empleos desde APIs Externas
 *
 * Este script se ejecuta via CRON cada 6 horas (6am y 6pm)
 * Importa empleos de Costa Rica desde APIs gratuitas disponibles
 *
 * Cron configurado:
 * 0 6,18 * * * php /home/comprati/public_html/scripts/import_jobs.php >> /home/marugaul/public_html/logs/import_jobs.log 2>&1
 */

// Configuración de errores y log
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Definir constantes de rutas
define('ROOT_PATH', dirname(__DIR__));
define('LOG_PATH', ROOT_PATH . '/logs');
define('LOG_FILE', LOG_PATH . '/import_jobs.log');

// Crear directorio de logs si no existe
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Función de logging
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    // Escribir en consola
    echo $logEntry;

    // Escribir en archivo de log
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// Función para manejar errores
function handleError($message) {
    logMessage($message, 'ERROR');
}

// Inicio del script
logMessage("==========================================================");
logMessage("Iniciando importación de empleos de Costa Rica");
logMessage("==========================================================");

try {
    // Incluir archivos necesarios
    require_once ROOT_PATH . '/includes/db.php';
    require_once ROOT_PATH . '/includes/config.php';

    logMessage("Archivos de configuración cargados correctamente");

    // Obtener conexión a base de datos
    $pdo = db();
    logMessage("Conexión a base de datos establecida");

    // Verificar que la tabla job_listings existe
    $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='job_listings'");
    if (!$tableCheck->fetch()) {
        throw new Exception("La tabla 'job_listings' no existe en la base de datos");
    }
    logMessage("Tabla 'job_listings' verificada");

    // Verificar/crear employer genérico para importaciones
    $importerEmail = 'importador@compratica.com';
    $stmt = $pdo->prepare("SELECT id FROM jobs_employers WHERE email = ?");
    $stmt->execute([$importerEmail]);
    $employer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employer) {
        logMessage("Creando empleador genérico para importaciones...");
        $stmt = $pdo->prepare("
            INSERT INTO jobs_employers (name, email, phone, password_hash, company_name, company_description, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Sistema de Importación',
            $importerEmail,
            '0000-0000',
            password_hash('importador123', PASSWORD_DEFAULT),
            'Compratica - Importador Automático',
            'Cuenta automática para importar empleos desde fuentes externas',
            1
        ]);
        $employerId = $pdo->lastInsertId();
        logMessage("Empleador genérico creado con ID: $employerId");
    } else {
        $employerId = $employer['id'];
        logMessage("Usando empleador existente ID: $employerId");
    }

    // ====================================================================
    // IMPORTACIÓN DESDE FUENTES DISPONIBLES
    // ====================================================================

    $totalImported = 0;
    $totalErrors = 0;

    // OPCIÓN 1: Importar desde RSS/Feeds (si están disponibles)
    logMessage("Buscando empleos en fuentes RSS...");

    // Lista de fuentes RSS de empleos en Costa Rica
    $rssSources = [
        'https://www.tecoloco.co.cr/rss/ofertas-empleo.xml',
        'https://cr.computrabajo.com/rss/trabajo.xml',
    ];

    foreach ($rssSources as $source) {
        try {
            logMessage("Intentando obtener empleos desde: $source");

            // Configurar contexto con user agent
            $context = stream_context_create([
                'http' => [
                    'user_agent' => 'Mozilla/5.0 (compatible; CompraticaBot/1.0)',
                    'timeout' => 10
                ]
            ]);

            // Intentar cargar el RSS
            $rssContent = @file_get_contents($source, false, $context);

            if ($rssContent === false) {
                logMessage("No se pudo acceder a: $source", 'WARNING');
                continue;
            }

            // Parsear XML
            $xml = @simplexml_load_string($rssContent);

            if ($xml === false) {
                logMessage("No se pudo parsear XML de: $source", 'WARNING');
                continue;
            }

            $jobsCount = 0;

            // Procesar items del RSS
            foreach ($xml->channel->item as $item) {
                try {
                    // Extraer información del empleo
                    $title = (string)$item->title;
                    $description = (string)$item->description;
                    $link = (string)$item->link;
                    $pubDate = (string)$item->pubDate;

                    // Limpiar descripción HTML
                    $description = strip_tags($description);
                    $description = trim($description);

                    if (strlen($description) > 5000) {
                        $description = substr($description, 0, 4997) . '...';
                    }

                    // Verificar si ya existe este empleo (por título y URL)
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM job_listings
                        WHERE title = ? AND application_url = ?
                        LIMIT 1
                    ");
                    $checkStmt->execute([$title, $link]);

                    if ($checkStmt->fetch()) {
                        logMessage("Empleo ya existe (omitiendo): $title", 'DEBUG');
                        continue;
                    }

                    // Insertar nuevo empleo
                    $insertStmt = $pdo->prepare("
                        INSERT INTO job_listings (
                            employer_id, listing_type, title, description,
                            category, location, province, application_url,
                            is_active, is_featured, start_date, end_date,
                            created_at, updated_at
                        ) VALUES (
                            ?, 'job', ?, ?,
                            'EMP: General', 'Costa Rica', 'San José', ?,
                            1, 0, datetime('now'), datetime('now', '+30 days'),
                            datetime('now'), datetime('now')
                        )
                    ");

                    $insertStmt->execute([
                        $employerId,
                        $title,
                        $description,
                        $link
                    ]);

                    $jobsCount++;
                    $totalImported++;

                    logMessage("Empleo importado: $title");

                } catch (Exception $e) {
                    $totalErrors++;
                    handleError("Error al importar empleo individual: " . $e->getMessage());
                }
            }

            logMessage("Importados $jobsCount empleos desde: $source");

        } catch (Exception $e) {
            $totalErrors++;
            handleError("Error al procesar fuente $source: " . $e->getMessage());
        }
    }

    // OPCIÓN 2: Importar desde scraping básico de ANE (Agencia Nacional de Empleo)
    // NOTA: Esta sección está comentada por defecto para evitar bloqueos
    // Descomentar solo si se tiene permiso explícito para scraping

    /*
    logMessage("Intentando obtener empleos de ANE Costa Rica...");
    try {
        $aneUrl = 'https://www.ane.cr/Puesto';
        $context = stream_context_create([
            'http' => [
                'user_agent' => 'Mozilla/5.0 (compatible; CompraticaBot/1.0)',
                'timeout' => 15
            ]
        ]);

        $html = @file_get_contents($aneUrl, false, $context);

        if ($html !== false) {
            // Aquí iría la lógica de scraping
            // Por ahora solo log
            logMessage("ANE: Scraping no implementado aún", 'INFO');
        }
    } catch (Exception $e) {
        handleError("Error al acceder a ANE: " . $e->getMessage());
    }
    */

    // ====================================================================
    // RESUMEN DE IMPORTACIÓN
    // ====================================================================

    logMessage("==========================================================");
    logMessage("RESUMEN DE IMPORTACIÓN");
    logMessage("Total empleos importados: $totalImported");
    logMessage("Total errores: $totalErrors");
    logMessage("==========================================================");

    // Limpiar empleos vencidos
    logMessage("Limpiando empleos vencidos...");
    $cleanupStmt = $pdo->prepare("
        UPDATE job_listings
        SET is_active = 0
        WHERE end_date < datetime('now')
        AND is_active = 1
        AND employer_id = ?
    ");
    $cleanupStmt->execute([$employerId]);
    $cleaned = $cleanupStmt->rowCount();
    logMessage("Empleos desactivados por vencimiento: $cleaned");

    // Estadísticas finales
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN listing_type = 'job' THEN 1 ELSE 0 END) as empleos
        FROM job_listings
        WHERE employer_id = $employerId
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    logMessage("Estadísticas totales:");
    logMessage("  - Total en BD: {$stats['total']}");
    logMessage("  - Activos: {$stats['activos']}");
    logMessage("  - Empleos: {$stats['empleos']}");

    logMessage("==========================================================");
    logMessage("Importación finalizada exitosamente");
    logMessage("==========================================================");

    exit(0);

} catch (Exception $e) {
    handleError("ERROR CRÍTICO: " . $e->getMessage());
    handleError("Stack trace: " . $e->getTraceAsString());
    logMessage("==========================================================");
    logMessage("Importación finalizada con ERRORES");
    logMessage("==========================================================");
    exit(1);
}
