<?php
/**
 * Test automático de todas las opciones del avatar contra DiceBear v9.x
 * Uso: php tools/test_avatar_options.php
 *
 * Verifica cada valor de AV_HAIR, AV_EYES, AV_MOUTH, AV_CLOTHES,
 * AV_FACIAL_HAIR y accessory mappings.
 * Imprime qué pasa y genera la lista de valores inválidos.
 */

require_once __DIR__ . '/../includes/avatar_builder.php';

define('BASE_URL', 'https://api.dicebear.com/9.x/avataaars/svg?backgroundColor=transparent&size=50');
define('TIMEOUT', 12);

// ── Función de curl ───────────────────────────────────────────────────────────
function testUrl(string $url): array {
    if (!function_exists('curl_init')) return ['ok'=>false,'code'=>0,'bytes'=>0,'err'=>'no curl'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'COMPRATICA-Test/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $isValidSvg = $body && $code === 200
               && strpos($body, '<svg') !== false
               && strpos($body, 'Error') === false
               && strpos($body, 'error') === false
               && strlen($body) > 500;

    return ['ok' => $isValidSvg, 'code' => $code, 'bytes' => strlen($body ?: ''), 'err' => $err];
}

function buildUrl(array $params): string {
    $url = BASE_URL;
    foreach ($params as $k => $v) {
        $url .= '&' . $k . '=' . rawurlencode($v);
    }
    return $url;
}

function check(string $section, string $key, string $dicebearParam, string $value): bool {
    $url = buildUrl([$dicebearParam => $value]);
    $r   = testUrl($url);
    $icon = $r['ok'] ? '✅' : '❌';
    $note = $r['ok'] ? "({$r['bytes']} bytes)" : "HTTP {$r['code']} {$r['err']}";
    echo "  $icon  [$section] key='$key'  param={$dicebearParam}[]='$value'  $note\n";
    return $r['ok'];
}

// ── Resultados globales ───────────────────────────────────────────────────────
$VALID   = [];
$INVALID = [];

// ─────────────────────────────────────────────────────────────────────────────
// 1. CABELLO (top[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== AV_HAIR (top[]) ===\n";
foreach (AV_HAIR as $key => $dicebearVal) {
    if ($dicebearVal === '') {
        // bald: test con topProbability=0
        $url = BASE_URL . '&topProbability=0';
        $r = testUrl($url);
        $icon = $r['ok'] ? '✅' : '❌';
        echo "  $icon  [hair] key='$key'  topProbability=0 ({$r['bytes']} bytes)\n";
        if ($r['ok']) $VALID['hair'][$key] = $dicebearVal;
        else          $INVALID['hair'][] = $key;
    } else {
        $ok = check('hair', $key, 'top[]', $dicebearVal);
        if ($ok) $VALID['hair'][$key] = $dicebearVal;
        else     $INVALID['hair'][] = $key;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. OJOS (eyes[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== AV_EYES (eyes[]) ===\n";
foreach (AV_EYES as $key => $label) {
    $ok = check('eyes', $key, 'eyes[]', $key);
    if ($ok) $VALID['eyes'][$key] = $label;
    else     $INVALID['eyes'][] = $key;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. BOCA (mouth[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== AV_MOUTH (mouth[]) ===\n";
foreach (AV_MOUTH as $key => $label) {
    $ok = check('mouth', $key, 'mouth[]', $key);
    if ($ok) $VALID['mouth'][$key] = $label;
    else     $INVALID['mouth'][] = $key;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ROPA (clothing[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== AV_CLOTHES (clothing[]) ===\n";
foreach (AV_CLOTHES as $key => $label) {
    $ok = check('clothes', $key, 'clothing[]', $key);
    if ($ok) $VALID['clothes'][$key] = $label;
    else     $INVALID['clothes'][] = $key;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. BARBA (facialHair[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== AV_FACIAL_HAIR (facialHair[]) ===\n";
foreach (AV_FACIAL_HAIR as $key => $label) {
    if ($key === '') {
        $url = BASE_URL . '&facialHairProbability=0';
        $r = testUrl($url);
        $icon = $r['ok'] ? '✅' : '❌';
        echo "  $icon  [facialHair] key=''  facialHairProbability=0 ({$r['bytes']} bytes)\n";
        if ($r['ok']) $VALID['facialHair'][''] = $label;
        else          $INVALID['facialHair'][] = '(none)';
    } else {
        $ok = check('facialHair', $key, 'facialHair[]', $key);
        if ($ok) $VALID['facialHair'][$key] = $label;
        else     $INVALID['facialHair'][] = $key;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ACCESORIOS — faciales (accessories[])
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== FACE ACCESSORIES (accessories[]) ===\n";
$faceAccTests = [
    'glasses'        => 'prescription01',
    'sunglasses'     => 'sunglasses',
    'round'          => 'round',
    'prescription02' => 'prescription02',
    'wayfarers'      => 'wayfarers',
    'kurt'           => 'kurt',
    'eyepatch'       => 'eyepatch',
];
$validFaceAcc = [];
foreach ($faceAccTests as $key => $val) {
    $ok = check('faceAcc', $key, 'accessories[]', $val);
    if ($ok) $validFaceAcc[$key] = $val;
    else     $INVALID['faceAccessory'][] = "$key → $val";
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. ACCESORIOS — de cabeza (top[] override)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== HEAD ACCESSORIES (top[] override) ===\n";
$headAccTests = [
    'hat'         => 'hat',
    'bow/froBand' => 'froBand',
    'cap/winterHat1' => 'winterHat1',
    'cap/winterHat02'=> 'winterHat02',
    'cap/winterHat03'=> 'winterHat03',
    'crown/turban'   => 'turban',
    'hijab'          => 'hijab',
];
$validHeadAcc = [];
foreach ($headAccTests as $key => $val) {
    $ok = check('headAcc', $key, 'top[]', $val);
    if ($ok) $validHeadAcc[$key] = $val;
    else     $INVALID['headAccessory'][] = "$key → $val";
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. COLORES DE PIEL
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== SKIN COLORS (skinColor[]) ===\n";
$skinTests = ['FDDBB4','EDB98A','D08B5B','AE5D29','7D4E2D','2A1300','F8D25C'];
$validSkins = [];
foreach ($skinTests as $hex) {
    $ok = check('skin', "#$hex", 'skinColor[]', $hex);
    if ($ok) $validSkins[] = $hex;
    else     $INVALID['skin'][] = $hex;
}

// ─────────────────────────────────────────────────────────────────────────────
// RESUMEN
// ─────────────────────────────────────────────────────────────────────────────
echo "\n\n" . str_repeat('═', 70) . "\n";
echo "RESUMEN\n";
echo str_repeat('═', 70) . "\n";

$totalOk   = array_sum(array_map('count', $VALID));
$totalFail = array_sum(array_map('count', $INVALID));
echo "✅ Válidos: $totalOk    ❌ Inválidos: $totalFail\n\n";

if (!empty($INVALID)) {
    echo "Opciones a ELIMINAR:\n";
    foreach ($INVALID as $sec => $list) {
        echo "  [$sec]: " . implode(', ', $list) . "\n";
    }
} else {
    echo "¡Todo en orden! No hay opciones inválidas.\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// VALID FACE ACC para copiar a avatar_builder.php
// ─────────────────────────────────────────────────────────────────────────────
echo "\n--- Accesorios faciales válidos ---\n";
foreach ($validFaceAcc as $k => $v) echo "  '$k' => '$v'\n";

echo "\n--- Accesorios de cabeza válidos ---\n";
foreach ($validHeadAcc as $k => $v) echo "  '$k' (top[]='$v')\n";

echo "\n";
