<?php
/**
 * Configuración de Base de Datos MySQL
 * Usado por: Email Marketing System, Places CR
 */

return [
    'host' => '127.0.0.1',
    'database' => 'comprati_marketplace',
    'username' => 'comprati_places_user',
    'password' => 'Marden7i/',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
];
