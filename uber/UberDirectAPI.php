<?php
declare(strict_types=1);
/**
 * UberDirectAPI - Integración con Uber Direct API
 * Documentación: https://developer.uber.com/docs/deliveries
 * 
 * UBICACIÓN: /uber/UberDirectAPI.php
 * 
 * USO:
 * require_once __DIR__ . '/uber/UberDirectAPI.php';
 * $uber = new UberDirectAPI($pdo);
 */

class UberDirectAPI {
    private $pdo;
    private $client_id;
    private $client_secret;
    private $customer_id;
    private $is_sandbox;
    private $access_token;
    private $token_expires_at;
    private $commission_percentage;
    
    private const SANDBOX_BASE_URL = 'https://api.uber.com/v1';
    private const PROD_BASE_URL = 'https://api.uber.com/v1';
    private const TOKEN_URL = 'https://login.uber.com/oauth/v2/token';
    
    public function __construct($pdo, $affiliate_id = null) {
        $this->pdo = $pdo;
        $this->loadConfig($affiliate_id);
    }
    
    /**
     * Cargar configuración de Uber (global o por afiliado)
     */
    private function loadConfig($affiliate_id = null) {
        $sql = "SELECT * FROM uber_config WHERE ";
        $sql .= $affiliate_id ? "affiliate_id = ?" : "(affiliate_id IS NULL OR affiliate_id = 0)";
        $sql .= " AND is_active = 1 ORDER BY id DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        if ($affiliate_id) {
            $stmt->execute([$affiliate_id]);
        } else {
            $stmt->execute();
        }
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            throw new Exception("Configuración de Uber no encontrada");
        }
        
        $this->client_id = $config['client_id'] ?? '';
        $this->client_secret = $config['client_secret'] ?? '';
        $this->customer_id = $config['customer_id'] ?? '';
        $this->is_sandbox = (bool)($config['is_sandbox'] ?? true);
        $this->access_token = $config['access_token'] ?? '';
        $this->token_expires_at = $config['token_expires_at'] ?? '';
        $this->commission_percentage = (float)($config['commission_percentage'] ?? 15.0);
        
        if (!$this->client_id || !$this->client_secret) {
            throw new Exception("Credenciales de Uber no configuradas");
        }
    }
    
    /**
     * Obtener o renovar access token
     */
    private function getAccessToken(): string {
        // Si el token existe y no ha expirado, usarlo
        if ($this->access_token && $this->token_expires_at) {
            $expires = strtotime($this->token_expires_at);
            if ($expires > time() + 300) { // 5 minutos de margen
                return $this->access_token;
            }
        }
        
        // Solicitar nuevo token
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'client_credentials',
                'scope' => 'eats.deliveries'
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Error al obtener token de Uber: HTTP $http_code - $response");
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Token no recibido de Uber");
        }
        
        $this->access_token = $data['access_token'];
        $expires_in = (int)($data['expires_in'] ?? 3600);
        $this->token_expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        // Guardar token en DB
        $this->pdo->prepare("
            UPDATE uber_config 
            SET access_token = ?, 
                token_expires_at = ?,
                updated_at = datetime('now')
            WHERE customer_id = ?
        ")->execute([$this->access_token, $this->token_expires_at, $this->customer_id]);
        
        return $this->access_token;
    }
    
    /**
     * Hacer request a la API de Uber
     */
    private function request(string $method, string $endpoint, array $data = null): array {
        $token = $this->getAccessToken();
        $base_url = $this->is_sandbox ? self::SANDBOX_BASE_URL : self::PROD_BASE_URL;
        $url = $base_url . $endpoint;
        
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);
        
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true) ?? [];
        
        if ($http_code < 200 || $http_code >= 300) {
            $error = $result['message'] ?? $result['error'] ?? 'Error desconocido';
            throw new Exception("Uber API Error (HTTP $http_code): $error");
        }
        
        return $result;
    }
    
    /**
     * Obtener cotización de envío
     */
    public function getQuote(array $params): array {
        /*
        $params = [
            'pickup' => [
                'address' => 'Dirección completa',
                'lat' => 9.9281,
                'lng' => -84.0907
            ],
            'dropoff' => [
                'address' => 'Dirección completa',
                'lat' => 9.9355,
                'lng' => -84.0834
            ]
        ];
        */
        
        $payload = [
            'pickup' => [
                'location' => [
                    'address' => $params['pickup']['address'] ?? '',
                    'latitude' => (float)($params['pickup']['lat'] ?? 0),
                    'longitude' => (float)($params['pickup']['lng'] ?? 0)
                ]
            ],
            'dropoff' => [
                'location' => [
                    'address' => $params['dropoff']['address'] ?? '',
                    'latitude' => (float)($params['dropoff']['lat'] ?? 0),
                    'longitude' => (float)($params['dropoff']['lng'] ?? 0)
                ]
            ]
        ];
        
        $response = $this->request('POST', "/customers/{$this->customer_id}/delivery_quotes", $payload);
        
        // Calcular costos con comisión
        $uber_cost = (float)($response['fee'] ?? 0);
        $commission = $uber_cost * ($this->commission_percentage / 100);
        $total = $uber_cost + $commission;
        
        return [
            'quote_id' => $response['id'] ?? '',
            'uber_base_cost' => $uber_cost,
            'platform_commission' => $commission,
            'total_cost' => $total,
            'currency' => $response['currency_code'] ?? 'CRC',
            'estimated_pickup_time' => $response['pickup_eta'] ?? null,
            'estimated_delivery_time' => $response['dropoff_eta'] ?? null,
            'expires_at' => $response['expires_at'] ?? null,
            'raw_response' => json_encode($response)
        ];
    }
    
    /**
     * Crear delivery en Uber
     */
    public function createDelivery(array $params): array {
        /*
        $params = [
            'quote_id' => 'xxx',
            'pickup' => [
                'address' => '...',
                'lat' => 9.9281,
                'lng' => -84.0907,
                'contact_name' => 'Juan Pérez',
                'contact_phone' => '+50612345678',
                'instructions' => 'Tocar timbre azul'
            ],
            'dropoff' => [
                'address' => '...',
                'lat' => 9.9355,
                'lng' => -84.0834,
                'contact_name' => 'María González',
                'contact_phone' => '+50687654321',
                'instructions' => 'Dejar en recepción'
            ],
            'items' => [
                [
                    'title' => 'Producto 1',
                    'quantity' => 2,
                    'price' => 1000
                ]
            ]
        ];
        */
        
        $payload = [
            'quote_id' => $params['quote_id'] ?? null,
            'pickup' => [
                'location' => [
                    'address' => $params['pickup']['address'] ?? '',
                    'latitude' => (float)($params['pickup']['lat'] ?? 0),
                    'longitude' => (float)($params['pickup']['lng'] ?? 0)
                ],
                'contact' => [
                    'name' => $params['pickup']['contact_name'] ?? '',
                    'phone' => [
                        'number' => $params['pickup']['contact_phone'] ?? '',
                        'sms_enabled' => true
                    ]
                ],
                'notes' => $params['pickup']['instructions'] ?? ''
            ],
            'dropoff' => [
                'location' => [
                    'address' => $params['dropoff']['address'] ?? '',
                    'latitude' => (float)($params['dropoff']['lat'] ?? 0),
                    'longitude' => (float)($params['dropoff']['lng'] ?? 0)
                ],
                'contact' => [
                    'name' => $params['dropoff']['contact_name'] ?? '',
                    'phone' => [
                        'number' => $params['dropoff']['contact_phone'] ?? '',
                        'sms_enabled' => true
                    ]
                ],
                'notes' => $params['dropoff']['instructions'] ?? ''
            ]
        ];
        
        // Agregar items si existen
        if (!empty($params['items'])) {
            $payload['manifest'] = [
                'items' => array_map(function($item) {
                    return [
                        'name' => $item['title'] ?? '',
                        'quantity' => (int)($item['quantity'] ?? 1),
                        'size' => 'small' // small, medium, large, xlarge
                    ];
                }, $params['items'])
            ];
        }
        
        $response = $this->request('POST', "/customers/{$this->customer_id}/deliveries", $payload);
        
        return [
            'delivery_id' => $response['id'] ?? '',
            'tracking_url' => $response['tracking_url'] ?? '',
            'status' => $response['status'] ?? 'pending',
            'courier' => $response['courier'] ?? null,
            'raw_response' => json_encode($response)
        ];
    }
    
    /**
     * Obtener estado de delivery
     */
    public function getDeliveryStatus(string $delivery_id): array {
        $response = $this->request('GET', "/customers/{$this->customer_id}/deliveries/{$delivery_id}");
        
        return [
            'status' => $response['status'] ?? 'unknown',
            'courier' => $response['courier'] ?? null,
            'tracking_url' => $response['tracking_url'] ?? '',
            'pickup_time' => $response['pickup']['time'] ?? null,
            'dropoff_time' => $response['dropoff']['time'] ?? null,
            'raw_response' => json_encode($response)
        ];
    }
    
    /**
     * Cancelar delivery
     */
    public function cancelDelivery(string $delivery_id, string $reason = 'Cancelado por el usuario'): array {
        $payload = ['reason' => $reason];
        $response = $this->request('POST', "/customers/{$this->customer_id}/deliveries/{$delivery_id}/cancel", $payload);
        
        return [
            'cancelled' => true,
            'status' => $response['status'] ?? 'cancelled',
            'raw_response' => json_encode($response)
        ];
    }
    
    /**
     * Geocodificar dirección (obtener lat/lng)
     */
    public function geocodeAddress(string $address): ?array {
        // Usar Google Geocoding API o similar
        // Por ahora retornar null para implementación manual
        return null;
    }
}