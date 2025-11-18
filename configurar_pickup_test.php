<?php
/**
 * CONFIGURAR PICKUP DE PRUEBA
 * Script temporal para agregar dirección de pickup a un espacio
 */

require_once __DIR__ . '/includes/db.php';

$secret = 'PICKUP2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

$pdo = db();

echo "<pre>";
echo "==============================================\n";
echo "CONFIGURAR PICKUP LOCATION DE PRUEBA\n";
echo "==============================================\n\n";

// Listar espacios disponibles
$stmt = $pdo->query("
    SELECT s.id, s.title, s.affiliate_id, a.name as affiliate_name,
           (SELECT COUNT(*) FROM sale_pickup_locations WHERE sale_id = s.id AND is_active = 1) as has_pickup
    FROM sales s
    JOIN affiliates a ON a.id = s.affiliate_id
    ORDER BY s.id DESC
    LIMIT 10
");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sales)) {
    echo "❌ No hay espacios de venta en el sistema\n";
    exit;
}

echo "ESPACIOS DISPONIBLES:\n";
echo str_repeat("─", 80) . "\n";
foreach ($sales as $sale) {
    $status = $sale['has_pickup'] > 0 ? '✅ CON PICKUP' : '❌ SIN PICKUP';
    echo sprintf("ID: %-4d | %s | Afiliado: %s | %s\n",
        $sale['id'],
        substr($sale['title'], 0, 30),
        $sale['affiliate_name'],
        $status
    );
}
echo str_repeat("─", 80) . "\n\n";

// Si viene el parámetro sale_id, configurar pickup
if (isset($_GET['sale_id'])) {
    $sale_id = (int)$_GET['sale_id'];

    // Verificar que existe el espacio
    $stmt = $pdo->prepare("SELECT id, affiliate_id, title FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        echo "❌ Espacio ID $sale_id no encontrado\n";
        exit;
    }

    echo "CONFIGURANDO PICKUP PARA:\n";
    echo "  Espacio: {$sale['title']} (ID: {$sale['id']})\n";
    echo "  Afiliado ID: {$sale['affiliate_id']}\n\n";

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM sale_pickup_locations WHERE sale_id = ? AND is_active = 1");
    $stmt->execute([$sale_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "⚠️  Ya existe una dirección de pickup activa\n";
        echo "  Actualizando...\n\n";

        $stmt = $pdo->prepare("
            UPDATE sale_pickup_locations SET
                address = ?,
                city = ?,
                state = ?,
                lat = ?,
                lng = ?,
                contact_name = ?,
                contact_phone = ?,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([
            'Avenida Central, Calle 5, San José Centro',
            'San José',
            'San José',
            9.9281, // Coordenadas del centro de San José
            -84.0907,
            'Vendedor Test',
            '8888-8888',
            $existing['id']
        ]);

        echo "✅ Pickup location ACTUALIZADA\n";
    } else {
        echo "➕ Creando nueva dirección de pickup...\n\n";

        $stmt = $pdo->prepare("
            INSERT INTO sale_pickup_locations (
                sale_id, affiliate_id, address, city, state,
                lat, lng, contact_name, contact_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sale_id,
            $sale['affiliate_id'],
            'Avenida Central, Calle 5, San José Centro',
            'San José',
            'San José',
            9.9281,
            -84.0907,
            'Vendedor Test',
            '8888-8888'
        ]);

        echo "✅ Pickup location CREADA\n";
    }

    echo "\n";
    echo "DATOS CONFIGURADOS:\n";
    echo "  📍 Dirección: Avenida Central, Calle 5, San José Centro\n";
    echo "  🏙️  Ciudad: San José\n";
    echo "  🗺️  Coordenadas: 9.9281, -84.0907\n";
    echo "  👤 Contacto: Vendedor Test (8888-8888)\n";
    echo "\n";
    echo "==============================================\n";
    echo "✅ LISTO PARA PROBAR\n";
    echo "==============================================\n";
    echo "\n";
    echo "Ahora puedes ir al checkout del espacio ID $sale_id\n";
    echo "y deberías ver la opción de Uber habilitada.\n";
    echo "\n";

} else {
    echo "\n";
    echo "PARA CONFIGURAR PICKUP:\n";
    echo "Agrega &sale_id=X a la URL (reemplaza X con el ID del espacio)\n";
    echo "\nEjemplo:\n";
    if (!empty($sales)) {
        $first_sale = $sales[0];
        echo "  " . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/configurar_pickup_test.php?key=PICKUP2024&sale_id={$first_sale['id']}\n";
    }
    echo "\n";
}

echo "</pre>";
?>
