<?php
/**
 * PASO 2: Poblar base de datos con lugares iniciales
 *
 * INSTRUCCIONES:
 * 1. Primero ejecuta install_places_db.php
 * 2. Luego abre este archivo: https://compratica.com/seed_places.php
 * 3. Los datos serán importados a la tabla places_cr
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_places.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Poblar BD Lugares - Compratica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 2rem;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .step {
            margin: 1rem 0;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .category {
            background: #f7fafc;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-card .label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-airport { background: #dbeafe; color: #1e40af; }
        .badge-port { background: #fce7f3; color: #831843; }
        .badge-city { background: #e0e7ff; color: #3730a3; }
        .badge-beach { background: #fef3c7; color: #92400e; }
        .badge-park { background: #d1fae5; color: #065f46; }
        .badge-hotel { background: #ede9fe; color: #5b21b6; }
        .badge-mall { background: #fce7f3; color: #9f1239; }
        .badge-supermarket { background: #ddd6fe; color: #5b21b6; }
        .badge-hospital { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🌴 Poblar Base de Datos de Lugares</h1>";

try {
    $pdo = db_places();

    echo "<div class='success'>✅ Conectado exitosamente a MySQL</div>";

    // Verificar que las tablas existen
    $stmt = $pdo->query("SHOW TABLES LIKE 'places_cr'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("La tabla places_cr no existe. Por favor ejecuta install_places_db.php primero.");
    }

    // Verificar si ya hay datos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM places_cr");
    $currentCount = $stmt->fetch()['count'];

    if ($currentCount > 0) {
        echo "<div class='warning'>
            ⚠️ La tabla ya contiene {$currentCount} lugares.<br>
            <form method='POST' style='margin-top: 1rem;'>
                <button type='submit' name='clear' style='background: #ef4444; color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer;'>
                    Borrar todos y reimportar
                </button>
                <button type='submit' name='append' style='background: #10b981; color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; margin-left: 0.5rem;'>
                    Agregar adicionales (sin borrar)
                </button>
            </form>
        </div>";

        // Si no se presionó ningún botón, salir
        if (!isset($_POST['clear']) && !isset($_POST['append'])) {
            echo "</div></body></html>";
            exit;
        }

        if (isset($_POST['clear'])) {
            $pdo->exec("TRUNCATE TABLE places_cr");
            echo "<div class='info'>🗑️ Tabla limpiada. Importando datos frescos...</div>";
        } else {
            echo "<div class='info'>➕ Agregando lugares adicionales...</div>";
        }
    }

    // Definir todos los lugares (extraídos de shuttle_search.php)
    $places = [
        'airports' => [
            ['id' => 'SJO', 'name' => 'Aeropuerto Juan Santamaría (SJO)', 'city' => 'Alajuela', 'province' => 'Alajuela', 'priority' => 10],
            ['id' => 'LIR', 'name' => 'Aeropuerto Daniel Oduber (LIR)', 'city' => 'Liberia', 'province' => 'Guanacaste', 'priority' => 10],
            ['id' => 'LIO', 'name' => 'Aeropuerto de Limón (LIO)', 'city' => 'Limón', 'province' => 'Limón', 'priority' => 9],
            ['id' => 'SYQ', 'name' => 'Aeropuerto Tobías Bolaños (SYQ)', 'city' => 'Pavas', 'province' => 'San José', 'priority' => 8],
            ['id' => 'TOO', 'name' => 'Aeropuerto de San Vito (TOO)', 'city' => 'San Vito', 'province' => 'Puntarenas', 'priority' => 7]
        ],
        'ports' => [
            ['id' => 'PCL', 'name' => 'Puerto Caldera', 'city' => 'Puntarenas', 'province' => 'Puntarenas', 'priority' => 8],
            ['id' => 'PLI', 'name' => 'Puerto Limón', 'city' => 'Limón', 'province' => 'Limón', 'priority' => 8],
            ['id' => 'PGO', 'name' => 'Golfo de Papagayo', 'city' => 'Guanacaste', 'province' => 'Guanacaste', 'priority' => 7]
        ],
        'cities' => [
            // San José
            ['id' => 'SJO-CITY', 'name' => 'San José Centro', 'city' => 'San José', 'province' => 'San José', 'priority' => 10],
            ['id' => 'ESC', 'name' => 'Escazú', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 9],
            ['id' => 'SAN', 'name' => 'Santa Ana', 'city' => 'Santa Ana', 'province' => 'San José', 'priority' => 9],
            ['id' => 'CUR', 'name' => 'Curridabat', 'city' => 'Curridabat', 'province' => 'San José', 'priority' => 7],
            ['id' => 'MOR', 'name' => 'Moravia', 'city' => 'Moravia', 'province' => 'San José', 'priority' => 6],
            ['id' => 'DES', 'name' => 'Desamparados', 'city' => 'Desamparados', 'province' => 'San José', 'priority' => 7],
            // Alajuela
            ['id' => 'ALA', 'name' => 'Alajuela Centro', 'city' => 'Alajuela', 'province' => 'Alajuela', 'priority' => 8],
            ['id' => 'GRE', 'name' => 'Grecia', 'city' => 'Grecia', 'province' => 'Alajuela', 'priority' => 6],
            ['id' => 'SAR', 'name' => 'Sarchí', 'city' => 'Sarchí', 'province' => 'Alajuela', 'priority' => 6],
            ['id' => 'LFO', 'name' => 'La Fortuna', 'city' => 'San Carlos', 'province' => 'Alajuela', 'priority' => 9],
            // Heredia
            ['id' => 'HER', 'name' => 'Heredia Centro', 'city' => 'Heredia', 'province' => 'Heredia', 'priority' => 8],
            ['id' => 'SBA', 'name' => 'San Pablo de Heredia', 'city' => 'San Pablo', 'province' => 'Heredia', 'priority' => 6],
            ['id' => 'SAR2', 'name' => 'Sarapiquí', 'city' => 'Sarapiquí', 'province' => 'Heredia', 'priority' => 6],
            // Cartago
            ['id' => 'CAR', 'name' => 'Cartago Centro', 'city' => 'Cartago', 'province' => 'Cartago', 'priority' => 8],
            ['id' => 'TUR', 'name' => 'Turrialba', 'city' => 'Turrialba', 'province' => 'Cartago', 'priority' => 7],
            // Guanacaste
            ['id' => 'LIB', 'name' => 'Liberia', 'city' => 'Liberia', 'province' => 'Guanacaste', 'priority' => 9],
            ['id' => 'TAM', 'name' => 'Tamarindo', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 9],
            ['id' => 'SAM', 'name' => 'Sámara', 'city' => 'Nicoya', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'FLA', 'name' => 'Flamingo', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'CON', 'name' => 'Playa Conchal', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'COC', 'name' => 'Coco (Playas del Coco)', 'city' => 'Liberia', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'NOS', 'name' => 'Nosara', 'city' => 'Nicoya', 'province' => 'Guanacaste', 'priority' => 8],
            // Puntarenas
            ['id' => 'PUN', 'name' => 'Puntarenas Centro', 'city' => 'Puntarenas', 'province' => 'Puntarenas', 'priority' => 8],
            ['id' => 'JAC', 'name' => 'Jacó', 'city' => 'Garabito', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'QUE', 'name' => 'Quepos', 'city' => 'Quepos', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'MAN', 'name' => 'Manuel Antonio', 'city' => 'Quepos', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'MON', 'name' => 'Monteverde', 'city' => 'Puntarenas', 'province' => 'Puntarenas', 'priority' => 8],
            ['id' => 'UVI', 'name' => 'Uvita', 'city' => 'Osa', 'province' => 'Puntarenas', 'priority' => 7],
            ['id' => 'DOM', 'name' => 'Dominical', 'city' => 'Osa', 'province' => 'Puntarenas', 'priority' => 7],
            // Limón
            ['id' => 'LIM', 'name' => 'Limón Centro', 'city' => 'Limón', 'province' => 'Limón', 'priority' => 7],
            ['id' => 'CAH', 'name' => 'Cahuita', 'city' => 'Talamanca', 'province' => 'Limón', 'priority' => 8],
            ['id' => 'PVI', 'name' => 'Puerto Viejo', 'city' => 'Talamanca', 'province' => 'Limón', 'priority' => 9],
            ['id' => 'TOR', 'name' => 'Tortuguero', 'city' => 'Pococi', 'province' => 'Limón', 'priority' => 7]
        ],
        'beaches' => [
            ['id' => 'TAM-BEACH', 'name' => 'Playa Tamarindo', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 9],
            ['id' => 'HER-BEACH', 'name' => 'Playa Hermosa', 'city' => 'Liberia', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'CON-BEACH', 'name' => 'Playa Conchal', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'FLA-BEACH', 'name' => 'Playa Flamingo', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'POT-BEACH', 'name' => 'Playa Potrero', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 7],
            ['id' => 'GRA-BEACH', 'name' => 'Playa Grande', 'city' => 'Santa Cruz', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'NOS-BEACH', 'name' => 'Playa Nosara', 'city' => 'Nicoya', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'SAM-BEACH', 'name' => 'Playa Sámara', 'city' => 'Nicoya', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'JAC-BEACH', 'name' => 'Playa Jacó', 'city' => 'Garabito', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'HER2-BEACH', 'name' => 'Playa Herradura', 'city' => 'Garabito', 'province' => 'Puntarenas', 'priority' => 7],
            ['id' => 'ESM-BEACH', 'name' => 'Playa Espadilla (Manuel Antonio)', 'city' => 'Quepos', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'CAR-BEACH', 'name' => 'Playa Carrillo', 'city' => 'Nicoya', 'province' => 'Guanacaste', 'priority' => 7],
            ['id' => 'CAH-BEACH', 'name' => 'Playa Cahuita', 'city' => 'Talamanca', 'province' => 'Limón', 'priority' => 8],
            ['id' => 'PVI-BEACH', 'name' => 'Playa Puerto Viejo', 'city' => 'Talamanca', 'province' => 'Limón', 'priority' => 9],
            ['id' => 'MAN-BEACH', 'name' => 'Playa Manzanillo', 'city' => 'Talamanca', 'province' => 'Limón', 'priority' => 7]
        ],
        'parks' => [
            ['id' => 'PNM-ANT', 'name' => 'Parque Nacional Manuel Antonio', 'city' => 'Quepos', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'PNV-ARE', 'name' => 'Parque Nacional Volcán Arenal', 'city' => 'San Carlos', 'province' => 'Alajuela', 'priority' => 9],
            ['id' => 'PNV-POA', 'name' => 'Parque Nacional Volcán Poás', 'city' => 'Alajuela', 'province' => 'Alajuela', 'priority' => 8],
            ['id' => 'PNV-IRA', 'name' => 'Parque Nacional Volcán Irazú', 'city' => 'Cartago', 'province' => 'Cartago', 'priority' => 8],
            ['id' => 'PNT-TOR', 'name' => 'Parque Nacional Tortuguero', 'city' => 'Pococi', 'province' => 'Limón', 'priority' => 8],
            ['id' => 'PNM-NUB', 'name' => 'Reserva Monteverde', 'city' => 'Monteverde', 'province' => 'Puntarenas', 'priority' => 9],
            ['id' => 'PNC-COR', 'name' => 'Parque Nacional Corcovado', 'city' => 'Osa', 'province' => 'Puntarenas', 'priority' => 7],
            ['id' => 'PNR-SIL', 'name' => 'Parque Nacional Rincón de la Vieja', 'city' => 'Liberia', 'province' => 'Guanacaste', 'priority' => 7]
        ],
        'hotels' => [
            // San José
            ['id' => 'HTL-MAR-CR', 'name' => 'Hotel Marriott San José', 'city' => 'San Antonio de Belén', 'province' => 'Heredia', 'priority' => 8],
            ['id' => 'HTL-RAD-SJ', 'name' => 'Radisson San José', 'city' => 'San José', 'province' => 'San José', 'priority' => 7],
            ['id' => 'HTL-HOL-ESC', 'name' => 'Holiday Inn Escazú', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 7],
            ['id' => 'HTL-INT-SJ', 'name' => 'InterContinental San José', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 8],
            // Guanacaste
            ['id' => 'HTL-RIU-GUA', 'name' => 'RIU Guanacaste', 'city' => 'Matapalo', 'province' => 'Guanacaste', 'priority' => 8],
            ['id' => 'HTL-HIL-PAP', 'name' => 'Hilton Papagayo', 'city' => 'Papagayo', 'province' => 'Guanacaste', 'priority' => 9],
            ['id' => 'HTL-WES-CON', 'name' => 'Westin Conchal', 'city' => 'Conchal', 'province' => 'Guanacaste', 'priority' => 9],
            ['id' => 'HTL-TAM-DIA', 'name' => 'Tamarindo Diria', 'city' => 'Tamarindo', 'province' => 'Guanacaste', 'priority' => 7],
            // Manuel Antonio
            ['id' => 'HTL-ARE-DEL', 'name' => 'Arenas del Mar', 'city' => 'Manuel Antonio', 'province' => 'Puntarenas', 'priority' => 8],
            ['id' => 'HTL-SIP-MAR', 'name' => 'Si Como No', 'city' => 'Manuel Antonio', 'province' => 'Puntarenas', 'priority' => 8],
            // Arenal
            ['id' => 'HTL-TAB-ARE', 'name' => 'Tabacón Resort', 'city' => 'La Fortuna', 'province' => 'Alajuela', 'priority' => 9],
            ['id' => 'HTL-SPR-ARE', 'name' => 'The Springs Resort', 'city' => 'La Fortuna', 'province' => 'Alajuela', 'priority' => 9]
        ],
        'malls' => [
            ['id' => 'MALL-MUL', 'name' => 'Multiplaza Escazú', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 9],
            ['id' => 'MALL-LIN', 'name' => 'Lincoln Plaza', 'city' => 'Moravia', 'province' => 'San José', 'priority' => 8],
            ['id' => 'MALL-TER', 'name' => 'Terramall', 'city' => 'Tres Ríos', 'province' => 'Cartago', 'priority' => 8],
            ['id' => 'MALL-OXI', 'name' => 'Oxígeno', 'city' => 'Heredia', 'province' => 'Heredia', 'priority' => 8],
            ['id' => 'MALL-PAS', 'name' => 'Paseo de las Flores', 'city' => 'Heredia', 'province' => 'Heredia', 'priority' => 7],
            ['id' => 'MALL-CIT', 'name' => 'City Mall Alajuela', 'city' => 'Alajuela', 'province' => 'Alajuela', 'priority' => 7],
            ['id' => 'MALL-MOM', 'name' => 'Mall San Pedro', 'city' => 'San Pedro', 'province' => 'San José', 'priority' => 8]
        ],
        'supermarkets' => [
            ['id' => 'SUPER-AM-ESC', 'name' => 'AutoMercado Escazú', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 7],
            ['id' => 'SUPER-AM-LIN', 'name' => 'AutoMercado Lincoln Plaza', 'city' => 'Moravia', 'province' => 'San José', 'priority' => 7],
            ['id' => 'SUPER-PV-MAS', 'name' => 'PriceSmart Zapote', 'city' => 'San José', 'province' => 'San José', 'priority' => 7],
            ['id' => 'SUPER-WAL-ESC', 'name' => 'Walmart Escazú', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 7]
        ],
        'hospitals' => [
            ['id' => 'HOSP-CIM', 'name' => 'Hospital CIMA San José', 'city' => 'Escazú', 'province' => 'San José', 'priority' => 8],
            ['id' => 'HOSP-CLI', 'name' => 'Clínica Bíblica', 'city' => 'San José', 'province' => 'San José', 'priority' => 8],
            ['id' => 'HOSP-CAT', 'name' => 'Hospital Católica', 'city' => 'San José', 'province' => 'San José', 'priority' => 7]
        ]
    ];

    // Estadísticas de importación
    $stats = [];
    $totalInserted = 0;
    $totalDuplicates = 0;

    // Preparar statement de inserción
    $stmt = $pdo->prepare("
        INSERT INTO places_cr (
            name, type, category, city, province, priority, source, is_active
        ) VALUES (
            :name, :type, :category, :city, :province, :priority, 'hardcoded', 1
        )
    ");

    echo "<div class='step'><strong>Importando lugares por categoría:</strong></div>";

    // Insertar lugares por categoría
    foreach ($places as $category => $items) {
        echo "<div class='category'>📍 Categoría: <strong>" . ucfirst($category) . "</strong> (" . count($items) . " lugares)</div>";

        $categoryInserted = 0;
        $categoryDuplicates = 0;

        foreach ($items as $place) {
            try {
                $stmt->execute([
                    ':name' => $place['name'],
                    ':type' => rtrim($category, 's'), // Singular (airports -> airport)
                    ':category' => $category,
                    ':city' => $place['city'],
                    ':province' => $place['province'],
                    ':priority' => $place['priority']
                ]);
                $categoryInserted++;
                $totalInserted++;
            } catch (PDOException $e) {
                // Si es error de duplicado (nombre único), contar como duplicado
                if ($e->getCode() == 23000) {
                    $categoryDuplicates++;
                    $totalDuplicates++;
                } else {
                    throw $e;
                }
            }
        }

        $stats[$category] = [
            'total' => count($items),
            'inserted' => $categoryInserted,
            'duplicates' => $categoryDuplicates
        ];

        echo "<div style='margin-left: 1.5rem; color: #059669;'>
            ✓ Insertados: {$categoryInserted} |
            <span style='color: #9ca3af;'>Duplicados: {$categoryDuplicates}</span>
        </div>";
    }

    echo "<div class='success'>
        <h3>✅ Importación Completada</h3>
        <p><strong>Total insertados:</strong> {$totalInserted} lugares nuevos</p>
        <p><strong>Duplicados omitidos:</strong> {$totalDuplicates}</p>
    </div>";

    // Mostrar estadísticas en tarjetas
    echo "<div class='stats'>";
    foreach ($stats as $category => $stat) {
        echo "<div class='stat-card'>
            <div class='number'>{$stat['inserted']}</div>
            <div class='label'>" . ucfirst($category) . "</div>
        </div>";
    }
    echo "</div>";

    // Mostrar vista previa de los datos insertados
    echo "<div class='step'><strong>Vista previa de lugares importados:</strong></div>";

    $stmt = $pdo->query("
        SELECT id, name, type, category, city, province, priority
        FROM places_cr
        ORDER BY priority DESC, category, name
        LIMIT 50
    ");

    echo "<table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Ciudad</th>
                <th>Provincia</th>
                <th>Prioridad</th>
            </tr>
        </thead>
        <tbody>";

    while ($row = $stmt->fetch()) {
        $badgeClass = "badge-" . $row['category'];
        echo "<tr>
            <td>{$row['id']}</td>
            <td><strong>{$row['name']}</strong></td>
            <td><span class='badge {$badgeClass}'>{$row['category']}</span></td>
            <td>{$row['city']}</td>
            <td>{$row['province']}</td>
            <td style='text-align: center;'>{$row['priority']}</td>
        </tr>";
    }

    echo "</tbody></table>";

    // Contar total de lugares en la base de datos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM places_cr");
    $totalPlaces = $stmt->fetch()['total'];

    echo "<div class='info'>
        📊 <strong>Total de lugares en la base de datos:</strong> {$totalPlaces}
    </div>";

    echo "<div class='success'>
        <h3>🎉 ¡Proceso Completado!</h3>
        <p>La base de datos está lista para usarse con el buscador de shuttles.</p>
        <p><strong>Próximos pasos:</strong></p>
        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
            <li>Crear API de búsqueda (api/search_places.php)</li>
            <li>Actualizar shuttle_search.php para usar MySQL</li>
            <li>Opcional: Importar datos completos de OpenStreetMap</li>
        </ul>
    </div>";

    echo "<a href='shuttle_search.php' class='btn'>🚐 Ir al buscador de shuttles</a>";
    echo "<a href='install_places_db.php' class='btn' style='margin-left: 1rem; background: #6b7280;'>🔙 Volver al instalador</a>";

} catch (PDOException $e) {
    echo "<div class='error'>
        <h3>❌ Error de Base de Datos</h3>
        <p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Código:</strong> " . $e->getCode() . "</p>
    </div>";

    echo "<div class='info'>
        <h4>💡 Posibles soluciones:</h4>
        <ul>
            <li>Verifica que ejecutaste <strong>install_places_db.php</strong> primero</li>
            <li>Verifica que el usuario tenga permisos INSERT en la tabla</li>
            <li>Revisa que la conexión en includes/db_places.php sea correcta</li>
        </ul>
    </div>";
} catch (Exception $e) {
    echo "<div class='error'>
        <h3>❌ Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "
    </div>
</body>
</html>";
?>
