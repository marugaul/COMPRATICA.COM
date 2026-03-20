<?php
/**
 * Avatar Builder v3 — DiceBear avataaars + SVG body composite
 * Avatares profesionales con cuerpos personalizables para emprendedores
 */

// ── Mapas de opciones DiceBear ──────────────────────────────────────────────

const AV_HAIR = [
    // Estilos largos (mujer/niña)
    'long_straight' => 'LongHairStraight',
    'long_wavy'     => 'LongHairCurvy',
    'long_curly'    => 'LongHairCurly',
    'long_bun'      => 'LongHairBun',
    'long_dreads'   => 'LongHairDreads',
    'afro'          => 'LongHairFro',
    // Estilos cortos (hombre/niño)
    'short_flat'    => 'ShortHairShortFlat',
    'short_wavy'    => 'ShortHairShortWaved',
    'short_curly'   => 'ShortHairShortCurly',
    'short_dreads'  => 'ShortHairDreads01',
    'sides'         => 'ShortHairSides',
    // Neutros
    'bald'          => 'NoHair',
    'hat'           => 'Hat',
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

const AV_EYES = [
    'Default'   => '😐 Expresivo',
    'Happy'     => '😊 Feliz',
    'Surprised' => '😮 Sorprendido',
    'Wink'      => '😉 Guiño',
    'Squint'    => '😴 Soñoliento',
    'Hearts'    => '😍 Enamorado',
    'EyeRoll'   => '🙄 Cansado',
];

const AV_MOUTH = [
    'Smile'     => '😄 Sonrisa',
    'Twinkle'   => '😏 Pícara',
    'Default'   => '😶 Neutral',
    'Tongue'    => '😛 Lengua',
    'Serious'   => '😑 Serio',
    'Concerned' => '😟 Preocupado',
];

const AV_ACCESSORIES = [
    'Blank'          => '🚫 Ninguno',
    'Sunglasses'     => '😎 Gafas de sol',
    'Round'          => '🔵 Gafas redondas',
    'Prescription01' => '👓 Gafas clásicas',
    'Wayfarers'      => '😎 Wayfarers',
];

const AV_CLOTHES = [
    'BlazerShirt'   => '🧥 Blazer + camisa',
    'BlazerSweater' => '🧥 Blazer + suéter',
    'CollarSweater' => '🧶 Suéter cuello',
    'Hoodie'        => '👕 Hoodie',
    'ShirtCrewNeck' => '👕 Camiseta',
    'GraphicShirt'  => '🎨 Camiseta gráfica',
    'Overall'       => '👗 Overall',
];

const AV_FACIAL_HAIR = [
    'Blank'          => '🚫 Sin barba',
    'BeardLight'     => '🧔 Barba ligera',
    'BeardMedium'    => '🧔 Barba mediana',
    'BeardMagestic'  => '🧔 Barba majestuosa',
    'MoustacheFancy' => '👨 Bigote',
];

// ── Función principal: URL de DiceBear ────────────────────────────────────────

function avatarUrl(array $cfg, int $size = 100): string {
    $hair    = AV_HAIR[ $cfg['hair']   ?? 'short_flat' ] ?? 'ShortHairShortFlat';
    $skin    = (AV_SKIN[$cfg['skin']   ?? 'light'] ?? AV_SKIN['light'])['dicebear'];
    $hairHex = ltrim($cfg['hairColor']    ?? '4a312c', '#');
    $clothHex= ltrim($cfg['clothesColor'] ?? '667eea', '#');
    $eyes    = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['eyes']       ?? 'Default');
    $mouth   = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['mouth']      ?? 'Smile');
    $acc     = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['accessory']  ?? 'Blank');
    $clothes = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['clothes']    ?? 'ShirtCrewNeck');
    $facial  = preg_replace('/[^a-zA-Z0-9]/', '', $cfg['facialHair'] ?? 'Blank');

    $url  = 'https://api.dicebear.com/9.x/avataaars/svg?backgroundColor=transparent';
    $url .= '&size='           . $size;
    $url .= '&top[]='          . rawurlencode($hair);
    $url .= '&skin[]='         . rawurlencode($skin);
    $url .= '&hairColor[]='    . rawurlencode($hairHex);
    $url .= '&eyes[]='         . rawurlencode($eyes);
    $url .= '&mouth[]='        . rawurlencode($mouth);
    $url .= '&accessories[]='  . rawurlencode($acc);
    $url .= '&clothes[]='      . rawurlencode($clothes);
    $url .= '&clothesColor[]=' . rawurlencode($clothHex);
    $url .= '&facialHair[]='   . rawurlencode($facial);

    return $url;
}

// ── Avatar circular para tarjetas del catálogo ───────────────────────────────

function avatarImg(array $cfg, int $size = 72, string $extra = '', string $title = ''): string {
    $url = htmlspecialchars(avatarUrl($cfg, $size));
    $ttl = $title ? " title='" . htmlspecialchars($title) . "'" : '';
    return "<img src='{$url}' width='{$size}' height='{$size}'{$ttl} {$extra}" .
           " alt='Avatar' loading='lazy'" .
           " style='border-radius:50%;object-fit:cover;display:block;" .
           "border:3px solid rgba(255,255,255,.6);box-shadow:0 3px 12px rgba(0,0,0,.2);'>";
}

// ── Avatar compuesto: DiceBear head + cuerpo SVG con contornos ───────────────

function avatarFull(array $cfg, int $w = 160): string {
    $type      = $cfg['type']        ?? 'woman';
    $isSkirt   = in_array($type, ['woman', 'girl']);
    $shape     = AV_BODY[$cfg['body_shape'] ?? 'average'] ?? AV_BODY['average'];
    $clothHex  = _avHex($cfg['clothesColor'] ?? '#667eea');
    $clothDark = _avDarken($clothHex, 30);
    $skinKey   = $cfg['skin'] ?? 'light';
    $skinHex   = (AV_SKIN[$skinKey] ?? AV_SKIN['light'])['hex'];

    $hw       = $shape['hw'];
    $lw       = $shape['lw'];
    $cx       = (int)($w / 2);
    $headH    = $w;
    $bodyH    = (int)($w * 0.58);
    $totalH   = $headH + $bodyH + 12;
    $waistY   = (int)($headH * $shape['waistRatio']);

    $faceUrl  = htmlspecialchars(avatarUrl($cfg, $w));
    $parts    = [];

    // ── Cuerpo según tipo ────────────────────────────────────────────────────
    if ($isSkirt) {
        // Falda A-line con forma según contorno
        $skirtBot = $headH + (int)($bodyH * 0.72);
        $flare    = (int)($hw * 1.5);

        // Curva de falda con bezier
        $parts[] = "<defs>" .
            "<linearGradient id='skirtG' x1='0' y1='0' x2='1' y2='0'>" .
            "<stop offset='0%' stop-color='{$clothDark}'/>" .
            "<stop offset='50%' stop-color='{$clothHex}'/>" .
            "<stop offset='100%' stop-color='{$clothDark}'/>" .
            "</linearGradient>" .
            "<linearGradient id='legG' x1='0' y1='0' x2='0' y2='1'>" .
            "<stop offset='0%' stop-color='{$skinHex}'/>" .
            "<stop offset='100%' stop-color='" . _avDarken($skinHex, 15) . "'/>" .
            "</linearGradient>" .
            "</defs>";

        $midY = (int)(($waistY + $skirtBot) / 2);
        $parts[] = "<path d='M" . ($cx-$hw) . " {$waistY} " .
                   "Q" . ($cx-$flare) . " {$midY} " . ($cx-$flare) . " {$skirtBot} " .
                   "L" . ($cx+$flare) . " {$skirtBot} " .
                   "Q" . ($cx+$flare) . " {$midY} " . ($cx+$hw) . " {$waistY} Z' " .
                   "fill='url(#skirtG)'/>";
        // Sombra central (pliegue)
        $parts[] = "<path d='M{$cx} {$waistY} Q" . ($cx+3) . " {$midY} {$cx} {$skirtBot}' " .
                   "stroke='{$clothDark}' stroke-width='1.5' fill='none' opacity='0.35'/>";

        // Piernas debajo de la falda
        $legTop = $skirtBot;
        $legBot = $headH + $bodyH;
        $legL   = $cx - (int)($lw * 1.5);
        $legR   = $cx + (int)($lw * 0.5);
        $lh     = $legBot - $legTop;

        $parts[] = "<rect x='" . ($legL-$lw) . "' y='{$legTop}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#legG)' rx='{$lw}'/>";
        $parts[] = "<rect x='" . ($legR-$lw) . "' y='{$legTop}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#legG)' rx='{$lw}'/>";

        // Zapatos
        $sy = $legBot + 5;
        $parts[] = "<ellipse cx='{$legL}' cy='{$sy}' rx='" . ($lw+6) . "' ry='5' fill='#1a0a00'/>";
        $parts[] = "<ellipse cx='{$legR}' cy='{$sy}' rx='" . ($lw+6) . "' ry='5' fill='#1a0a00'/>";
        // Tacón
        $parts[] = "<rect x='" . ($legR+$lw-2) . "' y='{$sy}' width='4' height='6' fill='#0a0500' rx='1'/>";

    } else {
        // Pantalón con forma según contorno
        $pantBot = $headH + $bodyH;
        $pantMid = (int)($headH + $bodyH * 0.12);
        $legL    = $cx - (int)($lw * 1.4);
        $legR    = $cx + (int)($lw * 0.4);
        $lh      = $pantBot - $pantMid;

        $parts[] = "<defs>" .
            "<linearGradient id='pantG' x1='0' y1='0' x2='0' y2='1'>" .
            "<stop offset='0%' stop-color='{$clothHex}'/>" .
            "<stop offset='100%' stop-color='{$clothDark}'/>" .
            "</linearGradient>" .
            "</defs>";

        // Cintura/cadera
        $parts[] = "<rect x='" . ($cx-$hw) . "' y='{$waistY}' width='" . ($hw*2) . "' height='" . ($pantMid-$waistY+$lw) . "' fill='url(#pantG)' rx='6'/>";
        // Piernas
        $parts[] = "<rect x='" . ($legL-$lw) . "' y='{$pantMid}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#pantG)' rx='{$lw}'/>";
        $parts[] = "<rect x='" . ($legR-$lw) . "' y='{$pantMid}' width='" . ($lw*2) . "' height='{$lh}' fill='url(#pantG)' rx='{$lw}'/>";
        // Línea separadora pantalonera
        $parts[] = "<line x1='{$cx}' y1='{$pantMid}' x2='{$cx}' y2='{$pantBot}' stroke='{$clothDark}' stroke-width='2' opacity='0.4'/>";
        // Zapatos
        $sy = $pantBot + 6;
        $parts[] = "<ellipse cx='{$legL}' cy='{$sy}' rx='" . ($lw+7) . "' ry='6' fill='#111'/>";
        $parts[] = "<ellipse cx='{$legR}' cy='{$sy}' rx='" . ($lw+7) . "' ry='6' fill='#111'/>";
    }

    // DiceBear head encima de todo (frente)
    $parts[] = "<image href='{$faceUrl}' x='0' y='0' width='{$w}' height='{$headH}'" .
               " preserveAspectRatio='xMidYMid meet'/>";

    $svg  = "<svg xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink'";
    $svg .= " viewBox='0 0 {$w} {$totalH}' width='{$w}' height='{$totalH}'>";
    $svg .= implode('', $parts);
    $svg .= "</svg>";
    return $svg;
}

// ── Defaults por tipo de personaje ───────────────────────────────────────────

function avatarDefaults(string $type = 'woman'): array {
    $d = [
        'woman' => [
            'hair' => 'long_straight', 'hairColor' => '#8B4513', 'skin' => 'light',
            'eyes' => 'Happy',   'mouth' => 'Smile',   'accessory' => 'Blank',
            'clothes' => 'BlazerShirt', 'clothesColor' => '#ec4899',
            'facialHair' => 'Blank', 'body_shape' => 'average',
        ],
        'man' => [
            'hair' => 'short_flat', 'hairColor' => '#1a1a1a', 'skin' => 'light',
            'eyes' => 'Default', 'mouth' => 'Default', 'accessory' => 'Blank',
            'clothes' => 'BlazerShirt', 'clothesColor' => '#2563eb',
            'facialHair' => 'Blank', 'body_shape' => 'athletic',
        ],
        'girl' => [
            'hair' => 'long_curly', 'hairColor' => '#D4A843', 'skin' => 'pale',
            'eyes' => 'Happy',   'mouth' => 'Smile',   'accessory' => 'Round',
            'clothes' => 'ShirtCrewNeck', 'clothesColor' => '#f97316',
            'facialHair' => 'Blank', 'body_shape' => 'slim',
        ],
        'boy' => [
            'hair' => 'short_curly', 'hairColor' => '#2c1a0e', 'skin' => 'tanned',
            'eyes' => 'Squint',  'mouth' => 'Twinkle', 'accessory' => 'Blank',
            'clothes' => 'Hoodie', 'clothesColor' => '#16a34a',
            'facialHair' => 'Blank', 'body_shape' => 'slim',
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
