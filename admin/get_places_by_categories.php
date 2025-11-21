<?php
/**
 * API para cargar lugares específicos por categorías seleccionadas
 * Retorna JSON con lugares que tienen email
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

// Verificar autenticación
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado']));
}

$config = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$categories = $_POST['categories'] ?? [];

if (empty($categories)) {
    echo json_encode(['places' => []]);
    exit;
}

// Obtener lugares de las categorías seleccionadas
$placeholders = str_repeat('?,', count($categories) - 1) . '?';

$stmt = $pdo->prepare("
    SELECT id, name, phone, tags, lat, lon, category, type
    FROM places_cr
    WHERE type IN ($placeholders)
    AND tags IS NOT NULL
    AND tags != ''
    ORDER BY name ASC
");
$stmt->execute($categories);

$places = [];

while ($place = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tags = json_decode($place['tags'], true);
    $email = $tags['email'] ?? null;

    // Solo incluir lugares con email válido
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $places[] = [
            'id' => $place['id'],
            'name' => $place['name'],
            'email' => $email,
            'phone' => $place['phone'] ?? $tags['phone'] ?? 'N/A',
            'owner' => $tags['owner'] ?? $tags['contact:name'] ?? 'N/A',
            'address' => $tags['addr:street'] ?? $tags['address'] ?? 'N/A',
            'city' => $tags['addr:city'] ?? 'N/A',
            'lat' => $place['lat'],
            'lon' => $place['lon'],
            'category' => $place['category'],
            'type' => $place['type'],
            'tags' => $tags
        ];
    }
}

echo json_encode([
    'success' => true,
    'count' => count($places),
    'places' => $places
]);
