<?php
/**
 * Simular exactamente lo que ve products.php cuando vanecastro inicia sesión
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'vanecastro@gmail.com';

try {
    $pdo = db();

    echo "=== SIMULACIÓN DE SESIÓN AFILIADO ===\n\n";

    // 1. Obtener el usuario
    $user = $pdo->prepare("SELECT id, email, name FROM users WHERE email = ?");
    $user->execute([$email]);
    $userData = $user->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo "❌ Usuario no encontrado\n";
        exit(1);
    }

    $aff_id = (int)$userData['id']; // Esto es lo que hace login_user()

    echo "👤 Usuario: {$userData['name']}\n";
    echo "📧 Email: {$userData['email']}\n";
    echo "🆔 SESSION['aff_id'] simulado: $aff_id\n\n";

    // 2. Buscar espacios exactamente como lo hace products.php línea 13
    echo "📦 ESPACIOS ACTIVOS (consulta exacta de products.php):\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "SQL: SELECT id, title FROM sales WHERE affiliate_id=$aff_id AND is_active=1\n\n";

    $ms = $pdo->prepare("SELECT id, title FROM sales WHERE affiliate_id=? AND is_active=1 ORDER BY datetime(start_at) DESC");
    $ms->execute([$aff_id]);
    $my_sales = $ms->fetchAll(PDO::FETCH_ASSOC);

    if (empty($my_sales)) {
        echo "❌ NO SE ENCONTRARON ESPACIOS ACTIVOS\n";
        echo "\n⚠️  El afiliado verá el mensaje:\n";
        echo "   \"No tenés espacios activos. Creá y pagá uno en Mis Espacios para poder subir productos.\"\n\n";

        echo "🔍 Diagnóstico:\n";
        echo "   - El sistema busca espacios con affiliate_id=$aff_id\n";
        echo "   - No encuentra ningún espacio activo\n";
        echo "   - El afiliado NO puede crear productos\n\n";
    } else {
        echo "✅ ESPACIOS ACTIVOS ENCONTRADOS: " . count($my_sales) . "\n\n";

        foreach ($my_sales as $sale) {
            echo "   📍 ID {$sale['id']}: {$sale['title']}\n";
        }

        echo "\n✅ El afiliado PUEDE crear productos\n";
        echo "   El dropdown de 'Espacio de Venta' mostrará " . count($my_sales) . " opción(es)\n\n";
    }

    // 3. Verificar todos los espacios del afiliado (activos e inactivos)
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 TODOS LOS ESPACIOS DEL AFILIADO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $allSpaces = $pdo->prepare("SELECT id, title, is_active, start_at, end_at FROM sales WHERE affiliate_id=? ORDER BY id DESC");
    $allSpaces->execute([$aff_id]);
    $all = $allSpaces->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all)) {
        echo "❌ No tiene ningún espacio creado\n";
    } else {
        foreach ($all as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "$status ID {$sp['id']}: {$sp['title']}\n";
            echo "         Fechas: {$sp['start_at']} → {$sp['end_at']}\n";
        }
    }

    echo "\n";

    // 4. Instrucciones
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "💡 RESULTADO:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if (!empty($my_sales)) {
        echo "✅ El afiliado TIENE espacios activos\n";
        echo "✅ PUEDE crear productos sin problemas\n\n";
        echo "Si el afiliado sigue viendo el error:\n";
        echo "1. Cerrar sesión completamente\n";
        echo "2. Limpiar cookies y caché del navegador\n";
        echo "3. Cerrar el navegador completamente\n";
        echo "4. Volver a iniciar sesión\n";
        echo "5. O usar modo incógnito/privado\n";
    } else {
        echo "❌ El afiliado NO tiene espacios activos\n";
        echo "❌ NO puede crear productos\n\n";
        echo "Ejecutar: php admin/create_space_vanecastro.php\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
