<?php
/**
 * transporte.php
 * Muestra los servicios de transporte de compratica.com/transporte
 * Reutiliza servicios.php con el filtro de categoría preestablecido.
 */
$_GET['cat'] = 'Transporte';
define('PAGE_TRANSPORTE', true);
require __DIR__ . '/servicios.php';
