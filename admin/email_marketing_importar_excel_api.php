<?php
/**
 * admin/email_marketing_importar_excel_api.php
 * API para: previsualizar Excel, importar por lotes, listar/eliminar contactos, gestionar tipos
 */
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Log helper
$_LOG_FILE = __DIR__ . '/logs/importar_excel_api.log';
function apiLog(string $msg, array $ctx = []): void {
    global $_LOG_FILE;
    $line = date('Y-m-d H:i:s') . ' ' . $msg;
    if ($ctx) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    @file_put_contents($_LOG_FILE, $line . "\n", FILE_APPEND);
}
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) {
    apiLog("PHP_ERROR [$errno]", ['msg'=>$errstr,'file'=>basename($errfile),'line'=>$errline]);
    return false;
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        apiLog("FATAL", ['msg'=>$e['message'],'file'=>basename($e['file']),'line'=>$e['line']]);
    }
});

// Enviar header JSON ANTES de cualquier include para evitar HTML contaminando la respuesta
header('Content-Type: application/json; charset=utf-8');

apiLog("REQUEST", ['action'=>$_POST['action']??$_GET['action']??'?','ip'=>$_SERVER['REMOTE_ADDR']??'']);

require_once __DIR__ . '/../includes/config.php';

// Verificar autenticación de admin (igual que email_marketing_api.php)
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    apiLog("AUTH_FAIL", ['session_keys'=>array_keys($_SESSION)]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}
apiLog("AUTH_OK");

$config   = require __DIR__ . '/../config/database.php';
$pdo      = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Debug: ver raw primera línea del CSV ─────────────────────
        case 'debug_csv':
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error al subir el archivo.');
            }
            $handle = fopen($file['tmp_name'], 'r');
            $raw = fread($handle, 500);
            fclose($handle);
            $commas    = substr_count(strtok($raw, "\n"), ',');
            $semis     = substr_count(strtok($raw, "\n"), ';');
            $tabs      = substr_count(strtok($raw, "\n"), "\t");
            $detectedSep = $semis >= $commas ? ';' : ($tabs >= $commas ? 'TAB' : ',');
            echo json_encode([
                'ok'          => true,
                'first_500'   => mb_substr($raw, 0, 200),
                'commas'      => $commas,
                'semicolons'  => $semis,
                'tabs'        => $tabs,
                'detected_sep'=> $detectedSep,
            ]);
            break;

        // ── Previsualizar archivo (guarda en servidor, devuelve solo preview) ──
        case 'preview':
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error al subir el archivo.');
            }
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls','csv','ods'], true)) {
                throw new RuntimeException('Formato no soportado. Usá .xlsx, .xls, .csv u .ods');
            }

            $tmpDir = dirname(__DIR__) . '/uploads/import_tmp';
            if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);
            // Limpiar archivos viejos (> 2h)
            foreach (glob($tmpDir . '/file_*') as $old) {
                if (filemtime($old) < time() - 7200) @unlink($old);
            }

            $fileId   = bin2hex(random_bytes(12));
            $savedPath = $tmpDir . '/file_' . $fileId . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $savedPath);

            // Para CSV: contar filas y leer preview sin cargar todo en RAM
            if ($ext === 'csv') {
                $handle   = fopen($savedPath, 'r');
                $firstLine = fgets($handle); rewind($handle);
                $sep      = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

                $headers  = [];
                $preview  = [];
                $total    = 0;
                $first    = true;
                while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                    if ($first) { $headers = $row; $first = false; continue; }
                    if (!array_filter($row)) continue;
                    if (count($preview) < 5) $preview[] = $row;
                    $total++;
                }
                fclose($handle);
                // Guardar metadata (sep + headers)
                file_put_contents($tmpDir . '/meta_' . $fileId . '.json', json_encode(['sep'=>$sep,'headers'=>$headers,'ext'=>$ext,'total'=>$total]));
            } else {
                // Para xlsx/ods: cargar en memoria (PhpSpreadsheet requerido)
                [$headers, $rows] = parseFile($savedPath, $ext);
                $total   = count($rows);
                $preview = array_slice($rows, 0, 5);
                // Guardar todas las filas en JSON (xlsx suele ser más pequeño)
                file_put_contents($tmpDir . '/meta_' . $fileId . '.json', json_encode(['sep'=>null,'headers'=>$headers,'ext'=>$ext,'total'=>$total]));
                file_put_contents($tmpDir . '/rows_' . $fileId . '.json', json_encode($rows));
            }

            echo json_encode([
                'ok'         => true,
                'file_id'    => $fileId,
                'headers'    => $headers,
                'preview'    => $preview,
                'total_rows' => $total,
            ]);
            break;

        // ── Importar lote (lee del archivo guardado en servidor) ───────
        case 'import_batch':
            $fileId  = preg_replace('/[^a-f0-9]/', '', $_POST['file_id'] ?? '');
            $offset  = (int)($_POST['offset']  ?? 0);
            $limit   = (int)($_POST['limit']   ?? 500);
            $colMap  = json_decode($_POST['col_map'] ?? '{}', true);
            $tipoId  = (int)($_POST['tipo_correo_id'] ?? 0);
            $skipDup = ($_POST['skip_dup'] ?? '1') === '1';

            if (!$fileId || empty($colMap)) {
                echo json_encode(['ok'=>true,'imported'=>0,'skipped'=>0,'errors'=>0,'done'=>true]);
                break;
            }

            $tmpDir  = dirname(__DIR__) . '/uploads/import_tmp';
            $metaPath = $tmpDir . '/meta_' . $fileId . '.json';
            if (!file_exists($metaPath)) {
                throw new RuntimeException('Sesión expirada. Volvé a subir el archivo.');
            }
            $meta = json_decode(file_get_contents($metaPath), true);
            $total = (int)$meta['total'];

            if ($meta['ext'] === 'csv') {
                // Leer CSV por offset sin cargar todo en RAM
                $savedPath = $tmpDir . '/file_' . $fileId . '.csv';
                $handle    = fopen($savedPath, 'r');
                $sep       = $meta['sep'];
                $rows      = [];
                $lineNum   = -1; // -1 = header
                while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                    if ($lineNum === -1) { $lineNum = 0; continue; } // skip header
                    if (!array_filter($row)) continue;
                    if ($lineNum < $offset) { $lineNum++; continue; }
                    if (count($rows) >= $limit) break;
                    $rows[] = $row;
                    $lineNum++;
                }
                fclose($handle);
            } else {
                $rowsPath = $tmpDir . '/rows_' . $fileId . '.json';
                $allRows  = json_decode(file_get_contents($rowsPath), true);
                $rows     = array_slice($allRows, $offset, $limit);
            }

            $done = ($offset + $limit) >= $total;

            $imported = $skipped = $errors = 0;
            $now = date('Y-m-d H:i:s');

            $stmtCheck = $pdo->prepare("SELECT id FROM importa_excel WHERE correo = ? LIMIT 1");
            $stmtIns   = $pdo->prepare("
                INSERT INTO importa_excel
                    (cedula, nombre, correo, telefono, direccion, tipo_correo_id, fecha_ingreso)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            // Debug: muestra info del primer row si offset=0
            $debugInfo = null;
            if ($offset === 0 && !empty($rows)) {
                $firstRow = $rows[0];
                $correoIdx = $colMap['correo'] ?? null;
                $debugInfo = [
                    'first_row'   => $firstRow,
                    'col_map'     => $colMap,
                    'correo_idx'  => $correoIdx,
                    'correo_val'  => $correoIdx !== null ? ($firstRow[$correoIdx] ?? 'INDEX_NOT_FOUND') : 'NO_MAPPING',
                    'sep_used'    => $meta['sep'] ?? 'NULL',
                    'row_count'   => count($rows),
                    'total_file'  => $total,
                ];
            }

            foreach ($rows as $row) {
                try {
                    $correo = isset($colMap['correo']) ? trim((string)($row[$colMap['correo']] ?? '')) : '';
                    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $errors++; continue; }

                    if ($skipDup) {
                        $stmtCheck->execute([$correo]);
                        if ($stmtCheck->fetchColumn()) { $skipped++; continue; }
                    }

                    $stmtIns->execute([
                        isset($colMap['cedula'])    ? trim((string)($row[$colMap['cedula']]    ?? '')) : null,
                        isset($colMap['nombre'])    ? trim((string)($row[$colMap['nombre']]    ?? '')) : null,
                        $correo,
                        isset($colMap['telefono'])  ? trim((string)($row[$colMap['telefono']]  ?? '')) : null,
                        isset($colMap['direccion']) ? trim((string)($row[$colMap['direccion']] ?? '')) : null,
                        $tipoId ?: null,
                        $now,
                    ]);
                    $imported++;
                } catch (Throwable $e) {
                    $errors++;
                }
            }

            // Borrar archivos temporales si terminamos
            if ($done) {
                foreach (glob($tmpDir . '/*_' . $fileId . '.*') as $f) @unlink($f);
            }

            echo json_encode(['ok'=>true,'imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors,'done'=>$done,'total'=>$total,'debug'=>$debugInfo]);
            break;

        // ── Listar contactos ───────────────────────────────────────────
        case 'list':
            $tipo  = (int)($_POST['tipo'] ?? 0);
            $q     = '%' . trim($_POST['q'] ?? '') . '%';
            $where = ['1=1'];
            $params = [];

            if ($tipo > 0) { $where[] = 'i.tipo_correo_id = ?'; $params[] = $tipo; }
            if (trim($_POST['q'] ?? '') !== '') {
                $where[] = '(i.nombre LIKE ? OR i.correo LIKE ? OR i.cedula LIKE ?)';
                $params  = array_merge($params, [$q, $q, $q]);
            }

            $sql   = "SELECT i.*, t.nombre AS tipo_nombre
                      FROM importa_excel i
                      LEFT JOIN tipos_correo t ON t.id = i.tipo_correo_id
                      WHERE " . implode(' AND ', $where) . "
                      ORDER BY i.fecha_ingreso DESC LIMIT 200";
            $stmt  = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows  = $stmt->fetchAll();

            $cntSql = "SELECT COUNT(*) FROM importa_excel i WHERE " . implode(' AND ', $where);
            $stmtC  = $pdo->prepare($cntSql);
            $stmtC->execute($params);
            $total  = (int)$stmtC->fetchColumn();

            echo json_encode(['ok'=>true,'rows'=>$rows,'total'=>$total]);
            break;

        // ── Eliminar uno ───────────────────────────────────────────────
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM importa_excel WHERE id = ?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;

        // ── Eliminar varios ────────────────────────────────────────────
        case 'delete_many':
            $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM importa_excel WHERE id IN ($ph)")->execute($ids);
            }
            echo json_encode(['ok'=>true]);
            break;

        // ── Agregar tipo ───────────────────────────────────────────────
        case 'add_tipo':
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') throw new RuntimeException('El nombre no puede estar vacío.');
            $pdo->prepare("INSERT INTO tipos_correo (nombre) VALUES (?)")->execute([$nombre]);
            echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
            break;

        // ── Eliminar tipo ──────────────────────────────────────────────
        case 'delete_tipo':
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE importa_excel SET tipo_correo_id = NULL WHERE tipo_correo_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM tipos_correo WHERE id = ?")->execute([$id]);
            echo json_encode(['ok'=>true]);
            break;

        // ── Lista tipos (para campaña) ─────────────────────────────────
        case 'get_tipos':
            $tipos = $pdo->query("
                SELECT t.id, t.nombre,
                       COUNT(i.id) AS total
                FROM tipos_correo t
                LEFT JOIN importa_excel i ON i.tipo_correo_id = t.id
                GROUP BY t.id, t.nombre
                ORDER BY t.nombre
            ")->fetchAll();
            echo json_encode(['ok'=>true,'tipos'=>$tipos]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Acción no válida']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}

// ──────────────────────────────────────────────────────────────────────
// Parsear archivo Excel/CSV/ODS → [headers[], rows[]]
// ──────────────────────────────────────────────────────────────────────
function parseFile(string $path, string $ext): array
{
    if ($ext === 'csv') {
        return parseCSV($path);
    }

    // xlsx, xls, ods → PhpSpreadsheet
    $autoloadPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
    ];
    foreach ($autoloadPaths as $p) {
        if (file_exists($p)) { require_once $p; break; }
    }

    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        if ($ext === 'csv') return parseCSV($path);
        throw new RuntimeException('PhpSpreadsheet no está instalado. Usá formato CSV.');
    }

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet       = $spreadsheet->getActiveSheet();
    $all         = $sheet->toArray(null, true, true, false);

    if (empty($all)) return [[], []];

    $headers = array_map('strval', array_shift($all));
    $rows    = [];
    foreach ($all as $row) {
        // Omitir filas completamente vacías
        if (!array_filter($row, fn($v) => $v !== null && $v !== '')) continue;
        $rows[] = array_values($row);
    }

    return [$headers, $rows];
}

function parseCSV(string $path): array
{
    $headers = [];
    $rows    = [];
    $handle  = fopen($path, 'r');
    if (!$handle) throw new RuntimeException('No se pudo leer el archivo CSV.');

    // Auto-detectar separador: leer primera línea y contar comas vs punto y coma
    $firstLine = fgets($handle);
    rewind($handle);
    $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

    $first = true;
    while (($row = fgetcsv($handle, 0, $sep)) !== false) {
        if ($first) { $headers = $row; $first = false; continue; }
        if (!array_filter($row)) continue;
        $rows[] = $row;
    }
    fclose($handle);
    return [$headers, $rows];
}
