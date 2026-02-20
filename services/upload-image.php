<?php
// services/upload-image.php
// Endpoint para subir imágenes de servicios (con moderación de contenido)
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['agent_id'])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo']);
    exit;
}

$file = $_FILES['image'];

// Validaciones básicas
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo no se subió completo.',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
    ];
    echo json_encode(['ok' => false, 'error' => $errors[$file['error']] ?? 'Error de subida desconocido']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'El archivo supera 5MB.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed)) {
    echo json_encode(['ok' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.']);
    exit;
}

// Carpeta de subida
$uploadDir = __DIR__ . '/../uploads/services/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
$filename = 'svc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo.']);
    exit;
}

// Moderación con Sightengine (opcional, si está configurado)
$sightengineUser   = defined('SIGHTENGINE_API_USER')   ? SIGHTENGINE_API_USER   : '';
$sightengineSecret = defined('SIGHTENGINE_API_SECRET') ? SIGHTENGINE_API_SECRET : '';

if ($sightengineUser && $sightengineSecret) {
    try {
        $ch = curl_init('https://api.sightengine.com/1.0/check.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'models'     => 'nudity-2.0,gore,offensive',
            'api_user'   => $sightengineUser,
            'api_secret' => $sightengineSecret,
            'media'      => new CURLFile($destPath),
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['status']) && $result['status'] === 'success') {
                $nudity  = $result['nudity']['sexual_activity'] ?? 0;
                $gore    = $result['gore']['prob'] ?? 0;
                $offensive = $result['offensive']['prob'] ?? 0;

                if ($nudity > 0.5 || $gore > 0.7 || $offensive > 0.7) {
                    @unlink($destPath);
                    echo json_encode(['ok' => false, 'error' => 'La imagen fue rechazada por el sistema de moderación de contenido.']);
                    exit;
                }
            }
        }
    } catch (Throwable $ignored) {
        // Si falla la moderación, continúa (log silencioso)
        error_log('[services/upload-image.php] Error Sightengine: ' . $ignored->getMessage());
    }
}

// Construir URL pública
$baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$url     = $baseUrl . '/uploads/services/' . $filename;

echo json_encode(['ok' => true, 'url' => $url, 'filename' => $filename]);
