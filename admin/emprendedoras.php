<?php
/**
 * admin/emprendedoras.php
 * Panel de administración para suscripciones de Emprendedoras.
 * Permite aprobar / rechazar suscripciones pendientes de SINPE.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

require_login();
$pdo = db();

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$msg = '';
$msgType = 'success';

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subId  = (int)($_POST['sub_id'] ?? 0);
    $act    = $_POST['action'] ?? '';

// ── Cambiar contraseña de emprendedor/a ─────────────────────────────────────
    if ($act === 'change_password') {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_pass'] ?? '');
        if ($userId <= 0 || $newPass === '') {
            $msg = 'ID de usuario o contraseña inválidos.';
            $msgType = 'error';
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE users SET password_hash=?, oauth_provider=NULL, oauth_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $upd->execute([$hash, $userId]);
                if ($upd->rowCount() === 0) throw new RuntimeException('Usuario no encontrado.');
                $msg = 'Contraseña actualizada correctamente.';
            } catch (Throwable $e) {
                $msg = 'Error: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($subId > 0 && in_array($act, ['approve', 'reject', 'activate', 'inactivate'], true)) {
        try {
            // Obtener suscripción
            $sub = $pdo->prepare("
                SELECT s.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email,
                       COALESCE(u.seller_type, 'emprendedora') AS seller_type
                FROM entrepreneur_subscriptions s
                JOIN entrepreneur_plans p ON p.id = s.plan_id
                JOIN users u ON u.id = s.user_id
                WHERE s.id = ? AND s.status = 'pending'
            ");
            $sub->execute([$subId]);
            $row = $sub->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $msg = 'Suscripción no encontrada o ya procesada.';
                $msgType = 'error';
            } else {
                $empLabelAdmin  = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? 'Emprendedor' : 'Emprendedora';
                $empHeaderAdmin = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? '💼 CompraTica Emprendedores' : '🌸 CompraTica Emprendedoras';
                $pdo->beginTransaction();

                if ($act === 'approve') {
                    $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));
                    $pdo->prepare("
                        UPDATE entrepreneur_subscriptions
                        SET status='active', start_date=datetime('now'), end_date=?, updated_at=datetime('now')
                        WHERE id=?
                    ")->execute([$endDate, $subId]);

                    $pdo->commit();
                    $msg = "Suscripción de {$row['user_name']} aprobada correctamente.";

                    // Email al usuario
                    if ($row['user_email']) {
                        $html = "
                        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                            <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
                                <h1 style='color:#fff;margin:0;font-size:1.8rem;'>" . $empHeaderAdmin . "</h1>
                            </div>
                            <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
                                <h2 style='color:#27ae60;'>✅ ¡Tu cuenta fue aprobada!</h2>
                                <p>Hola <strong>" . h($row['user_name']) . "</strong>,</p>
                                <p>Nos complace informarte que tu suscripción al plan <strong>" . h($row['plan_name']) . "</strong> ha sido <strong>aprobada</strong>.</p>
                                <p>Ya puedes acceder a tu dashboard y comenzar a publicar tus productos.</p>
                                <div style='text-align:center;margin:30px 0;'>
                                    <a href='" . SITE_URL . "/emprendedores-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ir a mi Dashboard</a>
                                </div>
                            </div>
                            <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
                                CompraTica — El marketplace costarricense
                            </div>
                        </div>";
                        try {
                            send_email($row['user_email'], '✅ Cuenta Aprobada - CompraTica', $html);
                        } catch (Throwable $e) {
                            error_log('[admin/emprendedoras] Email aprobacion error: ' . $e->getMessage());
                        }
                    }

                } else { // reject
                    $pdo->prepare("
                        UPDATE entrepreneur_subscriptions
                        SET status='cancelled', updated_at=datetime('now')
                        WHERE id=?
                    ")->execute([$subId]);

                    $pdo->commit();
                    $msg = "Suscripción de {$row['user_name']} rechazada.";
                    $msgType = 'warning';

                    // Email al usuario
                    if ($row['user_email']) {
                        $html = "
                        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                            <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
                                <h1 style='color:#fff;margin:0;font-size:1.8rem;'>" . $empHeaderAdmin . "</h1>
                            </div>
                            <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
                                <h2 style='color:#e74c3c;'>❌ Comprobante no aprobado</h2>
                                <p>Hola <strong>" . h($row['user_name']) . "</strong>,</p>
                                <p>Lamentablemente no pudimos verificar tu comprobante de pago para el plan <strong>" . h($row['plan_name']) . "</strong>.</p>
                                <p>Por favor contáctanos por WhatsApp o correo para resolver esta situación, o intenta enviar nuevamente tu comprobante.</p>
                                <div style='text-align:center;margin:30px 0;'>
                                    <a href='" . SITE_URL . "/emprendedores-planes' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ver planes</a>
                                </div>
                            </div>
                            <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
                                CompraTica — El marketplace costarricense
                            </div>
                        </div>";
                        try {
                            send_email($row['user_email'], '❌ Comprobante no aprobado - CompraTica', $html);
                        } catch (Throwable $e) {
                            error_log('[admin/emprendedoras] Email rechazo error: ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    // Activar suscripción
    if ($subId > 0 && $act === 'activate') {
        try {
            $sub = $pdo->prepare("
                SELECT s.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email,
                       COALESCE(u.seller_type, 'emprendedora') AS seller_type
                FROM entrepreneur_subscriptions s
                JOIN entrepreneur_plans p ON p.id = s.plan_id
                JOIN users u ON u.id = s.user_id
                WHERE s.id = ?
            ");
            $sub->execute([$subId]);
            $row = $sub->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $msg = 'Suscripción no encontrada.';
                $msgType = 'error';
            } else {
                $empLabelAdmin  = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? 'Emprendedor' : 'Emprendedora';
                $empHeaderAdmin = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? '💼 CompraTica Emprendedores' : '🌸 CompraTica Emprendedoras';
                $pdo->beginTransaction();

                // Reactivar por 1 mes desde hoy
                $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));
                $pdo->prepare("
                    UPDATE entrepreneur_subscriptions
                    SET status='active', start_date=datetime('now'), end_date=?, updated_at=datetime('now')
                    WHERE id=?
                ")->execute([$endDate, $subId]);

                $pdo->commit();
                $msg = "Suscripción de {$row['user_name']} reactivada correctamente.";
                $msgType = 'success';

                // Email al usuario
                if ($row['user_email']) {
                    $html = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
                            <h1 style='color:#fff;margin:0;font-size:1.8rem;'>" . $empHeaderAdmin . "</h1>
                        </div>
                        <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
                            <h2 style='color:#27ae60;'>✅ ¡Tu suscripción fue reactivada!</h2>
                            <p>Hola <strong>" . h($row['user_name']) . "</strong>,</p>
                            <p>Tu suscripción al plan <strong>" . h($row['plan_name']) . "</strong> ha sido <strong>reactivada</strong>.</p>
                            <p>Ya puedes acceder nuevamente a tu dashboard y seguir publicando tus productos.</p>
                            <div style='text-align:center;margin:30px 0;'>
                                <a href='" . SITE_URL . "/emprendedores-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ir a mi Dashboard</a>
                            </div>
                        </div>
                        <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
                            CompraTica — El marketplace costarricense
                        </div>
                    </div>";
                    try {
                        send_email($row['user_email'], '✅ Suscripción Reactivada - CompraTica', $html);
                    } catch (Throwable $e) {
                        error_log('[admin/emprendedoras] Email reactivación error: ' . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    // Inactivar suscripción
    if ($subId > 0 && $act === 'inactivate') {
        try {
            $sub = $pdo->prepare("
                SELECT s.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email,
                       COALESCE(u.seller_type, 'emprendedora') AS seller_type
                FROM entrepreneur_subscriptions s
                JOIN entrepreneur_plans p ON p.id = s.plan_id
                JOIN users u ON u.id = s.user_id
                WHERE s.id = ?
            ");
            $sub->execute([$subId]);
            $row = $sub->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $msg = 'Suscripción no encontrada.';
                $msgType = 'error';
            } else {
                $empLabelAdmin  = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? 'Emprendedor' : 'Emprendedora';
                $empHeaderAdmin = ($row['seller_type'] ?? 'emprendedora') === 'emprendedor' ? '💼 CompraTica Emprendedores' : '🌸 CompraTica Emprendedoras';
                $pdo->beginTransaction();

                $pdo->prepare("
                    UPDATE entrepreneur_subscriptions
                    SET status='cancelled', updated_at=datetime('now')
                    WHERE id=?
                ")->execute([$subId]);

                $pdo->commit();
                $msg = "Suscripción de {$row['user_name']} desactivada correctamente.";
                $msgType = 'warning';

                // Email al usuario
                if ($row['user_email']) {
                    $html = "
                    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
                            <h1 style='color:#fff;margin:0;font-size:1.8rem;'>" . $empHeaderAdmin . "</h1>
                        </div>
                        <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
                            <h2 style='color:#e74c3c;'>⚠️ Tu suscripción fue desactivada</h2>
                            <p>Hola <strong>" . h($row['user_name']) . "</strong>,</p>
                            <p>Tu suscripción al plan <strong>" . h($row['plan_name']) . "</strong> ha sido <strong>desactivada</strong>.</p>
                            <p>Si crees que esto es un error o deseas reactivar tu cuenta, por favor contáctanos.</p>
                            <div style='text-align:center;margin:30px 0;'>
                                <a href='" . SITE_URL . "/emprendedores-planes' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ver planes</a>
                            </div>
                        </div>
                        <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
                            CompraTica — El marketplace costarricense
                        </div>
                    </div>";
                    try {
                        send_email($row['user_email'], '⚠️ Suscripción Desactivada - CompraTica', $html);
                    } catch (Throwable $e) {
                        error_log('[admin/emprendedoras] Email desactivación error: ' . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    // ── Crear plan ────────────────────────────────────────────────────────────
    if ($act === 'plan_create') {
        $planName    = trim($_POST['plan_name']    ?? '');
        $planDesc    = trim($_POST['plan_desc']    ?? '');
        $planMonthly = (float)($_POST['plan_monthly'] ?? 0);
        $planAnnual  = (float)($_POST['plan_annual']  ?? 0);
        $planMaxProd = (int)($_POST['plan_max_prod']  ?? 0);
        $planActive  = (int)($_POST['plan_active']    ?? 1);
        $featuresRaw = trim($_POST['plan_features']   ?? '');
        // Parsear features: una por línea → JSON array
        $featuresArr = array_values(array_filter(array_map('trim', explode("\n", $featuresRaw))));
        $featuresJson = json_encode($featuresArr, JSON_UNESCAPED_UNICODE);

        if (!$planName) {
            $msg = 'El nombre del plan es obligatorio.'; $msgType = 'error';
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO entrepreneur_plans
                        (name, description, price_monthly, price_annual, max_products, commission_rate, features, is_active, display_order)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, (SELECT COALESCE(MAX(display_order),0)+1 FROM entrepreneur_plans))
                ")->execute([$planName, $planDesc, $planMonthly, $planAnnual, $planMaxProd, $featuresJson, $planActive]);
                $msg = 'Plan "' . h($planName) . '" creado correctamente.';
            } catch (Throwable $e) {
                $msg = 'Error al crear plan: ' . $e->getMessage(); $msgType = 'error';
            }
        }
    }

    // ── Actualizar plan ───────────────────────────────────────────────────────
    if ($act === 'plan_update') {
        $planId      = (int)($_POST['plan_id']       ?? 0);
        $planName    = trim($_POST['plan_name']       ?? '');
        $planDesc    = trim($_POST['plan_desc']       ?? '');
        $planMonthly = (float)($_POST['plan_monthly'] ?? 0);
        $planAnnual  = (float)($_POST['plan_annual']  ?? 0);
        $planMaxProd = (int)($_POST['plan_max_prod']  ?? 0);
        $planActive  = (int)($_POST['plan_active']    ?? 1);
        $featuresRaw = trim($_POST['plan_features']   ?? '');
        $featuresArr = array_values(array_filter(array_map('trim', explode("\n", $featuresRaw))));
        $featuresJson = json_encode($featuresArr, JSON_UNESCAPED_UNICODE);

        if (!$planId || !$planName) {
            $msg = 'Datos inválidos.'; $msgType = 'error';
        } else {
            try {
                $pdo->prepare("
                    UPDATE entrepreneur_plans
                    SET name=?, description=?, price_monthly=?, price_annual=?, max_products=?,
                        features=?, is_active=?
                    WHERE id=?
                ")->execute([$planName, $planDesc, $planMonthly, $planAnnual, $planMaxProd, $featuresJson, $planActive, $planId]);
                $msg = 'Plan "' . h($planName) . '" actualizado correctamente.';
            } catch (Throwable $e) {
                $msg = 'Error al actualizar plan: ' . $e->getMessage(); $msgType = 'error';
            }
        }
    }

    // ── Eliminar plan ─────────────────────────────────────────────────────────
    if ($act === 'plan_delete') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if (!$planId) {
            $msg = 'ID de plan inválido.'; $msgType = 'error';
        } else {
            // Verificar si tiene suscripciones activas
            $activeSubs = (int)$pdo->prepare("SELECT COUNT(*) FROM entrepreneur_subscriptions WHERE plan_id=? AND status='active'")->execute([$planId]) ? $pdo->query("SELECT COUNT(*) FROM entrepreneur_subscriptions WHERE plan_id=$planId AND status='active'")->fetchColumn() : 0;
            if ($activeSubs > 0) {
                $msg = "No se puede eliminar: tiene $activeSubs suscripción(es) activa(s). Desactivá el plan en su lugar.";
                $msgType = 'error';
            } else {
                try {
                    $pdo->prepare("DELETE FROM entrepreneur_plans WHERE id=?")->execute([$planId]);
                    $msg = 'Plan eliminado.'; $msgType = 'warning';
                } catch (Throwable $e) {
                    $msg = 'Error al eliminar plan: ' . $e->getMessage(); $msgType = 'error';
                }
            }
        }
    }
}

// ── Obtener suscripciones ─────────────────────────────────────────────────────
$pending = $pdo->query("
    SELECT s.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email
    FROM entrepreneur_subscriptions s
    JOIN entrepreneur_plans p ON p.id = s.plan_id
    JOIN users u ON u.id = s.user_id
    WHERE s.status = 'pending'
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$all = $pdo->query("
    SELECT s.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email
    FROM entrepreneur_subscriptions s
    JOIN entrepreneur_plans p ON p.id = s.plan_id
    JOIN users u ON u.id = s.user_id
    ORDER BY s.created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener comprobantes para suscripciones pendientes (keyed by user_id)
$receiptMap = [];
if (!empty($pending)) {
    $userIds = implode(',', array_map(fn($r) => (int)$r['user_id'], $pending));
    try {
        $recs = $pdo->query("
            SELECT * FROM payment_receipts
            WHERE listing_type='subscription' AND user_id IN ($userIds)
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        // Keep only the most recent receipt per user
        foreach ($recs as $rec) {
            $uid = (int)$rec['user_id'];
            if (!isset($receiptMap[$uid])) {
                $receiptMap[$uid] = $rec;
            }
        }
    } catch (Throwable $e) {}
}

// ── Obtener planes ─────────────────────────────────────────────────────────────
$plans = $pdo->query("SELECT * FROM entrepreneur_plans ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

$navStyle = "display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1rem;background:rgba(255,255,255,0.1);color:white;text-decoration:none;border-radius:6px;font-size:0.875rem;font-weight:500;border:1px solid rgba(255,255,255,0.2);";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprendedoras | Admin CompraTica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #333; }
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 1.5rem;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;
        }
        .logo { font-size: 1.2rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 0.6rem; }
        nav { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        nav a { <?php echo $navStyle; ?> }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        h2 { margin-bottom: 1.5rem; color: #4c1d95; }
        .msg {
            padding: 14px 20px; border-radius: 10px; margin-bottom: 20px;
            font-weight: 500;
        }
        .msg.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .msg.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .msg.warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .card {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px; overflow: hidden;
        }
        .card-header {
            padding: 20px 25px; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h3 { font-size: 1.1rem; color: #333; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
        }
        .badge-pending  { background: #fef3c7; color: #92400e; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-cancelled{ background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9ff; padding: 12px 16px; text-align: left; font-size: 0.85rem; color: #666; font-weight: 600; border-bottom: 2px solid #e8e8f0; }
        td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-approve { background: #10b981; color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject  { background: #ef4444; color: white; }
        .btn-reject:hover  { background: #dc2626; }
        .btn-view    { background: #667eea; color: white; }
        .btn-view:hover { background: #5a6fd6; }
        .btn-activate { background: #10b981; color: white; }
        .btn-activate:hover { background: #059669; }
        .btn-inactivate { background: #f59e0b; color: white; }
        .btn-inactivate:hover { background: #d97706; }
        .empty { text-align: center; padding: 40px; color: #999; }
        .empty i { font-size: 2.5rem; margin-bottom: 10px; }
        .btn-edit { background: #6366f1; color: white; }
        .btn-edit:hover { background: #4f46e5; }
        .edit-row { display: none; background: #f8f7ff; }
        .edit-row td { padding: 16px 20px; }
        .edit-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .edit-form label { font-size: .85rem; font-weight: 600; color: #4c1d95; white-space: nowrap; }
        .edit-form input[type=password] { padding: 8px 12px; border: 1.5px solid #c4b5fd; border-radius: 8px; font-size: .9rem; width: 220px; }
        .btn-save-pass { background: #10b981; color: white; padding: 8px 18px; border: none; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .btn-save-pass:hover { background: #059669; }
        .btn-cancel-edit { background: #e5e7eb; color: #374151; padding: 8px 14px; border: none; border-radius: 8px; font-size: .85rem; cursor: pointer; }
        /* ── Planes ── */
        .btn-plan-new  { background: linear-gradient(135deg,#667eea,#764ba2); color:white; padding:9px 20px; border:none; border-radius:9px; font-size:.88rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
        .btn-plan-edit { background:#6366f1; color:white; }
        .btn-plan-edit:hover { background:#4f46e5; }
        .btn-plan-del  { background:#ef4444; color:white; }
        .btn-plan-del:hover  { background:#dc2626; }
        .plan-edit-row { display:none; background:#f8f7ff; }
        .plan-edit-row td { padding:18px 20px; }
        .plan-form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px 16px; }
        .plan-form-grid label { display:block; font-size:.8rem; font-weight:600; color:#4c1d95; margin-bottom:4px; }
        .plan-form-grid input[type=text],.plan-form-grid input[type=number],.plan-form-grid textarea,.plan-form-grid select {
            width:100%; padding:8px 10px; border:1.5px solid #c4b5fd; border-radius:8px; font-size:.88rem; font-family:inherit;
        }
        .plan-form-grid textarea { resize:vertical; min-height:70px; }
        .plan-form-wide { grid-column:1/-1; }
        .plan-form-actions { grid-column:1/-1; display:flex; gap:8px; margin-top:4px; }
        .btn-plan-save { background:#10b981; color:white; padding:9px 20px; border:none; border-radius:8px; font-size:.85rem; font-weight:700; cursor:pointer; }
        .btn-plan-save:hover { background:#059669; }
        .badge-plan-on  { background:#d1fae5; color:#065f46; }
        .badge-plan-off { background:#f3f4f6; color:#6b7280; }
        /* Create form */
        #new-plan-row { display:none; background:#f0fdf4; }
        #new-plan-row td { padding:18px 20px; }
    </style>
</head>
<body>
<header>
    <div class="logo"><i class="fas fa-crown"></i> Emprendedoras — Admin</div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="../emprendedores-dashboard"><i class="fas fa-store"></i> Portal Emprendedores</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
    </nav>
</header>

<div class="container">
    <?php if ($msg): ?>
        <div class="msg <?php echo $msgType; ?>"><i class="fas fa-info-circle"></i> <?php echo h($msg); ?></div>
    <?php endif; ?>

    <!-- Suscripciones Pendientes -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock" style="color:#f59e0b;"></i> Suscripciones Pendientes
                <?php if (count($pending)): ?>
                    <span class="badge badge-pending" style="margin-left:10px;"><?php echo count($pending); ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if (empty($pending)): ?>
            <div class="empty">
                <i class="fas fa-check-circle" style="color:#10b981;"></i>
                <p>No hay suscripciones pendientes.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Emprendedora</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Pago</th>
                        <th>Comprobante</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $row): ?>
                    <?php $receipt = $receiptMap[(int)$row['user_id']] ?? null; ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo h($row['user_name']); ?></strong></td>
                        <td><?php echo h($row['user_email']); ?></td>
                        <td><?php echo h($row['plan_name']); ?></td>
                        <td><?php echo ucfirst(h($row['payment_method'] ?? 'N/A')); ?></td>
                        <td>
                            <?php if ($receipt && !empty($receipt['receipt_url'])): ?>
                                <a href="<?php echo h($receipt['receipt_url']); ?>" target="_blank" class="btn btn-view">
                                    <i class="fas fa-image"></i> Ver
                                </a>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        <td style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="post" onsubmit="return confirm('¿Aprobar esta suscripción?');">
                                <input type="hidden" name="sub_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve">
                                    <i class="fas fa-check"></i> Aprobar
                                </button>
                            </form>
                            <form method="post" onsubmit="return confirm('¿Rechazar esta suscripción?');">
                                <input type="hidden" name="sub_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject">
                                    <i class="fas fa-times"></i> Rechazar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Historial completo -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Todas las Suscripciones</h3>
        </div>
        <?php if (empty($all)): ?>
            <div class="empty">
                <i class="fas fa-inbox"></i>
                <p>No hay suscripciones registradas.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Emprendedora</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Pago</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Vence</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all as $row): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo h($row['user_name']); ?></td>
                        <td><?php echo h($row['user_email']); ?></td>
                        <td><?php echo h($row['plan_name']); ?></td>
                        <td><?php echo ucfirst(h($row['payment_method'] ?? 'N/A')); ?></td>
                        <td>
                            <?php
                            $st = $row['status'];
                            $cls = match($st) { 'active' => 'active', 'pending' => 'pending', default => 'cancelled' };
                            $label = match($st) { 'active' => '✅ Activo', 'pending' => '⏳ Pendiente', 'cancelled' => '❌ Cancelado', 'expired' => '⌛ Expirado', default => $st };
                            echo "<span class='badge badge-{$cls}'>{$label}</span>";
                            ?>
                        </td>
                        <td><?php echo $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : '—'; ?></td>
                        <td><?php echo $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : '—'; ?></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <?php if ($row['status'] === 'active'): ?>
                                <form method="post" onsubmit="return confirm('¿Desactivar esta suscripción?');" style="display:inline;">
                                    <input type="hidden" name="sub_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="inactivate">
                                    <button type="submit" class="btn btn-inactivate">
                                        <i class="fas fa-pause"></i> Desactivar
                                    </button>
                                </form>
                            <?php elseif (in_array($row['status'], ['cancelled', 'expired'])): ?>
                                <form method="post" onsubmit="return confirm('¿Reactivar esta suscripción por 1 mes?');" style="display:inline;">
                                    <input type="hidden" name="sub_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="action" value="activate">
                                    <button type="submit" class="btn btn-activate">
                                        <i class="fas fa-play"></i> Reactivar
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-edit" onclick="toggleEdit(<?php echo $row['id']; ?>)">
                                <i class="fas fa-key"></i> Editar
                            </button>
                        </td>
                    </tr>
                    <tr class="edit-row" id="edit-row-<?php echo $row['id']; ?>">
                        <td colspan="9">
                            <form method="post" class="edit-form" onsubmit="return confirm('¿Cambiar la contraseña de <?php echo h(addslashes($row['user_name'])); ?>?');">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="user_id" value="<?php echo (int)$row['user_id']; ?>">
                                <label><i class="fas fa-lock"></i> Nueva contraseña para <?php echo h($row['user_name']); ?>:</label>
                                <input type="password" name="new_pass" placeholder="Mínimo 6 caracteres" minlength="6" required>
                                <button type="submit" class="btn-save-pass"><i class="fas fa-save"></i> Guardar</button>
                                <button type="button" class="btn-cancel-edit" onclick="toggleEdit(<?php echo $row['id']; ?>)">Cancelar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
    <!-- ── GESTIÓN DE PLANES ──────────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-layer-group" style="color:#667eea;"></i> Gestión de Planes</h3>
            <button class="btn-plan-new" onclick="toggleNewPlan()">
                <i class="fas fa-plus"></i> Nuevo Plan
            </button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Mensual (₡)</th>
                    <th>Anual (₡)</th>
                    <th>Max. Productos</th>
                    <th>Estado</th>
                    <th>Beneficios</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Fila Nuevo Plan -->
                <tr id="new-plan-row">
                    <td colspan="8">
                        <form method="post">
                            <input type="hidden" name="action" value="plan_create">
                            <div class="plan-form-grid">
                                <div>
                                    <label><i class="fas fa-tag"></i> Nombre *</label>
                                    <input type="text" name="plan_name" placeholder="Ej: Plan Pro" required>
                                </div>
                                <div>
                                    <label><i class="fas fa-align-left"></i> Descripción</label>
                                    <input type="text" name="plan_desc" placeholder="Descripción breve">
                                </div>
                                <div>
                                    <label><i class="fas fa-calendar-day"></i> Mensual (₡)</label>
                                    <input type="number" name="plan_monthly" value="0" min="0" step="100">
                                </div>
                                <div>
                                    <label><i class="fas fa-calendar-alt"></i> Anual (₡)</label>
                                    <input type="number" name="plan_annual" value="0" min="0" step="1000">
                                </div>
                                <div>
                                    <label><i class="fas fa-box"></i> Máx. Productos (0=ilimitado)</label>
                                    <input type="number" name="plan_max_prod" value="5" min="0">
                                </div>
                                <div>
                                    <label><i class="fas fa-toggle-on"></i> Estado</label>
                                    <select name="plan_active">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="plan-form-wide">
                                    <label><i class="fas fa-list-ul"></i> Beneficios (una por línea)</label>
                                    <textarea name="plan_features" placeholder="Hasta 5 productos&#10;Soporte básico&#10;Sin comisiones"></textarea>
                                </div>
                                <div class="plan-form-actions">
                                    <button type="submit" class="btn-plan-save"><i class="fas fa-save"></i> Crear Plan</button>
                                    <button type="button" class="btn-cancel-edit" onclick="toggleNewPlan()">Cancelar</button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>

                <?php foreach ($plans as $plan):
                    $featList = json_decode($plan['features'] ?? '[]', true) ?: [];
                    $featText = implode("\n", $featList);
                    $maxLabel = (int)$plan['max_products'] === 0 ? 'Ilimitados' : number_format((int)$plan['max_products']);
                ?>
                <tr>
                    <td><?= (int)$plan['id'] ?></td>
                    <td>
                        <strong><?= h($plan['name']) ?></strong>
                        <?php if ($plan['description']): ?>
                            <div style="font-size:.78rem;color:#9ca3af;margin-top:2px;"><?= h($plan['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>₡<?= number_format((float)$plan['price_monthly'], 0) ?></td>
                    <td>₡<?= number_format((float)$plan['price_annual'], 0) ?></td>
                    <td><?= $maxLabel ?></td>
                    <td>
                        <span class="badge <?= $plan['is_active'] ? 'badge-plan-on' : 'badge-plan-off' ?>">
                            <?= $plan['is_active'] ? '● Activo' : '○ Inactivo' ?>
                        </span>
                    </td>
                    <td style="font-size:.8rem;color:#6b7280;max-width:180px;">
                        <?= h(implode(' · ', array_slice($featList, 0, 3))) ?>
                        <?php if (count($featList) > 3): ?><em>+<?= count($featList)-3 ?> más</em><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button class="btn btn-plan-edit" onclick="togglePlanEdit(<?= (int)$plan['id'] ?>)">
                            <i class="fas fa-pencil-alt"></i> Editar
                        </button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar el plan <?= h(addslashes($plan['name'])) ?>? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="action" value="plan_delete">
                            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                            <button type="submit" class="btn btn-plan-del"><i class="fas fa-trash"></i> Eliminar</button>
                        </form>
                    </td>
                </tr>
                <!-- Fila edición inline -->
                <tr class="plan-edit-row" id="plan-edit-row-<?= (int)$plan['id'] ?>">
                    <td colspan="8">
                        <form method="post">
                            <input type="hidden" name="action" value="plan_update">
                            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                            <div class="plan-form-grid">
                                <div>
                                    <label><i class="fas fa-tag"></i> Nombre *</label>
                                    <input type="text" name="plan_name" value="<?= h($plan['name']) ?>" required>
                                </div>
                                <div>
                                    <label><i class="fas fa-align-left"></i> Descripción</label>
                                    <input type="text" name="plan_desc" value="<?= h($plan['description'] ?? '') ?>">
                                </div>
                                <div>
                                    <label><i class="fas fa-calendar-day"></i> Mensual (₡)</label>
                                    <input type="number" name="plan_monthly" value="<?= (float)$plan['price_monthly'] ?>" min="0" step="100">
                                </div>
                                <div>
                                    <label><i class="fas fa-calendar-alt"></i> Anual (₡)</label>
                                    <input type="number" name="plan_annual" value="<?= (float)$plan['price_annual'] ?>" min="0" step="1000">
                                </div>
                                <div>
                                    <label><i class="fas fa-box"></i> Máx. Productos (0=ilimitado)</label>
                                    <input type="number" name="plan_max_prod" value="<?= (int)$plan['max_products'] ?>" min="0">
                                </div>
                                <div>
                                    <label><i class="fas fa-toggle-on"></i> Estado</label>
                                    <select name="plan_active">
                                        <option value="1" <?= $plan['is_active'] ? 'selected' : '' ?>>Activo</option>
                                        <option value="0" <?= !$plan['is_active'] ? 'selected' : '' ?>>Inactivo</option>
                                    </select>
                                </div>
                                <div class="plan-form-wide">
                                    <label><i class="fas fa-list-ul"></i> Beneficios (una por línea)</label>
                                    <textarea name="plan_features"><?= h($featText) ?></textarea>
                                </div>
                                <div class="plan-form-actions">
                                    <button type="submit" class="btn-plan-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                                    <button type="button" class="btn-cancel-edit" onclick="togglePlanEdit(<?= (int)$plan['id'] ?>)">Cancelar</button>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- ── FIN GESTIÓN DE PLANES ──────────────────────────────────────────────── -->

</div>
<script>
function toggleEdit(id) {
    var row = document.getElementById('edit-row-' + id);
    row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    if (row.style.display === 'table-row') {
        row.querySelector('input[type=password]').focus();
    }
}
function toggleNewPlan() {
    var row = document.getElementById('new-plan-row');
    row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    if (row.style.display === 'table-row') {
        row.querySelector('input[type=text]').focus();
    }
}
function togglePlanEdit(id) {
    // Cerrar cualquier otro abierto
    document.querySelectorAll('.plan-edit-row').forEach(function(r) {
        if (r.id !== 'plan-edit-row-' + id) r.style.display = 'none';
    });
    var row = document.getElementById('plan-edit-row-' + id);
    row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
    if (row.style.display === 'table-row') {
        row.querySelector('input[type=text]').focus();
    }
}
</script>
</body>
</html>
