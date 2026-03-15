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
 * Traducir usando Google Translate (método gratuito)
 * Usa la API no oficial de Google Translate
 */
function translateText($text, $from, $to) {
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl="
           . urlencode($from) . "&tl=" . urlencode($to) . "&dt=t&q=" . urlencode($text);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'Translation failed: ' . $error];
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result[0])) {
        return ['error' => 'Invalid response from translation service'];
    }

    $translated = '';
    foreach ($result[0] as $chunk) {
        if (isset($chunk[0])) {
            $translated .= $chunk[0];
        }
    }

    return ['translated' => $translated, 'original' => $text];
}

$result = translateText($text, $from, $to);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
