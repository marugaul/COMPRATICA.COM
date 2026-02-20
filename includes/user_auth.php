<?php
/**
 * Sistema de autenticación unificado
 * Maneja usuarios de todos los módulos: Bienes Raíces, Servicios, Empleos, Afiliados
 */

require_once __DIR__ . '/db.php';

/**
 * Autenticar usuario con email y contraseña
 * @return array|false Usuario si es válido, false si no
 */
function authenticate_user($email, $password) {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Verificar que esté activo
    if ((int)($user['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('Tu cuenta no está activa.');
    }

    return $user;
}

/**
 * Obtener usuario por email
 */
function get_user_by_email($email) {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener usuario por ID
 */
function get_user_by_id($id) {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtener usuario por slug (para afiliados)
 */
function get_user_by_slug($slug) {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crear nuevo usuario
 * @return int ID del usuario creado
 */
function create_user($data) {
    $pdo = db();

    // Campos requeridos
    $required = ['name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new RuntimeException("El campo '$field' es requerido.");
        }
    }

    // Verificar si el email ya existe
    if (get_user_by_email($data['email'])) {
        throw new RuntimeException('Ya existe una cuenta con este correo.');
    }

    // Hash de la contraseña
    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Preparar datos
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, phone, password_hash,
            company_name, company_description, company_logo, website,
            slug, avatar, fee_pct, offers_products, offers_services, business_description,
            license_number, specialization, bio, profile_image,
            facebook, instagram, whatsapp,
            is_active, created_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, datetime('now')
        )
    ");

    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'] ?? null,
        $passwordHash,
        $data['company_name'] ?? null,
        $data['company_description'] ?? null,
        $data['company_logo'] ?? null,
        $data['website'] ?? null,
        $data['slug'] ?? null,
        $data['avatar'] ?? null,
        $data['fee_pct'] ?? 0.10,
        $data['offers_products'] ?? 0,
        $data['offers_services'] ?? 0,
        $data['business_description'] ?? null,
        $data['license_number'] ?? null,
        $data['specialization'] ?? null,
        $data['bio'] ?? null,
        $data['profile_image'] ?? null,
        $data['facebook'] ?? null,
        $data['instagram'] ?? null,
        $data['whatsapp'] ?? null,
        $data['is_active'] ?? 1
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Obtener o crear usuario desde OAuth (Google, Facebook, etc.)
 * @return array Usuario
 */
function get_or_create_oauth_user($email, $name, $provider = 'google', $oauth_id = null) {
    $pdo = db();

    // Buscar usuario existente por email
    $user = get_user_by_email($email);

    if ($user) {
        // Actualizar información de OAuth si no existe
        if (empty($user['oauth_provider'])) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET oauth_provider = ?, oauth_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$provider, $oauth_id, $user['id']]);
            $user['oauth_provider'] = $provider;
            $user['oauth_id'] = $oauth_id;
        }
        return $user;
    }

    // Crear nuevo usuario
    // Para usuarios OAuth, generar una contraseña aleatoria (no se usará)
    $randomPassword = bin2hex(random_bytes(16));

    $userId = create_user([
        'name' => $name,
        'email' => $email,
        'password' => $randomPassword,
        'is_active' => 1
    ]);

    // Actualizar con información de OAuth
    $stmt = $pdo->prepare("
        UPDATE users
        SET oauth_provider = ?, oauth_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$provider, $oauth_id, $userId]);

    return get_user_by_id($userId);
}

/**
 * Iniciar sesión unificada
 * Establece las variables de sesión necesarias
 */
function login_user($user) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_regenerate_id(true);

    // Variables de sesión unificadas
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];

    // Para compatibilidad con código antiguo
    $_SESSION['uid'] = (int)$user['id'];
    $_SESSION['name'] = (string)$user['name'];

    // Para dashboards específicos (mantener compatibilidad)
    $_SESSION['agent_id'] = (int)$user['id'];   // Para bienes raíces y servicios
    $_SESSION['agent_name'] = (string)$user['name'];
    $_SESSION['employer_id'] = (int)$user['id']; // Para empleos
    $_SESSION['employer_name'] = (string)$user['name'];
    $_SESSION['aff_id'] = (int)$user['id'];      // Para afiliados
}

/**
 * Verificar si el usuario está autenticado
 */
function is_user_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return !empty($_SESSION['user_id']);
}

/**
 * Obtener usuario actual de la sesión
 */
function get_session_user() {
    if (!is_user_logged_in()) {
        return null;
    }

    return get_user_by_id($_SESSION['user_id']);
}

/**
 * Cerrar sesión
 */
function logout_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Limpiar todas las variables de sesión
    $_SESSION = [];

    // Destruir la sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Actualizar información del usuario
 */
function update_user($userId, $data) {
    $pdo = db();

    // Campos permitidos para actualizar
    $allowedFields = [
        'name', 'phone', 'company_name', 'company_description', 'company_logo',
        'website', 'slug', 'avatar', 'fee_pct', 'offers_products', 'offers_services',
        'business_description', 'license_number', 'specialization', 'bio',
        'profile_image', 'facebook', 'instagram', 'whatsapp', 'is_active'
    ];

    $updates = [];
    $values = [];

    foreach ($data as $field => $value) {
        if (in_array($field, $allowedFields)) {
            $updates[] = "$field = ?";
            $values[] = $value;
        }
    }

    if (empty($updates)) {
        return false;
    }

    $values[] = $userId;

    $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = datetime('now') WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

/**
 * Validar email
 */
function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar y limpiar teléfono
 */
function clean_phone($phone) {
    $phone = trim((string)$phone);
    if (!preg_match('/^[0-9 \-\+\(\)]{7,20}$/', $phone)) {
        return false;
    }
    return $phone;
}
