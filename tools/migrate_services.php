<?php
/**
 * Migración del Sistema de Servicios
 *
 * Crea las tablas necesarias para el sistema de servicios con:
 * - Categorías de servicios (Abogados, Mantenimiento, Tutorías, Fletes)
 * - Servicios ofrecidos por afiliados
 * - Sistema de disponibilidad/calendario
 * - Reservas de clientes
 * - Calificaciones y reviews
 * - Métodos de pago por servicio
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();
$ok = [];
$err = [];

function nowts() {
    return date('Y-m-d H:i:s');
}

// ==========================================
// 1. CATEGORÍAS DE SERVICIOS
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        icon TEXT NOT NULL,
        description TEXT,
        requires_online_payment INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        display_order INTEGER DEFAULT 0,
        created_at TEXT,
        updated_at TEXT
    )");
    $ok[] = "service_categories OK";
} catch (Exception $e) {
    $err[] = "service_categories: " . $e->getMessage();
}

// ==========================================
// 2. SERVICIOS OFRECIDOS POR AFILIADOS
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        description TEXT,
        short_description TEXT,
        cover_image TEXT,
        price_per_hour REAL DEFAULT 0,
        currency TEXT DEFAULT 'CRC',
        duration_minutes INTEGER DEFAULT 60,
        is_active INTEGER DEFAULT 1,
        accepts_online_payment INTEGER DEFAULT 0,
        requires_address INTEGER DEFAULT 0,
        max_distance_km INTEGER DEFAULT NULL,
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
    )");
    $ok[] = "services OK";
} catch (Exception $e) {
    $err[] = "services: " . $e->getMessage();
}

// ==========================================
// 3. MÉTODOS DE PAGO ACEPTADOS POR SERVICIO
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_payment_methods (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        method_type TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        details TEXT,
        created_at TEXT,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )");
    $ok[] = "service_payment_methods OK";
} catch (Exception $e) {
    $err[] = "service_payment_methods: " . $e->getMessage();
}

// ==========================================
// 4. DISPONIBILIDAD/CALENDARIO DEL PROVEEDOR
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_availability (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        is_active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )");
    $ok[] = "service_availability OK";
} catch (Exception $e) {
    $err[] = "service_availability: " . $e->getMessage();
}

// ==========================================
// 5. EXCEPCIONES DE DISPONIBILIDAD (días específicos)
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_availability_exceptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        exception_date TEXT NOT NULL,
        start_time TEXT,
        end_time TEXT,
        is_available INTEGER DEFAULT 0,
        reason TEXT,
        created_at TEXT,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )");
    $ok[] = "service_availability_exceptions OK";
} catch (Exception $e) {
    $err[] = "service_availability_exceptions: " . $e->getMessage();
}

// ==========================================
// 6. RESERVAS DE CLIENTES
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        user_id INTEGER DEFAULT NULL,
        affiliate_id INTEGER NOT NULL,
        customer_name TEXT NOT NULL,
        customer_email TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        booking_date TEXT NOT NULL,
        booking_time TEXT NOT NULL,
        duration_minutes INTEGER NOT NULL,
        address TEXT,
        notes TEXT,
        status TEXT DEFAULT 'Pendiente',
        total_amount REAL NOT NULL,
        currency TEXT DEFAULT 'CRC',
        payment_status TEXT DEFAULT 'Pendiente',
        payment_method TEXT,
        payment_proof TEXT,
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $ok[] = "service_bookings OK";
} catch (Exception $e) {
    $err[] = "service_bookings: " . $e->getMessage();
}

// ==========================================
// 7. CALIFICACIONES Y REVIEWS
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_id INTEGER NOT NULL,
        booking_id INTEGER DEFAULT NULL,
        user_id INTEGER DEFAULT NULL,
        customer_name TEXT NOT NULL,
        rating INTEGER NOT NULL,
        comment TEXT,
        is_verified INTEGER DEFAULT 0,
        is_approved INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES service_bookings(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CHECK (rating >= 1 AND rating <= 5)
    )");
    $ok[] = "service_reviews OK";
} catch (Exception $e) {
    $err[] = "service_reviews: " . $e->getMessage();
}

// ==========================================
// 8. TABLA DE USUARIOS (si no existe)
// ==========================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        phone TEXT,
        password_hash TEXT NOT NULL,
        avatar TEXT,
        is_active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT
    )");
    $ok[] = "users OK";
} catch (Exception $e) {
    $err[] = "users: " . $e->getMessage();
}

// ==========================================
// 9. AGREGAR COLUMNAS A AFFILIATES (si no existen)
// ==========================================
try {
    $cols = $pdo->query("PRAGMA table_info(affiliates)")->fetchAll(PDO::FETCH_ASSOC);
    $has = [];
    foreach ($cols as $c) {
        $has[$c['name']] = true;
    }

    if (!isset($has['offers_products'])) {
        $pdo->exec("ALTER TABLE affiliates ADD COLUMN offers_products INTEGER DEFAULT 1");
        $ok[] = "affiliates.offers_products agregado";
    }

    if (!isset($has['offers_services'])) {
        $pdo->exec("ALTER TABLE affiliates ADD COLUMN offers_services INTEGER DEFAULT 0");
        $ok[] = "affiliates.offers_services agregado";
    }

    if (!isset($has['business_description'])) {
        $pdo->exec("ALTER TABLE affiliates ADD COLUMN business_description TEXT");
        $ok[] = "affiliates.business_description agregado";
    }
} catch (Exception $e) {
    $err[] = "affiliates columns: " . $e->getMessage();
}

// ==========================================
// 10. SEED DE CATEGORÍAS DE SERVICIOS
// ==========================================
try {
    $count = $pdo->query("SELECT COUNT(*) FROM service_categories")->fetchColumn();

    if ($count == 0) {
        $now = nowts();
        $categories = [
            [
                'name' => 'Abogados',
                'slug' => 'abogados',
                'icon' => 'fas fa-balance-scale',
                'description' => 'Servicios legales profesionales: derecho civil, penal, laboral, familiar y más',
                'requires_online_payment' => 0,
                'display_order' => 1
            ],
            [
                'name' => 'Mantenimiento y Reparación',
                'slug' => 'mantenimiento-reparacion',
                'icon' => 'fas fa-tools',
                'description' => 'Técnicos especializados en reparación y mantenimiento del hogar y equipos',
                'requires_online_payment' => 0,
                'display_order' => 2
            ],
            [
                'name' => 'Tutorías',
                'slug' => 'tutorias',
                'icon' => 'fas fa-chalkboard-teacher',
                'description' => 'Clases particulares y refuerzo académico en todas las materias',
                'requires_online_payment' => 1,
                'display_order' => 3
            ],
            [
                'name' => 'Fletes',
                'slug' => 'fletes',
                'icon' => 'fas fa-truck',
                'description' => 'Servicio de transporte y mudanzas dentro de Costa Rica',
                'requires_online_payment' => 1,
                'display_order' => 4
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO service_categories
            (name, slug, icon, description, requires_online_payment, is_active, display_order, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");

        foreach ($categories as $cat) {
            $stmt->execute([
                $cat['name'],
                $cat['slug'],
                $cat['icon'],
                $cat['description'],
                $cat['requires_online_payment'],
                $cat['display_order'],
                $now,
                $now
            ]);
        }

        $ok[] = "4 categorías de servicios seeded";
    } else {
        $ok[] = "Categorías de servicios ya existen";
    }
} catch (Exception $e) {
    $err[] = "seed categories: " . $e->getMessage();
}

// ==========================================
// SALIDA
// ==========================================
header('Content-Type: text/plain; charset=utf-8');
echo "==============================================\n";
echo "MIGRACIÓN DEL SISTEMA DE SERVICIOS\n";
echo "==============================================\n\n";

if (!empty($ok)) {
    echo "✅ OPERACIONES EXITOSAS:\n";
    foreach ($ok as $msg) {
        echo "   • " . $msg . "\n";
    }
}

if (!empty($err)) {
    echo "\n❌ ERRORES:\n";
    foreach ($err as $msg) {
        echo "   • " . $msg . "\n";
    }
} else {
    echo "\n🎉 Migración completada sin errores\n";
}

echo "\n==============================================\n";
echo "RESUMEN:\n";
echo "   • " . count($ok) . " operaciones exitosas\n";
echo "   • " . count($err) . " errores\n";
echo "==============================================\n";
