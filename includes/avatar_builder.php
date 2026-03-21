<?php
/**
 * Avatar Builder v3 — DiceBear avataaars + SVG body composite
 * Avatares profesionales con cuerpos personalizables para emprendedores
 */

// ── Mapas de opciones DiceBear ──────────────────────────────────────────────

// Valores correctos para DiceBear v9.x (camelCase, nombres actualizados)
const AV_HAIR = [
    // Estilos largos (mujer/niña)
    'long_straight' => 'straight01',
    'long_wavy'     => 'curvy',
    'long_curly'    => 'curly',
    'long_bun'      => 'bun',
    'long_dreads'   => 'dreads',
    'afro'          => 'fro',
    // Estilos cortos (hombre/niño)
    'short_flat'    => 'shortFlat',
    'short_wavy'    => 'shortWaved',
    'short_curly'   => 'shortCurly',
    'short_dreads'  => 'dreads01',
    'sides'         => 'sides',
    // Neutros (topProbability=0 para calvo)
    'bald'          => '',
    'hat'           => 'hat',
];

const AV_SKIN = [
    'pale'    => ['dicebear' => 'Pale',      'hex' => '#FDDBB4'],
    'light'   => ['dicebear' => 'Light',     'hex' => '#EDB98A'],
    'tanned'  => ['dicebear' => 'Tanned',    'hex' => '#D08B5B'],
    'yellow'  => ['dicebear' => 'Yellow',    'hex' => '#F8D25C'],
    'brown'   => ['dicebear' => 'Brown',     'hex' => '#AE5D29'],
    'dark'    => ['dicebear' => 'DarkBrown', 'hex' => '#7D4E2D'],
    'black'   => ['dicebear' => 'Black',     'hex' => '#2A1300'],
];

// Contornos de cuerpo: hw = semiancho de cadera, lw = semiancho de pierna
const AV_BODY = [
    'slim'     => ['hw' => 12, 'lw' => 8,  'waistRatio' => 0.65, 'label' => '🌿 Delgada/o'],
    'average'  => ['hw' => 16, 'lw' => 11, 'waistRatio' => 0.70, 'label' => '🙂 Promedio'],
    'curvy'    => ['hw' => 22, 'lw' => 12, 'waistRatio' => 0.68, 'label' => '💃 Curvilínea'],
    'athletic' => ['hw' => 18, 'lw' => 13, 'waistRatio' => 0.72, 'label' => '💪 Atlética/o'],
    'plus'     => ['hw' => 27, 'lw' => 14, 'waistRatio' => 0.68, 'label' => '✨ Talla grande'],
];

// Claves son los valores v9 que se pasan a la API; valores son etiquetas UI
const AV_EYES = [
    'default'   => '😐 Expresivo',
    'happy'     => '😊 Feliz',
    'surprised' => '😮 Sorprendido',
    'wink'      => '😉 Guiño',
    'squint'    => '😴 Soñoliento',
    'hearts'    => '😍 Enamorado',
    'eyeRoll'   => '🙄 Cansado',
];

const AV_MOUTH = [
    'smile'    => '😄 Sonrisa',
    'twinkle'  => '😏 Pícara',
    'default'  => '😶 Neutral',
    'tongue'   => '😛 Lengua',
    'serious'  => '😑 Serio',
    'concerned'=> '😟 Preocupado',
];

// Accesorios: 'none','hat','bow','cap','crown' se mapean a top[] en DiceBear
// 'glasses' → accessories[]=prescription01
const AV_ACCESSORIES = [
    'none'    => '🚫 Ninguno',
    'glasses' => '👓 Gafas',
    'hat'     => '🎩 Sombrero',
    'bow'     => '🎀 Moño',
    'cap'     => '🧢 Gorra',
    'crown'   => '👑 Corona',
];

// Valores v9 para clothing (param correcto es "clothing", no "clothes")
const AV_CLOTHES = [
    'blazerAndShirt'   => '🧥 Blazer + camisa',
    'blazerAndSweater' => '🧥 Blazer + suéter',
    'collarAndSweater' => '🧶 Suéter cuello',
    'hoodie'           => '👕 Hoodie',
    'shirtCrewNeck'    => '👕 Camiseta',
    'graphicShirt'     => '🎨 Camiseta gráfica',
    'overall'          => '👗 Overall',
];

// '' = sin barba (facialHairProbability=0)
const AV_FACIAL_HAIR = [
    ''               => '🚫 Sin barba',
    'beardLight'     => '🧔 Barba ligera',
    'beardMedium'    => '🧔 Barba mediana',
    'beardMajestic'  => '🧔 Barba majestuosa',
    'moustacheFancy' => '👨 Bigote',
];

// ── Función principal: URL de DiceBear ────────────────────────────────────────

// ── Helper: obtener hex de piel (acepta clave AV_SKIN o hex directo) ─────────
function avSkinHex(string $skin): string {
    if (preg_match('/^#?[0-9a-fA-F]{6}$/i', $skin)) {
        return preg_match('/^#/', $skin) ? $skin : '#' . $skin;
    }
    return (AV_SKIN[$skin] ?? AV_SKIN['light'])['hex'];
}

function avatarUrl(array $cfg, int $size = 100): string {
    // ── Cabello ───────────────────────────────────────────────────────────────
    $hairKey = $cfg['hair'] ?? 'long_straight';
    $hair    = AV_HAIR[$hairKey] ?? 'straight01';

    // ── Piel: acepta clave AV_SKIN ('light') o hex directo ('#EDB98A') ────────
    $skinHex = ltrim(avSkinHex($cfg['skin'] ?? 'light'), '#');

    // ── Colores ───────────────────────────────────────────────────────────────
    $hairHex  = ltrim($cfg['hairColor']    ?? '#4a312c', '#');
    $clothHex = ltrim($cfg['clothesColor'] ?? '#667eea', '#');

    // ── Expresión ─────────────────────────────────────────────────────────────
    $eyes  = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['eyes']  ?? 'happy');
    $mouth = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['mouth'] ?? 'smile');

    // ── Ropa ──────────────────────────────────────────────────────────────────
    $clothes = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['clothes'] ?? 'shirtCrewNeck');

    // ── Barba ─────────────────────────────────────────────────────────────────
    $facial = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['facialHair'] ?? '');

    // ── Accesorio: algunos son "de cabeza" (top[]), otros "faciales" (accessories[]) ─
    $accVal = $cfg['accessory'] ?? 'none';
    // Accesorios que reemplazan el estilo de cabello en top[]
    $HEAD_ACC = ['hat' => 'hat', 'bow' => 'froBand', 'cap' => 'winterHat1', 'crown' => 'turban'];
    // Accesorios faciales (gafas) → accessories[]
    $FACE_ACC = ['glasses' => 'prescription01', 'sunglasses' => 'sunglasses',
                 'round' => 'round', 'prescription01' => 'prescription01', 'wayfarers' => 'wayfarers'];
    $headAcc = $HEAD_ACC[$accVal] ?? null;
    $faceAcc = $FACE_ACC[$accVal] ?? null;

    // ── Build URL ─────────────────────────────────────────────────────────────
    $url  = 'https://api.dicebear.com/9.x/avataaars/svg?backgroundColor=transparent';
    $url .= '&size=' . $size;

    // Top: accesorio de cabeza tiene prioridad sobre estilo de cabello
    if ($headAcc !== null) {
        $url .= '&top[]=' . rawurlencode($headAcc);
    } elseif ($hair !== '') {
        $url .= '&top[]=' . rawurlencode($hair);
    } else {
        $url .= '&topProbability=0';
    }

    $url .= '&skinColor[]='  . rawurlencode($skinHex);
    $url .= '&hairColor[]='  . rawurlencode($hairHex);
    $url .= '&eyes[]='       . rawurlencode($eyes);
    $url .= '&mouth[]='      . rawurlencode($mouth);

    if ($faceAcc !== null) {
        $url .= '&accessories[]=' . rawurlencode($faceAcc);
    } else {
        $url .= '&accessoriesProbability=0';
    }

    $url .= '&clothing[]='    . rawurlencode($clothes);
    $url .= '&clothesColor[]=' . rawurlencode($clothHex);

    if ($facial !== '') {
        $url .= '&facialHair[]=' . rawurlencode($facial);
    } else {
        $url .= '&facialHairProbability=0';
    }

    return $url;
}

// ── URL del proxy local (mismo origen, evita CORB) ───────────────────────────

function avatarProxyUrl(array $cfg, int $size = 100, bool $faceOnly = false): string {
    $url = 'api/dicebear-proxy.php?size=' . $size
         . '&cfg=' . rawurlencode(json_encode($cfg));
    if ($faceOnly) $url .= '&face=1';
    return $url;
}

// ── Avatar circular para tarjetas del catálogo ───────────────────────────────

function avatarImg(array $cfg, int $size = 72, string $extra = '', string $title = ''): string {
    $url = htmlspecialchars(avatarProxyUrl($cfg, $size));
    $ttl = $title ? " title='" . htmlspecialchars($title) . "'" : '';
    return "<img src='{$url}' width='{$size}' height='{$size}'{$ttl} {$extra}" .
           " alt='Avatar' loading='lazy'" .
           " style='border-radius:50%;object-fit:cover;display:block;" .
           "border:3px solid rgba(255,255,255,.6);box-shadow:0 3px 12px rgba(0,0,0,.2);'>";
}

// ── Avatar compuesto: cara circular DiceBear + cuerpo SVG completo (con brazos) ─

function avatarFull(array $cfg, int $w = 160): string {
    $type      = $cfg['type']       ?? 'woman';
    $isSkirt   = in_array($type, ['woman', 'girl']);
    $shape     = AV_BODY[$cfg['body_shape'] ?? 'average'] ?? AV_BODY['average'];
    $clothHex  = _avHex($cfg['clothesColor'] ?? '#667eea');
    $clothDark = _avDarken($clothHex, 30);
    $skinHex   = avSkinHex($cfg['skin'] ?? 'light');
    $skinDark  = _avDarken($skinHex, 15);

    $hw = $shape['hw'];
    $lw = $shape['lw'];
    $cx = (int)($w / 2);

    // ── Dimensiones ─────────────────────────────────────────────────────────
    // Cara: círculo centrado en la parte superior
    $headSz  = (int)($w * 0.56);          // diámetro de la cara
    $headX   = $cx - (int)($headSz / 2);  // posición X de la cara
    $headY   = 0;

    // Cuello
    $neckW   = max(6, (int)($headSz * 0.09));
    $neckTop = (int)($headSz * 0.80);
    $neckH   = (int)($headSz * 0.14);
    $shouldY = $neckTop + $neckH;          // inicio del torso/hombros

    // Torso
    $torsoH = (int)($w * 0.30);
    $waistY = $shouldY + $torsoH;

    // Brazos: rectángulo redondeado a cada lado del torso
    $armW   = max(7, (int)($hw * 0.38));
    $armH   = (int)($torsoH * 0.80);
    $armLX  = $cx - $hw - $armW;
    $armRX  = $cx + $hw;
    $armR   = max(4, (int)($armW / 2));

    // Piernas/falda
    $legH   = (int)($w * 0.32);
    $totalH = $waistY + $legH + 14;

    $parts = [];

    // ── Defs (gradientes reutilizables) ──────────────────────────────────────
    $parts[] = "<defs>" .
        "<linearGradient id='cG' x1='0' y1='0' x2='1' y2='0'>" .
            "<stop offset='0%' stop-color='{$clothDark}'/>" .
            "<stop offset='50%' stop-color='{$clothHex}'/>" .
            "<stop offset='100%' stop-color='{$clothDark}'/>" .
        "</linearGradient>" .
        "<linearGradient id='cV' x1='0' y1='0' x2='0' y2='1'>" .
            "<stop offset='0%' stop-color='{$clothHex}'/>" .
            "<stop offset='100%' stop-color='{$clothDark}'/>" .
        "</linearGradient>" .
        "<linearGradient id='sG' x1='0' y1='0' x2='0' y2='1'>" .
            "<stop offset='0%' stop-color='{$skinHex}'/>" .
            "<stop offset='100%' stop-color='{$skinDark}'/>" .
        "</linearGradient>" .
        "</defs>";

    // ── Cuello ───────────────────────────────────────────────────────────────
    $parts[] = "<rect x='" . ($cx-$neckW) . "' y='{$neckTop}' " .
               "width='" . ($neckW*2) . "' height='{$neckH}' fill='url(#sG)' rx='{$neckW}'/>";

    // ── Brazos (detrás del torso) ─────────────────────────────────────────────
    $parts[] = "<rect x='{$armLX}' y='{$shouldY}' width='{$armW}' height='{$armH}' fill='url(#cV)' rx='{$armR}'/>";
    $parts[] = "<rect x='{$armRX}' y='{$shouldY}' width='{$armW}' height='{$armH}' fill='url(#cV)' rx='{$armR}'/>";
    // Manos (piel al final del brazo)
    $handR = max(4, $armR - 1);
    $handH = (int)($armW * 1.1);
    $handY = $shouldY + $armH - 2;
    $parts[] = "<rect x='{$armLX}' y='{$handY}' width='{$armW}' height='{$handH}' fill='url(#sG)' rx='{$handR}'/>";
    $parts[] = "<rect x='{$armRX}' y='{$handY}' width='{$armW}' height='{$handH}' fill='url(#sG)' rx='{$handR}'/>";

    if ($isSkirt) {
        // ── Blusa ────────────────────────────────────────────────────────────
        $buH = (int)($torsoH * 0.52);
        $buW = $hw + 2;
        $parts[] = "<rect x='" . ($cx-$buW) . "' y='{$shouldY}' width='" . ($buW*2) . "' height='{$buH}' fill='url(#cG)' rx='4'/>";

        // ── Falda A-line ─────────────────────────────────────────────────────
        $skirtTop = $shouldY + (int)($torsoH * 0.46);
        $skirtBot = $waistY + (int)($legH * 0.46);
        $flare    = (int)($hw * 1.6);
        $midY     = (int)(($skirtTop + $skirtBot) / 2);
        $parts[]  = "<path d='M" . ($cx-$hw) . " {$skirtTop} " .
                    "Q" . ($cx-$flare) . " {$midY} " . ($cx-$flare) . " {$skirtBot} " .
                    "L" . ($cx+$flare) . " {$skirtBot} " .
                    "Q" . ($cx+$flare) . " {$midY} " . ($cx+$hw) . " {$skirtTop} Z' fill='url(#cG)'/>";
        $parts[]  = "<path d='M{$cx} {$skirtTop} Q" . ($cx+3) . " {$midY} {$cx} {$skirtBot}' " .
                    "stroke='{$clothDark}' stroke-width='1.5' fill='none' opacity='0.3'/>";

        // ── Piernas ───────────────────────────────────────────────────────────
        $legTop = $skirtBot;
        $legBot = $waistY + $legH;
        $legL   = $cx - (int)($lw * 1.5);
        $legR   = $cx + (int)($lw * 0.5);
        $lh     = $legBot - $legTop;
        $parts[] = "<rect x='" . ($legL-$lw) . "' y='{$legTop}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#sG)' rx='{$lw}'/>";
        $parts[] = "<rect x='" . ($legR-$lw) . "' y='{$legTop}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#sG)' rx='{$lw}'/>";

        // ── Zapatos con tacón ──────────────────────────────────────────────────
        $sy = $legBot + 5;
        $parts[] = "<ellipse cx='{$legL}' cy='{$sy}' rx='" . ($lw+6) . "' ry='5' fill='#1a0a00'/>";
        $parts[] = "<ellipse cx='{$legR}' cy='{$sy}' rx='" . ($lw+6) . "' ry='5' fill='#1a0a00'/>";
        $parts[] = "<rect x='" . ($legR+$lw-3) . "' y='{$sy}' width='4' height='5' fill='#0a0500' rx='1'/>";

    } else {
        // ── Camisa/torso ────────────────────────────────────────────────────
        $parts[] = "<rect x='" . ($cx-$hw) . "' y='{$shouldY}' width='" . ($hw*2) . "' height='{$torsoH}' fill='url(#cG)' rx='5'/>";

        // ── Pantalón ──────────────────────────────────────────────────────────
        $pantColor = _avDarken($clothHex, 38);
        $pantDark  = _avDarken($clothHex, 55);
        $pantTop   = $waistY - (int)($torsoH * 0.08);
        $pantBot   = $waistY + $legH;
        $legL      = $cx - (int)($lw * 1.4);
        $legR      = $cx + (int)($lw * 0.4);
        $lh        = $pantBot - $pantTop;
        $parts[] = "<rect x='" . ($legL-$lw) . "' y='{$pantTop}' width='" . ($lw*2) . "' height='{$lh}' fill='{$pantColor}' rx='{$lw}'/>";
        $parts[] = "<rect x='" . ($legR-$lw) . "' y='{$pantTop}' width='" . ($lw*2) . "' height='{$lh}' fill='{$pantColor}' rx='{$lw}'/>";
        $parts[] = "<line x1='{$cx}' y1='{$pantTop}' x2='{$cx}' y2='{$pantBot}' stroke='{$pantDark}' stroke-width='2' opacity='0.35'/>";

        // ── Zapatos ────────────────────────────────────────────────────────────
        $sy = $pantBot + 6;
        $parts[] = "<ellipse cx='{$legL}' cy='{$sy}' rx='" . ($lw+7) . "' ry='6' fill='#111'/>";
        $parts[] = "<ellipse cx='{$legR}' cy='{$sy}' rx='" . ($lw+7) . "' ry='6' fill='#111'/>";
    }

    // ── HTML compuesto: SVG cuerpo + img cara circular (DiceBear style=circle) ─
    $bodySvg  = "<svg xmlns='http://www.w3.org/2000/svg'";
    $bodySvg .= " viewBox='0 0 {$w} {$totalH}' width='{$w}' height='{$totalH}'";
    $bodySvg .= " style='position:absolute;top:0;left:0;pointer-events:none;'>";
    $bodySvg .= implode('', $parts);
    $bodySvg .= "</svg>";

    // DiceBear con style=circle → solo cara circular, sin cuerpo/brazos propios
    $faceUrl = htmlspecialchars(avatarProxyUrl($cfg, $headSz, true));

    $html  = "<div style='position:relative;width:{$w}px;height:{$totalH}px;display:inline-block;vertical-align:bottom;'>";
    $html .= $bodySvg;
    $html .= "<img src='{$faceUrl}' width='{$headSz}' height='{$headSz}' alt='avatar' loading='eager'";
    $html .= " style='position:absolute;top:{$headY}px;left:{$headX}px;width:{$headSz}px;height:{$headSz}px;display:block;border-radius:50%;overflow:hidden;'>";
    $html .= "</div>";
    return $html;
}

// ── Defaults por tipo de personaje ───────────────────────────────────────────

function avatarDefaults(string $type = 'woman'): array {
    $d = [
        'woman' => [
            'hair' => 'long_straight', 'hairColor' => '#8B4513', 'skin' => '#EDB98A',
            'eyes' => 'happy',   'mouth' => 'smile',   'accessory' => 'none',
            'clothes' => 'blazerAndShirt', 'clothesColor' => '#ec4899',
            'facialHair' => '', 'body_shape' => 'average',
        ],
        'man' => [
            'hair' => 'short_flat', 'hairColor' => '#1a1a1a', 'skin' => '#EDB98A',
            'eyes' => 'default', 'mouth' => 'smile',   'accessory' => 'none',
            'clothes' => 'blazerAndShirt', 'clothesColor' => '#2563eb',
            'facialHair' => '', 'body_shape' => 'athletic',
        ],
        'girl' => [
            'hair' => 'long_curly', 'hairColor' => '#D4A843', 'skin' => '#FDDBB4',
            'eyes' => 'happy',   'mouth' => 'smile',   'accessory' => 'glasses',
            'clothes' => 'shirtCrewNeck', 'clothesColor' => '#f97316',
            'facialHair' => '', 'body_shape' => 'slim',
        ],
        'boy' => [
            'hair' => 'short_curly', 'hairColor' => '#2c1a0e', 'skin' => '#D08B5B',
            'eyes' => 'squint',  'mouth' => 'smile',   'accessory' => 'none',
            'clothes' => 'hoodie', 'clothesColor' => '#16a34a',
            'facialHair' => '', 'body_shape' => 'slim',
        ],
    ];
    $def = $d[$type] ?? $d['woman'];
    $def['type'] = $type;
    return $def;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function _avHex(string $c): string {
    $c = trim($c);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) return htmlspecialchars($c);
    if (preg_match('/^#[0-9a-fA-F]{3}$/', $c))
        return '#'.str_repeat($c[1],2).str_repeat($c[2],2).str_repeat($c[3],2);
    return '#667eea';
}

function _avDarken(string $hex, int $by = 20): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return sprintf('#%02x%02x%02x',
        max(0, hexdec(substr($hex,0,2)) - $by),
        max(0, hexdec(substr($hex,2,2)) - $by),
        max(0, hexdec(substr($hex,4,2)) - $by)
    );
}

function _avSlug(string $s): string { return preg_replace('/[^a-zA-Z0-9_]/', '', $s); }
