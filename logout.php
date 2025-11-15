<?php
declare(strict_types=1);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/logout_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $enc = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line .= ' | ' . ($enc !== false ? $enc : print_r($data, true));
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("LOGOUT_START", ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $_SERVER['REQUEST_METHOD'] ?? '']);

// Detectar HTTPS y cookie domain
$__isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $__isHttps = true;
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $__isHttps = true;

$host = $_SERVER['HTTP_HOST'] ?? parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST) ?? '';
$cookieDomain = '';
if ($host && !preg_match('/^(localhost|127\.0\.0\.1|::1|\d+\.\d+\.\d+\.\d+)$/', $host)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $cookieDomain = $clean; // ✅ SIN PUNTO INICIAL
    }
}
logDebug("LOGOUT_COOKIE_DOMAIN", ['domain' => $cookieDomain, 'https' => $__isHttps, 'host' => $host]);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

session_name('PHPSESSID');
if (PHP_VERSION_ID < 70300) {
    session_set_cookie_params(0, '/', $cookieDomain ?: '', $__isHttps, true);
} else {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $cookieDomain ?: '',
        'secure'   => $__isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function expireCookieClient(string $name, string $domain, bool $secure, bool $httponly = true, string $samesite = 'Lax') {
    if (PHP_VERSION_ID >= 70300) {
        if ($domain !== '') {
            @setcookie($name, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        }
        @setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    } else {
        if ($domain !== '') {
            $cookie = rawurlencode($name) . '=; Expires=' . gmdate('D, d-M-Y H:i:s T', time() - 3600) . '; Path=/; Domain=' . $domain . '; ' . ($secure ? 'Secure; ' : '') . 'HttpOnly; SameSite=' . $samesite;
            header('Set-Cookie: ' . $cookie, false);
        }
        $cookie2 = rawurlencode($name) . '=; Expires=' . gmdate('D, d-M-Y H:i:s T', time() - 3600) . '; Path=/; ' . ($secure ? 'Secure; ' : '') . 'HttpOnly; SameSite=' . $samesite;
        header('Set-Cookie: ' . $cookie2, false);
    }
}

function removeSessionFilesForUid($uid) {
    $savePath = session_save_path();
    if (!$savePath) {
        logDebug("REMOVE_SESSIONS_NO_SAVE_PATH", ['save_path' => $savePath]);
        return 0;
    }
    if (strpos($savePath, ';') !== false) {
        $parts = explode(';', $savePath, 2);
        $savePath = $parts[1] ?? $savePath;
    }
    $savePath = rtrim($savePath, DIRECTORY_SEPARATOR);
    if (!is_dir($savePath)) {
        logDebug("REMOVE_SESSIONS_SAVE_PATH_NOT_DIR", ['path' => $savePath]);
        return 0;
    }

    $count = 0;
    $files = glob($savePath . DIRECTORY_SEPARATOR . 'sess_*');
    if (!$files) return 0;

    foreach ($files as $f) {
        if (!is_file($f) || !is_readable($f)) continue;
        $content = @file_get_contents($f);
        if ($content === false) continue;

        $uidEsc = preg_quote((string)$uid, '/');
        $patterns = [
            '/uid\|i:'.$uidEsc.';/',
            '/"uid";i:'.$uidEsc.';/',
            '/uid\|s:\d+:"'.$uidEsc.'";/',
            '/s:\d+:"uid";i:'.$uidEsc.';/',
            '/"uid":"'.$uidEsc.'"/',
            '/"uid":\s*'.$uidEsc.'([,\}])/'
        ];
        $match = false;
        foreach ($patterns as $pat) {
            if (preg_match($pat, $content)) { $match = true; break; }
        }
        if ($match) {
            @unlink($f);
            $count++;
        }
    }
    logDebug("REMOVE_SESSIONS_FILES_COUNT", ['uid' => $uid, 'removed' => $count]);
    return $count;
}

function invalidateDbSessionsForUid($uid) {
    try {
        if (!file_exists(__DIR__ . '/includes/db.php')) {
            logDebug("DB_INVALIDATION_SKIPPED", ['reason' => 'no includes/db.php']);
            return 0;
        }
        require_once __DIR__ . '/includes/db.php';
        $pdo = db();

        $affected = 0;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sqlCheck = "SELECT name FROM sqlite_master WHERE type='table' AND name='user_sessions'";
            $exists = (bool)$pdo->query($sqlCheck)->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_sessions'");
            $stmt->execute();
            $exists = ((int)$stmt->fetchColumn() > 0);
        }

        if ($exists) {
            try {
                $upd = $pdo->prepare('UPDATE user_sessions SET revoked = 1 WHERE user_id = ?');
                $upd->execute([$uid]);
                $affected = $upd->rowCount();
                logDebug("DB_INVALIDATED_USER_SESSIONS", ['uid' => $uid, 'affected' => $affected]);
            } catch (Exception $e) {
                try {
                    $del = $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?');
                    $del->execute([$uid]);
                    $affected = $del->rowCount();
                    logDebug("DB_DELETED_USER_SESSIONS", ['uid' => $uid, 'affected' => $affected]);
                } catch (Exception $e2) {
                    logDebug("DB_INVALIDATION_ERROR", ['msg' => $e2->getMessage()]);
                }
            }
        } else {
            logDebug("DB_USER_SESSIONS_TABLE_NOT_FOUND");
        }
        return $affected;
    } catch (Exception $e) {
        logDebug("DB_INVALIDATION_EXCEPTION", ['msg' => $e->getMessage()]);
        return 0;
    }
}

// --- POST: ejecutar logout ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = $_POST['redirect'] ?? 'index.php';
    $allDevices = !empty($_POST['all_devices']) ? true : false;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $currentSid = session_id();
    $uid = $_SESSION['uid'] ?? null;

    logDebug("POST_LOGOUT", ['uid' => $uid, 'sid' => $currentSid, 'allDevices' => $allDevices]);

    // ✅ GUARDAR CARRITO ANTES DE DESTRUIR SESIÓN
    $savedCart = $_SESSION['cart'] ?? [];
    $cartItemCount = 0;
    if (is_array($savedCart)) {
        foreach ($savedCart as $item) {
            $cartItemCount += (int)($item['qty'] ?? 0);
        }
    }
    logDebug("CART_BEFORE_LOGOUT", ['items' => $cartItemCount, 'cart' => $savedCart]);
    
    // Guardar carrito en BD si hay items
    if ($cartItemCount > 0 && $uid) {
        try {
            require_once __DIR__ . '/includes/db.php';
            $pdo = db();

            // Obtener o crear cart_id del usuario
            $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $st->execute([$uid]);
            $cartId = $st->fetchColumn();

            if (!$cartId) {
                $st = $pdo->prepare("INSERT INTO carts (user_id, currency, created_at, updated_at) VALUES (?, 'CRC', datetime('now'), datetime('now'))");
                $st->execute([$uid]);
                $cartId = (int)$pdo->lastInsertId();
                logDebug("CART_CREATED_ON_LOGOUT", ['cart_id' => $cartId, 'uid' => $uid]);
            }

            // Guardar items del carrito en BD
            $groups = $savedCart['groups'] ?? [];
            foreach ($groups as $group) {
                $sale_id = (int)($group['sale_id'] ?? 0);
                $items = $group['items'] ?? [];

                foreach ($items as $item) {
                    $product_id = (int)($item['product_id'] ?? 0);
                    $qty = (float)($item['qty'] ?? 0);
                    $unit_price = (float)($item['unit_price'] ?? 0);

                    if ($product_id > 0 && $qty > 0) {
                        // Verificar si ya existe
                        $check = $pdo->prepare("SELECT id, qty FROM cart_items WHERE cart_id=? AND product_id=? AND sale_id=?");
                        $check->execute([$cartId, $product_id, $sale_id]);
                        $existing = $check->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            // Actualizar cantidad
                            $update = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=?");
                            $update->execute([$qty, $existing['id']]);
                        } else {
                            // Insertar nuevo
                            $insert = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, sale_id, qty, unit_price) VALUES (?, ?, ?, ?, ?)");
                            $insert->execute([$cartId, $product_id, $sale_id, $qty, $unit_price]);
                        }
                    }
                }
            }

            logDebug("CART_SAVED_TO_DB", ['cart_id' => $cartId, 'groups' => count($groups)]);

        } catch (Exception $e) {
            logDebug("CART_SAVE_ERROR", ['error' => $e->getMessage()]);
        }
    }

    if ($allDevices && $uid) {
        $dbAffected = invalidateDbSessionsForUid($uid);
        $filesRemoved = removeSessionFilesForUid($uid);
        logDebug("GLOBAL_LOGOUT_DONE", ['uid' => $uid, 'dbRows' => $dbAffected, 'filesRemoved' => $filesRemoved]);
    }

    logDebug("BEFORE_LOCAL_LOGOUT_SESSION", ['sid' => session_id()]);

    $sessName = session_name();
    $sessId = session_id();

    // Destruir sesión actual
    $_SESSION = [];
    session_unset();
    session_destroy();

    $savePath = session_save_path();
    if ($savePath) {
        if (strpos($savePath, ';') !== false) {
            $parts = explode(';', $savePath, 2);
            $savePath = $parts[1] ?? $savePath;
        }
        $sessFile = rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sess_' . $sessId;
        if (file_exists($sessFile)) {
            @unlink($sessFile);
            logDebug("SESSION_FILE_REMOVED", ['file' => $sessFile]);
        }
    }

    // ✅ CREAR NUEVA SESIÓN DE INVITADO Y RESTAURAR CARRITO
    session_start();
    session_regenerate_id(true);
    
    $_SESSION['cart'] = $savedCart;
    
    $newSid = session_id();
    logDebug("NEW_GUEST_SESSION_CREATED", [
        'new_sid' => $newSid,
        'cart_restored' => $cartItemCount
    ]);

    // Forzar envío de la nueva cookie de sesión
    if (PHP_VERSION_ID >= 70300) {
        setcookie($sessName, $newSid, [
            'expires'  => 0,
            'path'     => '/',
            'domain'   => $cookieDomain ?: '',
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie($sessName, $newSid, 0, '/', $cookieDomain ?: '', $__isHttps, true);
    }

    // Borrar otras cookies de usuario
    $otherCookies = ['vg_session', 'vg_guest'];
    foreach ($otherCookies as $c) {
        if (isset($_COOKIE[$c])) {
            expireCookieClient($c, $cookieDomain ?: '', $__isHttps, true, 'Lax');
            logDebug("COOKIE_CLEARED", ['name' => $c]);
        }
    }

    logDebug("LOGOUT_COMPLETE", ['redirect' => $redirect, 'cart_preserved' => true]);
    header('Location: ' . $redirect);
    exit;
}

// --- GET: UI ---
$redirect = $_GET['redirect'] ?? 'index.php';
$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cerrar sesión - <?= htmlspecialchars($APP_NAME) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial;color:#111;background:linear-gradient(135deg,#f6f8ff 0%,#eef2ff 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:14px;box-shadow:0 20px 50px rgba(12,18,50,0.08);max-width:720px;width:100%;overflow:hidden}
  .card-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:28px;text-align:center}
  .card-header h1{font-size:1.4rem;margin-bottom:6px}
  .card-header p{opacity:0.95;font-size:0.95rem}
  .card-body{padding:28px}
  .desc{color:#374151;margin-bottom:18px;font-size:1rem}
  .actions{display:flex;gap:12px;align-items:center}
  .btn{padding:12px 18px;border-radius:10px;border:0;cursor:pointer;font-weight:700;font-size:0.95rem}
  .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
  .btn-ghost{background:transparent;border:2px solid #e6e7f8;color:#374151;border-radius:10px;padding:10px 16px}
  .checkbox-row{margin-top:18px;display:flex;align-items:center;gap:10px}
  input[type="checkbox"]{width:18px;height:18px}
  @media(max-width:600px){
    .card{margin:12px}
    .actions{flex-direction:column}
    .btn{width:100%}
  }
</style>
</head>
<body>
  <div class="card" role="main" aria-labelledby="logoutTitle">
    <div class="card-header">
      <h1 id="logoutTitle">Cerrar sesión</h1>
      <p>Has iniciado sesión en <?= htmlspecialchars($APP_NAME) ?> — elige cómo quieres salir.</p>
    </div>

    <div class="card-body">
      <p class="desc">Al cerrar sesión se eliminarán tus datos de sesión en este navegador. Tu carrito se mantendrá guardado. Si quieres asegurarte de que la sesión se cierre en todos los dispositivos donde hayas iniciado sesión, marca la opción "Cerrar sesión en todos los dispositivos".</p>

      <form method="post" style="margin-bottom:12px">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div class="checkbox-row">
          <input type="checkbox" id="all_devices" name="all_devices" value="1">
          <label for="all_devices">Cerrar sesión en todos los dispositivos</label>
        </div>

        <div class="actions" style="margin-top:18px">
          <button type="submit" class="btn btn-primary">Cerrar sesión</button>
          <a href="<?= htmlspecialchars($redirect) ?>" class="btn btn-ghost" role="button">Volver</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>