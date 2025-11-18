<?php
/**
 * TEST DE UBER DIRECT API
 *
 * Este script prueba:
 * 1. Autenticación OAuth con Uber
 * 2. Obtención de cotización de delivery
 *
 * USO: php uber/test_uber_api.php
 */

declare(strict_types=1);

echo "==============================================\n";
echo "TEST DE UBER DIRECT API\n";
echo "==============================================\n\n";

// Credenciales
$client_id = 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O';
$client_secret = 'UBR:EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_';
$customer_id = 'af3e1e84-ea00-4be1-af4c-5bd162a31a34';

echo "[1/3] Probando autenticación OAuth...\n";
echo "  Client ID: " . substr($client_id, 0, 20) . "...\n";
echo "  Customer ID: $customer_id\n\n";

// ==================================================
// PASO 1: Obtener Token OAuth
// ==================================================
// Usar endpoint de SANDBOX según el curl de ejemplo de Uber
$token_url = 'https://auth.uber.com/oauth/v2/token';

$ch = curl_init($token_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'client_credentials',
        'scope' => 'eats.deliveries'
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ ERROR DE CONEXIÓN: $curl_error\n";
    exit(1);
}

echo "  HTTP Status: $http_code\n";

if ($http_code !== 200) {
    echo "\n❌ AUTENTICACIÓN FALLÓ\n";
    echo "  Respuesta de Uber:\n";
    echo "  " . $response . "\n\n";
    echo "POSIBLES CAUSAS:\n";
    echo "  1. Las credenciales son incorrectas\n";
    echo "  2. El client_id o client_secret están mal copiados\n";
    echo "  3. La cuenta de Uber no tiene permisos para 'eats.deliveries'\n";
    echo "  4. El ambiente (sandbox/producción) no coincide\n\n";
    exit(1);
}

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    echo "\n❌ NO SE RECIBIÓ TOKEN\n";
    echo "  Respuesta: " . print_r($token_data, true) . "\n";
    exit(1);
}

$access_token = $token_data['access_token'];
$expires_in = (int)($token_data['expires_in'] ?? 3600);

echo "  ✅ Token obtenido exitosamente\n";
echo "  Token: " . substr($access_token, 0, 30) . "...\n";
echo "  Expira en: " . ($expires_in / 60) . " minutos\n\n";

// ==================================================
// PASO 2: Probar Quote API
// ==================================================
echo "[2/3] Probando Quote API (cotización de delivery)...\n";

// Coordenadas de prueba en San José, Costa Rica
$pickup_lat = 9.9281;   // San José centro
$pickup_lng = -84.0907;
$dropoff_lat = 9.9355;  // Cerca de San José
$dropoff_lng = -84.0834;

// Usar endpoint de SANDBOX
$quote_url = "https://sandbox-api.uber.com/v1/customers/$customer_id/delivery_quotes";

$quote_payload = [
    'pickup' => [
        'location' => [
            'address' => 'Avenida Central, San José, Costa Rica',
            'latitude' => $pickup_lat,
            'longitude' => $pickup_lng
        ]
    ],
    'dropoff' => [
        'location' => [
            'address' => 'Sabana, San José, Costa Rica',
            'latitude' => $dropoff_lat,
            'longitude' => $dropoff_lng
        ]
    ]
];

echo "  Pickup: Avenida Central, San José\n";
echo "  Dropoff: Sabana, San José\n\n";

$ch = curl_init($quote_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($quote_payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ ERROR DE CONEXIÓN: $curl_error\n";
    exit(1);
}

echo "  HTTP Status: $http_code\n";

$quote_data = json_decode($response, true);

if ($http_code === 200 && isset($quote_data['id'])) {
    echo "\n  ✅ COTIZACIÓN EXITOSA\n";
    echo "  Quote ID: " . $quote_data['id'] . "\n";
    echo "  Costo: " . ($quote_data['fee'] ?? 'N/A') . " " . ($quote_data['currency_code'] ?? '') . "\n";
    echo "  Pickup ETA: " . ($quote_data['pickup_eta'] ?? 'N/A') . "\n";
    echo "  Dropoff ETA: " . ($quote_data['dropoff_eta'] ?? 'N/A') . "\n";
    echo "  Expira: " . ($quote_data['expires_at'] ?? 'N/A') . "\n\n";
} else {
    echo "\n  ⚠️ COTIZACIÓN FALLÓ\n";
    echo "  Código HTTP: $http_code\n";
    echo "  Respuesta:\n";
    echo "  " . json_encode($quote_data, JSON_PRETTY_PRINT) . "\n\n";

    echo "ANÁLISIS DEL ERROR:\n";

    if ($http_code === 400) {
        echo "  ❌ Bad Request (400)\n";
        $error_type = $quote_data['code'] ?? $quote_data['message'] ?? 'unknown';
        echo "  Error: $error_type\n\n";

        if (stripos($error_type, 'undeliverable') !== false) {
            echo "  CAUSA: La ubicación no está en el área de cobertura de Uber Direct\n";
            echo "  SOLUCIÓN: Uber Direct no opera en todas las ciudades de Costa Rica.\n";
            echo "            Verifica en https://www.uber.com/cr/es/business/uber-direct/\n\n";
        } elseif (stripos($error_type, 'unknown_location') !== false) {
            echo "  CAUSA: Las coordenadas no son válidas\n";
            echo "  SOLUCIÓN: Verifica las coordenadas de pickup/dropoff\n\n";
        } else {
            echo "  SOLUCIÓN: Revisa la documentación de Uber Direct API\n\n";
        }
    } elseif ($http_code === 401) {
        echo "  ❌ No autorizado (401)\n";
        echo "  CAUSA: El token expiró o es inválido\n\n";
    } elseif ($http_code === 402) {
        echo "  ❌ Cuenta suspendida (402)\n";
        echo "  CAUSA: Tu cuenta de Uber está suspendida o tiene problemas de pago\n\n";
    } elseif ($http_code === 403) {
        echo "  ❌ Acceso denegado (403)\n";
        echo "  CAUSA: El customer_id no es válido o no tienes permisos\n\n";
    } elseif ($http_code === 404) {
        echo "  ❌ Customer no encontrado (404)\n";
        echo "  CAUSA: El customer_id '$customer_id' no existe\n";
        echo "  SOLUCIÓN: Verifica tu customer_id en Uber Dashboard\n\n";
    }
}

// ==================================================
// PASO 3: Resumen Final
// ==================================================
echo "[3/3] Resumen de la Prueba\n";
echo "==============================================\n\n";

if ($http_code === 200 && isset($quote_data['id'])) {
    echo "✅ TODAS LAS PRUEBAS PASARON\n\n";
    echo "Tu integración con Uber Direct está funcionando correctamente.\n";
    echo "Puedes proceder a:\n";
    echo "  1. Ejecutar la migración: php uber/migrate_uber_integration.php\n";
    echo "  2. Configurar direcciones de pickup de afiliados\n";
    echo "  3. Probar cotizaciones en el checkout\n\n";
} else {
    echo "⚠️ HAY PROBLEMAS CON LA API\n\n";
    echo "ESTADO:\n";
    echo "  ✅ Autenticación OAuth: FUNCIONA\n";
    echo "  ❌ Quote API: NO FUNCIONA (ver detalles arriba)\n\n";

    echo "PRÓXIMOS PASOS:\n";
    echo "  1. Revisa el error específico arriba\n";
    echo "  2. Verifica que Uber Direct opere en tu ciudad\n";
    echo "  3. Contacta a soporte de Uber si el problema persiste\n\n";

    echo "MIENTRAS TANTO:\n";
    echo "  El sistema funcionará en MODO SANDBOX con cotizaciones simuladas.\n\n";
}

echo "==============================================\n";
