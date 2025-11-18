<?php
/**
 * API para calcular cotización de servicio de transporte turístico
 * Calcula el precio basado en la distancia desde la dirección de recogida
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$serviceId = (int)($input['service_id'] ?? 0);
$pickupAddress = trim($input['pickup_address'] ?? '');
$destination = trim($input['destination'] ?? ''); // Aeropuerto o destino

if (!$serviceId || !$pickupAddress) {
    echo json_encode(['ok' => false, 'error' => 'Servicio y dirección de recogida son requeridos']);
    exit;
}

try {
    $pdo = db();

    // Obtener información del servicio
    $stmt = $pdo->prepare("
        SELECT * FROM services
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['ok' => false, 'error' => 'Servicio no encontrado']);
        exit;
    }

    // Aeropuertos de Costa Rica con coordenadas aproximadas
    $airports = [
        'SJO' => ['name' => 'Aeropuerto Juan Santamaría', 'lat' => 9.9936, 'lon' => -84.2089],
        'LIR' => ['name' => 'Aeropuerto Daniel Oduber (Liberia)', 'lat' => 10.5933, 'lon' => -85.5444],
        'LIO' => ['name' => 'Aeropuerto de Limón', 'lat' => 9.9580, 'lon' => -83.0220],
        'TOO' => ['name' => 'Aeropuerto de San Vito', 'lat' => 8.8261, 'lon' => -82.9589],
        'SYQ' => ['name' => 'Aeropuerto Tobías Bolaños (Pavas)', 'lat' => 9.9571, 'lon' => -84.1398]
    ];

    // Ciudades/zonas principales de Costa Rica
    $locations = [
        'san-jose' => ['lat' => 9.9281, 'lon' => -84.0907],
        'heredia' => ['lat' => 9.9989, 'lon' => -84.1172],
        'alajuela' => ['lat' => 10.0162, 'lon' => -84.2119],
        'cartago' => ['lat' => 9.8626, 'lon' => -83.9194],
        'samara' => ['lat' => 10.2964, 'lon' => -85.5280],
        'tamarindo' => ['lat' => 10.3000, 'lon' => -85.8376],
        'jaco' => ['lat' => 9.6146, 'lon' => -84.6287],
        'puntarenas' => ['lat' => 9.9763, 'lon' => -84.8350],
        'limon' => ['lat' => 10.0000, 'lon' => -83.0333]
    ];

    // Determinar destino (coordenadas)
    $destLat = null;
    $destLon = null;
    $destName = '';

    if ($destination && isset($airports[$destination])) {
        $destLat = $airports[$destination]['lat'];
        $destLon = $airports[$destination]['lon'];
        $destName = $airports[$destination]['name'];
    }

    // Calcular coordenadas aproximadas de la dirección de recogida
    // (En producción usarías Google Maps Geocoding API)
    // Por ahora usaremos San José como centro por defecto
    $pickupLat = 9.9281;
    $pickupLon = -84.0907;

    // Detectar ciudad en la dirección
    $addressLower = strtolower($pickupAddress);
    foreach ($locations as $city => $coords) {
        if (strpos($addressLower, $city) !== false) {
            $pickupLat = $coords['lat'];
            $pickupLon = $coords['lon'];
            break;
        }
    }

    // Calcular distancia usando fórmula de Haversine
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        return $distance;
    }

    $distanceKm = 0;
    if ($destLat && $destLon) {
        $distanceKm = calculateDistance($pickupLat, $pickupLon, $destLat, $destLon);
    } else {
        // Si no hay destino específico, usar la distancia base del servicio
        $distanceKm = 20; // Default 20km
    }

    // Calcular precio basado en distancia
    // Precio base + (₡500 por km adicional sobre los primeros 10km)
    $basePrice = (float)$service['price_per_hour'];
    $pricePerKm = 500;
    $freeKm = 10;

    $additionalKm = max(0, $distanceKm - $freeKm);
    $totalPrice = $basePrice + ($additionalKm * $pricePerKm);

    // Redondear al múltiplo de 1000 más cercano
    $totalPrice = round($totalPrice / 1000) * 1000;

    echo json_encode([
        'ok' => true,
        'service_id' => $serviceId,
        'service_title' => $service['title'],
        'pickup_address' => $pickupAddress,
        'destination' => $destination,
        'destination_name' => $destName,
        'distance_km' => round($distanceKm, 1),
        'base_price' => $basePrice,
        'price_per_km' => $pricePerKm,
        'total_price' => $totalPrice,
        'currency' => 'CRC',
        'formatted_price' => '₡' . number_format($totalPrice, 0, ',', '.'),
        'breakdown' => [
            'base' => '₡' . number_format($basePrice, 0, ',', '.'),
            'distance' => round($distanceKm, 1) . ' km',
            'additional_km' => round($additionalKm, 1) . ' km',
            'additional_cost' => '₡' . number_format($additionalKm * $pricePerKm, 0, ',', '.')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al calcular cotización: ' . $e->getMessage()]);
}
