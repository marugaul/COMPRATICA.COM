<?php
/**
 * Verificar que los emails y tags se guardaron correctamente
 */

header('Content-Type: text/plain; charset=utf-8');

$config = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║        VERIFICACIÓN DE EMAILS Y TAGS EN LA BASE DE DATOS        ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

    // Total de lugares
    $totalPlaces = $pdo->query("SELECT COUNT(*) FROM places_cr")->fetchColumn();
    echo "📊 Total de lugares en BD: " . number_format($totalPlaces) . "\n\n";

    // Lugares con tags no nulos
    $withTags = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags IS NOT NULL AND tags != ''")->fetchColumn();
    $pctTags = round(($withTags / $totalPlaces) * 100, 2);
    echo "✅ Lugares con tags: " . number_format($withTags) . " ($pctTags%)\n\n";

    // Buscar lugares con email en tags
    $stmt = $pdo->query("
        SELECT name, type, tags, phone, website
        FROM places_cr
        WHERE tags LIKE '%email%'
        ORDER BY RAND()
        LIMIT 10
    ");

    echo "📧 EJEMPLOS DE LUGARES CON EMAIL EN TAGS:\n";
    echo str_repeat("=", 70) . "\n\n";

    $count = 0;
    while ($place = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        $tags = json_decode($place['tags'], true);

        echo "$count. {$place['name']} ({$place['type']})\n";
        if (!empty($place['phone'])) echo "   ☎️  Teléfono: {$place['phone']}\n";
        if (!empty($place['website'])) echo "   🌐 Website: {$place['website']}\n";
        if (!empty($tags['email'])) echo "   📧 Email: {$tags['email']}\n";
        if (!empty($tags['operator'])) echo "   👤 Operador: {$tags['operator']}\n";
        if (!empty($tags['brand'])) echo "   🏷️  Marca: {$tags['brand']}\n";
        if (!empty($tags['opening_hours'])) echo "   ⏰ Horario: {$tags['opening_hours']}\n";
        echo "\n";
    }

    if ($count === 0) {
        echo "⚠️  No se encontraron lugares con email en tags\n\n";
    }

    // Estadísticas de tags
    echo "\n📊 ESTADÍSTICAS DE DATOS EN TAGS:\n";
    echo str_repeat("=", 70) . "\n";

    $emailCount = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags LIKE '%email%'")->fetchColumn();
    $operatorCount = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags LIKE '%operator%'")->fetchColumn();
    $brandCount = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags LIKE '%brand%'")->fetchColumn();
    $hoursCount = $pdo->query("SELECT COUNT(*) FROM places_cr WHERE tags LIKE '%opening_hours%'")->fetchColumn();

    $pctEmail = round(($emailCount / $totalPlaces) * 100, 2);
    $pctOperator = round(($operatorCount / $totalPlaces) * 100, 2);
    $pctBrand = round(($brandCount / $totalPlaces) * 100, 2);
    $pctHours = round(($hoursCount / $totalPlaces) * 100, 2);

    printf("📧 Email:           %6d lugares (%5.2f%%)\n", $emailCount, $pctEmail);
    printf("👤 Operador:        %6d lugares (%5.2f%%)\n", $operatorCount, $pctOperator);
    printf("🏷️  Marca:           %6d lugares (%5.2f%%)\n", $brandCount, $pctBrand);
    printf("⏰ Horarios:        %6d lugares (%5.2f%%)\n", $hoursCount, $pctHours);

    echo "\n✅ Verificación completada\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
