<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// Carga llaves OAuth
$OAUTH = ['google'=>['id'=>'','secret'=>''],'facebook'=>['id'=>'','secret'=>''],'apple'=>[]];
$path = __DIR__ . '/../includes/config.oauth.php';
if (file_exists($path)) { $OAUTH = include $path; }

$provider = strtolower((string)($_GET['provider'] ?? $_POST['provider'] ?? ''));
$state    = (string)($_GET['state'] ?? $_POST['state'] ?? '');
if (!$provider || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
  safe_redirect('/?login=failed', '/');
}

// Helpers HTTP
function http_post_form(string $url, array $fields): array {
  $opts = ['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'content' => http_build_query($fields),
    'timeout' => 15,
  ]];
  $resp = @file_get_contents($url, false, stream_context_create($opts));
  return json_decode($resp ?: '[]', true) ?: [];
}
function http_get_json(string $url): array {
  $resp = @file_get_contents($url);
  return json_decode($resp ?: '[]', true) ?: [];
}

// Garantiza tablas mínimas (por si no se llamó la API del carrito aún)
$pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, name TEXT, phone TEXT, password_hash TEXT, status TEXT DEFAULT 'active', created_at TEXT DEFAULT (datetime('now')));
CREATE TABLE IF NOT EXISTS user_social_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, provider TEXT NOT NULL, provider_user_id TEXT NOT NULL, email TEXT, created_at TEXT DEFAULT (datetime('now')), UNIQUE(provider, provider_user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS carts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, guest_sid TEXT, currency TEXT DEFAULT 'CRC', created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')), UNIQUE(user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE INDEX IF NOT EXISTS idx_carts_guest ON carts(guest_sid);
CREATE TABLE IF NOT EXISTS cart_items (id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id INTEGER NOT NULL, product_id INTEGER NOT NULL, variant_id INTEGER, qty INTEGER NOT NULL, unit_price NUMERIC NOT NULL, tax_rate NUMERIC DEFAULT 0.0, created_at TEXT DEFAULT (datetime('now')), FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_items_cart ON cart_items(cart_id);
");

try {
  $baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
  $redirect = $baseUrl . '/auth/callback.php?provider=' . urlencode($provider);

  $pid = null; $email = null; $name = 'Cliente';

  if ($provider === 'google') {
    $code  = (string)($_GET['code'] ?? '');
    $token = http_post_form('https://oauth2.googleapis.com/token', [
      'code' => $code,
      'client_id' => $OAUTH['google']['id'] ?? '',
      'client_secret' => $OAUTH['google']['secret'] ?? '',
      'redirect_uri' => $redirect,
      'grant_type' => 'authorization_code',
    ]);
    if (empty($token['id_token'])) throw new RuntimeException('id_token vacío');
    $info = http_get_json('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token['id_token']));
    $pid   = $info['sub'] ?? null;
    $email = $info['email'] ?? null;
    $name  = $info['name'] ?? 'Cliente';
  } elseif ($provider === 'facebook') {
    $code = (string)($_GET['code'] ?? '');
    $tok  = http_post_form('https://graph.facebook.com/v19.0/oauth/access_token', [
      'client_id' => $OAUTH['facebook']['id'] ?? '',
      'client_secret' => $OAUTH['facebook']['secret'] ?? '',
      'redirect_uri' => $redirect,
      'code' => $code,
    ]);
    if (empty($tok['access_token'])) throw new RuntimeException('access_token vacío');
    $me = http_get_json('https://graph.facebook.com/me?fields=id,name,email&access_token=' . urlencode($tok['access_token']));
    $pid   = $me['id'] ?? null;
    $email = $me['email'] ?? null;
    $name  = $me['name'] ?? 'Cliente';
  } elseif ($provider === 'apple') {
    // Scaffold: requiere firmar client_secret (ES256) y validar id_token (JWT).
    throw new RuntimeException('Configura firma ES256 para Apple antes de habilitar.');
  } else {
    throw new RuntimeException('Proveedor inválido');
  }

  if (!$pid) throw new RuntimeException('ID proveedor no recibido');

  // Upsert de usuario + vínculo social
  $pdo->beginTransaction();
  if ($email) {
    $st = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
  } else $user = null;

  if (!$user) {
    $st = $pdo->prepare("INSERT INTO users(email,name,status) VALUES(?,?, 'active')");
    $st->execute([$email, $name]);
    $uid = (int)$pdo->lastInsertId();
  } else {
    $uid = (int)$user['id'];
  }

  $pdo->prepare("INSERT INTO user_social_accounts(user_id,provider,provider_user_id,email)
                 VALUES(?,?,?,?)
                 ON CONFLICT(provider,provider_user_id) DO UPDATE SET user_id=excluded.user_id, email=excluded.email")
      ->execute([$uid,$provider,$pid,$email]);

  // Fusionar carrito invitado → usuario
  $sid = $_SESSION['guest_sid'] ?? null;
  if ($sid) {
    $cur = $pdo->prepare("SELECT id FROM carts WHERE user_id=?"); $cur->execute([$uid]); $userCart = $cur->fetchColumn();
    $gst = $pdo->prepare("SELECT id FROM carts WHERE guest_sid=?"); $gst->execute([$sid]); $guestCart = $gst->fetchColumn();

    if ($guestCart && $userCart && (int)$guestCart !== (int)$userCart) {
      $items = $pdo->prepare("SELECT id,product_id,variant_id,qty FROM cart_items WHERE cart_id=?");
      $items->execute([$guestCart]);
      foreach ($items as $it) {
        $sel = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id=? AND product_id=? AND (variant_id IS ? OR variant_id=?)");
        $sel->execute([$userCart, $it['product_id'], $it['variant_id'], $it['variant_id']]);
        $exist = $sel->fetchColumn();
        if ($exist) {
          $pdo->prepare("UPDATE cart_items SET qty=qty+? WHERE id=?")->execute([$it['qty'],$exist]);
          $pdo->prepare("DELETE FROM cart_items WHERE id=?")->execute([$it['id']]);
        } else {
          $pdo->prepare("UPDATE cart_items SET cart_id=? WHERE id=?")->execute([$userCart,$it['id']]);
        }
      }
      $pdo->prepare("DELETE FROM carts WHERE id=?")->execute([$guestCart]);
    } elseif ($guestCart && !$userCart) {
      $pdo->prepare("UPDATE carts SET user_id=?, guest_sid=NULL WHERE id=?")->execute([$uid,$guestCart]);
    }
  }

  $pdo->commit();

  session_regenerate_id(true);
  $_SESSION['uid'] = $uid;
  unset($_SESSION['oauth_state']);

  safe_redirect('/?login=ok', '/');
} catch (Throwable $e) {
  safe_redirect('/?login=failed', '/');
}
