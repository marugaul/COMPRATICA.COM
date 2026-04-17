<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/image-moderation.php';

$pdo = db();

ob_clean(); // discard any stray output from includes
header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar autenticación — acepta agentes (agent_id) o usuarios regulares (uid/user_id)
$agentId = (int)($_SESSION['agent_id'] ?? $_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0);
if ($agentId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Verificar CSRF si está presente
if (isset($_POST['csrf_token']) && $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

// Obtener el plan del usuario si se proporciona
$pricingPlanId = isset($_POST['pricing_plan_id']) ? (int)$_POST['pricing_plan_id'] : 0;

// Obtener el límite de fotos del plan desde la base de datos
$maxPhotos = 3; // Valor por defecto
if ($pricingPlanId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT max_photos FROM listing_pricing WHERE id = ? LIMIT 1");
        $stmt->execute([$pricingPlanId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($plan && isset($plan['max_photos'])) {
            $maxPhotos = (int)$plan['max_photos'];
        }
    } catch (Throwable $e) {
        error_log('[upload-image.php] Error al obtener límite de fotos: ' . $e->getMessage());
    }
}

// Contar cuántas imágenes se están subiendo actualmente
$currentImagesCount = isset($_POST['current_images_count']) ? (int)$_POST['current_images_count'] : 0;

// Verificar que no se exceda el límite
if (!isset($_FILES['images'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron archivos']);
    exit;
}

$files = $_FILES['images'];
$uploadCount = is_array($files['name']) ? count($files['name']) : 1;

if ($currentImagesCount + $uploadCount > $maxPhotos) {
    echo json_encode([
        'success' => false,
        'error' => "Tu plan permite máximo $maxPhotos fotos. Ya tienes $currentImagesCount imagen(es)."
    ]);
    exit;
}

// Configuración
$uploadDir = __DIR__ . '/uploads/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 10 * 1024 * 1024; // 10MB

// Crear directorio si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Inicializar moderador de imágenes
$moderator = new ImageModeration();

// Resultado
$uploadedImages = [];
$errors = [];

// Procesar archivos
if (is_array($files['name'])) {
    // Múltiples archivos
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        $result = processUpload(
            $files['tmp_name'][$i],
            $files['name'][$i],
            $files['type'][$i],
            $files['size'][$i],
            $files['error'][$i],
            $uploadDir,
            $allowedTypes,
            $maxFileSize,
            $moderator,
            $agentId
        );

        if ($result['success']) {
            $uploadedImages[] = $result['url'];
        } else {
            $errors[] = $result['error'];
        }
    }
} else {
    // Un solo archivo
    $result = processUpload(
        $files['tmp_name'],
        $files['name'],
        $files['type'],
        $files['size'],
        $files['error'],
        $uploadDir,
        $allowedTypes,
        $maxFileSize,
        $moderator,
        $agentId
    );

    if ($result['success']) {
        $uploadedImages[] = $result['url'];
    } else {
        $errors[] = $result['error'];
    }
}

// Respuesta
if (count($uploadedImages) > 0) {
    echo json_encode([
        'success' => true,
        'images' => $uploadedImages,
        'errors' => $errors,
        'moderation_status' => $moderator->getStatus()
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo subir ninguna imagen',
        'details' => $errors
    ]);
}

/**
 * Procesa la subida de un archivo
 */
function processUpload($tmpName, $name, $type, $size, $error, $uploadDir, $allowedTypes, $maxFileSize, $moderator, $agentId) {
    // Verificar errores de upload
    if ($error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => "Error al subir archivo: $name (código: $error)"];
    }

    // Verificar tamaño
    if ($size > $maxFileSize) {
        $maxMB = $maxFileSize / 1024 / 1024;
        return ['success' => false, 'error' => "Archivo muy grande: $name. Máximo {$maxMB}MB"];
    }

    // Detectar MIME real desde el contenido del archivo (no confiar en $_FILES['type'])
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!in_array($realMime, $allowedTypes)) {
        return ['success' => false, 'error' => "Tipo de archivo no permitido: $name. Use JPG, PNG, GIF o WebP"];
    }

    // Validar que sea una imagen real
    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => "El archivo no es una imagen válida: $name"];
    }

    // MODERACIÓN DE CONTENIDO
    $moderationResult = $moderator->validateImage($tmpName);

    if (!$moderationResult['approved']) {
        error_log("Imagen rechazada por moderación - Agente: $agentId - Archivo: $name - Razón: {$moderationResult['reason']}");

        return [
            'success' => false,
            'error' => "Imagen rechazada: {$moderationResult['reason']}"
        ];
    }

    // Derivar extensión desde el MIME real (nunca del nombre original)
    $mimeExtMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $extension  = $mimeExtMap[$realMime] ?? 'jpg';
    $newFilename = uniqid('img_' . $agentId . '_') . '.' . $extension;
    $targetPath = $uploadDir . $newFilename;

    // Mover archivo
    if (!move_uploaded_file($tmpName, $targetPath)) {
        return ['success' => false, 'error' => "Error al guardar archivo: $name"];
    }

    // Optimizar imagen (reducir tamaño si es muy grande)
    optimizeImage($targetPath, $imageInfo[2]);

    // Generar URL relativa
    $url = '/real-estate/uploads/' . $newFilename;

    return [
        'success' => true,
        'url' => $url,
        'filename' => $newFilename,
        'moderation' => $moderationResult
    ];
}

/**
 * Optimiza una imagen para reducir su tamaño sin perder mucha calidad
 */
function optimizeImage($path, $imageType) {
    $maxWidth = 1920;
    $maxHeight = 1080;
    $quality = 85;

    // Cargar imagen según tipo
    $image = null;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($path);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($path);
            break;
        case IMAGETYPE_GIF:
            $image = @imagecreatefromgif($path);
            break;
        case IMAGETYPE_WEBP:
            $image = @imagecreatefromwebp($path);
            break;
    }

    if (!$image) {
        return; // No se pudo cargar la imagen
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Calcular nuevas dimensiones si es necesario
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Redimensionar
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG y GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }

    // Guardar imagen optimizada
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            imagejpeg($image, $path, $quality);
            break;
        case IMAGETYPE_PNG:
            // PNG usa compresión 0-9 (0=sin compresión, 9=máxima)
            $pngQuality = (int)((100 - $quality) / 10);
            imagepng($image, $path, $pngQuality);
            break;
        case IMAGETYPE_GIF:
            imagegif($image, $path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($image, $path, $quality);
            break;
    }

    imagedestroy($image);
}
