<?php
header('Content-Type: text/plain');
require_once 'includes/db_places.php';

try {
    $pdo = db_places();

    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║          ESTRUCTURA DE LA TABLA places_cr                   ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";

    $stmt = $pdo->query("DESCRIBE places_cr");

    printf("%-20s %-30s %-10s %-10s %s\n", "COLUMN", "TYPE", "NULL", "KEY", "DEFAULT");
    echo str_repeat("=", 90) . "\n";

    while ($row = $stmt->fetch()) {
        printf("%-20s %-30s %-10s %-10s %s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key'],
            $row['Default'] ?? 'NULL'
        );
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
