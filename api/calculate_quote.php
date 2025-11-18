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
$origin = trim($input['origin'] ?? '');
$originType = trim($input['origin_type'] ?? 'address'); // 'address' o 'airport'
$destination = trim($input['destination'] ?? '');
$destType = trim($input['destination_type'] ?? 'address'); // 'address' o 'airport'

if (!$serviceId || !$origin || !$destination) {
    echo json_encode(['ok' => false, 'error' => 'Servicio, origen y destino son requeridos']);
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

    // Función para obtener coordenadas según tipo
    function getCoordinates($value, $type, $airports, $locations) {
        if ($type === 'airport' && isset($airports[$value])) {
            return [
                'lat' => $airports[$value]['lat'],
                'lon' => $airports[$value]['lon'],
                'name' => $airports[$value]['name']
            ];
        } else {
            // Es una dirección o coordenadas
            // Si viene como "lat,lon" (desde geolocalización)
            if (strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                if (count($parts) === 2) {
                    return [
                        'lat' => (float)trim($parts[0]),
                        'lon' => (float)trim($parts[1]),
                        'name' => 'Ubicación actual'
                    ];
                }
            }

            // Detectar ciudad en la dirección
            $addressLower = strtolower($value);
            foreach ($locations as $city => $coords) {
                if (strpos($addressLower, $city) !== false) {
                    return [
                        'lat' => $coords['lat'],
                        'lon' => $coords['lon'],
                        'name' => ucfirst($city)
                    ];
                }
            }

            // Por defecto: San José centro
            return [
                'lat' => 9.9281,
                'lon' => -84.0907,
                'name' => 'San José'
            ];
        }
    }

    // Obtener coordenadas de origen y destino
    $originCoords = getCoordinates($origin, $originType, $airports, $locations);
    $destCoords = getCoordinates($destination, $destType, $airports, $locations);

    $originLat = $originCoords['lat'];
    $originLon = $originCoords['lon'];
    $originName = $originCoords['name'];

    $destLat = $destCoords['lat'];
    $destLon = $destCoords['lon'];
    $destName = $destCoords['name'];

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

    // Calcular distancia entre origen y destino
    $distanceKm = calculateDistance($originLat, $originLon, $destLat, $destLon);

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
        'origin' => [
            'value' => $origin,
            'type' => $originType,
            'name' => $originName,
            'lat' => $originLat,
            'lon' => $originLon
        ],
        'destination' => [
            'value' => $destination,
            'type' => $destType,
            'name' => $destName,
            'lat' => $destLat,
            'lon' => $destLon
        ],
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
