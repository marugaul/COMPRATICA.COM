<?php
/**
 * Redirección a la página de Blacklist en el panel de administración
 * Este archivo redirige automáticamente al panel correcto
 */

require_once __DIR__ . '/includes/logger.php';

logError('error_Blacklist.log', 'ROOT create_blacklist_table.php - ACCESO A ARCHIVO DE REDIRECT EN ROOT', [
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'none'
]);

// Redirigir al panel de administración - Blacklist
logError('error_Blacklist.log', 'ROOT create_blacklist_table.php - Redirigiendo a /admin/email_marketing.php?page=blacklist');
header('Location: /admin/email_marketing.php?page=blacklist');
exit;
