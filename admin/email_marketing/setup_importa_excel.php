<?php
/**
 * Migración: Crea las tablas tipos_correo e importa_excel
 * Ejecutar una sola vez desde el navegador: /admin/email_marketing.php?page=setup-importa-excel
 */
require_once __DIR__ . '/../../includes/config.php';
$config = require __DIR__ . '/../../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$results = [];

// 1. Tabla tipos_correo
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tipos_correo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            descripcion VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['ok', 'Tabla <code>tipos_correo</code> creada'];
} catch (Exception $e) {
    $results[] = ['err', 'tipos_correo: ' . $e->getMessage()];
}

// 2. Datos iniciales tipos_correo
try {
    $pdo->exec("INSERT IGNORE INTO tipos_correo (nombre) VALUES ('Tarjeta'),('Restaurantes'),('Hoteles'),('Empresas')");
    $results[] = ['ok', 'Datos iniciales insertados en <code>tipos_correo</code>'];
} catch (Exception $e) {
    $results[] = ['err', 'Datos tipos_correo: ' . $e->getMessage()];
}

// 3. Tabla importa_excel
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS importa_excel (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cedula VARCHAR(20) DEFAULT NULL,
            nombre VARCHAR(200) DEFAULT NULL,
            correo VARCHAR(200) DEFAULT NULL,
            telefono VARCHAR(50) DEFAULT NULL,
            direccion VARCHAR(500) DEFAULT NULL,
            tipo_correo_id INT DEFAULT NULL,
            fecha_ingreso DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_correo (correo),
            KEY idx_tipo (tipo_correo_id),
            CONSTRAINT fk_importa_tipo FOREIGN KEY (tipo_correo_id)
                REFERENCES tipos_correo(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['ok', 'Tabla <code>importa_excel</code> creada'];
} catch (Exception $e) {
    $results[] = ['err', 'importa_excel: ' . $e->getMessage()];
}

$allOk = !array_filter($results, fn($r) => $r[0] === 'err');
?>
<div class="container-fluid">
  <h4><i class="fas fa-database"></i> Migración: Importar Excel</h4>
  <?php foreach ($results as [$type, $msg]): ?>
    <div class="alert alert-<?= $type === 'ok' ? 'success' : 'danger' ?> py-2">
      <i class="fas fa-<?= $type === 'ok' ? 'check' : 'times' ?>-circle"></i> <?= $msg ?>
    </div>
  <?php endforeach; ?>
  <?php if ($allOk): ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle"></i> Migración completa.
      <a href="?page=importar-excel-bd" class="btn btn-sm btn-primary ms-2">
        <i class="fas fa-file-excel"></i> Ir a Importar Excel
      </a>
    </div>
  <?php endif; ?>
</div>
