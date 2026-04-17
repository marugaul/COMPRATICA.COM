<?php
/**
 * cron/real-estate-expiry-notify.php
 *
 * Envía notificaciones de expiración de publicaciones de bienes raíces.
 *  - Aviso 2 días antes: "Tu publicación vence pronto"
 *  - Aviso de expiración: "Tu publicación fue desactivada"
 *
 * Configurar en cPanel Cron Jobs (una vez al día a las 8am):
 *   0 8 * * * /usr/bin/php /home/comprati/public_html/cron/real-estate-expiry-notify.php >> /home/comprati/public_html/logs/cron-expiry.log 2>&1
 */

define('CRON_CONTEXT', true);
date_default_timezone_set('America/Costa_Rica');

$rootDir = __DIR__ . '/..';
require_once $rootDir . '/includes/db.php';
require_once $rootDir . '/includes/config.php';
require_once $rootDir . '/includes/mailer.php';

$pdo = db();
$log = [];

function clog(string $msg): void {
    global $log;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    $log[] = $line;
}

clog('====== INICIO real-estate-expiry-notify ======');

// ── Asegurar columna expiry_warning_sent ─────────────────────────────────────
try {
    $cols = $pdo->query("PRAGMA table_info(real_estate_listings)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    if (!in_array('expiry_warning_sent', $colNames)) {
        $pdo->exec("ALTER TABLE real_estate_listings ADD COLUMN expiry_warning_sent INTEGER DEFAULT 0");
        clog('Columna expiry_warning_sent creada.');
    }
} catch (Throwable $e) {
    clog('ERROR al verificar columna: ' . $e->getMessage());
}

// ── Helper: obtener email de contacto del listing ────────────────────────────
function get_contact(array $row): array {
    // Prioridad: contact_email del listing → email del agente → email del usuario
    $email = trim($row['contact_email'] ?? '');
    $name  = trim($row['contact_name']  ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = trim($row['agent_email'] ?? '');
        $name  = trim($row['agent_name']  ?? $name);
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = trim($row['user_email'] ?? '');
        $name  = trim($row['user_name']  ?? $name);
    }
    return ['email' => $email, 'name' => $name ?: 'Cliente'];
}

// ── Helper: URL limpia de la publicación ────────────────────────────────────
function listing_url(int $id, string $title): string {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(
        iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title
    ));
    $slug = trim($slug, '-');
    return 'https://compratica.com/propiedad/' . $id . ($slug ? '-' . $slug : '');
}

$renewUrl = 'https://compratica.com/real-estate/dashboard.php';

// ════════════════════════════════════════════════════════════════════════════
// 1. AVISO: publicaciones que vencen en los próximos 2 días (sin aviso aún)
// ════════════════════════════════════════════════════════════════════════════
clog('--- Verificando avisos de 2 días antes ---');

$warnStmt = $pdo->prepare("
    SELECT l.id, l.title, l.end_date,
           l.contact_email, l.contact_name,
           a.email AS agent_email, a.name AS agent_name,
           u.email AS user_email,  u.name AS user_name
    FROM real_estate_listings l
    LEFT JOIN real_estate_agents a ON a.id = l.agent_id
    LEFT JOIN users              u ON u.id = l.user_id
    WHERE l.is_active = 1
      AND (l.payment_status = 'free' OR l.payment_status = 'confirmed')
      AND l.end_date >= datetime('now')
      AND l.end_date <= datetime('now', '+2 days')
      AND (l.expiry_warning_sent IS NULL OR l.expiry_warning_sent = 0)
");
$warnStmt->execute();
$toWarn = $warnStmt->fetchAll(PDO::FETCH_ASSOC);

clog('Publicaciones a avisar: ' . count($toWarn));

foreach ($toWarn as $row) {
    $contact  = get_contact($row);
    if (!$contact['email']) {
        clog("  [SKIP id={$row['id']}] Sin email de contacto.");
        continue;
    }

    $endFormatted = date('d/m/Y', strtotime($row['end_date']));
    $listingLink  = listing_url((int)$row['id'], $row['title']);
    $html = email_template_warning($contact['name'], $row['title'], $endFormatted, $listingLink, $renewUrl);
    $subject = '⚠️ Tu publicación "' . $row['title'] . '" vence el ' . $endFormatted;

    $sent = send_email($contact['email'], $subject, $html);

    if ($sent) {
        $pdo->prepare("UPDATE real_estate_listings SET expiry_warning_sent = 1 WHERE id = ?")
            ->execute([$row['id']]);
        clog("  [OK] Aviso enviado a {$contact['email']} — id={$row['id']} \"{$row['title']}\"");
    } else {
        clog("  [FAIL] No se pudo enviar a {$contact['email']} — id={$row['id']}");
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 2. EXPIRACIÓN: publicaciones vencidas, aún activas → desactivar + notificar
// ════════════════════════════════════════════════════════════════════════════
clog('--- Verificando publicaciones vencidas ---');

$expStmt = $pdo->prepare("
    SELECT l.id, l.title, l.end_date,
           l.contact_email, l.contact_name,
           a.email AS agent_email, a.name AS agent_name,
           u.email AS user_email,  u.name AS user_name
    FROM real_estate_listings l
    LEFT JOIN real_estate_agents a ON a.id = l.agent_id
    LEFT JOIN users              u ON u.id = l.user_id
    WHERE l.is_active = 1
      AND (l.payment_status = 'free' OR l.payment_status = 'confirmed')
      AND l.end_date < datetime('now')
");
$expStmt->execute();
$toExpire = $expStmt->fetchAll(PDO::FETCH_ASSOC);

clog('Publicaciones vencidas: ' . count($toExpire));

foreach ($toExpire as $row) {
    // Desactivar primero
    $pdo->prepare("UPDATE real_estate_listings SET is_active = 0, updated_at = datetime('now') WHERE id = ?")
        ->execute([$row['id']]);
    clog("  [DESACTIVADA] id={$row['id']} \"{$row['title']}\"");

    $contact = get_contact($row);
    if (!$contact['email']) {
        clog("  [SKIP email id={$row['id']}] Sin email de contacto.");
        continue;
    }

    $endFormatted = date('d/m/Y', strtotime($row['end_date']));
    $html    = email_template_expired($contact['name'], $row['title'], $endFormatted, $renewUrl);
    $subject = '📋 Tu publicación "' . $row['title'] . '" ha vencido';

    $sent = send_email($contact['email'], $subject, $html);
    clog($sent
        ? "  [OK] Correo de expiración a {$contact['email']} — id={$row['id']}"
        : "  [FAIL] No se pudo enviar a {$contact['email']} — id={$row['id']}"
    );
}

clog('====== FIN real-estate-expiry-notify ======');

// Guardar log
$logDir = $rootDir . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logDir . '/cron-expiry.log', implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND);

// ════════════════════════════════════════════════════════════════════════════
// PLANTILLAS DE EMAIL
// ════════════════════════════════════════════════════════════════════════════

function email_template_warning(string $name, string $title, string $endDate, string $listingUrl, string $renewUrl): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:30px 40px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">CompraТica</h1>
        <p style="margin:6px 0 0;color:#93c5fd;font-size:13px;">100% Costarricense</p>
      </td></tr>

      <!-- Alerta -->
      <tr><td style="background:#fff8e1;border-left:4px solid #f59e0b;padding:16px 40px;">
        <p style="margin:0;color:#92400e;font-weight:700;font-size:15px;">⚠️ Tu publicación vence en 2 días</p>
      </td></tr>

      <!-- Cuerpo -->
      <tr><td style="padding:36px 40px;">
        <p style="color:#374151;font-size:16px;">Hola <strong>{$name}</strong>,</p>
        <p style="color:#374151;font-size:15px;line-height:1.6;">
          Tu publicación en Bienes Raíces vence el <strong style="color:#dc2626;">{$endDate}</strong>:
        </p>

        <!-- Tarjeta de la publicación -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:20px 0;">
          <tr><td style="padding:20px;">
            <p style="margin:0;font-size:16px;font-weight:700;color:#1e3a5f;">{$title}</p>
            <p style="margin:8px 0 0;color:#6b7280;font-size:13px;">📅 Vence el <strong>{$endDate}</strong></p>
          </td></tr>
        </table>

        <p style="color:#374151;font-size:15px;line-height:1.6;">
          Pasada esa fecha tu propiedad <strong>dejará de mostrarse</strong> en el listado público.
          Renovando tu plan podés mantenerla activa y seguir recibiendo consultas.
        </p>

        <!-- Botones -->
        <table cellpadding="0" cellspacing="0" style="margin:28px 0;">
          <tr>
            <td style="padding-right:12px;">
              <a href="{$renewUrl}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;">🔄 Renovar plan ahora</a>
            </td>
            <td>
              <a href="{$listingUrl}" style="display:inline-block;background:#f8fafc;color:#374151;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;border:1px solid #e2e8f0;">👁 Ver mi publicación</a>
            </td>
          </tr>
        </table>

        <p style="color:#6b7280;font-size:13px;">
          Si ya renovaste tu plan podés ignorar este mensaje.
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 40px;text-align:center;">
        <p style="margin:0;color:#9ca3af;font-size:12px;">
          © {$GLOBALS['_year']} Compratica.com — El marketplace 100% costarricense<br>
          <a href="https://compratica.com" style="color:#2563eb;text-decoration:none;">compratica.com</a>
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

function email_template_expired(string $name, string $title, string $endDate, string $renewUrl): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:30px 40px;text-align:center;">
        <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">CompraТica</h1>
        <p style="margin:6px 0 0;color:#93c5fd;font-size:13px;">100% Costarricense</p>
      </td></tr>

      <!-- Alerta roja -->
      <tr><td style="background:#fef2f2;border-left:4px solid #dc2626;padding:16px 40px;">
        <p style="margin:0;color:#991b1b;font-weight:700;font-size:15px;">📋 Tu publicación ha vencido y fue desactivada</p>
      </td></tr>

      <!-- Cuerpo -->
      <tr><td style="padding:36px 40px;">
        <p style="color:#374151;font-size:16px;">Hola <strong>{$name}</strong>,</p>
        <p style="color:#374151;font-size:15px;line-height:1.6;">
          El plan de tu publicación venció el <strong style="color:#dc2626;">{$endDate}</strong> y fue desactivada automáticamente:
        </p>

        <!-- Tarjeta de la publicación -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;margin:20px 0;">
          <tr><td style="padding:20px;">
            <p style="margin:0;font-size:16px;font-weight:700;color:#991b1b;">{$title}</p>
            <p style="margin:8px 0 0;color:#b91c1c;font-size:13px;">❌ Desactivada — venció el {$endDate}</p>
          </td></tr>
        </table>

        <p style="color:#374151;font-size:15px;line-height:1.6;">
          Tu propiedad ya <strong>no aparece en el listado público</strong>.
          Para volver a publicarla, ingresá a tu panel y renovate con el plan que mejor se ajuste a tus necesidades.
        </p>

        <!-- Botón principal -->
        <table cellpadding="0" cellspacing="0" style="margin:28px 0;">
          <tr><td>
            <a href="{$renewUrl}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:16px 36px;border-radius:8px;font-weight:700;font-size:16px;">🔄 Renovar mi publicación</a>
          </td></tr>
        </table>

        <p style="color:#374151;font-size:14px;line-height:1.6;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:14px;">
          💡 <strong>¿Sabías que?</strong> Con el <strong>Plan 30 días ($2 USD)</strong> o el <strong>Plan 90 días ($3.50 USD)</strong>
          podés destacar tu propiedad y llegar a más compradores potenciales.
        </p>

        <p style="color:#6b7280;font-size:13px;margin-top:20px;">
          ¿Necesitás ayuda? Escribinos a
          <a href="mailto:info@compratica.com" style="color:#2563eb;">info@compratica.com</a>
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 40px;text-align:center;">
        <p style="margin:0;color:#9ca3af;font-size:12px;">
          © {$GLOBALS['_year']} Compratica.com — El marketplace 100% costarricense<br>
          <a href="https://compratica.com" style="color:#2563eb;text-decoration:none;">compratica.com</a>
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

$GLOBALS['_year'] = date('Y');
