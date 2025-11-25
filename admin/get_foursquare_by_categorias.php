<?php
/**
 * API para obtener lugares de Foursquare por categorías
 * Usado en Nueva Campaña de Email Marketing
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar que la tabla existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_foursquare'")->fetch();
    if (!$check) {
        echo json_encode(['success' => false, 'error' => 'Tabla lugares_foursquare no existe']);
        exit;
    }

    // Obtener categorías seleccionadas
    $categorias = $_POST['categorias'] ?? [];

    if (empty($categorias)) {
        echo json_encode(['success' => false, 'error' => 'No se seleccionaron categorías']);
        exit;
    }

    // Construir query con placeholders
    $placeholders = implode(',', array_fill(0, count($categorias), '?'));

    $sql = "
        SELECT id, foursquare_id, nombre, categoria, telefono, email, website, direccion, ciudad, provincia
        FROM lugares_foursquare
        WHERE categoria IN ($placeholders)
          AND email IS NOT NULL
          AND email != ''
        ORDER BY nombre
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($categorias);
    $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($lugares),
        'lugares' => $lugares
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
