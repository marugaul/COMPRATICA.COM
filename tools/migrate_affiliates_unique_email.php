<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
header('Content-Type: text/plain; charset=utf-8');

try {
  // Crear índice único si no existe
  $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_affiliates_email_unique ON affiliates(email);");
  echo "OK: Índice único en affiliates.email listo.\n";
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}
