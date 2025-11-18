<?php
/**
 * Script de Datos de Prueba para Sistema de Servicios
 *
 * Crea servicios de ejemplo en las 4 categorías:
 * - Abogados
 * - Mantenimiento y Reparación
 * - Tutorías
 * - Fletes
 *
 * Incluye reviews, disponibilidad y métodos de pago
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
// 1. CREAR AFILIADO DE PRUEBA (si no existe)
// ==========================================
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM affiliates");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Crear afiliado de prueba
        $stmt = $pdo->prepare("
            INSERT INTO affiliates
            (name, email, phone, password_hash, is_active, offers_products, offers_services, business_description, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, 1, 1, ?, ?, ?)
        ");
        $stmt->execute([
            'Servicios Demo CR',
            'demo@compratica.com',
            '8888-8888',
            password_hash('demo123', PASSWORD_DEFAULT),
            'Proveedor de servicios profesionales en Costa Rica. Ofrecemos calidad y compromiso en cada trabajo.',
            nowts(),
            nowts()
        ]);
        $affId = $pdo->lastInsertId();
        $ok[] = "Afiliado de prueba creado (ID: $affId)";
    } else {
        // Usar el primer afiliado
        $stmt = $pdo->query("SELECT id FROM affiliates LIMIT 1");
        $affId = $stmt->fetchColumn();
        $ok[] = "Usando afiliado existente (ID: $affId)";
    }
} catch (Exception $e) {
    $err[] = "Error al crear/obtener afiliado: " . $e->getMessage();
    die("Error crítico: " . $e->getMessage());
}

// ==========================================
// 2. OBTENER CATEGORÍAS
// ==========================================
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM service_categories ORDER BY id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ok[] = count($categories) . " categorías encontradas";
} catch (Exception $e) {
    $err[] = "Error al obtener categorías: " . $e->getMessage();
    die("Error crítico: " . $e->getMessage());
}

// ==========================================
// 3. DATOS DE SERVICIOS DE PRUEBA
// ==========================================
$servicesData = [
    // ABOGADOS
    [
        'category_slug' => 'abogados',
        'title' => 'Consultoría Legal en Derecho Civil',
        'slug' => 'consultoria-legal-civil',
        'description' => 'Asesoría especializada en derecho civil: contratos, arrendamientos, divorcios, herencias y sucesiones. Más de 10 años de experiencia ayudando a familias y empresas costarricenses.',
        'short_description' => 'Asesoría en contratos, divorcios, herencias y más',
        'cover_image' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&h=400&fit=crop',
        'price_per_hour' => 25000,
        'duration_minutes' => 60,
        'requires_address' => 0,
        'accepts_online_payment' => 0
    ],
    [
        'category_slug' => 'abogados',
        'title' => 'Abogado Laboral - Defensa de Trabajadores',
        'slug' => 'abogado-laboral-trabajadores',
        'description' => 'Especialista en derecho laboral. Defendemos tus derechos como trabajador: despidos injustificados, salarios no pagados, acoso laboral, accidentes de trabajo. Primera consulta gratis.',
        'short_description' => 'Defensa laboral: despidos, salarios, accidentes',
        'cover_image' => 'https://images.unsplash.com/photo-1505664194779-8beaceb93744?w=600&h=400&fit=crop',
        'price_per_hour' => 20000,
        'duration_minutes' => 45,
        'requires_address' => 0,
        'accepts_online_payment' => 0
    ],
    [
        'category_slug' => 'abogados',
        'title' => 'Trámites Migratorios y Residencias',
        'slug' => 'tramites-migratorios',
        'description' => 'Gestión completa de trámites migratorios: residencias temporales, permanentes, pensionados, rentistas, vínculos. Acompañamiento en todo el proceso ante Migración.',
        'short_description' => 'Residencias y trámites migratorios en Costa Rica',
        'cover_image' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?w=600&h=400&fit=crop',
        'price_per_hour' => 30000,
        'duration_minutes' => 90,
        'requires_address' => 0,
        'accepts_online_payment' => 0
    ],

    // MANTENIMIENTO Y REPARACIÓN
    [
        'category_slug' => 'mantenimiento-reparacion',
        'title' => 'Reparación de Electrodomésticos a Domicilio',
        'slug' => 'reparacion-electrodomesticos',
        'description' => 'Reparamos refrigeradoras, lavadoras, secadoras, microondas y más. Servicio a domicilio en GAM. Repuestos originales, garantía de 3 meses. Diagnóstico gratis.',
        'short_description' => 'Reparación de refrigeradoras, lavadoras y más',
        'cover_image' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=600&h=400&fit=crop',
        'price_per_hour' => 15000,
        'duration_minutes' => 120,
        'requires_address' => 1,
        'max_distance_km' => 20,
        'accepts_online_payment' => 0
    ],
    [
        'category_slug' => 'mantenimiento-reparacion',
        'title' => 'Plomería Profesional - Emergencias 24/7',
        'slug' => 'plomeria-profesional',
        'description' => 'Servicio de plomería profesional: fugas, tuberías tapadas, instalación de grifos, tanques, calentadores. Atención de emergencias 24/7. Presupuesto sin compromiso.',
        'short_description' => 'Plomería y emergencias 24/7',
        'cover_image' => 'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=600&h=400&fit=crop',
        'price_per_hour' => 12000,
        'duration_minutes' => 60,
        'requires_address' => 1,
        'max_distance_km' => 30,
        'accepts_online_payment' => 0
    ],
    [
        'category_slug' => 'mantenimiento-reparacion',
        'title' => 'Electricista Certificado - Residencial',
        'slug' => 'electricista-residencial',
        'description' => 'Instalaciones eléctricas residenciales: tableros, cableado, tomacorrientes, iluminación LED, reparación de cortocircuitos. Electricista certificado por CONELECTRIDAD.',
        'short_description' => 'Instalaciones y reparaciones eléctricas certificadas',
        'cover_image' => 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=600&h=400&fit=crop',
        'price_per_hour' => 18000,
        'duration_minutes' => 90,
        'requires_address' => 1,
        'max_distance_km' => 25,
        'accepts_online_payment' => 0
    ],

    // TUTORÍAS
    [
        'category_slug' => 'tutorias',
        'title' => 'Tutoría de Matemáticas - Primaria y Secundaria',
        'slug' => 'tutoria-matematicas',
        'description' => 'Clases particulares de matemáticas para estudiantes de primaria y secundaria. Preparación para exámenes, refuerzo de conceptos, ejercicios prácticos. Modalidad presencial u online.',
        'short_description' => 'Matemáticas para primaria y secundaria',
        'cover_image' => 'https://images.unsplash.com/photo-1596495578065-6e0763fa1178?w=600&h=400&fit=crop',
        'price_per_hour' => 8000,
        'duration_minutes' => 60,
        'requires_address' => 0,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'tutorias',
        'title' => 'Inglés Conversacional - Todos los Niveles',
        'slug' => 'ingles-conversacional',
        'description' => 'Aprende inglés de forma natural con un profesor nativo. Enfoque en conversación práctica, pronunciación y vocabulario del día a día. Clases 100% en inglés.',
        'short_description' => 'Inglés conversacional con profesor nativo',
        'cover_image' => 'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?w=600&h=400&fit=crop',
        'price_per_hour' => 10000,
        'duration_minutes' => 60,
        'requires_address' => 0,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'tutorias',
        'title' => 'Programación Web - HTML, CSS, JavaScript',
        'slug' => 'programacion-web',
        'description' => 'Aprende desarrollo web desde cero. Clases de HTML, CSS, JavaScript y frameworks modernos. Proyectos prácticos, portafolio profesional. Ideal para principiantes.',
        'short_description' => 'Desarrollo web: HTML, CSS, JavaScript',
        'cover_image' => 'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&h=400&fit=crop',
        'price_per_hour' => 12000,
        'duration_minutes' => 90,
        'requires_address' => 0,
        'accepts_online_payment' => 1
    ],

    // FLETES
    [
        'category_slug' => 'fletes',
        'title' => 'Flete Pequeño - Pick-up',
        'slug' => 'flete-pickup',
        'description' => 'Servicio de flete con pick-up para cargas pequeñas y medianas. Ideal para mudanzas de apartamento, muebles, electrodomésticos. Cobertura GAM. Carga y descarga incluida.',
        'short_description' => 'Flete pick-up para cargas pequeñas',
        'cover_image' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=600&h=400&fit=crop',
        'price_per_hour' => 15000,
        'duration_minutes' => 60,
        'requires_address' => 1,
        'max_distance_km' => 40,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'fletes',
        'title' => 'Mudanzas Completas - Camión 3 Toneladas',
        'slug' => 'mudanzas-completas',
        'description' => 'Mudanzas residenciales y de oficina. Camión de 3 toneladas, equipo de 3 personas, embalaje profesional. Protección de muebles, seguro incluido. Servicio nacional.',
        'short_description' => 'Mudanzas con camión y equipo profesional',
        'cover_image' => 'https://images.unsplash.com/photo-1600518464441-9154a4dea21b?w=600&h=400&fit=crop',
        'price_per_hour' => 25000,
        'duration_minutes' => 180,
        'requires_address' => 1,
        'max_distance_km' => 100,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'fletes',
        'title' => 'Transporte de Materiales de Construcción',
        'slug' => 'transporte-materiales',
        'description' => 'Flete especializado en materiales de construcción: cemento, varillas, blocks, arena, piedra. Camión con capacidad de 5 toneladas. Servicio a ferreterías y obras.',
        'short_description' => 'Transporte de materiales de construcción',
        'cover_image' => 'https://images.unsplash.com/photo-1581094271901-8022df4466f9?w=600&h=400&fit=crop',
        'price_per_hour' => 20000,
        'duration_minutes' => 120,
        'requires_address' => 1,
        'max_distance_km' => 50,
        'accepts_online_payment' => 1
    ],

    // TURISMO
    [
        'category_slug' => 'turismo',
        'title' => 'Tour de Playa - Día Completo',
        'slug' => 'tour-playa-samara',
        'description' => 'Día completo en Playa Sámara: transporte ida y vuelta desde San José o alrededores. Incluye vehículo con aire acondicionado, conductor bilingüe. Salida 6am, regreso 6pm. Ideal para familias y grupos.',
        'short_description' => 'Transporte a Playa Sámara - Día completo',
        'cover_image' => 'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=600&h=400&fit=crop',
        'price_per_hour' => 80000,
        'duration_minutes' => 720,
        'requires_address' => 1,
        'max_distance_km' => 250,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'turismo',
        'title' => 'Shuttle al Aeropuerto - Juan Santamaría (SJO)',
        'slug' => 'shuttle-aeropuerto-sjo',
        'description' => 'Servicio de transporte privado al Aeropuerto Juan Santamaría. Puntualidad garantizada, seguimiento de vuelos, vehículos cómodos y seguros. Recogida en cualquier punto de San José y alrededores. Precio se calcula automáticamente según distancia.',
        'short_description' => 'Shuttle privado al Aeropuerto SJO',
        'cover_image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=600&h=400&fit=crop',
        'price_per_hour' => 25000,
        'duration_minutes' => 60,
        'requires_address' => 1,
        'max_distance_km' => 100,
        'accepts_online_payment' => 1
    ],
    [
        'category_slug' => 'turismo',
        'title' => 'Shuttle al Aeropuerto - Daniel Oduber Liberia (LIR)',
        'slug' => 'shuttle-aeropuerto-lir',
        'description' => 'Transporte seguro al Aeropuerto de Liberia. Ideal para playas de Guanacaste: Tamarindo, Flamingo, Conchal, Potrero. Vehículos modernos, conductores profesionales, monitoreo de vuelos. Cotización automática por distancia.',
        'short_description' => 'Shuttle privado al Aeropuerto Liberia',
        'cover_image' => 'https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?w=600&h=400&fit=crop',
        'price_per_hour' => 35000,
        'duration_minutes' => 90,
        'requires_address' => 1,
        'max_distance_km' => 150,
        'accepts_online_payment' => 1
    ]
];

// ==========================================
// 4. CREAR SERVICIOS
// ==========================================
$serviceIds = [];
foreach ($servicesData as $svcData) {
    try {
        // Buscar categoría
        $cat = array_filter($categories, function($c) use ($svcData) {
            return $c['slug'] === $svcData['category_slug'];
        });
        $cat = array_values($cat)[0] ?? null;

        if (!$cat) {
            $err[] = "Categoría '{$svcData['category_slug']}' no encontrada";
            continue;
        }

        // Verificar si el servicio ya existe
        $stmt = $pdo->prepare("SELECT id FROM services WHERE slug = ?");
        $stmt->execute([$svcData['slug']]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $serviceIds[$svcData['slug']] = $existing;
            continue;
        }

        // Crear servicio
        $stmt = $pdo->prepare("
            INSERT INTO services
            (affiliate_id, category_id, title, slug, description, short_description, cover_image, price_per_hour,
             duration_minutes, is_active, accepts_online_payment, requires_address, max_distance_km, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $affId,
            $cat['id'],
            $svcData['title'],
            $svcData['slug'],
            $svcData['description'],
            $svcData['short_description'],
            $svcData['cover_image'] ?? null,
            $svcData['price_per_hour'],
            $svcData['duration_minutes'],
            $svcData['accepts_online_payment'],
            $svcData['requires_address'],
            $svcData['max_distance_km'] ?? null,
            nowts(),
            nowts()
        ]);

        $serviceIds[$svcData['slug']] = $pdo->lastInsertId();
        $ok[] = "Servicio creado: {$svcData['title']}";

    } catch (Exception $e) {
        $err[] = "Error al crear servicio '{$svcData['title']}': " . $e->getMessage();
    }
}

// ==========================================
// 5. AGREGAR DISPONIBILIDAD
// ==========================================
foreach ($serviceIds as $slug => $serviceId) {
    try {
        // Lunes a Viernes, 8am-5pm
        for ($day = 1; $day <= 5; $day++) {
            $stmt = $pdo->prepare("
                INSERT INTO service_availability
                (service_id, day_of_week, start_time, end_time, is_active, created_at, updated_at)
                VALUES (?, ?, '08:00:00', '17:00:00', 1, ?, ?)
            ");
            $stmt->execute([$serviceId, $day, nowts(), nowts()]);
        }

        // Sábados 9am-1pm para algunos servicios
        if (in_array($slug, ['reparacion-electrodomesticos', 'plomeria-profesional', 'flete-pickup'])) {
            $stmt = $pdo->prepare("
                INSERT INTO service_availability
                (service_id, day_of_week, start_time, end_time, is_active, created_at, updated_at)
                VALUES (?, 6, '09:00:00', '13:00:00', 1, ?, ?)
            ");
            $stmt->execute([$serviceId, nowts(), nowts()]);
        }

    } catch (Exception $e) {
        $err[] = "Error al agregar disponibilidad para servicio ID $serviceId: " . $e->getMessage();
    }
}
$ok[] = "Disponibilidad agregada a " . count($serviceIds) . " servicios";

// ==========================================
// 6. AGREGAR MÉTODOS DE PAGO
// ==========================================
foreach ($serviceIds as $slug => $serviceId) {
    try {
        // Efectivo para todos
        $stmt = $pdo->prepare("
            INSERT INTO service_payment_methods
            (service_id, method_type, is_active, details, created_at)
            VALUES (?, 'efectivo', 1, 'Pago en efectivo al momento del servicio', ?)
        ");
        $stmt->execute([$serviceId, nowts()]);

        // SINPE Móvil para algunos
        if (in_array($slug, ['tutoria-matematicas', 'ingles-conversacional', 'programacion-web', 'flete-pickup', 'mudanzas-completas'])) {
            $stmt = $pdo->prepare("
                INSERT INTO service_payment_methods
                (service_id, method_type, is_active, details, created_at)
                VALUES (?, 'sinpe', 1, 'SINPE Móvil: 8888-8888', ?)
            ");
            $stmt->execute([$serviceId, nowts()]);
        }

    } catch (Exception $e) {
        $err[] = "Error al agregar métodos de pago para servicio ID $serviceId: " . $e->getMessage();
    }
}
$ok[] = "Métodos de pago agregados";

// ==========================================
// 7. AGREGAR REVIEWS DE PRUEBA
// ==========================================
$reviewsData = [
    ['rating' => 5, 'customer' => 'María González', 'comment' => 'Excelente servicio, muy profesional y puntual. Lo recomiendo 100%.'],
    ['rating' => 5, 'customer' => 'Carlos Rodríguez', 'comment' => 'Quedé muy satisfecho con el trabajo. Definitivamente volveré a contratar.'],
    ['rating' => 4, 'customer' => 'Ana Pérez', 'comment' => 'Buen servicio en general. El trabajo quedó bien hecho.'],
    ['rating' => 5, 'customer' => 'Luis Mora', 'comment' => 'Súper recomendado! Trabajo de calidad y excelente atención.'],
    ['rating' => 4, 'customer' => 'Carmen Solís', 'comment' => 'Muy profesional. Llegó a tiempo y resolvió el problema rápidamente.'],
];

foreach ($serviceIds as $slug => $serviceId) {
    // Agregar 3-5 reviews por servicio
    $numReviews = rand(3, 5);
    for ($i = 0; $i < $numReviews; $i++) {
        try {
            $review = $reviewsData[array_rand($reviewsData)];

            $stmt = $pdo->prepare("
                INSERT INTO service_reviews
                (service_id, customer_name, rating, comment, is_verified, is_approved, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, 1, ?, ?)
            ");
            $stmt->execute([
                $serviceId,
                $review['customer'],
                $review['rating'],
                $review['comment'],
                nowts(),
                nowts()
            ]);

        } catch (Exception $e) {
            $err[] = "Error al agregar review: " . $e->getMessage();
        }
    }
}
$ok[] = "Reviews agregadas a todos los servicios";

// ==========================================
// SALIDA
// ==========================================
header('Content-Type: text/plain; charset=utf-8');
echo "==============================================\n";
echo "SEED DE DATOS DE PRUEBA - SERVICIOS\n";
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
    echo "\n🎉 Seed completado sin errores\n";
}

echo "\n==============================================\n";
echo "RESUMEN:\n";
echo "   • " . count($serviceIds) . " servicios creados\n";
echo "   • Disponibilidad configurada\n";
echo "   • Métodos de pago agregados\n";
echo "   • Reviews agregadas\n";
echo "==============================================\n";
echo "\n📍 Ahora puedes visitar:\n";
echo "   https://compratica.com/servicios.php\n";
echo "==============================================\n";
