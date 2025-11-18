<?php
/**
 * TEST DE DIFERENTES SCOPES PARA UBER DIRECT
 */

$client_id = 'h1E61WLQil9DO6UIz3vPdLTHwsy6mM-O';
$client_secret = 'EuWlPhP-DDmo07J84kpiiIyJ6vP7HKoI96t3rd0_';
$token_url = 'https://sandbox-login.uber.com/oauth/v2/token';

echo "==============================================\n";
echo "PROBANDO DIFERENTES SCOPES\n";
echo "==============================================\n\n";

// Scopes comunes de Uber Direct
$scopes_to_test = [
    'Sin scope' => '',
    'direct.organizations' => 'direct.organizations',
    'direct.deliveries' => 'direct.deliveries',
    'eats.deliveries' => 'eats.deliveries',
    'delivery' => 'delivery',
    'organization.delivery' => 'organization.delivery',
];

foreach ($scopes_to_test as $name => $scope) {
    echo "Probando: $name\n";

    $params = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'client_credentials'
    ];

    if ($scope !== '') {
        $params['scope'] = $scope;
    }

    $ch = curl_init($token_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        echo "  ✅ ¡FUNCIONA! Token obtenido\n";
        echo "  Scope real: " . ($data['scope'] ?? 'ninguno') . "\n";
        echo "  Token: " . substr($data['access_token'], 0, 30) . "...\n\n";
        echo "==============================================\n";
        echo "SCOPE CORRECTO: $name\n";
        echo "==============================================\n";
        exit(0);
    } else {
        $error = json_decode($response, true);
        echo "  ❌ Error: " . ($error['error'] ?? 'unknown') . "\n\n";
    }
}

echo "==============================================\n";
echo "❌ NINGÚN SCOPE FUNCIONÓ\n";
echo "==============================================\n";
echo "\nEs posible que necesites usar Authorization Code Flow\n";
echo "en lugar de Client Credentials Flow.\n\n";
