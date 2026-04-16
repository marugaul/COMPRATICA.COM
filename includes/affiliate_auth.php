<?php
/**
 * Autenticación de Afiliados (Venta de Garaje)
 * Sistema SEPARADO de emprendedoras/usuarios — usa tabla affiliates, no users.
 */
// Configurar ruta de sesión ANTES de session_start
// (debe coincidir con la lógica de affiliate/login.php para que la sesión persista entre páginas)
if (session_status() === PHP_SESSION_NONE) {
    $__affSessPath = __DIR__ . '/../sessions';
    if (!is_dir($__affSessPath)) @mkdir($__affSessPath, 0755, true);
    if (is_dir($__affSessPath) && is_writable($__affSessPath)) {
        ini_set('session.save_path', $__affSessPath);
    } else {
        ini_set('session.save_path', '/tmp');
    }
}
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';

function aff_logged_in() {
    return !empty($_SESSION['aff_id']);
}

function aff_require_login() {
    if (!aff_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Autenticar afiliado con email y contraseña (tabla affiliates, NO users).
 */
function authenticate_affiliate(string $email, string $password) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $aff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aff) return false;
    if (!password_verify($password, $aff['password_hash'])) return false;
    if ((int)($aff['is_active'] ?? 1) !== 1) {
        throw new RuntimeException('Tu cuenta de afiliado no está activa.');
    }
    return $aff;
}

/**
 * Obtener o crear afiliado via OAuth (tabla affiliates, NO users).
 */
function get_or_create_oauth_affiliate(string $email, string $name, string $provider, string $oauthId) {
    $pdo = db();

    // Buscar por oauth_id + provider primero
    $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1");
    $stmt->execute([$provider, $oauthId]);
    $aff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aff) return $aff;

    // Buscar por email
    $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $aff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aff) {
        // Actualizar datos oauth
        $pdo->prepare("UPDATE affiliates SET oauth_provider=?, oauth_id=?, name=?, updated_at=datetime('now') WHERE id=?")
            ->execute([$provider, $oauthId, $name, $aff['id']]);
        $aff['oauth_provider'] = $provider;
        $aff['oauth_id'] = $oauthId;
        return $aff;
    }

    // Crear nuevo afiliado
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO affiliates (name, email, phone, password_hash, oauth_provider, oauth_id, is_active, created_at, updated_at)
        VALUES (?, ?, '', ?, ?, ?, 1, datetime('now'), datetime('now'))
    ")->execute([$name, $email, $hash, $provider, $oauthId]);

    $newId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ? LIMIT 1");
    $stmt->execute([$newId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Iniciar sesión como afiliado.
 * Escribe aff_id/aff_name/aff_email en la sesión existente SIN regenerar el ID
 * ni destruir el archivo de sesión.  Esto evita que el inicio de sesión de afiliado
 * cierre la sesión del sitio principal (uid/user_id) cuando ambas comparten PHPSESSID.
 */
function login_affiliate(array $aff): void {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $_SESSION['aff_id']    = (int)$aff['id'];
    $_SESSION['aff_name']  = (string)$aff['name'];
    $_SESSION['aff_email'] = (string)$aff['email'];
    // Conservar uid/user_id para que el usuario siga logueado en el sitio principal.
    // session_write_close() escribe el archivo en disco ANTES de que PHP envíe la
    // respuesta HTTP — esto evita la condición de carrera en PHP-FPM donde el cliente
    // puede llegar al dashboard antes de que el shutdown de PHP haya escrito la sesión.
    session_write_close();
}

/**
 * Obtener afiliado actual de sesión.
 */
function get_session_affiliate(): ?array {
    if (!aff_logged_in()) return null;
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['aff_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Cerrar sesión de afiliado.
 */
function logout_affiliate(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['aff_id'], $_SESSION['aff_name'], $_SESSION['aff_email']);
    session_write_close();
}
?>
