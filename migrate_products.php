<?php
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Revisar columnas existentes
    $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
    $have = [];
    foreach ($cols as $c) $have[strtolower($c['name'])] = true;

    if (empty($have['currency'])) {
        $pdo->exec("ALTER TABLE products ADD COLUMN currency TEXT NOT NULL DEFAULT 'CRC'");
        echo "Columna 'currency' agregada con valor por defecto 'CRC'.\n";
    } else {
        echo "La columna 'currency' ya existe.\n";
    }

    if (empty($have['active'])) {
        $pdo->exec("ALTER TABLE products ADD COLUMN active INTEGER NOT NULL DEFAULT 1");
        echo "Columna 'active' agregada con valor por defecto 1.\n";
    } else {
        echo "La columna 'active' ya existe.\n";
    }

    echo "Migración completada.";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}