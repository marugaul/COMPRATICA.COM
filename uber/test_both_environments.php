<?php
/**
 * TEST RÁPIDO DE AMBOS AMBIENTES
 * Prueba tanto sandbox como producción para determinar el ambiente correcto
 */

$client_id = 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O';
$client_secret = 'UBR:EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_';
$customer_id = 'af3e1e84-ea00-4be1-af4c-5bd162a31a34';

echo "==============================================\n";
echo "PROBANDO AMBOS AMBIENTES DE UBER\n";
echo "==============================================\n\n";

$ambientes = [
    'sandbox' => 'https://auth.uber.com/oauth/v2/token',
    'production' => 'https://login.uber.com/oauth/v2/token'
];

foreach ($ambientes as $nombre => $token_url) {
    echo "[$nombre] Probando autenticación...\n";
    echo "  Token URL: $token_url\n";

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
            echo "  ✅ ¡FUNCIONA! Token obtenido\n";
            echo "  Token: " . substr($data['access_token'], 0, 30) . "...\n\n";
            echo "==============================================\n";
            echo "RESULTADO: Tus credenciales son de " . strtoupper($nombre) . "\n";
            echo "==============================================\n";
            exit(0);
        }
    } else {
        $data = json_decode($response, true);
        echo "  ❌ Error: " . ($data['error'] ?? 'unknown') . "\n";
        echo "  Mensaje: " . ($data['error_description'] ?? 'N/A') . "\n\n";
    }
}

echo "==============================================\n";
echo "❌ NINGÚN AMBIENTE FUNCIONÓ\n";
echo "==============================================\n\n";
echo "RECOMENDACIÓN:\n";
echo "Las credenciales pueden estar incorrectas o mal copiadas.\n";
echo "Verifica en tu Uber Developer Dashboard:\n";
echo "https://developer.uber.com/dashboard\n\n";
