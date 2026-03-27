<?php
/**
 * SwiftPayClient — Middleware para SwiftPay Gateway
 * ─────────────────────────────────────────────────
 * Esta es la ÚNICA clase que se comunica directamente con SwiftPay.
 * Si SwiftPay cambia su API, solo se modifica este archivo.
 * El frontend y el checkout NO conocen los detalles del gateway.
 *
 * Uso básico (cobro en un solo paso):
 *   $sp     = new SwiftPayClient($pdo);
 *   $result = $sp->charge('15000.00', 'CRC', 'Compra en CompraTica', [
 *       'number' => '4111111111111111',
 *       'expiry' => '1228',
 *       'cvv'    => '123',
 *   ]);
 *   if ($result->isSuccess()) { ... }
 *   if ($result->needs3ds())  { header('Location: '.$result->redirectUrl); }
 */

// ── Value Object ────────────────────────────────────────────────────────────

class SwiftPayResult
{
    public bool   $approved;
    public bool   $pending3ds;
    public string $clientId;
    public string $orderId;
    public string $rrn;
    public string $intRef;
    public string $authCode;
    public string $redirectUrl;       // URL action del ACS para el form POST 3DS
    public string $creq;              // Campo creq para el form POST 3DS v2
    public string $threeDSSessionData; // Campo threeDSSessionData para el form POST 3DS v2
    public string $errorMessage;
    public array  $rawResponse;
    public int    $txId;              // ID en swiftpay_transactions

    public function __construct(
        bool   $approved,
        bool   $pending3ds,
        string $clientId,
        string $orderId,
        string $rrn,
        string $intRef,
        string $authCode,
        string $redirectUrl,
        string $creq,
        string $threeDSSessionData,
        string $errorMessage,
        array  $rawResponse,
        int    $txId
    ) {
        $this->approved           = $approved;
        $this->pending3ds         = $pending3ds;
        $this->clientId           = $clientId;
        $this->orderId            = $orderId;
        $this->rrn                = $rrn;
        $this->intRef             = $intRef;
        $this->authCode           = $authCode;
        $this->redirectUrl        = $redirectUrl;
        $this->creq               = $creq;
        $this->threeDSSessionData = $threeDSSessionData;
        $this->errorMessage       = $errorMessage;
        $this->rawResponse        = $rawResponse;
        $this->txId               = $txId;
    }

    /** Pago aprobado y sin 3DS pendiente */
    public function isSuccess(): bool { return $this->approved && !$this->pending3ds; }

    /** Requiere validación 3DS (redirigir a $redirectUrl) */
    public function needs3ds(): bool { return $this->pending3ds; }

    /** Convertir a array para respuestas JSON */
    public function toArray(): array
    {
        return [
            'approved'              => $this->approved,
            'pending_3ds'           => $this->pending3ds,
            'client_id'             => $this->clientId,
            'order_id'              => $this->orderId,
            'rrn'                   => $this->rrn,
            'int_ref'               => $this->intRef,
            'auth_code'             => $this->authCode,
            'action'                => $this->redirectUrl,
            'creq'                  => $this->creq,
            'three_ds_session_data' => $this->threeDSSessionData,
            'error'                 => $this->errorMessage,
            'tx_id'                 => $this->txId,
        ];
    }
}

class SwiftPayException extends RuntimeException {}

// ── Middleware Principal ────────────────────────────────────────────────────

class SwiftPayClient
{
    // ── Endpoints de producción ──────────────────────────────────────
    private const EP_VALIDATE   = '/api/card/validateCardExternal';
    private const EP_PAYMENT    = '/api/card/paymentExternal';
    private const EP_PRE_AUTH   = '/api/card/preAuthExternal';
    private const EP_COMPLETE   = '/api/card/completeAuthExternal';
    private const EP_VOID       = '/api/card/voidExternal';
    private const EP_3DS_RESULT = '/api/card/getResult3ds/';

    // ── Prefijo sandbox: /api/card/qa/... ───────────────────────────
    private const QA_REPLACE    = ['/api/card/' => '/api/card/qa/'];

    private PDO    $pdo;
    private string $jwt;
    private string $baseUrl;
    private bool   $sandbox;
    private string $mode;        // 'sandbox' | 'live' — solo para logs

    // ────────────────────────────────────────────────────────────────

    public function __construct(PDO $pdo)
    {
        $this->pdo     = $pdo;
        $this->sandbox = defined('SWIFTPAY_SANDBOX') ? (bool)SWIFTPAY_SANDBOX : true;
        $this->mode    = $this->sandbox ? 'sandbox' : 'live';

        // Seleccionar JWT y URL según el modo
        if ($this->sandbox) {
            $this->jwt     = defined('SWIFTPAY_JWT_SANDBOX')  ? SWIFTPAY_JWT_SANDBOX  : (defined('SWIFTPAY_JWT') ? SWIFTPAY_JWT : '');
            $this->baseUrl = defined('SWIFTPAY_URL_SANDBOX')  ? rtrim(SWIFTPAY_URL_SANDBOX, '/') : '';
        } else {
            $this->jwt     = defined('SWIFTPAY_JWT_LIVE')     ? SWIFTPAY_JWT_LIVE     : (defined('SWIFTPAY_JWT') ? SWIFTPAY_JWT : '');
            $this->baseUrl = defined('SWIFTPAY_URL_LIVE')     ? rtrim(SWIFTPAY_URL_LIVE, '/') : '';
        }

        if (empty($this->baseUrl)) {
            throw new SwiftPayException('SWIFTPAY_URL_' . strtoupper($this->mode) . ' no está configurado en config.php');
        }
        if (empty($this->jwt)) {
            throw new SwiftPayException('SWIFTPAY_JWT_' . strtoupper($this->mode) . ' no está configurado en config.php');
        }
    }

    // ════════════════════════════════════════════════════════════════
    // INTERFAZ PÚBLICA
    // ════════════════════════════════════════════════════════════════

    /**
     * Cobro en un solo paso: valida tarjeta → autoriza.
     * Es el método principal que usa el checkout.
     * Las credenciales de tarjeta NUNCA se almacenan; solo el tokenCard enmascarado.
     *
     * @param string $amount       Monto con decimales: "15000.00"
     * @param string $currency     "CRC" o "USD"
     * @param string $description  Texto en estado de cuenta del cliente
     * @param array  $card         ['number', 'expiry' (MMYY), 'cvv']
     * @param int    $referenceId  ID de la orden local (opcional, para trazabilidad)
     * @param string $referenceTable Tabla local (ej: 'orders')
     */
    public function charge(
        string $amount,
        string $currency,
        string $description,
        array  $card,
        int    $referenceId = 0,
        string $referenceTable = ''
    ): SwiftPayResult {
        if ($this->sandbox) {
            // En QA no existe qa/validateCardExternal — el token ficticio que devuelve
            // no es aceptado por qa/paymentExternal. Se envían los datos directamente.
            return $this->authorizeWithCard(
                $card['number'], $card['expiry'], $card['cvv'],
                $amount, $currency, $description, $referenceId, $referenceTable
            );
        }

        // Producción: Paso 1 → validar y obtener tokenCard; Paso 2 → autorizar
        $tokenCard = $this->validateCard($card['number'], $card['expiry'], $card['cvv']);
        return $this->authorize($tokenCard, $amount, $currency, $description, $referenceId, $referenceTable);
    }

    /**
     * Valida tarjeta con SwiftPay y retorna tokenCard.
     * Llamar este método directamente solo si necesitás el token por separado.
     */
    public function validateCard(string $cardNumber, string $expiry, string $cvv): string
    {
        $payload = [
            'card'  => ['card' => $cardNumber, 'expiration' => $expiry, 'cvv' => $cvv],
            'token' => $this->jwt,
        ];

        $response  = $this->post($this->ep(self::EP_VALIDATE), $payload);
        $tokenCard = $response['tokenCard']
                  ?? $response['cardToken']
                  ?? $response['data']['tokenCard']
                  ?? $response['data']['cardToken']
                  ?? $response['token']
                  ?? '';

        if (empty($tokenCard)) {
            throw new SwiftPayException(
                'validateCard: no se recibió tokenCard. Respuesta: ' . json_encode($response)
            );
        }

        return $tokenCard;
    }

    /**
     * Autorización directa con datos de tarjeta (sin tokenCard).
     * Solo para sandbox: en QA no existe qa/validateCardExternal,
     * así que el endpoint QA acepta card/expiration/cvv directamente.
     */
    private function authorizeWithCard(
        string $cardNumber,
        string $expiry,
        string $cvv,
        string $amount,
        string $currency,
        string $description,
        int    $referenceId = 0,
        string $referenceTable = ''
    ): SwiftPayResult {
        $clientId = $this->uuid();
        $payload  = [
            'clientId' => $clientId,
            'solicita' => 'dll',
            'card'     => [
                'card'        => $cardNumber,
                'expiration'  => $expiry,
                'cvv'         => $cvv,
                'amount'      => $this->formatAmount($amount),
                'currency'    => strtoupper($currency),
                'description' => $description,
                'page_result' => $this->returnUrl($clientId),
            ],
            'token'    => $this->jwt,
        ];

        $epUrl = $this->ep(self::EP_PAYMENT);
        $txId = $this->dbLog([
            'client_id'       => $clientId,
            'type'            => 'authorize',
            'status'          => 'pending',
            'amount'          => $amount,
            'currency'        => $currency,
            'description'     => $description,
            'reference_id'    => $referenceId,
            'reference_table' => $referenceTable,
            'raw_request'     => $this->requestLog($epUrl, $payload),
        ]);

        $response = $this->post($epUrl, $payload);
        return $this->buildResult($response, $clientId, $txId);
    }

    /**
     * Autorización (cobro inmediato).
     * Usá charge() en lugar de este método directamente a menos que ya tengás el tokenCard.
     */
    public function authorize(
        string $tokenCard,
        string $amount,
        string $currency,
        string $description,
        int    $referenceId = 0,
        string $referenceTable = ''
    ): SwiftPayResult {
        $clientId = $this->uuid();
        $payload  = [
            'clientId' => $clientId,
            'solicita' => 'dll',
            'card'     => [
                'tokenCard'   => $tokenCard,
                'amount'      => $this->formatAmount($amount),
                'currency'    => strtoupper($currency),
                'description' => $description,
                'page_result' => $this->returnUrl($clientId),
            ],
            'token'    => $this->jwt,
        ];

        $epUrl    = $this->ep(self::EP_PAYMENT);
        $txId     = $this->dbLog([
            'client_id'       => $clientId,
            'type'            => 'authorize',
            'status'          => 'pending',
            'amount'          => $amount,
            'currency'        => $currency,
            'description'     => $description,
            'token_card'      => $this->maskToken($tokenCard),
            'reference_id'    => $referenceId,
            'reference_table' => $referenceTable,
            'raw_request'     => $this->requestLog($epUrl, $payload),
        ]);

        $response = $this->post($epUrl, $payload);
        return $this->buildResult($response, $clientId, $txId);
    }

    /**
     * Pre-autorización: reserva fondos sin cobrar.
     * Luego completar con completeAuth() o cancelar con void().
     */
    public function preAuthorize(
        string $tokenCard,
        string $amount,
        string $currency,
        string $description,
        int    $referenceId = 0,
        string $referenceTable = ''
    ): SwiftPayResult {
        $clientId = $this->uuid();
        $payload  = [
            'clientId' => $clientId,
            'solicita' => 'dll',
            'card'     => [
                'tokenCard'   => $tokenCard,
                'amount'      => $this->formatAmount($amount),
                'currency'    => strtoupper($currency),
                'description' => $description,
                'page_result' => $this->returnUrl($clientId),
            ],
            'token'    => $this->jwt,
        ];

        $epUrl    = $this->ep(self::EP_PRE_AUTH);
        $txId     = $this->dbLog([
            'client_id'       => $clientId,
            'type'            => 'preauth',
            'status'          => 'pending',
            'amount'          => $amount,
            'currency'        => $currency,
            'description'     => $description,
            'token_card'      => $this->maskToken($tokenCard),
            'reference_id'    => $referenceId,
            'reference_table' => $referenceTable,
            'raw_request'     => $this->requestLog($epUrl, $payload),
        ]);

        $response = $this->post($epUrl, $payload);
        return $this->buildResult($response, $clientId, $txId);
    }

    /**
     * Completar una pre-autorización.
     * Requiere datos del resultado de preAuthorize().
     */
    public function completeAuth(
        string $tokenCard,
        string $amount,
        string $currency,
        string $rrn,
        string $intRef,
        string $orderId
    ): SwiftPayResult {
        $payload = [
            'card'  => [
                'tokenCard' => $tokenCard,
                'amount'    => $this->formatAmount($amount),
                'currency'  => strtoupper($currency),
                'rrn'       => $rrn,
                'intRef'    => $intRef,
                'orderId'   => $orderId,
            ],
            'token' => $this->jwt,
        ];

        $clientId = $this->uuid();
        $epUrl    = $this->ep(self::EP_COMPLETE);
        $txId     = $this->dbLog([
            'client_id'   => $clientId,
            'type'        => 'complete',
            'status'      => 'pending',
            'amount'      => $amount,
            'currency'    => $currency,
            'order_id'    => $orderId,
            'rrn'         => $rrn,
            'int_ref'     => $intRef,
            'token_card'  => $this->maskToken($tokenCard),
            'raw_request' => $this->requestLog($epUrl, $payload),
        ]);

        $response = $this->post($epUrl, $payload);
        return $this->buildResult($response, $clientId, $txId);
    }

    /**
     * Anular una transacción aprobada.
     * Datos requeridos vienen de la respuesta original de authorize/preAuthorize.
     */
    public function void(
        string $amount,
        string $currency,
        string $orderId,
        string $rrn,
        string $intRef,
        string $authCode
    ): SwiftPayResult {
        $payload = [
            'card'  => [
                'amount'   => $this->formatAmount($amount),
                'currency' => strtoupper($currency),
                'orderId'  => $orderId,
                'rrn'      => $rrn,
                'intRef'   => $intRef,
                'authCode' => $authCode,
            ],
            'token' => $this->jwt,
        ];

        $clientId = $this->uuid();
        $epUrl    = $this->ep(self::EP_VOID);
        $txId     = $this->dbLog([
            'client_id'   => $clientId,
            'type'        => 'void',
            'status'      => 'pending',
            'amount'      => $amount,
            'currency'    => $currency,
            'order_id'    => $orderId,
            'rrn'         => $rrn,
            'int_ref'     => $intRef,
            'auth_code'   => $authCode,
            'raw_request' => $this->requestLog($epUrl, $payload),
        ]);

        // Anulación: solo token en body, Auth: null según Postman (sin Bearer header)
        $response = $this->post($epUrl, $payload);
        return $this->buildResult($response, $clientId, $txId);
    }

    /**
     * Consultar resultado de validación 3DS.
     * Según demo oficial de SwiftPay: GET /getResult3ds/{swiftpayUuid}
     * SwiftPay envía su propio "uuid" al page_result — no nuestro clientId.
     *
     * @param string $swiftpayUuid  UUID que SwiftPay envió al page_result ($_GET['uuid'])
     * @param string $ourClientId   Nuestro clientId para buscar el registro en DB ($_GET['clientId'])
     */
    public function get3dsResult(string $swiftpayUuid, string $ourClientId = ''): SwiftPayResult
    {
        $url     = $this->ep(self::EP_3DS_RESULT . $swiftpayUuid);
        $decoded = $this->httpGet($url);

        $lookupId = !empty($ourClientId) ? $ourClientId : $swiftpayUuid;
        $tx       = $this->dbFindByClientId($lookupId);
        // Fallback: buscar por UUID de SwiftPay en el raw_response si no encontramos por clientId
        if (empty($tx) && !empty($swiftpayUuid)) {
            $tx = $this->dbFindBySwiftpayUuid($swiftpayUuid);
        }
        $txId     = (int)($tx['id'] ?? 0);

        // Guardar respuesta 3DS en columna separada (no pisa raw_response del paymentExternal)
        if ($txId > 0) {
            try {
                $this->pdo->prepare(
                    "UPDATE swiftpay_transactions SET raw_response_3ds = ?, updated_at = datetime('now') WHERE id = ?"
                )->execute([json_encode($decoded), $txId]);
            } catch (Throwable $e) {
                error_log('[SwiftPayClient] save raw_response_3ds error: ' . $e->getMessage());
            }
        }

        // updateRawResponse=false: preservar raw_response original del paymentExternal
        // El resultado 3DS ya se guardó en raw_response_3ds arriba
        return $this->buildResult($decoded, $lookupId, $txId, false);
    }

    /** Modo actual: 'sandbox' o 'live' */
    public function getMode(): string { return $this->mode; }

    // ════════════════════════════════════════════════════════════════
    // MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Construye el endpoint completo.
     * $raw = true devuelve el path sin la baseUrl (para GET 3DS).
     */
    private function ep(string $path, bool $raw = false): string
    {
        $full = $this->sandbox
            ? str_replace('/api/card/', '/api/card/qa/', $path)
            : $path;

        return $raw ? $full : $this->baseUrl . $full;
    }

    /** POST a SwiftPay con manejo de errores centralizado */
    private function post(string $url, array $payload, bool $withBearer = false): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($withBearer) {
            $headers[] = 'Authorization: Bearer ' . $this->jwt;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        error_log('[SwiftPay][' . $this->mode . '] POST ' . $url . ' → HTTP ' . $httpCode);

        if ($curlErr) {
            throw new SwiftPayException('Error de red al conectar con SwiftPay: ' . $curlErr);
        }

        $decoded = json_decode($raw ?: '{}', true);
        if (!is_array($decoded)) {
            throw new SwiftPayException(
                'Respuesta inválida de SwiftPay (HTTP ' . $httpCode . '): ' . substr((string)$raw, 0, 300)
            );
        }

        return $decoded;
    }

    /** GET a SwiftPay con manejo de errores centralizado */
    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        error_log('[SwiftPay][' . $this->mode . '] GET ' . $url . ' → HTTP ' . $httpCode);

        if ($curlErr) {
            throw new SwiftPayException('Error de red al conectar con SwiftPay: ' . $curlErr);
        }

        $decoded = json_decode($raw ?: '{}', true);
        if (!is_array($decoded)) {
            throw new SwiftPayException(
                'Respuesta inválida de SwiftPay (HTTP ' . $httpCode . '): ' . substr((string)$raw, 0, 300)
            );
        }

        return $decoded;
    }

    /** Construye SwiftPayResult desde la respuesta de SwiftPay y actualiza DB */
    private function buildResult(array $response, string $clientId, int $txId, bool $updateRawResponse = true): SwiftPayResult
    {
        // SwiftPay puede anidar la respuesta en múltiples niveles de "payResponse"
        // (getResult3ds devuelve hasta 3 niveles: payResponse.payResponse.payResponse)
        $r = $response;
        while (isset($r['payResponse']) && is_array($r['payResponse'])) {
            $r = $r['payResponse'];
        }

        $approved  = $this->isApproved($r);
        $needs3ds  = $this->detectsRedirect($r);
        $status    = $needs3ds ? 'pending_3ds' : ($approved ? 'approved' : 'declined');

        // Extraer campos del objeto interno
        $orderId            = $r['orderId']              ?? $r['data']['orderId']  ?? '';
        $rrn                = $r['rrn']                  ?? $r['data']['rrn']      ?? '';
        $intRef             = $r['intRef']               ?? $r['data']['intRef']   ?? '';
        $authCode           = $r['authCode']             ?? $r['data']['authCode'] ?? '';
        // URL del ACS para 3DS v2 viene en "action"; fallbacks para otras variantes
        $redirectUrl        = $r['action']               ?? $r['url3ds']     ?? $r['redirect'] ?? $r['urlRedirect'] ?? $r['url'] ?? '';
        $creq               = $r['creq']                 ?? '';
        $threeDSSessionData = $r['threeDSSessionData']   ?? '';
        $errorMsg           = $r['message']              ?? $r['error']       ?? $r['description'] ?? '';

        if ($txId > 0) {
            $this->dbUpdate($txId, [
                'status'        => $status,
                'order_id'      => $orderId,
                'rrn'           => $rrn,
                'int_ref'       => $intRef,
                'auth_code'     => $authCode,
                'is_3ds'        => $needs3ds ? 1 : 0,
                'error_message' => (!$approved && !$needs3ds) ? ($errorMsg ?: 'Transacción rechazada') : '',
                'raw_response'  => $updateRawResponse ? json_encode($response) : null,
            ]);
        }

        return new SwiftPayResult(
            approved:           $approved,
            pending3ds:         $needs3ds,
            clientId:           $clientId,
            orderId:            $orderId,
            rrn:                $rrn,
            intRef:             $intRef,
            authCode:           $authCode,
            redirectUrl:        $redirectUrl,
            creq:               $creq,
            threeDSSessionData: $threeDSSessionData,
            errorMessage:       $errorMsg,
            rawResponse:        $response,
            txId:               $txId,
        );
    }

    /** Detecta si la transacción fue aprobada */
    private function isApproved(array $r): bool
    {
        if (isset($r['approved']))     return (bool)$r['approved'];
        if (isset($r['success']))      return (bool)$r['success'];
        if (isset($r['ipsRc']))        return $r['ipsRc'] === '00';
        if (isset($r['responseCode'])) return $r['responseCode'] === '00';
        if (isset($r['status']))       return in_array(strtolower((string)$r['status']), ['approved', 'success', 'ok', '00'], true);
        // Si hay orderId y no hay error, asumir aprobado
        if (!empty($r['orderId']) && empty($r['error']) && empty($r['message'])) return true;
        return false;
    }

    /** Detecta si la respuesta requiere redirección 3DS */
    private function detectsRedirect(array $r): bool
    {
        return !empty($r['url3ds'])
            || !empty($r['urlRedirect'])
            || !empty($r['redirect'])
            || (isset($r['requires3ds']) && $r['requires3ds'])
            || (isset($r['status']) && in_array(strtolower((string)$r['status']), ['3ds', 'confirmed'], true));
    }

    /** URL a la que SwiftPay redirige al usuario después del 3DS */
    private function returnUrl(string $clientId): string
    {
        $base = defined('APP_URL')
            ? APP_URL
            : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
               . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return rtrim($base, '/') . '/api/swiftpay-3ds-return.php?clientId=' . urlencode($clientId);
    }

    private function formatAmount(string $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    private function maskToken(string $token): string
    {
        return strlen($token) > 10
            ? substr($token, 0, 6) . '****' . substr($token, -6)
            : $token;
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Enmascara datos sensibles del payload antes de guardarlo en DB.
     * Número de tarjeta → ****1234, CVV → ***, JWT → [JWT_REDACTED].
     */
    private function sanitizeForLog(array $payload): array
    {
        $p = $payload;
        if (isset($p['token'])) $p['token'] = '[JWT_REDACTED]';
        if (isset($p['card']['card'])) {
            $n = (string)$p['card']['card'];
            $p['card']['card'] = '****' . substr($n, -4);
        }
        if (isset($p['card']['cvv']))      $p['card']['cvv'] = '***';
        if (isset($p['card']['tokenCard'])) {
            $t = (string)$p['card']['tokenCard'];
            $p['card']['tokenCard'] = strlen($t) > 8 ? substr($t, 0, 4) . '****' . substr($t, -4) : '****';
        }
        return $p;
    }

    /**
     * Construye el JSON de log del request: endpoint + payload sanitizado.
     */
    private function requestLog(string $url, array $payload): string
    {
        return json_encode([
            'endpoint' => $url,
            'payload'  => $this->sanitizeForLog($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ── DB helpers ───────────────────────────────────────────────────

    private function dbLog(array $d): int
    {
        try {
            $this->pdo->prepare("
                INSERT INTO swiftpay_transactions
                (client_id, type, status, amount, currency, description,
                 order_id, rrn, int_ref, auth_code, token_card, is_3ds,
                 reference_id, reference_table, error_message, raw_request, raw_response, ip_address, mode)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $d['client_id']       ?? '',
                $d['type']            ?? '',
                $d['status']          ?? 'pending',
                $d['amount']          ?? null,
                $d['currency']        ?? null,
                $d['description']     ?? null,
                $d['order_id']        ?? null,
                $d['rrn']             ?? null,
                $d['int_ref']         ?? null,
                $d['auth_code']       ?? null,
                $d['token_card']      ?? null,
                $d['is_3ds']          ?? 0,
                $d['reference_id']    ?? 0,
                $d['reference_table'] ?? '',
                $d['error_message']   ?? null,
                $d['raw_request']     ?? null,
                $d['raw_response']    ?? null,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $this->mode,
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[SwiftPayClient] dbLog error: ' . $e->getMessage());
            return 0;
        }
    }

    private function dbUpdate(int $id, array $d): void
    {
        try {
            // Si raw_response es null, no se sobreescribe (preserva el del paymentExternal)
            if ($d['raw_response'] === null) {
                $this->pdo->prepare("
                    UPDATE swiftpay_transactions SET
                        status = ?, order_id = ?, rrn = ?, int_ref = ?,
                        auth_code = ?, is_3ds = ?, error_message = ?,
                        updated_at = datetime('now')
                    WHERE id = ?
                ")->execute([
                    $d['status']        ?? 'pending',
                    $d['order_id']      ?? null,
                    $d['rrn']           ?? null,
                    $d['int_ref']       ?? null,
                    $d['auth_code']     ?? null,
                    $d['is_3ds']        ?? 0,
                    $d['error_message'] ?? null,
                    $id,
                ]);
            } else {
                $this->pdo->prepare("
                    UPDATE swiftpay_transactions SET
                        status = ?, order_id = ?, rrn = ?, int_ref = ?,
                        auth_code = ?, is_3ds = ?, error_message = ?,
                        raw_response = ?, updated_at = datetime('now')
                    WHERE id = ?
                ")->execute([
                    $d['status']        ?? 'pending',
                    $d['order_id']      ?? null,
                    $d['rrn']           ?? null,
                    $d['int_ref']       ?? null,
                    $d['auth_code']     ?? null,
                    $d['is_3ds']        ?? 0,
                    $d['error_message'] ?? null,
                    $d['raw_response'],
                    $id,
                ]);
            }
        } catch (Throwable $e) {
            error_log('[SwiftPayClient] dbUpdate error: ' . $e->getMessage());
        }
    }

    private function dbFindByClientId(string $clientId): array
    {
        try {
            $s = $this->pdo->prepare(
                "SELECT * FROM swiftpay_transactions WHERE client_id = ? ORDER BY id DESC LIMIT 1"
            );
            $s->execute([$clientId]);
            return $s->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Fallback: buscar transacción por UUID de SwiftPay dentro de raw_response */
    private function dbFindBySwiftpayUuid(string $swiftpayUuid): array
    {
        try {
            $s = $this->pdo->prepare(
                "SELECT * FROM swiftpay_transactions WHERE raw_response LIKE ? ORDER BY id DESC LIMIT 1"
            );
            $s->execute(['%' . $swiftpayUuid . '%']);
            return $s->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
