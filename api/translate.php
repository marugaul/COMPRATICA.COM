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
 * Traducir usando múltiples servicios con fallback
 * Intenta primero MyMemory, luego Google Translate
 */
function translateText($text, $from, $to) {
    // Intentar MyMemory Translation API (mejor calidad, 1000 chars por request)
    $translated = translateWithMyMemory($text, $from, $to);
    if ($translated !== false) {
        return ['translated' => $translated, 'original' => $text];
    }

    // Fallback: Google Translate
    $translated = translateWithGoogle($text, $from, $to);
    if ($translated !== false) {
        return ['translated' => $translated, 'original' => $text];
    }

    return ['error' => 'All translation services failed'];
}

/**
 * MyMemory Translation API (mejor calidad)
 * Límite: 1000 caracteres por request, 10000 palabras/día
 */
function translateWithMyMemory($text, $from, $to) {
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
    curl_close($ch);

    if (!$response) return false;

    $result = json_decode($response, true);
    if (isset($result['responseData']['translatedText'])) {
        return $result['responseData']['translatedText'];
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
