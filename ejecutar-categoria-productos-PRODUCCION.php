<?php
/**
 * Script de ejecución para PRODUCCIÓN: Agregar columna category a products
 * Ejecutar desde navegador: https://compratica.com/ejecutar-categoria-productos-PRODUCCION.php
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Categoría a Productos - PRODUCCIÓN</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      max-width: 800px;
      margin: 2rem auto;
      padding: 2rem;
      background: #f5f7fa;
    }
    .container {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    }
    h1 {
      color: #2c3e50;
      border-bottom: 3px solid #3498db;
      padding-bottom: 1rem;
    }
    .result {
      margin: 1rem 0;
      padding: 1rem;
      border-radius: 8px;
      border-left: 4px solid;
    }
    .success {
      background: #d4edda;
      border-color: #28a745;
      color: #155724;
    }
    .info {
      background: #d1ecf1;
      border-color: #17a2b8;
      color: #0c5460;
    }
    .error {
      background: #f8d7da;
      border-color: #dc3545;
      color: #721c24;
    }
    .table-info {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 6px;
      margin-top: 1rem;
      font-family: monospace;
      font-size: 0.9rem;
      max-height: 400px;
      overflow-y: auto;
    }
    pre {
      margin: 0;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🏷️ Agregar Categoría a Productos - PRODUCCIÓN</h1>

    <?php
    $exitos = [];
    $errores = [];

    try {
      $pdo = db();

      echo "<p><strong>📋 Verificando estructura actual...</strong></p>";

      // Verificar si la columna ya existe
      $stmt = $pdo->query("PRAGMA table_info(products)");
      $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $categoryExists = false;
      foreach ($columns as $col) {
        if ($col['name'] === 'category') {
          $categoryExists = true;
          break;
        }
      }

      if ($categoryExists) {
        echo "<div class='result info'>ℹ️ <strong>La columna 'category' ya existe.</strong> No es necesario ejecutar la migración.</div>";
      } else {
        echo "<p><strong>⚙️ Ejecutando migración SQL...</strong></p>";

        // Agregar columna category
        try {
          $pdo->exec("ALTER TABLE products ADD COLUMN category TEXT DEFAULT NULL");
          $exitos[] = "✅ Columna 'category' agregada correctamente a la tabla products";
        } catch (Exception $e) {
          if (stripos($e->getMessage(), 'duplicate column name') !== false) {
            $exitos[] = "ℹ️ Columna 'category' ya existe (OK)";
          } else {
            throw $e;
          }
        }

        echo "<h2>✅ Migración Completada</h2>";

        foreach ($exitos as $msg) {
          echo "<div class='result success'>$msg</div>";
        }
      }

      // Mostrar estructura actualizada
      echo "<h3>📋 Estructura actualizada de la tabla products:</h3>";
      $stmt = $pdo->query("PRAGMA table_info(products)");
      $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<div class='table-info'><pre>";
      foreach ($columns as $col) {
        $highlight = ($col['name'] === 'category') ? ' ⬅️ NUEVA' : '';
        printf("%2d  %-20s %-10s %s%s\n",
          $col['cid'],
          $col['name'],
          $col['type'],
          $col['notnull'] ? 'NOT NULL' : '',
          $highlight
        );
      }
      echo "</pre></div>";

      echo "<div class='result success' style='margin-top:2rem;'>
        <strong>✅ ¡Todo listo!</strong><br><br>
        Ahora puedes usar el campo de categoría en:<br>
        📝 <a href='affiliate/products.php' style='color:#155724; font-weight:600;'>affiliate/products.php</a><br>
        🛠️ <a href='admin/dashboard.php' style='color:#155724; font-weight:600;'>admin/dashboard.php</a><br><br>
        <small><strong>IMPORTANTE:</strong> Por seguridad, elimina este archivo después de usarlo.</small>
      </div>";

    } catch (Throwable $e) {
      echo "<div class='result error'>";
      echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
      echo "<br><br><strong>Detalles técnicos:</strong><br>";
      echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
      echo "</div>";
      error_log('[ejecutar-categoria-productos-PRODUCCION.php] ' . $e->getMessage());
    }
    ?>

  </div>
</body>
</html>
