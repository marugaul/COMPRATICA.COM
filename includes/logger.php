<?php
/**
 * Sistema de Logging para Debugging
 */

function logError($file, $message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/' . $file;

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

    $logEntry = "[$timestamp] [$ip] [$uri]\n";
    $logEntry .= "MESSAGE: $message\n";

    if (!empty($context)) {
        $logEntry .= "CONTEXT: " . print_r($context, true) . "\n";
    }

    $logEntry .= "POST DATA: " . print_r($_POST, true) . "\n";
    $logEntry .= "GET DATA: " . print_r($_GET, true) . "\n";
    $logEntry .= "SERVER: " . print_r([
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'none',
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'none',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'none'
    ], true) . "\n";
    $logEntry .= str_repeat('-', 80) . "\n\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
