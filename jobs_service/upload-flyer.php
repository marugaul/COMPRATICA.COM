<?php
// jobs_service/upload-flyer.php
// Endpoint para subir flyer promocional de empleos/servicios
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error interno: ' . $err['message']]);
    }
});

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

ob_clean();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

$employer_id = (int)($_SESSION['employer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
$isAdmin = !empty($_SESSION['is_admin']);

if ($employer_id <= 0 && !$isAdmin) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['flyer']) || $_FILES['flyer']['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE  => 'El archivo supera el límite del servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite del formulario.',
        UPLOAD_ERR_PARTIAL   => 'El archivo no se subió completo.',
        UPLOAD_ERR_NO_FILE   => 'No se seleccionó ningún archivo.',
    ];
    $code = $_FILES['flyer']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'error' => $errors[$code] ?? 'Error de subida desconocido']);
    exit;
}

$file = $_FILES['flyer'];

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'El archivo supera 5MB.']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} else {
    $tempInfo = @getimagesize($file['tmp_name']);
    $mime = $tempInfo ? ($tempInfo['mime'] ?? 'application/octet-stream') : 'application/octet-stream';
}

if (!in_array($mime, $allowed)) {
    echo json_encode(['ok' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/flyers/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
$filename = 'flyer_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo.']);
    exit;
}

$url = '/uploads/flyers/' . $filename;

echo json_encode(['ok' => true, 'url' => $url, 'filename' => $filename]);
