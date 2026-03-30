<?php
/**
 * transporte.php
 * Muestra los servicios de transporte de compratica.com/transporte
 * Reutiliza servicios.php filtrando las categorías de transporte.
 */
define('PAGE_TRANSPORTE', true);
// Categorías de transporte en la tabla categories
$TRANSPORT_CATS = ['SERV: Shuttle y Transporte', 'SERV: Fletes y Mudanzas'];
require __DIR__ . '/servicios.php';
