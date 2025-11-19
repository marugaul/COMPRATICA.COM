<?php
/**
 * Conexión a la base de datos MySQL de lugares (separada de SQLite)
 * Base de datos: comprati_marketplace
 * Tabla: places_cr
 */

function db_places() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=comprati_marketplace;charset=utf8mb4',
            'comprati_places_user',
            'Marden7i/',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        error_log('Error conectando a MySQL places: ' . $e->getMessage());
        throw new Exception('No se pudo conectar a la base de datos de lugares');
    }
}
