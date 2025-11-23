<?php
/**
 * API para cargar lugares comerciales por tipos seleccionados
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
$config = require __DIR__ . '/../config/database.php';

try {
    // Conectar a la base de datos
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        $config['options']
    );

    // Verificar que se recibieron tipos
    if (!isset($_POST['tipos']) || !is_array($_POST['tipos']) || empty($_POST['tipos'])) {
        echo json_encode([
            'success' => false,
            'error' => 'No se recibieron tipos'
        ]);
        exit;
    }

    $tipos = $_POST['tipos'];

    // Construir query con placeholders
    $placeholders = str_repeat('?,', count($tipos) - 1) . '?';

    $sql = "
        SELECT
            id,
            nombre,
            tipo,
            categoria,
            email,
            telefono,
            direccion,
            ciudad,
            provincia
        FROM lugares_comerciales
        WHERE tipo IN ($placeholders)
            AND email IS NOT NULL
            AND email != ''
        ORDER BY nombre
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($tipos);
    $lugares = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'lugares' => $lugares,
        'count' => count($lugares)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
