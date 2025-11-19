<?php
/**
 * API: Obtener estado de importación OSM
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_places.php';

try {
    $pdo = db_places();

    // Crear tabla de progreso si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS import_progress (
            id INT PRIMARY KEY DEFAULT 1,
            status ENUM('idle', 'running', 'paused', 'completed', 'error') DEFAULT 'idle',
            current_category VARCHAR(100),
            current_category_index INT DEFAULT 0,
            total_categories INT DEFAULT 24,
            total_imported INT DEFAULT 0,
            progress DECIMAL(5,2) DEFAULT 0,
            message TEXT,
            last_log TEXT,
            started_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CHECK (id = 1)
        )
    ");

    // Obtener o crear registro de progreso
    $stmt = $pdo->query("SELECT * FROM import_progress WHERE id = 1");
    $progress = $stmt->fetch();

    if (!$progress) {
        // Insertar registro inicial
        $pdo->exec("
            INSERT INTO import_progress (id, status, current_category, message)
            VALUES (1, 'idle', 'No iniciado', 'Listo para importar')
        ");
        $stmt = $pdo->query("SELECT * FROM import_progress WHERE id = 1");
        $progress = $stmt->fetch();
    }

    echo json_encode([
        'success' => true,
        'status' => $progress['status'],
        'current_category' => $progress['current_category'] ?? 'N/A',
        'current_category_index' => (int)$progress['current_category_index'],
        'total_categories' => (int)$progress['total_categories'],
        'total_imported' => (int)$progress['total_imported'],
        'progress' => (float)$progress['progress'],
        'message' => $progress['message'],
        'last_log' => $progress['last_log'],
        'started_at' => $progress['started_at']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
