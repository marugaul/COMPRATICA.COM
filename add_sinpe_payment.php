<?php
/**
 * Script temporal para agregar método de pago SINPE a servicios
 */
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "<h2>Agregando método de pago SINPE Móvil</h2>";

// Ver servicios activos
echo "<h3>Servicios activos:</h3>";
$stmt = $pdo->query("
    SELECT s.id, s.title, s.affiliate_id, a.name as affiliate_name, a.phone as affiliate_phone
    FROM services s
    INNER JOIN affiliates a ON a.id = s.affiliate_id
    WHERE s.is_active = 1
    ORDER BY s.id DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Servicio</th><th>Afiliado</th><th>Teléfono</th><th>Acción</th></tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['title']}</td>";
    echo "<td>{$row['affiliate_name']}</td>";
    echo "<td>{$row['affiliate_phone']}</td>";

    // Verificar si ya tiene SINPE
    $check = $pdo->prepare("SELECT COUNT(*) FROM service_payment_methods WHERE service_id = ? AND method_type = 'sinpe'");
    $check->execute([$row['id']]);
    $hasSinpe = $check->fetchColumn() > 0;

    if ($hasSinpe) {
        echo "<td style='color: green;'>✓ Ya tiene SINPE</td>";
    } else {
        echo "<td><a href='?add={$row['id']}'>Agregar SINPE</a></td>";
    }
    echo "</tr>";
}
echo "</table>";

// Agregar SINPE si se solicita
if (isset($_GET['add']) && is_numeric($_GET['add'])) {
    $service_id = (int)$_GET['add'];

    // Obtener info del servicio
    $stmt = $pdo->prepare("
        SELECT s.*, a.phone as affiliate_phone, a.name as affiliate_name
        FROM services s
        INNER JOIN affiliates a ON a.id = s.affiliate_id
        WHERE s.id = ?
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($service) {
        // Insertar método SINPE
        $insert = $pdo->prepare("
            INSERT INTO service_payment_methods (service_id, method_type, details, is_active)
            VALUES (?, 'sinpe', ?, 1)
        ");

        $details = "Transferir a SINPE Móvil: {$service['affiliate_phone']}";
        $insert->execute([$service_id, $details]);

        echo "<div style='background: #d4edda; padding: 1rem; margin: 1rem 0; border-radius: 5px;'>";
        echo "✅ Método SINPE agregado exitosamente para: <strong>{$service['title']}</strong><br>";
        echo "📱 Número: {$service['affiliate_phone']}<br>";
        echo "<a href='?'>Refrescar</a>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<h3>Métodos de pago existentes:</h3>";
$stmt = $pdo->query("
    SELECT spm.*, s.title as service_title
    FROM service_payment_methods spm
    INNER JOIN services s ON s.id = spm.service_id
    ORDER BY spm.id DESC
    LIMIT 20
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Servicio</th><th>Método</th><th>Detalles</th><th>Activo</th></tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['service_title']}</td>";
    echo "<td><strong>{$row['method_type']}</strong></td>";
    echo "<td>{$row['details']}</td>";
    echo "<td>" . ($row['is_active'] ? '✓' : '✗') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
