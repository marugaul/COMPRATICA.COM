<?php
// TEST SIMPLE para verificar que PHP funciona
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API funcionando correctamente',
    'test' => 'OK'
]);
