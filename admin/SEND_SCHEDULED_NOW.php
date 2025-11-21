<?php
/**
 * SCRIPT DE EMERGENCIA: Enviar campañas programadas manualmente
 * Ejecuta el proceso de campañas programadas inmediatamente
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Enviar Campañas Programadas</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}";
echo ".ok{color:green;font-weight:bold;} .error{color:red;font-weight:bold;}";
echo "pre{background:#fff;padding:15px;border-radius:5px;border-left:4px solid #0891b2;}</style></head><body>";

echo "<h1>🚀 Procesando Campañas Programadas</h1>";
echo "<p><strong>Hora del servidor:</strong> " . date('Y-m-d H:i:s') . " UTC</p>";
echo "<p><strong>Hora Costa Rica:</strong> " . date('Y-m-d H:i:s', strtotime('-6 hours')) . " CST (UTC-6)</p>";
echo "<hr>";

// Cargar proceso
ob_start();
include __DIR__ . '/process_scheduled_campaigns.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<hr>";
echo "<p><a href='email_marketing.php?page=campaigns' class='btn'>← Volver a Campañas</a></p>";
echo "</body></html>";
