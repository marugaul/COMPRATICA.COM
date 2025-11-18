<?php
/**
 * MIGRACIÓN: Integración completa de Uber Direct
 *
 * Este script agrega:
 * 1. Campo uber_commission_percentage en settings (10%)
 * 2. Campos de peso, tamaño y tipo de vehículo en products
 * 3. Campos de ubicación geográfica en users (provincia, cantón, distrito)
 * 4. Tablas de Uber (uber_config, uber_deliveries, sale_pickup_locations)
 * 5. Credenciales de Uber en uber_config
 *
 * USO: php uber/migrate_uber_integration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

echo "==============================================\n";
echo "MIGRACIÓN: Integración Uber Direct\n";
echo "==============================================\n\n";

try {
    $pdo = db();
    $pdo->beginTransaction();

    // ==================================================
    // 1. AGREGAR uber_commission_percentage A SETTINGS
    // ==================================================
    echo "[1/6] Actualizando tabla settings...\n";

    $cols = $pdo->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    $hasUberCommission = false;
    foreach ($cols as $col) {
        if (strtolower($col['name']) === 'uber_commission_percentage') {
            $hasUberCommission = true;
            break;
        }
    }

    if (!$hasUberCommission) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN uber_commission_percentage REAL DEFAULT 10.0");
        echo "  ✓ Campo uber_commission_percentage agregado (10%)\n";
    } else {
        echo "  ⚠ Campo uber_commission_percentage ya existe\n";
    }

    // Asegurar que el valor esté configurado
    $pdo->exec("UPDATE settings SET uber_commission_percentage = 10.0 WHERE id = 1 AND uber_commission_percentage IS NULL");

    // ==================================================
    // 2. AGREGAR CAMPOS DE PESO/TAMAÑO A PRODUCTS
    // ==================================================
    echo "\n[2/6] Actualizando tabla products...\n";

    $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
    $existingCols = array_column($cols, 'name');
    $existingCols = array_map('strtolower', $existingCols);

    $fieldsToAdd = [
        'weight_kg' => 'REAL DEFAULT 0',           // Peso en kilogramos
        'size_cm_length' => 'REAL DEFAULT 0',      // Largo en cm
        'size_cm_width' => 'REAL DEFAULT 0',       // Ancho en cm
        'size_cm_height' => 'REAL DEFAULT 0',      // Alto en cm
        'uber_vehicle_type' => "TEXT DEFAULT 'auto'" // auto, moto, suv, bike
    ];

    foreach ($fieldsToAdd as $field => $definition) {
        if (!in_array(strtolower($field), $existingCols)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN $field $definition");
            echo "  ✓ Campo $field agregado\n";
        } else {
            echo "  ⚠ Campo $field ya existe\n";
        }
    }

    // ==================================================
    // 3. AGREGAR CAMPOS DE UBICACIÓN A USERS
    // ==================================================
    echo "\n[3/6] Actualizando tabla users...\n";

    // Verificar si la tabla users existe
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($tables)) {
        $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        $existingCols = array_column($cols, 'name');
        $existingCols = array_map('strtolower', $existingCols);

        $locationFields = [
            'provincia' => 'TEXT',
            'canton' => 'TEXT',
            'distrito' => 'TEXT',
            'direccion_completa' => 'TEXT',
            'lat' => 'REAL',
            'lng' => 'REAL'
        ];

        foreach ($locationFields as $field => $definition) {
            if (!in_array(strtolower($field), $existingCols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $field $definition");
                echo "  ✓ Campo $field agregado\n";
            } else {
                echo "  ⚠ Campo $field ya existe\n";
            }
        }
    } else {
        echo "  ⚠ Tabla users no existe aún (se creará cuando se registre el primer usuario)\n";
    }

    // ==================================================
    // 4. CREAR TABLAS DE UBER
    // ==================================================
    echo "\n[4/6] Creando tablas de Uber...\n";

    // Crear tabla sale_pickup_locations
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_pickup_locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER NOT NULL,
        affiliate_id INTEGER NOT NULL,
        address TEXT NOT NULL,
        address_line2 TEXT,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        country TEXT DEFAULT 'Costa Rica',
        postal_code TEXT,
        lat REAL,
        lng REAL,
        contact_name TEXT NOT NULL,
        contact_phone TEXT NOT NULL,
        pickup_instructions TEXT,
        is_active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )");
    echo "  ✓ Tabla sale_pickup_locations creada\n";

    // Crear tabla uber_deliveries
    $pdo->exec("CREATE TABLE IF NOT EXISTS uber_deliveries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        order_number TEXT NOT NULL,
        sale_id INTEGER NOT NULL,
        affiliate_id INTEGER NOT NULL,
        pickup_location_id INTEGER,
        pickup_address TEXT NOT NULL,
        pickup_address_line2 TEXT,
        pickup_city TEXT,
        pickup_state TEXT,
        pickup_postal_code TEXT,
        pickup_lat REAL,
        pickup_lng REAL,
        pickup_contact_name TEXT,
        pickup_contact_phone TEXT,
        pickup_instructions TEXT,
        delivery_address TEXT NOT NULL,
        delivery_address_line2 TEXT,
        delivery_city TEXT,
        delivery_state TEXT,
        delivery_postal_code TEXT,
        delivery_lat REAL,
        delivery_lng REAL,
        delivery_contact_name TEXT,
        delivery_contact_phone TEXT,
        delivery_instructions TEXT,
        uber_quote_id TEXT,
        uber_delivery_id TEXT,
        uber_tracking_url TEXT,
        uber_courier_name TEXT,
        uber_courier_phone TEXT,
        uber_courier_img TEXT,
        uber_courier_vehicle TEXT,
        uber_courier_license_plate TEXT,
        uber_base_cost REAL DEFAULT 0,
        uber_currency TEXT DEFAULT 'CRC',
        platform_commission REAL DEFAULT 0,
        total_shipping_cost REAL DEFAULT 0,
        commission_percentage REAL DEFAULT 10,
        status TEXT DEFAULT 'pending',
        quoted_at TEXT,
        confirmed_at TEXT,
        scheduled_at TEXT,
        courier_assigned_at TEXT,
        estimated_pickup_time TEXT,
        actual_pickup_time TEXT,
        estimated_delivery_time TEXT,
        actual_delivery_time TEXT,
        delivery_notes TEXT,
        cancellation_reason TEXT,
        failure_reason TEXT,
        uber_quote_response TEXT,
        uber_delivery_response TEXT,
        uber_webhook_data TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
        FOREIGN KEY (pickup_location_id) REFERENCES sale_pickup_locations(id) ON DELETE SET NULL
    )");
    echo "  ✓ Tabla uber_deliveries creada\n";

    // Crear tabla uber_config
    $pdo->exec("CREATE TABLE IF NOT EXISTS uber_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER,
        client_id TEXT,
        client_secret TEXT,
        customer_id TEXT,
        is_sandbox INTEGER DEFAULT 1,
        commission_percentage REAL DEFAULT 10.0,
        access_token TEXT,
        token_expires_at TEXT,
        is_active INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )");
    echo "  ✓ Tabla uber_config creada\n";

    // Crear índices
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_uber_deliveries_order ON uber_deliveries(order_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_uber_deliveries_sale ON uber_deliveries(sale_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_uber_deliveries_status ON uber_deliveries(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_uber_deliveries_affiliate ON uber_deliveries(affiliate_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pickup_locations_sale ON sale_pickup_locations(sale_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pickup_locations_active ON sale_pickup_locations(is_active)");
        echo "  ✓ Índices creados\n";
    } catch (Exception $e) {
        echo "  ⚠️  Algunos índices no se pudieron crear (no crítico)\n";
    }

    // ==================================================
    // 5. INSERTAR CREDENCIALES DE UBER
    // ==================================================
    echo "\n[5/6] Configurando credenciales de Uber...\n";

    // Verificar si ya existe configuración global
    $existing = $pdo->query("SELECT COUNT(*) FROM uber_config WHERE affiliate_id IS NULL")->fetchColumn();

    if ($existing == 0) {
        // Insertar configuración global con credenciales
        $stmt = $pdo->prepare("
            INSERT INTO uber_config (
                id,
                affiliate_id,
                client_id,
                client_secret,
                customer_id,
                is_sandbox,
                commission_percentage,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                1,
                NULL,
                :client_id,
                :client_secret,
                :customer_id,
                1,
                10.0,
                1,
                datetime('now'),
                datetime('now')
            )
        ");

        $stmt->execute([
            'client_id' => 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O',
            'client_secret' => 'UBR:EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_',
            'customer_id' => 'af3e1e84-ea00-4be1-af4c-5bd162a31a34'
        ]);

        echo "  ✓ Credenciales de Uber insertadas\n";
        echo "  ✓ Customer ID configurado: af3e1e84-ea00-4be1-af4c-5bd162a31a34\n";
    } else {
        echo "  ⚠ Configuración de Uber ya existe\n";

        // Actualizar credenciales
        $pdo->exec("
            UPDATE uber_config
            SET client_id = 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O',
                client_secret = 'UBR:EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_',
                customer_id = 'af3e1e84-ea00-4be1-af4c-5bd162a31a34',
                commission_percentage = 10.0,
                is_sandbox = 1,
                updated_at = datetime('now')
            WHERE affiliate_id IS NULL
        ");
        echo "  ✓ Credenciales actualizadas\n";
    }

    // ==================================================
    // 6. VERIFICACIÓN FINAL
    // ==================================================
    echo "\n[6/6] Verificación final...\n";

    // Verificar settings
    $settings = $pdo->query("SELECT uber_commission_percentage FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    echo "  ✓ Comisión admin configurada: " . ($settings['uber_commission_percentage'] ?? 'ERROR') . "%\n";

    // Verificar uber_config
    $uberConfig = $pdo->query("SELECT client_id, customer_id, is_sandbox FROM uber_config WHERE affiliate_id IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($uberConfig) {
        echo "  ✓ Uber config encontrada\n";
        echo "    - client_id: " . substr($uberConfig['client_id'], 0, 20) . "...\n";
        echo "    - customer_id: " . $uberConfig['customer_id'] . "\n";
        echo "    - Modo: " . ($uberConfig['is_sandbox'] ? 'SANDBOX (pruebas)' : 'PRODUCCIÓN') . "\n";
    }

    $pdo->commit();

    echo "\n==============================================\n";
    echo "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "==============================================\n\n";

    echo "PRÓXIMOS PASOS:\n";
    echo "1. ✅ Credenciales de Uber configuradas (Sandbox mode)\n";
    echo "2. Los afiliados deben configurar sus direcciones de pickup en su panel\n";
    echo "3. Los productos ahora pueden tener peso, dimensiones y tipo de vehículo\n";
    echo "4. Los usuarios pueden registrar su ubicación geográfica al registrarse\n";
    echo "5. El checkout ahora tiene geolocalización y cotización en tiempo real\n\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    exit(1);
}
