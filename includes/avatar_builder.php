<?php
/**
 * Avatar Builder — Generador de personajes chibi SVG animados
 * Compratica.com — Sistema de avatares para emprendedores
 */

/**
 * Genera un SVG de un personaje chibi según la configuración dada.
 *
 * @param array $cfg  Configuración del avatar
 * @param int   $size Ancho en px (alto se calcula proporcionalmente)
 * @return string     SVG completo como string
 */
function avatarSVG(array $cfg, int $size = 60): string {
    $type  = in_array($cfg['type'] ?? '', ['woman','man','girl','boy']) ? $cfg['type'] : 'woman';
    $skin  = _avHex($cfg['skin']       ?? '#F0C27F');
    $hs    = _avSlug($cfg['hair_style'] ?? 'long');
    $hc    = _avHex($cfg['hair_color'] ?? '#4a2040');
    $es    = _avSlug($cfg['eye_style']  ?? 'happy');
    $oc    = _avHex($cfg['outfit']      ?? '#667eea');
    $acc   = _avSlug($cfg['accessory']  ?? 'none');
    $blush = !empty($cfg['blush']);

    $skinD = _avDarken($skin, 28);
    $hcD   = _avDarken($hc,   22);
    $ocD   = _avDarken($oc,   28);
    $fh    = (int)round($size * 1.30);
    $isSkirt = in_array($type, ['woman', 'girl']);

    $o = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 130' width='{$size}' height='{$fh}' style='overflow:visible;'>";

    // ══════════════════════════════════════════════════════════════════
    //  HAIR BACK (pintado primero, detrás de todo)
    // ══════════════════════════════════════════════════════════════════
    switch ($hs) {
        case 'long':
            $o .= "<path d='M20 36 Q17 12 50 10 Q83 12 80 36 Q89 74 80 108 Q67 122 50 120 Q33 122 20 108 Q11 74 20 36Z' fill='{$hc}'/>";
            break;
        case 'bun':
            $o .= "<path d='M22 38 Q20 18 50 14 Q80 18 78 38 Q85 66 74 90 Q63 98 50 96 Q37 98 26 90 Q15 66 22 38Z' fill='{$hc}'/>";
            $o .= "<circle cx='50' cy='8' r='15' fill='{$hc}'/>";
            break;
        case 'ponytail':
            $o .= "<path d='M22 38 Q20 18 50 14 Q80 18 78 38 Q85 66 74 90 Q63 98 50 96 Q37 98 26 90 Q15 66 22 38Z' fill='{$hc}'/>";
            $o .= "<path d='M77 44 Q98 60 93 88 Q89 100 80 106 Q75 88 79 64Z' fill='{$hc}'/>";
            break;
        case 'afro':
        case 'curly':
            $o .= "<circle cx='50' cy='30' r='40' fill='{$hc}'/>";
            break;
        case 'braid':
            $o .= "<path d='M22 38 Q20 18 50 14 Q80 18 78 38 Q85 66 74 90 Q63 98 50 96 Q37 98 26 90 Q15 66 22 38Z' fill='{$hc}'/>";
            $o .= "<path d='M50 95 Q54 112 50 130 Q46 112 50 95Z' fill='{$hcD}' stroke='{$hcD}' stroke-width='5'/>";
            break;
        default: // short / spiky / bald
            $o .= "<path d='M22 40 Q20 20 50 16 Q80 20 78 40 Q82 34 50 30 Q18 34 22 40Z' fill='{$hc}'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  BRAZOS (detrás del cuerpo)
    // ══════════════════════════════════════════════════════════════════
    $o .= "<path d='M28 83 Q7 88 9 110 Q12 118 21 118' stroke='{$oc}' stroke-width='13' fill='none' stroke-linecap='round'/>";
    $o .= "<circle cx='19' cy='117' r='7' fill='{$skin}'/>";
    $o .= "<path d='M72 83 Q93 88 91 110 Q88 118 79 118' stroke='{$oc}' stroke-width='13' fill='none' stroke-linecap='round'/>";
    $o .= "<circle cx='81' cy='117' r='7' fill='{$skin}'/>";

    // ══════════════════════════════════════════════════════════════════
    //  CUERPO / ROPA
    // ══════════════════════════════════════════════════════════════════
    $o .= "<rect x='27' y='76' width='46' height='33' fill='{$oc}' rx='12'/>";
    // Detalle cuello / collar
    $o .= "<path d='M44 76 Q50 83 56 76' fill='none' stroke='{$ocD}' stroke-width='1.5' opacity='0.7'/>";
    // Sombra inferior torso
    $o .= "<rect x='27' y='101' width='46' height='8' fill='{$ocD}' rx='0 0 12 12' opacity='0.5'/>";

    if ($isSkirt) {
        // Falda
        $o .= "<path d='M24 104 Q27 126 50 128 Q73 126 76 104Z' fill='{$ocD}'/>";
        // Pliegues de falda
        $o .= "<path d='M36 104 Q38 122 40 128' stroke='{$oc}' stroke-width='1' fill='none' opacity='0.4'/>";
        $o .= "<path d='M60 104 Q62 122 64 128' stroke='{$oc}' stroke-width='1' fill='none' opacity='0.4'/>";
        // Piernas
        $o .= "<rect x='37' y='124' width='11' height='5' fill='{$skin}' rx='4'/>";
        $o .= "<rect x='52' y='124' width='11' height='5' fill='{$skin}' rx='4'/>";
        // Zapatos
        $o .= "<ellipse cx='42' cy='129' rx='9' ry='4' fill='#1a0a00'/>";
        $o .= "<ellipse cx='58' cy='129' rx='9' ry='4' fill='#1a0a00'/>";
    } else {
        // Pantalón
        $o .= "<rect x='27' y='106' width='19' height='22' fill='{$ocD}' rx='6'/>";
        $o .= "<rect x='54' y='106' width='19' height='22' fill='{$ocD}' rx='6'/>";
        // Zapatos
        $o .= "<ellipse cx='36' cy='128' rx='11' ry='5' fill='#111'/>";
        $o .= "<ellipse cx='64' cy='128' rx='11' ry='5' fill='#111'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  CUELLO
    // ══════════════════════════════════════════════════════════════════
    $o .= "<rect x='44' y='68' width='12' height='12' fill='{$skin}' rx='4'/>";

    // ══════════════════════════════════════════════════════════════════
    //  OREJAS
    // ══════════════════════════════════════════════════════════════════
    $o .= "<circle cx='21' cy='50' r='9' fill='{$skin}'/>";
    $o .= "<circle cx='79' cy='50' r='9' fill='{$skin}'/>";
    $o .= "<circle cx='21' cy='50' r='5' fill='{$skinD}'/>";
    $o .= "<circle cx='79' cy='50' r='5' fill='{$skinD}'/>";

    // ══════════════════════════════════════════════════════════════════
    //  CABEZA
    // ══════════════════════════════════════════════════════════════════
    $o .= "<ellipse cx='50' cy='46' rx='30' ry='28' fill='{$skin}'/>";
    // Sombra sutil en mentón
    $o .= "<ellipse cx='50' cy='69' rx='14' ry='5' fill='{$skinD}' opacity='0.12'/>";

    // ══════════════════════════════════════════════════════════════════
    //  HAIR FRONT (sobre la cabeza)
    // ══════════════════════════════════════════════════════════════════
    switch ($hs) {
        case 'long':
            $o .= "<path d='M20 36 Q26 18 50 14 Q74 18 80 36 Q68 26 50 24 Q32 26 20 36Z' fill='{$hc}'/>";
            // Mechones laterales (sobre orejas)
            $o .= "<path d='M20 36 Q16 48 18 58 Q22 44 24 36Z' fill='{$hc}'/>";
            $o .= "<path d='M80 36 Q84 48 82 58 Q78 44 76 36Z' fill='{$hc}'/>";
            break;
        case 'bun':
            $o .= "<path d='M22 40 Q27 22 50 16 Q73 22 78 40 Q68 30 50 28 Q32 30 22 40Z' fill='{$hc}'/>";
            $o .= "<circle cx='50' cy='8' r='12' fill='{$hc}'/>";
            $o .= "<circle cx='50' cy='8' r='5' fill='{$hcD}'/>";
            // Chignón wrap
            $o .= "<ellipse cx='43' cy='23' rx='5' ry='8' fill='{$hc}' transform='rotate(-15 43 23)'/>";
            $o .= "<ellipse cx='57' cy='23' rx='5' ry='8' fill='{$hc}' transform='rotate(15 57 23)'/>";
            break;
        case 'ponytail':
            $o .= "<path d='M22 40 Q27 22 50 16 Q73 22 78 40 Q68 30 50 28 Q32 30 22 40Z' fill='{$hc}'/>";
            $o .= "<ellipse cx='44' cy='22' rx='5' ry='9' fill='{$hc}' transform='rotate(-18 44 22)'/>";
            $o .= "<ellipse cx='56' cy='22' rx='5' ry='9' fill='{$hc}' transform='rotate(18 56 22)'/>";
            break;
        case 'short':
            $o .= "<path d='M22 42 Q26 22 50 16 Q74 22 78 42 Q70 28 50 26 Q30 28 22 42Z' fill='{$hc}'/>";
            break;
        case 'spiky':
            $o .= "<path d='M28 36 L22 13 L36 28 L42 9 L48 26 L50 9 L52 26 L58 9 L64 28 L78 13 L72 36 Q60 24 50 22 Q40 24 28 36Z' fill='{$hc}'/>";
            break;
        case 'afro':
        case 'curly':
            $o .= "<circle cx='32' cy='18' r='10' fill='{$hc}'/>";
            $o .= "<circle cx='50' cy='11' r='12' fill='{$hc}'/>";
            $o .= "<circle cx='68' cy='18' r='10' fill='{$hc}'/>";
            $o .= "<circle cx='24' cy='32' r='9'  fill='{$hc}'/>";
            $o .= "<circle cx='76' cy='32' r='9'  fill='{$hc}'/>";
            break;
        case 'braid':
            $o .= "<path d='M22 40 Q27 22 50 16 Q73 22 78 40 Q68 30 50 28 Q32 30 22 40Z' fill='{$hc}'/>";
            break;
        default:
            $o .= "<path d='M22 40 Q27 22 50 16 Q73 22 78 40 Q68 30 50 28 Q32 30 22 40Z' fill='{$hc}'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  ACCESORIOS (capas superiores — sombrero corona SOBRE pelo front)
    // ══════════════════════════════════════════════════════════════════
    if ($acc === 'hat') {
        // Corona del sombrero
        $o .= "<rect x='30' y='10' width='40' height='25' fill='{$hc}' rx='8'/>";
        $o .= "<ellipse cx='50' cy='10' rx='20' ry='5' fill='{$hcD}'/>";
        $o .= "<rect x='28' y='32' width='44' height='4' fill='{$hcD}' rx='2'/>";
    } elseif ($acc === 'cap') {
        $o .= "<path d='M20 42 Q20 20 50 16 Q80 20 80 42 Q68 30 50 28 Q32 30 20 42Z' fill='{$hc}'/>";
        $o .= "<circle cx='50' cy='17' r='4' fill='{$hcD}'/>";
    } elseif ($acc === 'crown') {
        $o .= "<path d='M26 24 L28 5 L38 17 L50 5 L62 17 L72 5 L74 24Z' fill='#FFD700'/>";
        $o .= "<path d='M26 20 Q50 28 74 20 L74 24 Q50 32 26 24Z' fill='#CC8800'/>";
        $o .= "<circle cx='50' cy='7'  r='3.5' fill='#FF4444'/>";
        $o .= "<circle cx='35' cy='14' r='2.5' fill='#4488FF'/>";
        $o .= "<circle cx='65' cy='14' r='2.5' fill='#44CC44'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  OJOS
    // ══════════════════════════════════════════════════════════════════
    switch ($es) {
        case 'happy':
            // Ojos cerrados sonrientes ^^
            $o .= "<path d='M34 49 Q40 42 46 49' stroke='#333' stroke-width='2.8' fill='none' stroke-linecap='round'/>";
            $o .= "<path d='M54 49 Q60 42 66 49' stroke='#333' stroke-width='2.8' fill='none' stroke-linecap='round'/>";
            break;
        case 'star':
            // Ojos estrella ✦
            $o .= "<ellipse cx='40' cy='48' rx='7' ry='7' fill='white'/>";
            $o .= "<ellipse cx='60' cy='48' rx='7' ry='7' fill='white'/>";
            $o .= "<text x='40' y='52' text-anchor='middle' font-size='9' fill='#8855FF'>✦</text>";
            $o .= "<text x='60' y='52' text-anchor='middle' font-size='9' fill='#8855FF'>✦</text>";
            break;
        case 'wink':
            // Un ojo abierto, uno guiño
            $o .= "<ellipse cx='40' cy='48' rx='7' ry='7' fill='white'/>";
            $o .= "<circle cx='41' cy='48' r='4.5' fill='#333'/>";
            $o .= "<circle cx='43' cy='46' r='2' fill='white' opacity='0.9'/>";
            $o .= "<path d='M54 48 Q60 41 66 48' stroke='#333' stroke-width='2.8' fill='none' stroke-linecap='round'/>";
            break;
        case 'sleepy':
            // Ojos medio cerrados
            $o .= "<path d='M33 50 Q40 45 47 50' stroke='#333' stroke-width='2.5' fill='none' stroke-linecap='round'/>";
            $o .= "<path d='M53 50 Q60 45 67 50' stroke='#333' stroke-width='2.5' fill='none' stroke-linecap='round'/>";
            $o .= "<ellipse cx='40' cy='52' rx='7' ry='3' fill='#9999aa' opacity='0.2'/>";
            $o .= "<ellipse cx='60' cy='52' rx='7' ry='3' fill='#9999aa' opacity='0.2'/>";
            break;
        default: // normal
            $o .= "<ellipse cx='40' cy='48' rx='7' ry='7' fill='white'/>";
            $o .= "<ellipse cx='60' cy='48' rx='7' ry='7' fill='white'/>";
            $o .= "<circle cx='41' cy='48' r='4.5' fill='#333'/>";
            $o .= "<circle cx='61' cy='48' r='4.5' fill='#333'/>";
            $o .= "<circle cx='43' cy='46' r='2' fill='white' opacity='0.9'/>";
            $o .= "<circle cx='63' cy='46' r='2' fill='white' opacity='0.9'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  GAFAS (después de ojos)
    // ══════════════════════════════════════════════════════════════════
    if ($acc === 'glasses') {
        $o .= "<circle cx='40' cy='48' r='9' fill='rgba(200,230,255,0.25)' stroke='#555' stroke-width='2'/>";
        $o .= "<circle cx='60' cy='48' r='9' fill='rgba(200,230,255,0.25)' stroke='#555' stroke-width='2'/>";
        $o .= "<line x1='49' y1='48' x2='51' y2='48' stroke='#555' stroke-width='2'/>";
        $o .= "<line x1='17' y1='47' x2='31' y2='48' stroke='#555' stroke-width='1.5'/>";
        $o .= "<line x1='69' y1='48' x2='83' y2='47' stroke='#555' stroke-width='1.5'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  NARIZ Y BOCA
    // ══════════════════════════════════════════════════════════════════
    $o .= "<ellipse cx='50' cy='57' rx='2.5' ry='2' fill='{$skinD}'/>";
    $o .= "<path d='M43 65 Q50 73 57 65' stroke='#c0504a' stroke-width='2.5' fill='none' stroke-linecap='round'/>";
    $o .= "<path d='M43 65 Q50 68 57 65' fill='#fff' opacity='0.35'/>";

    // ══════════════════════════════════════════════════════════════════
    //  RUBOR
    // ══════════════════════════════════════════════════════════════════
    if ($blush) {
        $o .= "<ellipse cx='33' cy='60' rx='9' ry='5' fill='#ff8888' opacity='0.38'/>";
        $o .= "<ellipse cx='67' cy='60' rx='9' ry='5' fill='#ff8888' opacity='0.38'/>";
    }

    // ══════════════════════════════════════════════════════════════════
    //  MOÑO / LAZO (encima de todo el pelo)
    // ══════════════════════════════════════════════════════════════════
    if ($acc === 'bow') {
        $o .= "<path d='M36 17 Q44 8 50 17 Q44 26 36 17Z' fill='#ff4d8d'/>";
        $o .= "<path d='M50 17 Q56 8 64 17 Q56 26 50 17Z' fill='#ff4d8d'/>";
        $o .= "<circle cx='50' cy='17' r='5' fill='#cc0055'/>";
    }

    // Ala delantera del sombrero (encima de todo para perspectiva)
    if ($acc === 'hat') {
        $o .= "<ellipse cx='50' cy='36' rx='43' ry='9' fill='{$hc}'/>";
        $o .= "<rect x='30' y='32' width='40' height='5' fill='{$hcD}' rx='1' opacity='0.8'/>";
    }

    $o .= "</svg>";
    return $o;
}

/** Valida y devuelve un hex de 6 dígitos seguro para SVG */
function _avHex(string $c): string {
    $c = trim($c);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) return htmlspecialchars($c);
    if (preg_match('/^#[0-9a-fA-F]{3}$/', $c))
        return '#' . str_repeat($c[1],2) . str_repeat($c[2],2) . str_repeat($c[3],2);
    return '#F0C27F';
}

/** Solo letras minúsculas (para estilos/tipos) */
function _avSlug(string $s): string { return preg_replace('/[^a-z]/', '', strtolower(trim($s))); }

/** Oscurece un color hex en $by unidades por canal */
function _avDarken(string $hex, int $by = 20): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return sprintf('#%02x%02x%02x',
        max(0, hexdec(substr($hex,0,2)) - $by),
        max(0, hexdec(substr($hex,2,2)) - $by),
        max(0, hexdec(substr($hex,4,2)) - $by)
    );
}

/** Valores por defecto del avatar según tipo */
function avatarDefaults(string $type = 'woman'): array {
    $defaults = [
        'woman' => ['hair_style'=>'long',   'hair_color'=>'#4a2040', 'outfit'=>'#ec4899', 'eye_style'=>'happy',  'accessory'=>'none', 'blush'=>true],
        'man'   => ['hair_style'=>'short',  'hair_color'=>'#2c1a0e', 'outfit'=>'#2563eb', 'eye_style'=>'normal', 'accessory'=>'none', 'blush'=>false],
        'girl'  => ['hair_style'=>'ponytail','hair_color'=>'#c0392b','outfit'=>'#f97316', 'eye_style'=>'star',   'accessory'=>'bow',  'blush'=>true],
        'boy'   => ['hair_style'=>'spiky',  'hair_color'=>'#1a1a1a', 'outfit'=>'#16a34a', 'eye_style'=>'wink',   'accessory'=>'cap',  'blush'=>false],
    ];
    $d = $defaults[$type] ?? $defaults['woman'];
    $d['type'] = $type;
    $d['skin'] = '#F0C27F';
    return $d;
}
