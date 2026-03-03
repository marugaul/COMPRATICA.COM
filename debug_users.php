<?php
// Script de diagnóstico para verificar usuarios
require_once __DIR__ . '/includes/db.php';

$pdo = db();

echo "<h2>Diagnóstico de Usuarios</h2>\n\n";

// Ver todos los usuarios
$stmt = $pdo->query("SELECT id, name, email, password_hash, is_active, oauth_provider, created_at FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Usuarios en la base de datos (" . count($users) . " total):</h3>\n";
echo "<table border='1' cellpadding='10'>\n";
echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Password Hash</th><th>Active</th><th>OAuth</th><th>Creado</th></tr>\n";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . (empty($user['password_hash']) ? '<span style="color:red">VACÍO</span>' : '<span style="color:green">Presente (' . strlen($user['password_hash']) . ' chars)</span>') . "</td>";
    echo "<td>" . ($user['is_active'] == 1 ? '<span style="color:green">SÍ</span>' : '<span style="color:red">NO</span>') . "</td>";
    echo "<td>" . htmlspecialchars($user['oauth_provider'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
    echo "</tr>\n";
}

echo "</table>\n\n";

// Ver estructura de la tabla
echo "<h3>Estructura de la tabla users:</h3>\n";
$stmt = $pdo->query("PRAGMA table_info(users)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>\n";
echo "<tr><th>Columna</th><th>Tipo</th><th>Nullable</th><th>Default</th></tr>\n";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($col['name']) . "</td>";
    echo "<td>" . htmlspecialchars($col['type']) . "</td>";
    echo "<td>" . ($col['notnull'] ? 'NO' : 'SI') . "</td>";
    echo "<td>" . htmlspecialchars($col['dflt_value'] ?? 'NULL') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "\n<h3>Instrucciones:</h3>\n";
echo "<p>Si necesitas activar un usuario, usa:</p>\n";
echo "<pre>UPDATE users SET is_active = 1 WHERE email = 'tu@email.com';</pre>\n\n";
echo "<p>Si necesitas resetear una contraseña, usa el siguiente código PHP:</p>\n";
echo "<pre>\$hash = password_hash('nueva_contraseña', PASSWORD_BCRYPT);\n";
echo "\$stmt->prepare('UPDATE users SET password_hash = ? WHERE email = ?');\n";
echo "\$stmt->execute([\$hash, 'tu@email.com']);</pre>\n";
?>
