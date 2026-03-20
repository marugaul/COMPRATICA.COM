<?php
declare(strict_types=1);
/**
 * MovingAPI - Integración con Mooving Delivery API
 * API para cotizaciones y gestión de envíos con Mooving
 *
 * UBICACIÓN: /mooving/MovingAPI.php
 *
 * USO:
 * require_once __DIR__ . '/mooving/MovingAPI.php';
 * $mooving = new MovingAPI($pdo);
 */

class MovingAPI {
    private $pdo;
    private $api_key;
    private $api_secret;
    private $merchant_id;
    private $is_sandbox;
    private $commission_percentage;

    private const SANDBOX_BASE_URL = 'https://sandbox-api.mooving.com/v1';
    private const PROD_BASE_URL = 'https://api.mooving.com/v1';

    public function __construct($pdo, $entrepreneur_id = null) {
        $this->pdo = $pdo;
        $this->loadConfig($entrepreneur_id);
    }

    /**
     * Cargar configuración de Mooving (global o por emprendedora)
     */
    private function loadConfig($entrepreneur_id = null) {
        $sql = "SELECT * FROM mooving_config WHERE ";
        $sql .= $entrepreneur_id ? "entrepreneur_id = ?" : "(entrepreneur_id IS NULL OR entrepreneur_id = 0)";
        $sql .= " AND is_active = 1 ORDER BY id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        if ($entrepreneur_id) {
            $stmt->execute([$entrepreneur_id]);
        } else {
            $stmt->execute();
        }

        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no hay configuración específica, usar valores por defecto
        if (!$config) {
            $this->api_key = '';
            $this->api_secret = '';
            $this->merchant_id = '';
            $this->is_sandbox = true;
            $this->commission_percentage = 15.0;
            return;
        }

        $this->api_key = $config['api_key'] ?? '';
        $this->api_secret = $config['api_secret'] ?? '';
        $this->merchant_id = $config['merchant_id'] ?? '';
        $this->is_sandbox = (bool)($config['is_sandbox'] ?? true);
        $this->commission_percentage = (float)($config['commission_percentage'] ?? 15.0);
    }

    /**
     * Crear tabla de configuración si no existe
     */
    public function initConfigTable(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS mooving_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entrepreneur_id INTEGER DEFAULT NULL,
                api_key TEXT NOT NULL,
                api_secret TEXT NOT NULL,
                merchant_id TEXT NOT NULL,
                is_sandbox INTEGER DEFAULT 1,
                is_active INTEGER DEFAULT 1,
                commission_percentage REAL DEFAULT 15.0,
                created_at TEXT DEFAULT (datetime('now','localtime')),
                updated_at TEXT DEFAULT (datetime('now','localtime')),
                UNIQUE(entrepreneur_id)
            )
        ");
    }

    /**
     * Obtener cotización de envío
     *
     * @param array $origin ['lat' => float, 'lng' => float, 'address' => string]
     * @param array $destination ['lat' => float, 'lng' => float, 'address' => string]
     * @param array $package ['weight' => float (kg), 'dimensions' => array, 'value' => float]
     * @return array ['ok' => bool, 'price' => float, 'currency' => string, 'estimated_time' => int (minutos), 'quote_id' => string]
     */
    public function getQuote(array $origin, array $destination, array $package = []): array {
        try {
            if (!$this->api_key || !$this->api_secret) {
                return [
                    'ok' => false,
                    'error' => 'API de Mooving no configurada',
                    'price' => 0,
                    'currency' => 'CRC'
                ];
            }

            $endpoint = '/deliveries/quote';
            $data = [
                'origin' => [
                    'latitude' => $origin['lat'] ?? 0,
                    'longitude' => $origin['lng'] ?? 0,
                    'address' => $origin['address'] ?? ''
                ],
                'destination' => [
                    'latitude' => $destination['lat'] ?? 0,
                    'longitude' => $destination['lng'] ?? 0,
                    'address' => $destination['address'] ?? ''
                ],
                'package' => [
                    'weight' => $package['weight'] ?? 1.0,
                    'value' => $package['value'] ?? 0,
                    'description' => $package['description'] ?? 'Productos de emprendedora'
                ]
            ];

            $response = $this->request('POST', $endpoint, $data);

            if (isset($response['error'])) {
                return [
                    'ok' => false,
                    'error' => $response['error'],
                    'price' => 0,
                    'currency' => 'CRC'
                ];
            }

            // Aplicar comisión si está configurada
            $basePrice = (float)($response['price'] ?? 0);
            $finalPrice = $basePrice * (1 + ($this->commission_percentage / 100));

            return [
                'ok' => true,
                'price' => round($finalPrice, 2),
                'base_price' => $basePrice,
                'commission' => $finalPrice - $basePrice,
                'currency' => $response['currency'] ?? 'CRC',
                'estimated_time' => (int)($response['estimated_minutes'] ?? 60),
                'quote_id' => $response['quote_id'] ?? uniqid('mov_'),
                'distance_km' => (float)($response['distance_km'] ?? 0)
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'price' => 0,
                'currency' => 'CRC'
            ];
        }
    }

    /**
     * Crear un pedido de envío
     *
     * @param array $quoteData Datos de la cotización previa
     * @param array $orderDetails Detalles adicionales del pedido
     * @return array ['ok' => bool, 'delivery_id' => string, 'tracking_url' => string, ...]
     */
    public function createDelivery(array $quoteData, array $orderDetails = []): array {
        try {
            if (!$this->api_key || !$this->api_secret) {
                return [
                    'ok' => false,
                    'error' => 'API de Mooving no configurada'
                ];
            }

            $endpoint = '/deliveries';
            $data = array_merge($quoteData, [
                'merchant_id' => $this->merchant_id,
                'order_reference' => $orderDetails['order_id'] ?? '',
                'contact' => [
                    'name' => $orderDetails['customer_name'] ?? '',
                    'phone' => $orderDetails['customer_phone'] ?? '',
                    'email' => $orderDetails['customer_email'] ?? ''
                ],
                'instructions' => $orderDetails['instructions'] ?? ''
            ]);

            $response = $this->request('POST', $endpoint, $data);

            if (isset($response['error'])) {
                return [
                    'ok' => false,
                    'error' => $response['error']
                ];
            }

            return [
                'ok' => true,
                'delivery_id' => $response['delivery_id'] ?? '',
                'tracking_url' => $response['tracking_url'] ?? '',
                'status' => $response['status'] ?? 'pending',
                'driver' => $response['driver'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estado de un envío
     */
    public function getDeliveryStatus(string $deliveryId): array {
        try {
            $endpoint = "/deliveries/{$deliveryId}";
            $response = $this->request('GET', $endpoint);

            return [
                'ok' => true,
                'status' => $response['status'] ?? 'unknown',
                'location' => $response['current_location'] ?? null,
                'eta' => $response['eta_minutes'] ?? null,
                'driver' => $response['driver'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancelar un envío
     */
    public function cancelDelivery(string $deliveryId, string $reason = ''): array {
        try {
            $endpoint = "/deliveries/{$deliveryId}/cancel";
            $data = ['reason' => $reason];
            $response = $this->request('POST', $endpoint, $data);

            return [
                'ok' => true,
                'status' => $response['status'] ?? 'cancelled',
                'refund' => $response['refund_amount'] ?? 0
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Hacer request a la API de Mooving
     */
    private function request(string $method, string $endpoint, array $data = null): array {
        $base_url = $this->is_sandbox ? self::SANDBOX_BASE_URL : self::PROD_BASE_URL;
        $url = $base_url . $endpoint;

        $ch = curl_init($url);

        // Headers con autenticación
        $timestamp = time();
        $signature = $this->generateSignature($method, $endpoint, $timestamp, $data);

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->api_key,
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error de conexión con Mooving: {$error}");
        }

        if ($http_code >= 400) {
            $errorData = json_decode($response, true);
            throw new Exception($errorData['message'] ?? "Error HTTP {$http_code}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Generar firma de seguridad para la API
     */
    private function generateSignature(string $method, string $endpoint, int $timestamp, ?array $data): string {
        $payload = $method . $endpoint . $timestamp;
        if ($data) {
            $payload .= json_encode($data);
        }
        return hash_hmac('sha256', $payload, $this->api_secret);
    }

    /**
     * Verificar si Mooving está configurado y activo
     */
    public function isConfigured(): bool {
        return !empty($this->api_key) && !empty($this->api_secret) && !empty($this->merchant_id);
    }
}
