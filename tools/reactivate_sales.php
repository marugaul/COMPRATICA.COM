<?php
/**
 * ============================================================================
 * SCRIPT SIMPLE DE REACTIVACIÓN DE VENTAS
 * ============================================================================
 * 1. Hace backup de la tabla sales
 * 2. Reactiva todas las ventas (is_active = 1)
 * 3. Actualiza fechas: desde ayer hasta 3 meses (Feb 2026)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    
    echo "==========================================\n";
    echo "REACTIVACIÓN DE VENTAS\n";
    echo "==========================================\n\n";
    
    // ========================================
    // PASO 1: BACKUP
    // ========================================
    echo "📦 Creando backup...\n";
    
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/sales_backup_' . $timestamp . '.sql';
    
    $stmt = $pdo->query("SELECT * FROM sales");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sqlBackup = "-- Backup tabla sales - " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($sales as $sale) {
        $id = (int)$sale['id'];
        $affiliate_id = (int)$sale['affiliate_id'];
        $title = $pdo->quote($sale['title']);
        $cover_image = $sale['cover_image'] ? $pdo->quote($sale['cover_image']) : 'NULL';
        $start_at = $pdo->quote($sale['start_at']);
        $end_at = $pdo->quote($sale['end_at']);
        $is_active = (int)$sale['is_active'];
        $created_at = $sale['created_at'] ? $pdo->quote($sale['created_at']) : 'NULL';
        $updated_at = $sale['updated_at'] ? $pdo->quote($sale['updated_at']) : 'NULL';
        
        $sqlBackup .= "INSERT INTO sales (id, affiliate_id, title, cover_image, start_at, end_at, is_active, created_at, updated_at) ";
        $sqlBackup .= "VALUES ({$id}, {$affiliate_id}, {$title}, {$cover_image}, {$start_at}, {$end_at}, {$is_active}, {$created_at}, {$updated_at});\n";
    }
    
    file_put_contents($backupFile, $sqlBackup);
    echo "✅ Backup guardado: {$backupFile}\n";
    echo "   Registros: " . count($sales) . "\n\n";
    
    // ========================================
    // PASO 2: CALCULAR FECHAS
    // ========================================
    // Fecha inicio: ayer
    $startDate = date('Y-m-d', strtotime('-1 day'));
    
    // Fecha fin: 3 meses desde ayer (Feb 2026)
    $endDate = date('Y-m-d', strtotime('-1 day +3 months'));
    
    echo "📅 Nuevas fechas:\n";
    echo "   Inicio: {$startDate}\n";
    echo "   Fin: {$endDate}\n\n";
    
    // ========================================
    // PASO 3: UPDATE - REACTIVAR Y ACTUALIZAR FECHAS
    // ========================================
    echo "🚀 Reactivando ventas y actualizando fechas...\n";
    
    $updateStmt = $pdo->prepare("
        UPDATE sales 
        SET 
            is_active = 1,
            start_at = :start_at,
            end_at = :end_at,
            updated_at = datetime('now', 'localtime')
    ");
    
    $updateStmt->execute([
        ':start_at' => $startDate,
        ':end_at' => $endDate
    ]);
    
    $affectedRows = $updateStmt->rowCount();
    
    echo "✅ Ventas actualizadas: {$affectedRows}\n\n";
    
    // ========================================
    // PASO 4: VERIFICACIÓN
    // ========================================
    echo "🔍 Verificación:\n";
    
    $verifyStmt = $pdo->query("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activas,
               SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactivas
        FROM sales
    ");
    $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Total ventas: {$result['total']}\n";
    echo "   ✅ Activas: {$result['activas']}\n";
    echo "   ❌ Inactivas: {$result['inactivas']}\n\n";
    
    // Mostrar algunas ventas de ejemplo
    echo "📋 Ejemplo de ventas actualizadas:\n";
    $exampleStmt = $pdo->query("
        SELECT id, title, start_at, end_at, is_active 
        FROM sales 
        LIMIT 5
    ");
    $examples = $exampleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($examples as $ex) {
        $status = $ex['is_active'] ? '✅' : '❌';
        echo "   {$status} ID {$ex['id']}: {$ex['title']}\n";
        echo "      Inicio: {$ex['start_at']} | Fin: {$ex['end_at']}\n";
    }
    
    echo "\n==========================================\n";
    echo "✨ PROCESO COMPLETADO\n";
    echo "==========================================\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}