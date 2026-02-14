<?php
/**
 * Página de búsqueda de Shuttle - Estilo KiwiTaxi
 * Permite buscar shuttles desde/hacia aeropuertos, puertos y direcciones en Costa Rica
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shuttle Aeropuerto Costa Rica | Transporte Turístico - CompraTica</title>
    <meta name="description" content="Reserva shuttle en Costa Rica. Transporte desde aeropuerto Juan Santamaría y Daniel Oduber a hoteles, playas y destinos turísticos. Precios económicos.">
    <meta name="keywords" content="shuttle costa rica, transporte aeropuerto costa rica, shuttle san jose, shuttle liberia, transfer aeropuerto, shuttle tamarindo, shuttle manuel antonio, shuttle la fortuna">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://compratica.com/shuttle_search.php">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://compratica.com/shuttle_search.php">
    <meta property="og:title" content="Shuttle Aeropuerto Costa Rica - Transporte Turístico">
    <meta property="og:description" content="Reserva shuttle desde aeropuerto a cualquier destino en Costa Rica. Precios económicos y servicio confiable.">
    <meta property="og:image" content="https://compratica.com/logo.png">

    <!-- Schema.org para Shuttle Service -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "TravelAgency",
      "name": "Shuttle Pura Vida - CompraTica",
      "description": "Servicio de shuttle y transporte turístico en Costa Rica",
      "url": "https://compratica.com/shuttle_search.php",
      "areaServed": {
        "@type": "Country",
        "name": "Costa Rica"
      },
      "serviceType": ["Airport Shuttle", "Hotel Transfer", "Tourist Transportation"]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">

    <!-- Soporte de emojis para todas las plataformas -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #1557b0;
            --secondary: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Clase para emojis - Asegura visibilidad en todas las plataformas */
        .emoji {
            font-family: "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", sans-serif;
            font-style: normal;
            font-weight: normal;
            line-height: 1;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: var(--white);
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .search-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
        }

        .search-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            font-size: 1.1rem;
        }

        .input-icon .form-input {
            padding-left: 3rem;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-top: none;
            border-radius: 0 0 var(--radius) var(--radius);
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 10;
            box-shadow: var(--shadow-lg);
        }

        .autocomplete-dropdown.show {
            display: block;
        }

        .autocomplete-item {
            padding: 1rem;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .autocomplete-item:hover {
            background: linear-gradient(90deg, var(--gray-50) 0%, var(--white) 100%);
            border-left: 3px solid var(--primary);
            padding-left: calc(1rem - 3px);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item .item-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            border-radius: 10px;
            font-size: 1rem;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(26, 115, 232, 0.2);
        }

        .autocomplete-item .item-text {
            flex: 1;
            min-width: 0;
        }

        .autocomplete-item .item-title {
            font-weight: 600;
            color: var(--gray-900);
            display: block;
            margin-bottom: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .autocomplete-item .item-subtitle {
            font-size: 0.85rem;
            color: var(--gray-500);
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .btn-search {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
            margin-top: 1rem;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-search:active {
            transform: translateY(0);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--white);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-link:hover {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .search-card {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="servicios.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Volver a servicios
    </a>

    <div class="header">
        <h1><i class="fas fa-shuttle-van"></i> Shuttle Pura Vida <span class="emoji">🇨🇷</span></h1>
        <p>Transporte privado en Costa Rica - Aeropuertos, playas, hoteles y más</p>
    </div>

    <div class="search-card">
        <h2 class="search-title">Encontrá tu shuttle ideal</h2>

        <form id="shuttleSearchForm" action="shuttle_results.php" method="GET">
            <!-- Origen -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-map-marker-alt"></i> Desde (aeropuerto, puerto, dirección)
                </label>
                <div class="input-icon" style="position: relative;">
                    <i class="fas fa-location-dot"></i>
                    <input
                        type="text"
                        class="form-input"
                        id="origin-input"
                        name="origin"
                        placeholder="Ej: San José, Aeropuerto SJO, Hotel..."
                        autocomplete="off"
                        required
                    >
                    <input type="hidden" id="origin-type" name="origin_type">
                    <input type="hidden" id="origin-id" name="origin_id">
                    <div class="autocomplete-dropdown" id="origin-dropdown"></div>
                </div>
            </div>

            <!-- Destino -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-map-pin"></i> Hacia (aeropuerto, puerto, dirección)
                </label>
                <div class="input-icon" style="position: relative;">
                    <i class="fas fa-flag-checkered"></i>
                    <input
                        type="text"
                        class="form-input"
                        id="destination-input"
                        name="destination"
                        placeholder="Ej: Aeropuerto Liberia, Tamarindo, Jaco..."
                        autocomplete="off"
                        required
                    >
                    <input type="hidden" id="destination-type" name="destination_type">
                    <input type="hidden" id="destination-id" name="destination_id">
                    <div class="autocomplete-dropdown" id="destination-dropdown"></div>
                </div>
            </div>

            <!-- Fecha y Hora -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i> Fecha del servicio
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-day"></i>
                        <input
                            type="date"
                            class="form-input"
                            name="date"
                            min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-clock"></i> Hora de recogida
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-clock"></i>
                        <input
                            type="time"
                            class="form-input"
                            name="time"
                            required
                        >
                    </div>
                </div>
            </div>

            <!-- Pasajeros y Maletas -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-users"></i> Pasajeros
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input
                            type="number"
                            class="form-input"
                            name="passengers"
                            min="1"
                            max="20"
                            value="2"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-suitcase"></i> Maletas
                    </label>
                    <div class="input-icon">
                        <i class="fas fa-luggage-cart"></i>
                        <input
                            type="number"
                            class="form-input"
                            name="luggage"
                            min="0"
                            max="20"
                            value="2"
                            required
                        >
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Buscar shuttles disponibles
            </button>
        </form>
    </div>
</div>

<script>
// Sistema de autocompletado expandido para lugares de Costa Rica - Estilo Kiwi
const places = {
    airports: [
        { id: 'SJO', type: 'airport', name: 'Aeropuerto Juan Santamaría (SJO)', city: 'Alajuela', province: 'Alajuela', icon: 'plane', priority: 10 },
        { id: 'LIR', type: 'airport', name: 'Aeropuerto Daniel Oduber (LIR)', city: 'Liberia', province: 'Guanacaste', icon: 'plane', priority: 10 },
        { id: 'LIO', type: 'airport', name: 'Aeropuerto de Limón (LIO)', city: 'Limón', province: 'Limón', icon: 'plane', priority: 9 },
        { id: 'SYQ', type: 'airport', name: 'Aeropuerto Tobías Bolaños (SYQ)', city: 'Pavas', province: 'San José', icon: 'plane', priority: 8 },
        { id: 'TOO', type: 'airport', name: 'Aeropuerto de San Vito (TOO)', city: 'San Vito', province: 'Puntarenas', icon: 'plane', priority: 7 }
    ],
    ports: [
        { id: 'PCL', type: 'port', name: 'Puerto Caldera', city: 'Puntarenas', province: 'Puntarenas', icon: 'ship', priority: 8 },
        { id: 'PLI', type: 'port', name: 'Puerto Limón', city: 'Limón', province: 'Limón', icon: 'ship', priority: 8 },
        { id: 'PGO', type: 'port', name: 'Golfo de Papagayo', city: 'Guanacaste', province: 'Guanacaste', icon: 'ship', priority: 7 }
    ],
    cities: [
        // San José
        { id: 'SJO-CITY', type: 'city', name: 'San José Centro', city: 'San José', province: 'San José', icon: 'city', priority: 10 },
        { id: 'ESC', type: 'city', name: 'Escazú', city: 'Escazú', province: 'San José', icon: 'city', priority: 9 },
        { id: 'SAN', type: 'city', name: 'Santa Ana', city: 'Santa Ana', province: 'San José', icon: 'city', priority: 9 },
        { id: 'CUR', type: 'city', name: 'Curridabat', city: 'Curridabat', province: 'San José', icon: 'city', priority: 7 },
        { id: 'MOR', type: 'city', name: 'Moravia', city: 'Moravia', province: 'San José', icon: 'city', priority: 6 },
        { id: 'DES', type: 'city', name: 'Desamparados', city: 'Desamparados', province: 'San José', icon: 'city', priority: 7 },
        // Alajuela
        { id: 'ALA', type: 'city', name: 'Alajuela Centro', city: 'Alajuela', province: 'Alajuela', icon: 'city', priority: 8 },
        { id: 'GRE', type: 'city', name: 'Grecia', city: 'Grecia', province: 'Alajuela', icon: 'city', priority: 6 },
        { id: 'SAR', type: 'city', name: 'Sarchí', city: 'Sarchí', province: 'Alajuela', icon: 'city', priority: 6 },
        { id: 'LFO', type: 'city', name: 'La Fortuna', city: 'San Carlos', province: 'Alajuela', icon: 'city', priority: 9 },
        // Heredia
        { id: 'HER', type: 'city', name: 'Heredia Centro', city: 'Heredia', province: 'Heredia', icon: 'city', priority: 8 },
        { id: 'SBA', type: 'city', name: 'San Pablo de Heredia', city: 'San Pablo', province: 'Heredia', icon: 'city', priority: 6 },
        { id: 'SAR2', type: 'city', name: 'Sarapiquí', city: 'Sarapiquí', province: 'Heredia', icon: 'city', priority: 6 },
        // Cartago
        { id: 'CAR', type: 'city', name: 'Cartago Centro', city: 'Cartago', province: 'Cartago', icon: 'city', priority: 8 },
        { id: 'TUR', type: 'city', name: 'Turrialba', city: 'Turrialba', province: 'Cartago', icon: 'city', priority: 7 },
        // Guanacaste
        { id: 'LIB', type: 'city', name: 'Liberia', city: 'Liberia', province: 'Guanacaste', icon: 'city', priority: 9 },
        { id: 'TAM', type: 'city', name: 'Tamarindo', city: 'Santa Cruz', province: 'Guanacaste', icon: 'city', priority: 9 },
        { id: 'SAM', type: 'city', name: 'Sámara', city: 'Nicoya', province: 'Guanacaste', icon: 'city', priority: 8 },
        { id: 'FLA', type: 'city', name: 'Flamingo', city: 'Santa Cruz', province: 'Guanacaste', icon: 'city', priority: 8 },
        { id: 'CON', type: 'city', name: 'Playa Conchal', city: 'Santa Cruz', province: 'Guanacaste', icon: 'city', priority: 8 },
        { id: 'COC', type: 'city', name: 'Coco (Playas del Coco)', city: 'Liberia', province: 'Guanacaste', icon: 'city', priority: 8 },
        { id: 'NOS', type: 'city', name: 'Nosara', city: 'Nicoya', province: 'Guanacaste', icon: 'city', priority: 8 },
        // Puntarenas
        { id: 'PUN', type: 'city', name: 'Puntarenas Centro', city: 'Puntarenas', province: 'Puntarenas', icon: 'city', priority: 8 },
        { id: 'JAC', type: 'city', name: 'Jacó', city: 'Garabito', province: 'Puntarenas', icon: 'city', priority: 9 },
        { id: 'QUE', type: 'city', name: 'Quepos', city: 'Quepos', province: 'Puntarenas', icon: 'city', priority: 9 },
        { id: 'MAN', type: 'city', name: 'Manuel Antonio', city: 'Quepos', province: 'Puntarenas', icon: 'city', priority: 9 },
        { id: 'MON', type: 'city', name: 'Monteverde', city: 'Puntarenas', province: 'Puntarenas', icon: 'city', priority: 8 },
        { id: 'UVI', type: 'city', name: 'Uvita', city: 'Osa', province: 'Puntarenas', icon: 'city', priority: 7 },
        { id: 'DOM', type: 'city', name: 'Dominical', city: 'Osa', province: 'Puntarenas', icon: 'city', priority: 7 },
        // Limón
        { id: 'LIM', type: 'city', name: 'Limón Centro', city: 'Limón', province: 'Limón', icon: 'city', priority: 7 },
        { id: 'CAH', type: 'city', name: 'Cahuita', city: 'Talamanca', province: 'Limón', icon: 'city', priority: 8 },
        { id: 'PVI', type: 'city', name: 'Puerto Viejo', city: 'Talamanca', province: 'Limón', icon: 'city', priority: 9 },
        { id: 'TOR', type: 'city', name: 'Tortuguero', city: 'Pococi', province: 'Limón', icon: 'city', priority: 7 }
    ],
    beaches: [
        { id: 'TAM-BEACH', type: 'beach', name: 'Playa Tamarindo', city: 'Santa Cruz', province: 'Guanacaste', icon: 'umbrella-beach', priority: 9 },
        { id: 'HER-BEACH', type: 'beach', name: 'Playa Hermosa', city: 'Liberia', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'CON-BEACH', type: 'beach', name: 'Playa Conchal', city: 'Santa Cruz', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'FLA-BEACH', type: 'beach', name: 'Playa Flamingo', city: 'Santa Cruz', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'POT-BEACH', type: 'beach', name: 'Playa Potrero', city: 'Santa Cruz', province: 'Guanacaste', icon: 'umbrella-beach', priority: 7 },
        { id: 'GRA-BEACH', type: 'beach', name: 'Playa Grande', city: 'Santa Cruz', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'NOS-BEACH', type: 'beach', name: 'Playa Nosara', city: 'Nicoya', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'SAM-BEACH', type: 'beach', name: 'Playa Sámara', city: 'Nicoya', province: 'Guanacaste', icon: 'umbrella-beach', priority: 8 },
        { id: 'JAC-BEACH', type: 'beach', name: 'Playa Jacó', city: 'Garabito', province: 'Puntarenas', icon: 'umbrella-beach', priority: 9 },
        { id: 'HER2-BEACH', type: 'beach', name: 'Playa Herradura', city: 'Garabito', province: 'Puntarenas', icon: 'umbrella-beach', priority: 7 },
        { id: 'ESM-BEACH', type: 'beach', name: 'Playa Espadilla (Manuel Antonio)', city: 'Quepos', province: 'Puntarenas', icon: 'umbrella-beach', priority: 9 },
        { id: 'CAR-BEACH', type: 'beach', name: 'Playa Carrillo', city: 'Nicoya', province: 'Guanacaste', icon: 'umbrella-beach', priority: 7 },
        { id: 'CAH-BEACH', type: 'beach', name: 'Playa Cahuita', city: 'Talamanca', province: 'Limón', icon: 'umbrella-beach', priority: 8 },
        { id: 'PVI-BEACH', type: 'beach', name: 'Playa Puerto Viejo', city: 'Talamanca', province: 'Limón', icon: 'umbrella-beach', priority: 9 },
        { id: 'MAN-BEACH', type: 'beach', name: 'Playa Manzanillo', city: 'Talamanca', province: 'Limón', icon: 'umbrella-beach', priority: 7 }
    ],
    parks: [
        { id: 'PNM-ANT', type: 'park', name: 'Parque Nacional Manuel Antonio', city: 'Quepos', province: 'Puntarenas', icon: 'tree', priority: 9 },
        { id: 'PNV-ARE', type: 'park', name: 'Parque Nacional Volcán Arenal', city: 'San Carlos', province: 'Alajuela', icon: 'mountain', priority: 9 },
        { id: 'PNV-POA', type: 'park', name: 'Parque Nacional Volcán Poás', city: 'Alajuela', province: 'Alajuela', icon: 'mountain', priority: 8 },
        { id: 'PNV-IRA', type: 'park', name: 'Parque Nacional Volcán Irazú', city: 'Cartago', province: 'Cartago', icon: 'mountain', priority: 8 },
        { id: 'PNT-TOR', type: 'park', name: 'Parque Nacional Tortuguero', city: 'Pococi', province: 'Limón', icon: 'tree', priority: 8 },
        { id: 'PNM-NUB', type: 'park', name: 'Reserva Monteverde', city: 'Monteverde', province: 'Puntarenas', icon: 'tree', priority: 9 },
        { id: 'PNC-COR', type: 'park', name: 'Parque Nacional Corcovado', city: 'Osa', province: 'Puntarenas', icon: 'tree', priority: 7 },
        { id: 'PNR-SIL', type: 'park', name: 'Parque Nacional Rincón de la Vieja', city: 'Liberia', province: 'Guanacaste', icon: 'mountain', priority: 7 }
    ],
    hotels: [
        // San José
        { id: 'HTL-MAR-CR', type: 'hotel', name: 'Hotel Marriott San José', city: 'San Antonio de Belén', province: 'Heredia', icon: 'hotel', priority: 8 },
        { id: 'HTL-RAD-SJ', type: 'hotel', name: 'Radisson San José', city: 'San José', province: 'San José', icon: 'hotel', priority: 7 },
        { id: 'HTL-HOL-ESC', type: 'hotel', name: 'Holiday Inn Escazú', city: 'Escazú', province: 'San José', icon: 'hotel', priority: 7 },
        { id: 'HTL-INT-SJ', type: 'hotel', name: 'InterContinental San José', city: 'Escazú', province: 'San José', icon: 'hotel', priority: 8 },
        // Guanacaste
        { id: 'HTL-RIU-GUA', type: 'hotel', name: 'RIU Guanacaste', city: 'Matapalo', province: 'Guanacaste', icon: 'hotel', priority: 8 },
        { id: 'HTL-HIL-PAP', type: 'hotel', name: 'Hilton Papagayo', city: 'Papagayo', province: 'Guanacaste', icon: 'hotel', priority: 9 },
        { id: 'HTL-WES-CON', type: 'hotel', name: 'Westin Conchal', city: 'Conchal', province: 'Guanacaste', icon: 'hotel', priority: 9 },
        { id: 'HTL-TAM-DIA', type: 'hotel', name: 'Tamarindo Diria', city: 'Tamarindo', province: 'Guanacaste', icon: 'hotel', priority: 7 },
        // Manuel Antonio
        { id: 'HTL-ARE-DEL', type: 'hotel', name: 'Arenas del Mar', city: 'Manuel Antonio', province: 'Puntarenas', icon: 'hotel', priority: 8 },
        { id: 'HTL-SIP-MAR', type: 'hotel', name: 'Si Como No', city: 'Manuel Antonio', province: 'Puntarenas', icon: 'hotel', priority: 8 },
        // Arenal
        { id: 'HTL-TAB-ARE', type: 'hotel', name: 'Tabacón Resort', city: 'La Fortuna', province: 'Alajuela', icon: 'hotel', priority: 9 },
        { id: 'HTL-SPR-ARE', type: 'hotel', name: 'The Springs Resort', city: 'La Fortuna', province: 'Alajuela', icon: 'hotel', priority: 9 }
    ],
    malls: [
        { id: 'MALL-MUL', type: 'mall', name: 'Multiplaza Escazú', city: 'Escazú', province: 'San José', icon: 'shopping-bag', priority: 9 },
        { id: 'MALL-LIN', type: 'mall', name: 'Lincoln Plaza', city: 'Moravia', province: 'San José', icon: 'shopping-bag', priority: 8 },
        { id: 'MALL-TER', type: 'mall', name: 'Terramall', city: 'Tres Ríos', province: 'Cartago', icon: 'shopping-bag', priority: 8 },
        { id: 'MALL-OXI', type: 'mall', name: 'Oxígeno', city: 'Heredia', province: 'Heredia', icon: 'shopping-bag', priority: 8 },
        { id: 'MALL-PAS', type: 'mall', name: 'Paseo de las Flores', city: 'Heredia', province: 'Heredia', icon: 'shopping-bag', priority: 7 },
        { id: 'MALL-CIT', type: 'mall', name: 'City Mall Alajuela', city: 'Alajuela', province: 'Alajuela', icon: 'shopping-bag', priority: 7 },
        { id: 'MALL-MOM', type: 'mall', name: 'Mall San Pedro', city: 'San Pedro', province: 'San José', icon: 'shopping-bag', priority: 8 }
    ],
    supermarkets: [
        { id: 'SUPER-AM-ESC', type: 'supermarket', name: 'AutoMercado Escazú', city: 'Escazú', province: 'San José', icon: 'store', priority: 7 },
        { id: 'SUPER-AM-LIN', type: 'supermarket', name: 'AutoMercado Lincoln Plaza', city: 'Moravia', province: 'San José', icon: 'store', priority: 7 },
        { id: 'SUPER-PV-MAS', type: 'supermarket', name: 'PriceSmart Zapote', city: 'San José', province: 'San José', icon: 'store', priority: 7 },
        { id: 'SUPER-WAL-ESC', type: 'supermarket', name: 'Walmart Escazú', city: 'Escazú', province: 'San José', icon: 'store', priority: 7 }
    ],
    hospitals: [
        { id: 'HOSP-CIM', type: 'hospital', name: 'Hospital CIMA San José', city: 'Escazú', province: 'San José', icon: 'hospital', priority: 8 },
        { id: 'HOSP-CLI', type: 'hospital', name: 'Clínica Bíblica', city: 'San José', province: 'San José', icon: 'hospital', priority: 8 },
        { id: 'HOSP-CAT', type: 'hospital', name: 'Hospital Católica', city: 'San José', province: 'San José', icon: 'hospital', priority: 7 }
    ]
};

// Combinar todos los lugares
const allPlaces = [
    ...places.airports,
    ...places.ports,
    ...places.cities,
    ...places.beaches,
    ...places.parks,
    ...places.hotels,
    ...places.malls,
    ...places.supermarkets,
    ...places.hospitals
];

let searchTimeout = null;

function setupAutocomplete(inputId, dropdownId, typeHiddenId, idHiddenId) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const typeHidden = document.getElementById(typeHiddenId);
    const idHidden = document.getElementById(idHiddenId);

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();

        // Limpiar timeout anterior
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        if (query.length < 2) {
            dropdown.classList.remove('show');
            return;
        }

        // Esperar 300ms antes de buscar (debounce)
        searchTimeout = setTimeout(async () => {
            // Buscar en lugares locales
            let filtered = allPlaces.filter(place =>
                place.name.toLowerCase().includes(query) ||
                place.city.toLowerCase().includes(query) ||
                place.province.toLowerCase().includes(query)
            );

            // Ordenar por prioridad
            filtered.sort((a, b) => b.priority - a.priority);

            // Limitar a 10 resultados
            filtered = filtered.slice(0, 10);

            let results = filtered.map(place => ({
                id: place.id,
                type: place.type,
                name: place.name,
                subtitle: `${place.city}, ${place.province}`,
                icon: place.icon,
                source: 'local'
            }));

            // Si hay menos de 5 resultados locales, buscar en API de direcciones
            if (results.length < 5 && query.length >= 3) {
                try {
                    const apiResults = await searchAddresses(query);
                    results = [...results, ...apiResults];
                } catch (error) {
                    console.log('Error en búsqueda de direcciones:', error);
                }
            }

            if (results.length === 0) {
                dropdown.classList.remove('show');
                return;
            }

            dropdown.innerHTML = results.map(place => `
                <div class="autocomplete-item"
                     data-id="${place.id}"
                     data-type="${place.type}"
                     data-name="${place.name}">
                    <span class="item-icon">
                        <i class="fas fa-${place.icon}"></i>
                    </span>
                    <span class="item-text">
                        <span class="item-title">${place.name}</span>
                        <span class="item-subtitle">${place.subtitle}</span>
                    </span>
                </div>
            `).join('');

            dropdown.classList.add('show');

            // Click en item
            dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                item.addEventListener('click', function() {
                    input.value = this.dataset.name;
                    typeHidden.value = this.dataset.type;
                    idHidden.value = this.dataset.id;
                    dropdown.classList.remove('show');
                });
            });
        }, 300);
    });

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// Buscar direcciones y POIs en OpenStreetMap Nominatim
async function searchAddresses(query) {
    try {
        const url = `https://nominatim.openstreetmap.org/search?` +
            `q=${encodeURIComponent(query)},Costa Rica&` +
            `format=json&` +
            `addressdetails=1&` +
            `limit=8&` +
            `countrycodes=cr&` +
            `extratags=1`;

        const response = await fetch(url, {
            headers: {
                'User-Agent': 'ShuttlePuraVida/1.0 (compratica.com)'
            }
        });

        if (!response.ok) return [];

        const data = await response.json();

        return data.map(item => {
            // Determinar tipo de lugar e ícono según el tipo OSM
            let type = 'address';
            let icon = 'map-pin';

            // Mapeo de tipos de OpenStreetMap a nuestros iconos
            const osmType = item.type || item.class;

            if (item.type === 'city' || item.type === 'town' || item.type === 'village') {
                type = 'city';
                icon = 'city';
            } else if (item.type === 'hotel' || item.type === 'motel' || item.type === 'guest_house' || osmType === 'hotel') {
                type = 'hotel';
                icon = 'hotel';
            } else if (item.type === 'beach' || osmType === 'beach') {
                type = 'beach';
                icon = 'umbrella-beach';
            } else if (item.type === 'hospital' || osmType === 'hospital' || osmType === 'clinic') {
                type = 'hospital';
                icon = 'hospital';
            } else if (osmType === 'bank' || osmType === 'atm') {
                type = 'bank';
                icon = 'building-columns';
            } else if (osmType === 'fuel' || osmType === 'gas' || osmType === 'fuel_station') {
                type = 'gas_station';
                icon = 'gas-pump';
            } else if (osmType === 'restaurant' || osmType === 'cafe' || osmType === 'fast_food') {
                type = 'restaurant';
                icon = 'utensils';
            } else if (osmType === 'supermarket' || osmType === 'mall' || osmType === 'shopping_center') {
                type = 'shopping';
                icon = 'shopping-cart';
            } else if (osmType === 'pharmacy') {
                type = 'pharmacy';
                icon = 'pills';
            } else if (osmType === 'park' || osmType === 'nature_reserve') {
                type = 'park';
                icon = 'tree';
            } else if (osmType === 'attraction' || osmType === 'tourist') {
                type = 'attraction';
                icon = 'camera';
            } else if (item.class === 'amenity') {
                // Amenity genérico
                icon = 'building';
            }

            const address = item.address || {};
            const subtitle = [
                address.road,
                address.city || address.town || address.village,
                address.state || address.province
            ].filter(Boolean).join(', ') || 'Costa Rica';

            // Usar el nombre si existe, sino usar display_name truncado
            let displayName = item.name || item.display_name.split(',')[0];

            return {
                id: `OSM-${item.osm_id}`,
                type: type,
                name: displayName,
                subtitle: subtitle,
                icon: icon,
                source: 'osm'
            };
        });
    } catch (error) {
        console.error('Error buscando direcciones:', error);
        return [];
    }
}

// Inicializar autocompletado
setupAutocomplete('origin-input', 'origin-dropdown', 'origin-type', 'origin-id');
setupAutocomplete('destination-input', 'destination-dropdown', 'destination-type', 'destination-id');
</script>

</body>
</html>
