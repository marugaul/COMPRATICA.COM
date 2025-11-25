<?php
/**
 * API para obtener lugares de Yelp por categorías
 * Para uso en Nueva Campaña
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar que la tabla existe
    $check = $pdo->query("SHOW TABLES LIKE 'lugares_yelp'")->fetch();
    if (!$check) {
        echo json_encode(['success' => false, 'error' => 'Tabla lugares_yelp no existe']);
        exit;
    }

    $categorias = $_POST['categorias'] ?? [];

    if (empty($categorias)) {
        echo json_encode(['success' => false, 'error' => 'No se especificaron categorías']);
        exit;
    }

    // Construir query
    $placeholders = implode(',', array_fill(0, count($categorias), '?'));
    $sql = "SELECT id, nombre, categoria, telefono, email, ciudad, rating
            FROM lugares_yelp
            WHERE categoria IN ($placeholders)
            ORDER BY rating DESC, nombre
            LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($categorias);
    $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'lugares' => $lugares,
        'count' => count($lugares)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
