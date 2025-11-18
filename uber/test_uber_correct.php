<?php
/**
 * TEST CON CREDENCIALES CORRECTAS SEGÚN DOCUMENTACIÓN DE UBER
 */

echo "==============================================\n";
echo "TEST UBER DIRECT - SANDBOX\n";
echo "==============================================\n\n";

// Credenciales CORREGIDAS según la documentación de Uber
$client_id = 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O';
$client_secret = 'EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_'; // SIN el prefijo "UBR:"
$customer_id = 'af3e1e84-ea00-4be1-af4c-5bd162a31a34';

echo "[1/2] Intentando Client Credentials Flow...\n";
echo "  URL: https://sandbox-login.uber.com/oauth/v2/token\n";
echo "  Client ID: " . substr($client_id, 0, 20) . "...\n\n";

// Probar con Client Credentials (sin código de autorización)
$token_url = 'https://sandbox-login.uber.com/oauth/v2/token';

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
curl_close($ch);

echo "  HTTP Status: $http_code\n";

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        echo "  ✅ ¡TOKEN OBTENIDO EXITOSAMENTE!\n";
        echo "  Token: " . substr($data['access_token'], 0, 40) . "...\n";
        echo "  Expira en: " . ($data['expires_in'] / 60) . " minutos\n\n";

        // Probar Quote API
        echo "[2/2] Probando Quote API...\n";
        $quote_url = "https://sandbox-api.uber.com/v1/customers/$customer_id/delivery_quotes";

        $payload = [
            'pickup' => [
                'location' => [
                    'address' => 'Avenida Central, San José, Costa Rica',
                    'latitude' => 9.9281,
                    'longitude' => -84.0907
                ]
            ],
            'dropoff' => [
                'location' => [
                    'address' => 'La Sabana, San José, Costa Rica',
                    'latitude' => 9.9355,
                    'longitude' => -84.0834
                ]
            ]
        ];

        $ch = curl_init($quote_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $data['access_token'],
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "  HTTP Status: $http_code\n";

        if ($http_code === 200) {
            $quote = json_decode($response, true);
            echo "  ✅ COTIZACIÓN EXITOSA\n";
            echo "  Quote ID: " . ($quote['id'] ?? 'N/A') . "\n";
            echo "  Costo: " . ($quote['fee'] ?? 'N/A') . " " . ($quote['currency_code'] ?? 'N/A') . "\n\n";

            echo "==============================================\n";
            echo "✅ ¡INTEGRACIÓN FUNCIONANDO CORRECTAMENTE!\n";
            echo "==============================================\n";
        } else {
            $error = json_decode($response, true);
            echo "  ⚠️ Error en cotización\n";
            echo "  " . json_encode($error, JSON_PRETTY_PRINT) . "\n\n";
        }
    }
} else {
    $data = json_decode($response, true);
    echo "  ❌ Error al obtener token\n";
    echo "  Error: " . ($data['error'] ?? 'unknown') . "\n";
    echo "  Mensaje: " . ($data['error_description'] ?? 'N/A') . "\n\n";

    echo "NOTA: Uber Direct puede requerir Authorization Code Flow\n";
    echo "en lugar de Client Credentials Flow para ciertas operaciones.\n";
}
