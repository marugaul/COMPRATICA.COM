<?php
/**
 * API de traducción gratuita usando Google Translate
 * Endpoint: /api/translate.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$text = $_POST['text'] ?? $_GET['text'] ?? '';
$from = $_POST['from'] ?? $_GET['from'] ?? 'en';
$to = $_POST['to'] ?? $_GET['to'] ?? 'es';

if (empty($text)) {
    echo json_encode(['error' => 'No text provided']);
    exit;
}

/**
 * Divide texto largo en chunks respetando límites de caracteres
 * Intenta cortar por oraciones o palabras, no en medio de palabras
 */
function splitTextIntoChunks($text, $maxLength = 450) {
    // Si el texto es corto, devolverlo directamente
    if (mb_strlen($text) <= $maxLength) {
        return [$text];
    }

    $chunks = [];
    $remainingText = $text;

    while (mb_strlen($remainingText) > 0) {
        if (mb_strlen($remainingText) <= $maxLength) {
            $chunks[] = $remainingText;
            break;
        }

        // Intentar cortar en un punto/salto de línea
        $cutPoint = $maxLength;
        $substr = mb_substr($remainingText, 0, $maxLength + 50); // Un poco más para buscar

        // Buscar el último punto, nueva línea o espacio
        $lastPeriod = mb_strrpos($substr, '.');
        $lastNewline = mb_strrpos($substr, "\n");
        $lastSpace = mb_strrpos($substr, ' ');

        // Usar el más cercano a maxLength pero no excederlo
        if ($lastPeriod !== false && $lastPeriod <= $maxLength) {
            $cutPoint = $lastPeriod + 1;
        } elseif ($lastNewline !== false && $lastNewline <= $maxLength) {
            $cutPoint = $lastNewline + 1;
        } elseif ($lastSpace !== false && $lastSpace <= $maxLength) {
            $cutPoint = $lastSpace + 1;
        }

        $chunks[] = mb_substr($remainingText, 0, $cutPoint);
        $remainingText = mb_substr($remainingText, $cutPoint);
    }

    return $chunks;
}

/**
 * Traducir usando múltiples servicios con fallback
 * Divide textos largos en chunks automáticamente
 */
function translateText($text, $from, $to) {
    // Dividir texto en chunks si es muy largo
    $chunks = splitTextIntoChunks($text, 450); // 450 chars por chunk (límite MyMemory es 500)

    $translatedChunks = [];
    $useGoogle = false;

    foreach ($chunks as $chunk) {
        // Intentar MyMemory primero
        if (!$useGoogle) {
            $translated = translateWithMyMemory($chunk, $from, $to);
            if ($translated !== false) {
                $translatedChunks[] = $translated;
                continue;
            } else {
                // Si MyMemory falla, cambiar a Google para los chunks restantes
                $useGoogle = true;
            }
        }

        // Fallback: Google Translate
        if ($useGoogle) {
            $translated = translateWithGoogle($chunk, $from, $to);
            if ($translated !== false) {
                $translatedChunks[] = $translated;
            } else {
                // Si ambos fallan, devolver el chunk original
                $translatedChunks[] = $chunk;
            }
        }

        // Pequeña pausa para no saturar las APIs
        usleep(100000); // 100ms entre chunks
    }

    if (empty($translatedChunks)) {
        return ['error' => 'Translation failed for all chunks'];
    }

    $finalTranslation = implode(' ', $translatedChunks);
    return [
        'translated' => $finalTranslation,
        'original' => $text,
        'chunks_count' => count($chunks)
    ];
}

/**
 * MyMemory Translation API (mejor calidad)
 * Límite: 500 caracteres por request, 10000 palabras/día
 */
function translateWithMyMemory($text, $from, $to) {
    // Verificar longitud
    if (mb_strlen($text) > 500) {
        return false; // Demasiado largo, se debe dividir antes
    }

    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($text)
           . "&langpair=" . urlencode($from) . "|" . urlencode($to);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CompraTica/1.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) return false;

    $result = json_decode($response, true);

    // Verificar si hay error de límite
    if (isset($result['responseData']['translatedText'])) {
        $translated = $result['responseData']['translatedText'];

        // Si el API devuelve el mismo error, intentar con Google
        if (stripos($translated, 'QUERY LENGTH LIMIT EXCEEDED') !== false) {
            return false;
        }

        return $translated;
    }

    return false;
}

/**
 * Google Translate (fallback)
 */
function translateWithGoogle($text, $from, $to) {
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl="
           . urlencode($from) . "&tl=" . urlencode($to) . "&dt=t&q=" . urlencode($text);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return false;

    $result = json_decode($response, true);
    if (!$result || !isset($result[0])) return false;

    $translated = '';
    foreach ($result[0] as $chunk) {
        if (isset($chunk[0])) {
            $translated .= $chunk[0];
        }
    }

    return $translated;
}

$result = translateText($text, $from, $to);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
