<?php
// Archivo de prueba para checkout

// Registrar log simple para confirmar carga
file_put_contents(__DIR__.'/logs/debug_checkout_test.log', date('Y-m-d H:i:s') . " - checkout_test.php loaded\n", FILE_APPEND);

// Mostrar contenido simple
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba Checkout</title>
</head>
<body>
    <h1>✅ checkout_test.php cargado correctamente</h1>
    <p>Parámetros GET recibidos:</p>
    <pre><?php print_r($_GET); ?></pre>
    <p><a href="cart.php">Volver al carrito</a></p>
</body>
</html>