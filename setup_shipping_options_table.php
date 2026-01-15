<?php
/**
 * Script para crear la tabla affiliate_shipping_options
 * Permite a los afiliados configurar qué opciones de envío quieren ofrecer
 */

require_once __DIR__ . '/includes/db.php';

$pdo = db();

try {
    // Crear tabla si no existe
    $sql = "
    CREATE TABLE IF NOT EXISTS affiliate_shipping_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        enable_pickup INTEGER DEFAULT 1,
        enable_free_shipping INTEGER DEFAULT 0,
        enable_uber INTEGER DEFAULT 0,
        pickup_instructions TEXT DEFAULT NULL,
        free_shipping_min_amount REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        updated_at TEXT DEFAULT (datetime('now','localtime')),
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "✅ Tabla 'affiliate_shipping_options' creada exitosamente.\n\n";

    // Crear índice
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_aff_shipping_options ON affiliate_shipping_options(affiliate_id)");
    echo "✅ Índice creado exitosamente.\n\n";

    // Verificar estructura
    $stmt = $pdo->query("PRAGMA table_info(affiliate_shipping_options)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 Estructura de la tabla:\n";
    echo "Columna                    | Tipo    | Nullable | Default\n";
    echo "---------------------------|---------|----------|------------------\n";
    foreach ($columns as $col) {
        printf("%-26s | %-7s | %-8s | %s\n",
            $col['name'],
            $col['type'],
            $col['notnull'] ? 'NO' : 'YES',
            $col['dflt_value'] ?? 'NULL'
        );
    }

    echo "\n✅ ¡Listo! La tabla está configurada correctamente.\n";
    echo "\nOpciones disponibles:\n";
    echo "  - enable_pickup: Recoger en tienda (por defecto: activado)\n";
    echo "  - enable_free_shipping: Envío gratis (por defecto: desactivado)\n";
    echo "  - enable_uber: Envío por Uber (por defecto: desactivado)\n";
    echo "  - pickup_instructions: Instrucciones para recoger en tienda\n";
    echo "  - free_shipping_min_amount: Monto mínimo para envío gratis\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
