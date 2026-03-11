<?php
/**
 * SEED DE PRUEBA - Emprendedoras
 * -------------------------------------------------------
 * Crea 2 emprendedoras de prueba con 15 productos cada una.
 * Ejecutar UNA sola vez en producción: /admin/seed-emprendedoras-test.php
 * BORRAR ESTE ARCHIVO después de ejecutar.
 * -------------------------------------------------------
 */
if (php_sapi_name() !== 'cli') {
    // Pequeña protección por si acaso
    $secret = $_GET['key'] ?? '';
    if ($secret !== 'seedtest2024') {
        http_response_code(403);
        exit('Acceso denegado. Agrega ?key=seedtest2024');
    }
}

require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log = [];

// ── Migrar columnas En Vivo si no existen ──────────────────────────────────
$cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['is_live'=>'INTEGER DEFAULT 0','live_title'=>'TEXT','live_link'=>'TEXT','live_started_at'=>'TEXT'] as $col => $def) {
    if (!in_array($col, $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
        $log[] = "✅ Columna users.$col creada";
    }
}

// ── Contraseña de prueba ────────────────────────────────────────────────────
$passHash = password_hash('Compratica2024!', PASSWORD_DEFAULT);

// ── Crear Vendedora 1: Marisol Artesanías CR ────────────────────────────────
$existing1 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$existing1->execute(['marisol.test@compratica.com']);
$uid1 = $existing1->fetchColumn();

if (!$uid1) {
    $pdo->prepare("INSERT INTO users (name, email, password_hash, status, is_active, is_live, live_title, live_link, live_started_at, bio, created_at)
        VALUES (?, ?, ?, 'active', 1, 1, ?, ?, datetime('now'), ?, datetime('now'))")
        ->execute([
            'Marisol Artesanías CR',
            'marisol.test@compratica.com',
            $passHash,
            'EN VIVO – Nueva colección verano 🎨',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'Artesana costarricense con 10 años de experiencia. Tejidos, cerámica y joyería hecha a mano.'
        ]);
    $uid1 = $pdo->lastInsertId();
    $log[] = "✅ Vendedora 1 creada: Marisol Artesanías CR (id=$uid1)";

    // Crear suscripción activa para que pueda acceder al dashboard
    $plan = $pdo->query("SELECT id FROM entrepreneur_plans ORDER BY id LIMIT 1")->fetchColumn();
    if ($plan) {
        $pdo->prepare("INSERT INTO entrepreneur_subscriptions (user_id, plan_id, status, created_at) VALUES (?, ?, 'active', datetime('now'))")
            ->execute([$uid1, $plan]);
        $log[] = "  → Suscripción activa asignada (plan $plan)";
    }
} else {
    // Activar En Vivo si ya existe
    $pdo->prepare("UPDATE users SET is_live=1, live_title=?, live_link=?, live_started_at=datetime('now') WHERE id=?")
        ->execute(['EN VIVO – Nueva colección verano 🎨', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', $uid1]);
    $log[] = "⚠️  Vendedora 1 ya existía (id=$uid1), En Vivo activado";
}

// ── Crear Vendedora 2: Café Tico Premium ────────────────────────────────────
$existing2 = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$existing2->execute(['cafe.test@compratica.com']);
$uid2 = $existing2->fetchColumn();

if (!$uid2) {
    $pdo->prepare("INSERT INTO users (name, email, password_hash, status, is_active, is_live, bio, created_at)
        VALUES (?, ?, ?, 'active', 1, 0, ?, datetime('now'))")
        ->execute([
            'Café Tico Premium',
            'cafe.test@compratica.com',
            $passHash,
            'Productores de café de altura de Tarrazú, Naranjo y Tres Ríos. Café 100% costarricense, tostado artesanal.'
        ]);
    $uid2 = $pdo->lastInsertId();
    $log[] = "✅ Vendedora 2 creada: Café Tico Premium (id=$uid2)";

    $plan = $pdo->query("SELECT id FROM entrepreneur_plans ORDER BY id LIMIT 1")->fetchColumn();
    if ($plan) {
        $pdo->prepare("INSERT INTO entrepreneur_subscriptions (user_id, plan_id, status, created_at) VALUES (?, ?, 'active', datetime('now'))")
            ->execute([$uid2, $plan]);
        $log[] = "  → Suscripción activa asignada";
    }
} else {
    $log[] = "⚠️  Vendedora 2 ya existía (id=$uid2)";
}

// ── Productos Marisol ────────────────────────────────────────────────────────
$products1 = [
    [3, 'Collar de Piedras Volcánicas',   'Collar artesanal hecho con piedras volcánicas del Irazú, diseño único y exclusivo.',     15500, 8,  1],
    [3, 'Aretes de Madera Tropical',      'Aretes livianos de madera de guanacaste, barnizados y pintados a mano.',                  9800,  12, 0],
    [7, 'Tapiz de Fique Trenzado',        'Tapiz decorativo trenzado en fique natural, colores neutros.',                           22000, 5,  1],
    [3, 'Pulsera de Semillas Silvestres', 'Pulsera con semillas silvestres: ojoche, jobillo y guácimo.',                             7500,  20, 0],
    [8, 'Cuadro en Técnica Óxido',        'Obra artística en técnica de óxido sobre tela, 40x40 cm, enmarcado.',                    45000, 3,  1],
    [7, 'Canasta de Palma Real',          'Canasta tejida a mano en palma real, resistente y decorativa.',                          18500, 7,  0],
    [3, 'Anillo de Tagua',               'Anillo de tagua (marfil vegetal) tallado a mano, acabado natural.',                       6500,  15, 0],
    [7, 'Portavelas de Cerámica',         'Portavelas artesanal en cerámica con motivos de mariposas tropicales.',                  12000, 10, 1],
    [3, 'Dije Tortuga de Plata',          'Dije en plata .925 con forma de tortuga baula, símbolo de Costa Rica.',                  28000, 4,  0],
    [7, 'Móvil de Bambú',               'Móvil decorativo de bambú con conchas y semillas, sonido relajante.',                     14500, 6,  0],
    [8, 'Maceta Pintada a Mano',          'Maceta de barro con diseños de flora costarricense, tamaño mediano.',                    11000, 9,  0],
    [3, 'Set de Aretes Tropicales x3',    'Set de 3 pares: tucán, mariposa y bromelia, pintados a mano.',                          18000, 8,  1],
    [7, 'Cojín de Algodón Pintado',       'Cojín 45x45 cm, algodón puro con pintura textil de quetzal.',                          25000, 5,  0],
    [8, 'Marcapáginas Artesanales x5',   'Set de 5 marcapáginas ilustrados con flora y fauna de CR, laminados.',                    4500,  25, 0],
    [7, 'Espejo con Marco de Bambú',      'Espejo redondo 30 cm con marco artesanal de bambú trenzado.',                           38000, 3,  1],
];

// Imágenes temáticas por categoría usando picsum con seed fijo
$imgs1 = [
    'https://picsum.photos/seed/aw-col1/400/400',
    'https://picsum.photos/seed/aw-col2/400/400',
    'https://picsum.photos/seed/aw-col3/400/400',
    'https://picsum.photos/seed/aw-col4/400/400',
    'https://picsum.photos/seed/aw-col5/400/400',
    'https://picsum.photos/seed/aw-col6/400/400',
    'https://picsum.photos/seed/aw-col7/400/400',
    'https://picsum.photos/seed/aw-col8/400/400',
    'https://picsum.photos/seed/aw-col9/400/400',
    'https://picsum.photos/seed/aw-col10/400/400',
    'https://picsum.photos/seed/aw-col11/400/400',
    'https://picsum.photos/seed/aw-col12/400/400',
    'https://picsum.photos/seed/aw-col13/400/400',
    'https://picsum.photos/seed/aw-col14/400/400',
    'https://picsum.photos/seed/aw-col15/400/400',
];

$products2 = [
    [1,  'Café Tarrazú Grano 250g',        'Café de altura 1800 msnm, perfil floral con notas de chocolate y caramelo.',           6500,  50, 1],
    [1,  'Café Naranjo Molido 500g',        'Café molido medio, tostado artesanal, ideal para cafetera de filtro.',                11000, 30, 0],
    [5,  'Mermelada de Cas 240g',           'Mermelada artesanal de cas sin conservantes, sabor intenso y natural.',                4800,  40, 1],
    [1,  'Café Tres Ríos Grano 500g',       'Denominación de origen, notas cítricas y acidez brillante.',                         13500, 20, 1],
    [5,  'Granola Tropical Artesanal 300g', 'Granola con avena, coco, piña deshidratada y macadamia, sin azúcar añadida.',         7200,  35, 0],
    [1,  'Cold Brew Concentrado 500ml',     'Cold brew de Tarrazú preparado en frío 18 horas, concentrado 1:4.',                   8500,  15, 1],
    [5,  'Miel de Abeja Silvestre 350g',    'Miel pura de abeja silvestre recolectada en montañas de Turrialba.',                   9800,  22, 0],
    [5,  'Chocolate 70% Cacao CR 100g',     'Tableta de chocolate oscuro elaborada con cacao costarricense, sin lecitina.',         5500,  45, 1],
    [1,  'Kit Degustación 4 Cafés',         '4 muestras de 60g: Tarrazú, Naranjo, Tres Ríos y Orosi.',                           19000, 10, 1],
    [5,  'Vinagre de Piña Artesanal 250ml', 'Vinagre natural fermentado de piña, para ensaladas y marinadas.',                     4200,  28, 0],
    [1,  'Café Volcán Poás Grano 250g',     'Cultivado a 1600 m en las faldas del Poás, perfil suave y dulce.',                   7000,  18, 0],
    [5,  'Galletas de Café y Canela x12',   'Galletas artesanales con café Tarrazú y canela de Ceilán.',                           5800,  30, 0],
    [5,  'Pasta de Maní y Café 250g',       'Mantequilla de maní con café tostado, sin azúcar, proteína natural.',                 6200,  25, 1],
    [1,  'Café Descafeinado Grano 250g',    'Proceso Swiss Water, sin químicos, todo el sabor sin cafeína.',                       7800,  12, 0],
    [5,  'Cascara (Té de Hoja de Café) 50g','Infusión tropical con notas de tamarindo e hibisco.',                                  3800,  40, 0],
];

$imgs2 = [
    'https://picsum.photos/seed/ct-caf1/400/400',
    'https://picsum.photos/seed/ct-caf2/400/400',
    'https://picsum.photos/seed/ct-caf3/400/400',
    'https://picsum.photos/seed/ct-caf4/400/400',
    'https://picsum.photos/seed/ct-caf5/400/400',
    'https://picsum.photos/seed/ct-caf6/400/400',
    'https://picsum.photos/seed/ct-caf7/400/400',
    'https://picsum.photos/seed/ct-caf8/400/400',
    'https://picsum.photos/seed/ct-caf9/400/400',
    'https://picsum.photos/seed/ct-caf10/400/400',
    'https://picsum.photos/seed/ct-caf11/400/400',
    'https://picsum.photos/seed/ct-caf12/400/400',
    'https://picsum.photos/seed/ct-caf13/400/400',
    'https://picsum.photos/seed/ct-caf14/400/400',
    'https://picsum.photos/seed/ct-caf15/400/400',
];

$stmt = $pdo->prepare("
    INSERT INTO entrepreneur_products
        (user_id, category_id, name, description, price, currency, stock,
         image_1, featured, is_active, views_count, sales_count,
         accepts_sinpe, accepts_paypal, sinpe_phone, paypal_email,
         shipping_available, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 'CRC', ?, ?, ?, 1, ?, ?, 1, 1, '88001234', 'cafético@paypal.com', 1, datetime('now'), datetime('now'))
");

// Insertar productos vendedora 1
$existProds1 = (int)$pdo->prepare("SELECT COUNT(*) FROM entrepreneur_products WHERE user_id=?")->execute([$uid1])
    ? $pdo->query("SELECT COUNT(*) FROM entrepreneur_products WHERE user_id=$uid1")->fetchColumn() : 0;

if ($existProds1 == 0) {
    foreach ($products1 as $i => $p) {
        [$cat, $name, $desc, $price, $stock, $feat] = $p;
        $stmt->execute([$uid1, $cat, $name, $desc, $price, $stock, $imgs1[$i], $feat, rand(20,300), rand(0,40)]);
    }
    $log[] = "✅ 15 productos Marisol Artesanías insertados";
} else {
    $log[] = "⚠️  Marisol ya tenía $existProds1 productos, no se duplicaron";
}

// Insertar productos vendedora 2
$existProds2 = (int)$pdo->query("SELECT COUNT(*) FROM entrepreneur_products WHERE user_id=$uid2")->fetchColumn();
if ($existProds2 == 0) {
    foreach ($products2 as $i => $p) {
        [$cat, $name, $desc, $price, $stock, $feat] = $p;
        $stmt->execute([$uid2, $cat, $name, $desc, $price, $stock, $imgs2[$i], $feat, rand(20,300), rand(0,50)]);
    }
    $log[] = "✅ 15 productos Café Tico Premium insertados";
} else {
    $log[] = "⚠️  Café Tico ya tenía $existProds2 productos, no se duplicaron";
}

// ── Resumen ──────────────────────────────────────────────────────────────────
$total = $pdo->query("SELECT COUNT(*) FROM entrepreneur_products WHERE is_active=1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Seed Emprendedoras</title>
<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:40px;max-width:700px;margin:0 auto;}
h1{color:#a78bfa;}h2{color:#60a5fa;margin-top:32px;}
.ok{color:#4ade80;}.warn{color:#fbbf24;}.info{color:#94a3b8;}
.box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:20px;margin:16px 0;}
.cred{background:#1e293b;border-left:4px solid #a78bfa;padding:12px 20px;margin:8px 0;border-radius:4px;}
a{color:#60a5fa;}
.delete{background:#ef4444;color:white;padding:8px 18px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:16px;}
</style>
</head>
<body>
<h1>🌱 Seed Emprendedoras — Resultado</h1>

<div class="box">
<?php foreach ($log as $line):
    $cls = str_starts_with($line,'✅') ? 'ok' : (str_starts_with($line,'⚠️') ? 'warn' : 'info');
?>
<div class="<?=$cls?>"><?=htmlspecialchars($line)?></div>
<?php endforeach; ?>
<div class="info">────</div>
<div class="ok">Total productos activos en BD: <?=$total?></div>
</div>

<h2>🔐 Credenciales de acceso</h2>
<div class="cred">
    <strong>Marisol Artesanías CR</strong> (🔴 EN VIVO activo)<br>
    Email: <code>marisol.test@compratica.com</code><br>
    Password: <code>Compratica2024!</code><br>
    Dashboard: <a href="/emprendedoras-dashboard.php">/emprendedoras-dashboard.php</a>
</div>
<div class="cred">
    <strong>Café Tico Premium</strong><br>
    Email: <code>cafe.test@compratica.com</code><br>
    Password: <code>Compratica2024!</code><br>
    Dashboard: <a href="/emprendedoras-dashboard.php">/emprendedoras-dashboard.php</a>
</div>

<h2>🛍️ Ver resultado</h2>
<a href="/emprendedoras-catalogo.php">→ Ver Mercadito Emprendedoras</a>

<h2>⚠️ Seguridad</h2>
<p class="warn">Borra este archivo cuando termines de probar.</p>
<p><a href="?key=seedtest2024&delete=1" class="delete">🗑️ Autodestruir este archivo</a></p>
<?php
// Autodestrucción
if (($_GET['delete'] ?? '') === '1' && ($_GET['key'] ?? '') === 'seedtest2024') {
    @unlink(__FILE__);
    echo '<p class="ok">✅ Archivo eliminado.</p>';
}
?>
</body>
</html>
