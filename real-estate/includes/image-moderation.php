<?php
/**
 * Sistema de Moderación de Imágenes
 * Valida que las imágenes no contengan contenido inapropiado
 *
 * Usa Sightengine API (https://sightengine.com/)
 * Plan Gratuito: 2000 operaciones/mes sin tarjeta de crédito
 *
 * Para activar:
 * 1. Regístrate en https://sightengine.com/
 * 2. Obtén tu API User y API Secret del dashboard
 * 3. Configúralos en config.php o usa las variables de entorno
 */

class ImageModeration {

    private $apiUser;
    private $apiSecret;
    private $enabled;

    public function __construct() {
        // Intentar obtener credenciales de diferentes fuentes
        $this->apiUser = getenv('SIGHTENGINE_API_USER') ?: (defined('SIGHTENGINE_API_USER') ? SIGHTENGINE_API_USER : '');
        $this->apiSecret = getenv('SIGHTENGINE_API_SECRET') ?: (defined('SIGHTENGINE_API_SECRET') ? SIGHTENGINE_API_SECRET : '');

        // La API está habilitada si hay credenciales configuradas
        $this->enabled = !empty($this->apiUser) && !empty($this->apiSecret);
    }

    /**
     * Valida una imagen para detectar contenido inapropiado
     *
     * @param string $imagePath Ruta local al archivo de imagen
     * @return array ['approved' => bool, 'reason' => string, 'details' => array]
     */
    public function validateImage($imagePath) {
        // Si la API no está configurada, usar validación básica
        if (!$this->enabled) {
            return $this->basicValidation($imagePath);
        }

        try {
            // Validación con Sightengine API
            return $this->sightengineValidation($imagePath);
        } catch (Exception $e) {
            // Si falla la API, usar validación básica como fallback
            error_log("Sightengine API error: " . $e->getMessage());
            return $this->basicValidation($imagePath);
        }
    }

    /**
     * Validación usando Sightengine API
     */
    private function sightengineValidation($imagePath) {
        $url = 'https://api.sightengine.com/1.0/check.json';

        // Parámetros de detección
        $params = [
            'models' => 'nudity-2.0,violence,gore,offensive',
            'api_user' => $this->apiUser,
            'api_secret' => $this->apiSecret
        ];

        // Preparar archivo para upload
        $file = new CURLFile($imagePath);
        $params['media'] = $file;

        // Hacer request a la API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API returned HTTP $httpCode");
        }

        $result = json_decode($response, true);

        if (!$result || isset($result['error'])) {
            throw new Exception($result['error']['message'] ?? 'Unknown API error');
        }

        // Analizar resultados
        return $this->analyzeSightengineResults($result);
    }

    /**
     * Analiza los resultados de Sightengine
     */
    private function analyzeSightengineResults($result) {
        $threshold = 0.5; // 50% de probabilidad
        $reasons = [];

        // Revisar nudity
        if (isset($result['nudity'])) {
            $nudity = $result['nudity'];
            if (($nudity['sexual_activity'] ?? 0) > $threshold) {
                $reasons[] = 'Contenido sexual explícito detectado';
            }
            if (($nudity['sexual_display'] ?? 0) > $threshold) {
                $reasons[] = 'Desnudez sexual detectada';
            }
            if (($nudity['erotica'] ?? 0) > $threshold) {
                $reasons[] = 'Contenido erótico detectado';
            }
        }

        // Revisar violencia
        if (isset($result['violence'])) {
            if (($result['violence'] ?? 0) > $threshold) {
                $reasons[] = 'Contenido violento detectado';
            }
        }

        // Revisar gore
        if (isset($result['gore'])) {
            if (($result['gore']['prob'] ?? 0) > $threshold) {
                $reasons[] = 'Contenido gore/sangriento detectado';
            }
        }

        // Revisar contenido ofensivo
        if (isset($result['offensive'])) {
            if (($result['offensive']['prob'] ?? 0) > $threshold) {
                $reasons[] = 'Contenido ofensivo detectado';
            }
        }

        $approved = empty($reasons);

        return [
            'approved' => $approved,
            'reason' => $approved ? 'Imagen válida' : implode('. ', $reasons),
            'details' => $result,
            'source' => 'sightengine'
        ];
    }

    /**
     * Validación básica cuando la API no está disponible
     * Solo verifica que sea una imagen válida
     */
    private function basicValidation($imagePath) {
        // Verificar que es una imagen real
        $imageInfo = @getimagesize($imagePath);

        if ($imageInfo === false) {
            return [
                'approved' => false,
                'reason' => 'El archivo no es una imagen válida',
                'details' => [],
                'source' => 'basic'
            ];
        }

        // Validar tipo MIME
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'], $allowedTypes)) {
            return [
                'approved' => false,
                'reason' => 'Tipo de imagen no permitido. Use JPG, PNG, GIF o WebP',
                'details' => ['mime' => $imageInfo['mime']],
                'source' => 'basic'
            ];
        }

        // Validar tamaño de imagen (max 10MB)
        $fileSize = filesize($imagePath);
        if ($fileSize > 10 * 1024 * 1024) {
            return [
                'approved' => false,
                'reason' => 'La imagen es demasiado grande. Máximo 10MB',
                'details' => ['size' => $fileSize],
                'source' => 'basic'
            ];
        }

        // Validación básica exitosa
        return [
            'approved' => true,
            'reason' => 'Imagen válida (validación básica - API de moderación no configurada)',
            'details' => [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'mime' => $imageInfo['mime'],
                'size' => $fileSize
            ],
            'source' => 'basic',
            'warning' => 'Para activar moderación de contenido, configure Sightengine API'
        ];
    }

    /**
     * Obtiene información sobre el estado de la API
     */
    public function getStatus() {
        return [
            'enabled' => $this->enabled,
            'service' => $this->enabled ? 'Sightengine API' : 'Validación básica',
            'message' => $this->enabled
                ? 'Moderación de contenido activa'
                : 'Configure Sightengine API para moderación de contenido automática'
        ];
    }
}
