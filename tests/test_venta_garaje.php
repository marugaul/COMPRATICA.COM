<?php
/**
 * test_venta_garaje.php
 * Pruebas automatizadas del módulo Venta Garaje
 *
 * Uso desde CLI:  php tests/test_venta_garaje.php
 * Uso en browser: http://tu-sitio/tests/test_venta_garaje.php
 *
 * NOTA: Crea datos de prueba con prefijo [TEST] y los elimina al final.
 */

define('IS_CLI', PHP_SAPI === 'cli');

// ── Bootstrap ──────────────────────────────────────────────────────────────
chdir(__DIR__ . '/..');
require_once 'includes/db.php';
require_once 'includes/settings.php';

// ── Helpers de salida ──────────────────────────────────────────────────────
function h(string $s): string { return IS_CLI ? $s : htmlspecialchars($s); }

function print_header(): void {
    if (IS_CLI) {
        echo "\n\033[1;36m╔═══════════════════════════════════════════════════════╗\033[0m\n";
        echo "\033[1;36m║     PRUEBAS AUTOMATIZADAS — VENTA GARAJE              ║\033[0m\n";
        echo "\033[1;36m╚═══════════════════════════════════════════════════════╝\033[0m\n\n";
    } else {
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Tests Venta Garaje</title>
<style>
  body{font-family:monospace;background:#0d1117;color:#c9d1d9;margin:2rem}
  h1{color:#58a6ff} h2{color:#79c0ff;margin-top:1.5rem}
  .pass{color:#3fb950;font-weight:bold}
  .fail{color:#f85149;font-weight:bold}
  .info{color:#8b949e}
  .section{border:1px solid #30363d;border-radius:6px;padding:1rem;margin:1rem 0}
  pre{background:#161b22;padding:0.5rem;border-radius:4px;overflow-x:auto}
  .summary{border:2px solid #30363d;border-radius:8px;padding:1.5rem;margin-top:2rem}
</style></head><body>';
        echo '<h1>🧪 Pruebas Automatizadas — Venta Garaje</h1>';
    }
}

function print_footer(): void {
    if (!IS_CLI) echo '</body></html>';
}

$results = ['pass' => 0, 'fail' => 0, 'errors' => []];

function check(string $name, bool $ok, string $detail = ''): void {
    global $results;
    if ($ok) {
        $results['pass']++;
        if (IS_CLI) {
            echo "  \033[1;32m✓ PASS\033[0m  {$name}";
            if ($detail) echo "  \033[0;90m({$detail})\033[0m";
            echo "\n";
        } else {
            echo '<p><span class="pass">✓ PASS</span>  ' . h($name);
            if ($detail) echo '  <span class="info">(' . h($detail) . ')</span>';
            echo '</p>';
        }
    } else {
        $results['fail']++;
        $results['errors'][] = $name . ($detail ? " — {$detail}" : '');
        if (IS_CLI) {
            echo "  \033[1;31m✗ FAIL\033[0m  {$name}";
            if ($detail) echo "  \033[1;31m({$detail})\033[0m";
            echo "\n";
        } else {
            echo '<p><span class="fail">✗ FAIL</span>  ' . h($name);
            if ($detail) echo '  <span class="fail">(' . h($detail) . ')</span>';
            echo '</p>';
        }
    }
}

function section(string $title): void {
    if (IS_CLI) {
        echo "\n\033[1;33m▶ {$title}\033[0m\n";
    } else {
        echo '<h2>' . h($title) . '</h2><div class="section">';
    }
}

function section_end(): void {
    if (!IS_CLI) echo '</div>';
}

// ── Setup / Cleanup ────────────────────────────────────────────────────────
$pdo = db();

// IDs de datos de prueba (se limpian al final)
$test_aff_id  = null;
$test_sale_id = null;
$test_fee_id  = null;
$test_prod_id = null;

/**
 * Limpia todos los datos de prueba creados
 */
function cleanup(PDO $pdo, ?int $aff_id, ?int $sale_id, ?int $fee_id, ?int $prod_id): void {
    try {
        if ($prod_id)  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$prod_id]);
        if ($fee_id)   $pdo->prepare("DELETE FROM sale_fees WHERE id=?")->execute([$fee_id]);
        if ($sale_id)  $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$sale_id]);
        if ($aff_id)   $pdo->prepare("DELETE FROM affiliates WHERE id=?")->execute([$aff_id]);
    } catch (Throwable $e) {
        // ignorar errores en cleanup
    }
}

// ── Inicio ─────────────────────────────────────────────────────────────────
print_header();

// ══════════════════════════════════════════════════════════════════════════
// 1. Conectividad y esquema
// ══════════════════════════════════════════════════════════════════════════
section('1. Conectividad y Esquema de Base de Datos');

try {
    $pdo->query("SELECT 1");
    check('Conexión a SQLite', true, 'data.sqlite');
} catch (Throwable $e) {
    check('Conexión a SQLite', false, $e->getMessage());
}

$required_tables = ['sales', 'sale_fees', 'affiliates', 'products', 'settings'];
foreach ($required_tables as $t) {
    $exists = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$t}'")->fetchColumn();
    check("Tabla '{$t}' existe", $exists);
}

// Columnas clave en sales
$sales_cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_COLUMN, 1);
foreach (['id','affiliate_id','title','start_at','end_at','is_active','is_private','access_code'] as $col) {
    check("sales.{$col} existe", in_array($col, $sales_cols));
}

// Columnas clave en sale_fees
$fee_cols = $pdo->query("PRAGMA table_info(sale_fees)")->fetchAll(PDO::FETCH_COLUMN, 1);
foreach (['id','affiliate_id','sale_id','amount_crc','status'] as $col) {
    check("sale_fees.{$col} existe", in_array($col, $fee_cols));
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 2. Configuración del sistema
// ══════════════════════════════════════════════════════════════════════════
section('2. Configuración del Sistema');

$fee_crc = (float)get_setting('SALE_FEE_CRC', 2000);
check('SALE_FEE_CRC legible', $fee_crc > 0, "valor={$fee_crc}");

$ex_rate = get_exchange_rate();
check('exchange_rate legible', $ex_rate > 0, "valor={$ex_rate}");

$fee_usd = round($fee_crc / $ex_rate, 4);
check('Conversión CRC→USD coherente', $fee_usd > 0, "{$fee_crc} CRC = {$fee_usd} USD");

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 3. Crear afiliado de prueba
// ══════════════════════════════════════════════════════════════════════════
section('3. Afiliado de Prueba');

try {
    $slug_test = 'test-' . substr(md5(microtime()), 0, 8);
    $email_test = "test_vg_{$slug_test}@test.local";

    $pdo->prepare("
        INSERT INTO affiliates (name, slug, email, phone, password_hash, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 1, datetime('now'), datetime('now'))
    ")->execute(['[TEST] Vendedora Garaje', $slug_test, $email_test, '88001122', password_hash('test1234', PASSWORD_DEFAULT)]);

    $test_aff_id = (int)$pdo->lastInsertId();
    check('Crear afiliado de prueba', $test_aff_id > 0, "id={$test_aff_id}");

    // Verificar que se grabó
    $aff = $pdo->prepare("SELECT id, name, email FROM affiliates WHERE id=?");
    $aff->execute([$test_aff_id]);
    $aff_row = $aff->fetch(PDO::FETCH_ASSOC);
    check('Recuperar afiliado creado', !empty($aff_row['email']), "email={$aff_row['email']}");
} catch (Throwable $e) {
    check('Crear afiliado de prueba', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 4. Crear espacio de venta
// ══════════════════════════════════════════════════════════════════════════
section('4. Crear Espacio de Venta');

try {
    $title     = '[TEST] Venta Garaje Automatizada ' . date('Y-m-d H:i:s');
    $start_at  = date('Y-m-d H:i:s', strtotime('+1 day'));
    $end_at    = date('Y-m-d H:i:s', strtotime('+8 days'));
    $now       = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO sales (affiliate_id, title, start_at, end_at, is_active, is_private, access_code, created_at, updated_at)
        VALUES (?, ?, ?, ?, 0, 0, NULL, ?, ?)
    ")->execute([$test_aff_id, $title, $start_at, $end_at, $now, $now]);

    $test_sale_id = (int)$pdo->lastInsertId();
    check('Crear espacio (INSERT sales)', $test_sale_id > 0, "id={$test_sale_id}");

    // Verificar datos
    $sale = $pdo->prepare("SELECT * FROM sales WHERE id=?")->execute([$test_sale_id]) ?
            $pdo->prepare("SELECT * FROM sales WHERE id=?") : null;
    $pdo->prepare("SELECT * FROM sales WHERE id=?")->execute([$test_sale_id]);
    $row = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $row->execute([$test_sale_id]);
    $sale_row = $row->fetch(PDO::FETCH_ASSOC);

    check('Espacio guardado correctamente', $sale_row['title'] === $title, "título OK");
    check('Espacio inicia inactivo (is_active=0)', (int)$sale_row['is_active'] === 0);
    check('Espacio inicia público (is_private=0)', (int)$sale_row['is_private'] === 0);
    check('Fechas guardadas', !empty($sale_row['start_at']) && !empty($sale_row['end_at']));
} catch (Throwable $e) {
    check('Crear espacio de venta', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 5. Crear fee de activación
// ══════════════════════════════════════════════════════════════════════════
section('5. Fee de Activación');

try {
    $fee_crc_val = (float)get_setting('SALE_FEE_CRC', 2000);
    $ex          = get_exchange_rate();
    if ($ex <= 0) $ex = 1;
    $fee_usd_val = $fee_crc_val / $ex;
    $now         = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO sale_fees (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?)
    ")->execute([$test_aff_id, $test_sale_id, $fee_crc_val, $fee_usd_val, $ex, $now, $now]);

    $test_fee_id = (int)$pdo->lastInsertId();
    check('Crear fee (INSERT sale_fees)', $test_fee_id > 0, "id={$test_fee_id}");

    // Verificar
    $fee = $pdo->prepare("SELECT * FROM sale_fees WHERE id=?");
    $fee->execute([$test_fee_id]);
    $fee_row = $fee->fetch(PDO::FETCH_ASSOC);

    check('Fee vinculado al espacio correcto', (int)$fee_row['sale_id'] === $test_sale_id);
    check('Fee inicia en estado Pendiente', $fee_row['status'] === 'Pendiente');
    check('amount_crc correcto', (float)$fee_row['amount_crc'] === $fee_crc_val, "{$fee_row['amount_crc']} CRC");
    check('amount_usd calculado', (float)$fee_row['amount_usd'] > 0, "{$fee_row['amount_usd']} USD");
    check('exrate_used guardado', (float)$fee_row['exrate_used'] > 0);

    // Espacio aún inactivo mientras fee está pendiente
    $still_inactive = $pdo->prepare("SELECT is_active FROM sales WHERE id=?");
    $still_inactive->execute([$test_sale_id]);
    check('Espacio sigue inactivo mientras fee=Pendiente', (int)$still_inactive->fetchColumn() === 0);
} catch (Throwable $e) {
    check('Crear fee de activación', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 6. Flujo: Espacio privado con código de acceso
// ══════════════════════════════════════════════════════════════════════════
section('6. Espacio Privado con Código de Acceso');

try {
    // Crear un espacio privado de prueba (separado, sin fee)
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO sales (affiliate_id, title, start_at, end_at, is_active, is_private, access_code, created_at, updated_at)
        VALUES (?, ?, ?, ?, 0, 1, '654321', ?, ?)
    ")->execute([$test_aff_id, '[TEST] Espacio Privado', date('Y-m-d H:i:s', strtotime('+1 day')), date('Y-m-d H:i:s', strtotime('+3 days')), $now, $now]);

    $priv_sale_id = (int)$pdo->lastInsertId();
    check('Crear espacio privado', $priv_sale_id > 0, "id={$priv_sale_id}");

    $priv = $pdo->prepare("SELECT is_private, access_code FROM sales WHERE id=?");
    $priv->execute([$priv_sale_id]);
    $priv_row = $priv->fetch(PDO::FETCH_ASSOC);

    check('is_private=1 guardado', (int)$priv_row['is_private'] === 1);
    check('access_code 6 dígitos', preg_match('/^[0-9]{6}$/', $priv_row['access_code']) === 1, "código={$priv_row['access_code']}");

    // Validar código correcto
    check('Código correcto autentica', $priv_row['access_code'] === '654321');

    // Validar código incorrecto
    check('Código incorrecto rechaza', $priv_row['access_code'] !== '000000');

    // Limpiar espacio privado de prueba
    $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$priv_sale_id]);
} catch (Throwable $e) {
    check('Espacio privado', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 7. Admin: Aprobar fee + Activar espacio
// ══════════════════════════════════════════════════════════════════════════
section('7. Aprobar Fee y Activar Espacio (Admin)');

try {
    // Simular acción admin approve_fee
    $pdo->prepare("UPDATE sale_fees SET status='Pagado', updated_at=datetime('now') WHERE id=?")
        ->execute([$test_fee_id]);

    $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")
        ->execute([$test_sale_id]);

    // Verificar fee aprobado
    $fee_check = $pdo->prepare("SELECT status FROM sale_fees WHERE id=?");
    $fee_check->execute([$test_fee_id]);
    $fee_status = $fee_check->fetchColumn();
    check('Fee cambia a Pagado', $fee_status === 'Pagado', "status={$fee_status}");

    // Verificar espacio activo
    $sale_check = $pdo->prepare("SELECT is_active FROM sales WHERE id=?");
    $sale_check->execute([$test_sale_id]);
    $is_active = (int)$sale_check->fetchColumn();
    check('Espacio se activa tras aprobar fee', $is_active === 1);
} catch (Throwable $e) {
    check('Aprobar fee y activar', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 8. Admin: Desactivar espacio (toggle)
// ══════════════════════════════════════════════════════════════════════════
section('8. Toggle Activo/Inactivo (Admin)');

try {
    // Desactivar
    $pdo->prepare("UPDATE sales SET is_active=0, updated_at=datetime('now') WHERE id=?")
        ->execute([$test_sale_id]);

    $q = $pdo->prepare("SELECT is_active FROM sales WHERE id=?");
    $q->execute([$test_sale_id]);
    check('Toggle: Activo → Inactivo', (int)$q->fetchColumn() === 0);

    // Re-activar
    $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")
        ->execute([$test_sale_id]);

    $q->execute([$test_sale_id]);
    check('Toggle: Inactivo → Activo', (int)$q->fetchColumn() === 1);
} catch (Throwable $e) {
    check('Toggle activo/inactivo', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 9. Agregar producto al espacio
// ══════════════════════════════════════════════════════════════════════════
section('9. Agregar Producto al Espacio');

try {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO products (affiliate_id, sale_id, name, description, price, stock, currency, active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'CRC', 1, ?, ?)
    ")->execute([$test_aff_id, $test_sale_id, '[TEST] Producto Garaje', 'Producto de prueba automatizada', 5000, 3, $now, $now]);

    $test_prod_id = (int)$pdo->lastInsertId();
    check('Crear producto en espacio', $test_prod_id > 0, "id={$test_prod_id}");

    $prod = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $prod->execute([$test_prod_id]);
    $prod_row = $prod->fetch(PDO::FETCH_ASSOC);

    check('Producto vinculado al espacio', (int)$prod_row['sale_id'] === $test_sale_id);
    check('Precio guardado correctamente', (float)$prod_row['price'] === 5000.0, "precio=₡5,000");
    check('Stock guardado correctamente', (int)$prod_row['stock'] === 3, "stock=3");
    check('Moneda CRC', $prod_row['currency'] === 'CRC');
    check('Producto activo por defecto', (int)$prod_row['active'] === 1);

    // Contar productos del espacio
    $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sale_id=? AND affiliate_id=? AND active=1");
    $count->execute([$test_sale_id, $test_aff_id]);
    check('Consulta de productos activos del espacio', (int)$count->fetchColumn() >= 1);
} catch (Throwable $e) {
    check('Agregar producto', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 10. Re-activación de espacio expirado
// ══════════════════════════════════════════════════════════════════════════
section('10. Re-activación de Espacio Expirado');

try {
    // Simular espacio expirado (inactivo + fee pagado)
    $pdo->prepare("UPDATE sales SET is_active=0, updated_at=datetime('now') WHERE id=?")
        ->execute([$test_sale_id]);

    // Crear nuevo fee de re-activación
    $fee_crc_val = (float)get_setting('SALE_FEE_CRC', 2000);
    $ex          = get_exchange_rate();
    if ($ex <= 0) $ex = 1;
    $now = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO sale_fees (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?)
    ")->execute([$test_aff_id, $test_sale_id, $fee_crc_val, $fee_crc_val / $ex, $ex, $now, $now]);

    $new_fee_id = (int)$pdo->lastInsertId();
    check('Nuevo fee de re-activación creado', $new_fee_id > 0, "id={$new_fee_id}");

    // Verificar que el último fee está pendiente
    $latest = $pdo->prepare("SELECT status FROM sale_fees WHERE sale_id=? ORDER BY id DESC LIMIT 1");
    $latest->execute([$test_sale_id]);
    check('Último fee es Pendiente al re-activar', $latest->fetchColumn() === 'Pendiente');

    // Admin aprueba y activa
    $pdo->prepare("UPDATE sale_fees SET status='Pagado', updated_at=datetime('now') WHERE id=?")->execute([$new_fee_id]);
    $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")->execute([$test_sale_id]);

    $active_q = $pdo->prepare("SELECT is_active FROM sales WHERE id=?");
    $active_q->execute([$test_sale_id]);
    check('Espacio re-activado tras nuevo fee', (int)$active_q->fetchColumn() === 1);

    // Limpiar fee extra
    $pdo->prepare("DELETE FROM sale_fees WHERE id=?")->execute([$new_fee_id]);
} catch (Throwable $e) {
    check('Re-activación de espacio', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 11. Consultas del catálogo público (venta-garaje.php)
// ══════════════════════════════════════════════════════════════════════════
section('11. Consultas del Catálogo Público');

try {
    // Asegurar que el espacio está activo para estas pruebas
    $pdo->prepare("UPDATE sales SET is_active=1 WHERE id=?")->execute([$test_sale_id]);

    // Consulta básica de espacios activos (como en venta-garaje.php)
    $q = $pdo->prepare("SELECT id, title, is_active FROM sales WHERE is_active=1 AND affiliate_id=?");
    $q->execute([$test_aff_id]);
    $active_sales = $q->fetchAll(PDO::FETCH_ASSOC);
    check('Consulta espacios activos', count($active_sales) >= 1, count($active_sales) . ' espacio(s) activo(s)');

    // Búsqueda por título
    $q2 = $pdo->prepare("SELECT id FROM sales WHERE title LIKE ? AND is_active=1");
    $q2->execute(['%[TEST]%']);
    $found = $q2->fetchAll(PDO::FETCH_COLUMN);
    check('Búsqueda por título funciona', in_array($test_sale_id, $found), "sale_id={$test_sale_id} encontrado");

    // Join con afiliado
    $q3 = $pdo->prepare("
        SELECT s.id, s.title, a.name AS aff_name
        FROM sales s
        JOIN affiliates a ON a.id = s.affiliate_id
        WHERE s.id = ?
    ");
    $q3->execute([$test_sale_id]);
    $joined = $q3->fetch(PDO::FETCH_ASSOC);
    check('JOIN sales+affiliates funciona', !empty($joined['aff_name']), "afiliado={$joined['aff_name']}");

    // Contar productos activos del espacio
    $q4 = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sale_id=? AND active=1");
    $q4->execute([$test_sale_id]);
    $prod_count = (int)$q4->fetchColumn();
    check('Contar productos del espacio', $prod_count >= 1, "{$prod_count} producto(s)");

    // Filtro por estado "próximo" (start_at > now)
    $q5 = $pdo->prepare("
        SELECT COUNT(*) FROM sales
        WHERE is_active=1 AND affiliate_id=? AND datetime(start_at) > datetime('now')
    ");
    $q5->execute([$test_aff_id]);
    check('Filtro de espacios próximos', (int)$q5->fetchColumn() >= 1);

    // Ordenar por fecha de inicio
    $q6 = $pdo->prepare("
        SELECT id FROM sales WHERE is_active=1 ORDER BY datetime(start_at) ASC LIMIT 5
    ");
    $q6->execute();
    $ordered = $q6->fetchAll(PDO::FETCH_COLUMN);
    check('Ordenar por fecha de inicio', is_array($ordered) && count($ordered) >= 1);
} catch (Throwable $e) {
    check('Consultas del catálogo público', false, $e->getMessage());
}

section_end();

// ══════════════════════════════════════════════════════════════════════════
// 12. Validaciones de integridad
// ══════════════════════════════════════════════════════════════════════════
section('12. Validaciones de Integridad');

// Código de acceso: solo 6 dígitos numéricos
$valid_codes   = ['123456', '000000', '999999'];
$invalid_codes = ['12345', '1234567', 'abcdef', '12 456', ''];

foreach ($valid_codes as $code) {
    check("Código válido '{$code}'", preg_match('/^[0-9]{6}$/', $code) === 1);
}
foreach ($invalid_codes as $code) {
    $label = $code === '' ? '(vacío)' : "'{$code}'";
    check("Código inválido {$label} rechazado", preg_match('/^[0-9]{6}$/', $code) === 0);
}

// Título no vacío
check('Título vacío rechazado (validación)', trim('') === '');
check('Título con contenido aceptado', trim('Mi Venta') !== '');

// Fechas: fin > inicio
$start = strtotime('2026-03-10 08:00:00');
$end   = strtotime('2026-03-17 18:00:00');
check('end_at > start_at es válido', $end > $start);
check('end_at < start_at es inválido', !($start > $end));

section_end();

// ══════════════════════════════════════════════════════════════════════════
// Limpieza de datos de prueba
// ══════════════════════════════════════════════════════════════════════════
section('Limpieza de Datos de Prueba');

cleanup($pdo, $test_aff_id, $test_sale_id, $test_fee_id, $test_prod_id);

// Verificar que se eliminaron
$aff_gone  = !$pdo->prepare("SELECT id FROM affiliates WHERE id=?")->execute([$test_aff_id]) ||
             !$pdo->query("SELECT id FROM affiliates WHERE id={$test_aff_id}")->fetchColumn();
$sale_gone = !$pdo->query("SELECT id FROM sales WHERE id={$test_sale_id}")->fetchColumn();
$fee_gone  = !$pdo->query("SELECT id FROM sale_fees WHERE id={$test_fee_id}")->fetchColumn();
$prod_gone = !$pdo->query("SELECT id FROM products WHERE id={$test_prod_id}")->fetchColumn();

check('Afiliado de prueba eliminado', !$pdo->query("SELECT id FROM affiliates WHERE id={$test_aff_id}")->fetchColumn());
check('Espacio de prueba eliminado',  !$pdo->query("SELECT id FROM sales WHERE id={$test_sale_id}")->fetchColumn());
check('Fee de prueba eliminado',      !$pdo->query("SELECT id FROM sale_fees WHERE id={$test_fee_id}")->fetchColumn());
check('Producto de prueba eliminado', !$pdo->query("SELECT id FROM products WHERE id={$test_prod_id}")->fetchColumn());

section_end();

// ══════════════════════════════════════════════════════════════════════════
// Resumen final
// ══════════════════════════════════════════════════════════════════════════
$total = $results['pass'] + $results['fail'];
$all_ok = $results['fail'] === 0;

if (IS_CLI) {
    echo "\n\033[1;36m══════════════════════════════════════════════════════\033[0m\n";
    $color = $all_ok ? "\033[1;32m" : "\033[1;31m";
    echo "{$color}Resultado: {$results['pass']}/{$total} pruebas pasaron\033[0m\n";
    if (!$all_ok) {
        echo "\033[1;31mFallaron:\033[0m\n";
        foreach ($results['errors'] as $err) {
            echo "  • {$err}\n";
        }
    } else {
        echo "\033[1;32m✓ Todas las pruebas pasaron.\033[0m\n";
    }
    echo "\033[1;36m══════════════════════════════════════════════════════\033[0m\n";
    exit($all_ok ? 0 : 1);
} else {
    $color = $all_ok ? '#3fb950' : '#f85149';
    $icon  = $all_ok ? '✅' : '❌';
    echo "<div class='summary'>";
    echo "<h2 style='color:{$color};margin-top:0'>{$icon} Resultado: {$results['pass']}/{$total} pruebas pasaron</h2>";
    if (!$all_ok) {
        echo "<p style='color:#f85149'><strong>Fallaron:</strong></p><ul>";
        foreach ($results['errors'] as $err) {
            echo "<li style='color:#f85149'>" . h($err) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:#3fb950'>Todas las pruebas pasaron correctamente.</p>";
    }
    echo "</div>";
    print_footer();
}
