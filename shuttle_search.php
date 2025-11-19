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
    <title>Shuttle Pura Vida 🇨🇷 - CompraTica</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            padding: 0.875rem 1rem;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-100);
        }

        .autocomplete-item:hover {
            background: var(--gray-50);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item .item-icon {
            display: inline-block;
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: var(--white);
            border-radius: 8px;
            text-align: center;
            line-height: 32px;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .autocomplete-item .item-text {
            display: inline-block;
            vertical-align: middle;
        }

        .autocomplete-item .item-title {
            font-weight: 600;
            color: var(--gray-900);
            display: block;
        }

        .autocomplete-item .item-subtitle {
            font-size: 0.85rem;
            color: var(--gray-500);
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

            <!-- Fecha -->
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
// Sistema de autocompletado para lugares de Costa Rica
const places = {
    airports: [
        { id: 'SJO', type: 'airport', name: 'Aeropuerto Juan Santamaría (SJO)', city: 'Alajuela', icon: 'plane' },
        { id: 'LIR', type: 'airport', name: 'Aeropuerto Daniel Oduber (LIR)', city: 'Liberia', icon: 'plane' },
        { id: 'LIO', type: 'airport', name: 'Aeropuerto de Limón (LIO)', city: 'Limón', icon: 'plane' },
        { id: 'SYQ', type: 'airport', name: 'Aeropuerto Tobías Bolaños (SYQ)', city: 'Pavas', icon: 'plane' },
        { id: 'TOO', type: 'airport', name: 'Aeropuerto de San Vito (TOO)', city: 'San Vito', icon: 'plane' }
    ],
    ports: [
        { id: 'PCL', type: 'port', name: 'Puerto Caldera', city: 'Puntarenas', icon: 'ship' },
        { id: 'PLI', type: 'port', name: 'Puerto Limón', city: 'Limón', icon: 'ship' },
        { id: 'PGO', type: 'port', name: 'Golfo de Papagayo', city: 'Guanacaste', icon: 'ship' }
    ],
    cities: [
        { id: 'SJO-CITY', type: 'city', name: 'San José Centro', city: 'San José', icon: 'city' },
        { id: 'ESC', type: 'city', name: 'Escazú', city: 'San José', icon: 'city' },
        { id: 'SAN', type: 'city', name: 'Santa Ana', city: 'San José', icon: 'city' },
        { id: 'HER', type: 'city', name: 'Heredia', city: 'Heredia', icon: 'city' },
        { id: 'CAR', type: 'city', name: 'Cartago', city: 'Cartago', icon: 'city' },
        { id: 'TAM', type: 'city', name: 'Tamarindo', city: 'Guanacaste', icon: 'city' },
        { id: 'JAC', type: 'city', name: 'Jacó', city: 'Puntarenas', icon: 'city' },
        { id: 'PUN', type: 'city', name: 'Puntarenas', city: 'Puntarenas', icon: 'city' },
        { id: 'QUE', type: 'city', name: 'Quepos', city: 'Puntarenas', icon: 'city' },
        { id: 'SAM', type: 'city', name: 'Sámara', city: 'Guanacaste', icon: 'city' },
        { id: 'FLA', type: 'city', name: 'Flamingo', city: 'Guanacaste', icon: 'city' },
        { id: 'CON', type: 'city', name: 'Conchal', city: 'Guanacaste', icon: 'city' }
    ]
};

// Combinar todos los lugares
const allPlaces = [...places.airports, ...places.ports, ...places.cities];

function setupAutocomplete(inputId, dropdownId, typeHiddenId, idHiddenId) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const typeHidden = document.getElementById(typeHiddenId);
    const idHidden = document.getElementById(idHiddenId);

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();

        if (query.length < 2) {
            dropdown.classList.remove('show');
            return;
        }

        const filtered = allPlaces.filter(place =>
            place.name.toLowerCase().includes(query) ||
            place.city.toLowerCase().includes(query)
        );

        if (filtered.length === 0) {
            dropdown.classList.remove('show');
            return;
        }

        dropdown.innerHTML = filtered.map(place => `
            <div class="autocomplete-item" data-id="${place.id}" data-type="${place.type}" data-name="${place.name}">
                <span class="item-icon">
                    <i class="fas fa-${place.icon}"></i>
                </span>
                <span class="item-text">
                    <span class="item-title">${place.name}</span>
                    <span class="item-subtitle">${place.city}</span>
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
    });

    // Cerrar dropdown al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// Inicializar autocompletado
setupAutocomplete('origin-input', 'origin-dropdown', 'origin-type', 'origin-id');
setupAutocomplete('destination-input', 'destination-dropdown', 'destination-type', 'destination-id');
</script>

</body>
</html>
