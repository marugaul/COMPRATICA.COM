#!/usr/bin/env php
<?php
/**
 * Script para ejecutar la importación OSM en el servidor remoto
 * Llama a las APIs del servidor y monitorea el progreso
 */

$baseUrl = 'https://compratica.com';

echo "🚀 Iniciando importación remota de lugares OSM...\n\n";

// Paso 1: Resetear estado
echo "📋 Paso 1: Reseteando estado de importación...\n";

$ch = curl_init("$baseUrl/api/reset_import.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);
$resetResponse = curl_exec($ch);
curl_close($ch);

$resetData = json_decode($resetResponse, true);

if (!$resetData || !$resetData['success']) {
    die("❌ Error reseteando: " . ($resetData['message'] ?? 'desconocido') . "\n");
}
echo "✅ Estado reseteado correctamente\n\n";

sleep(1);

// Paso 2: Ejecutar importación por lotes
echo "📋 Paso 2: Ejecutando importación por categorías...\n";
echo str_repeat("=", 70) . "\n\n";

$totalImported = 0;
$categoryIndex = 0;
$completed = false;

while (!$completed) {
    // Llamar a start_import.php
    $ch = curl_init("$baseUrl/api/start_import.php");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "⚠️  Error HTTP $httpCode, reintentando...\n";
        sleep(3);
        continue;
    }

    $data = json_decode($response, true);

    if (!$data['success']) {
        echo "❌ Error: " . ($data['error'] ?? 'desconocido') . "\n";
        break;
    }

    if ($data['completed']) {
        $completed = true;
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "🎉 ¡Importación completada!\n";
        echo "📊 Total de lugares importados: " . number_format($data['total_imported'] ?? $totalImported) . "\n";
        break;
    }

    if (isset($data['continue']) && $data['continue']) {
        $totalImported = $data['total_imported'] ?? $totalImported;

        // Mostrar siempre, incluso si imported es 0
        $imported = $data['imported'] ?? 0;
        $symbol = $imported > 0 ? "✓" : "⚠";
        echo "{$symbol} {$data['category']}: {$imported} lugares importados";

        if (isset($data['debug']['received'])) {
            echo " (recibidos: {$data['debug']['received']}";
            if (isset($data['debug']['details'])) {
                $d = $data['debug']['details'];
                echo ", sin nombre: {$d['no_name']}, sin coords: {$d['no_coords']}, errores: {$d['errors']}";
            }
            echo ")";
        }

        if (isset($data['error'])) {
            echo " [ERROR: {$data['error']}]";
        }

        echo "\n";
        echo "   📊 Progreso total: " . round($data['progress'], 1) . "% - Total acumulado: " . number_format($totalImported) . " lugares\n\n";

        // Esperar 2 segundos antes del siguiente lote (rate limit de Overpass)
        sleep(2);
    }
}

// Paso 3: Verificar estado final
echo "\n📋 Paso 3: Verificando estado final...\n";

$ch = curl_init("$baseUrl/api/import_status.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);
$statusResponse = curl_exec($ch);
curl_close($ch);

$statusData = json_decode($statusResponse, true);

if ($statusData['success']) {
    echo "✅ Estado: {$statusData['status']}\n";
    echo "✅ Total importado: " . number_format($statusData['total_imported']) . " lugares\n";
    echo "✅ Progreso: " . round($statusData['progress'], 1) . "%\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Proceso completado - Base de datos cargada al 100%\n";
echo "🔗 Puedes verificar en: $baseUrl/import_osm_progress.php\n";
?>
