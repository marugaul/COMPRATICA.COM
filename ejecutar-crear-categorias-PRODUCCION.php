<?php
/**
 * Script para PRODUCCIÓN: Crear tabla categories y cargar categorías iniciales
 * Ejecutar desde navegador: https://compratica.com/ejecutar-crear-categorias-PRODUCCION.php
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Categorías - PRODUCCIÓN</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      max-width: 900px;
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
    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    .category-card {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 8px;
      border: 1px solid #dee2e6;
      text-align: center;
    }
    .category-icon {
      font-size: 2rem;
      color: #3498db;
      margin-bottom: 0.5rem;
    }
    .category-name {
      font-weight: 600;
      color: #2c3e50;
    }
  </style>
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
</head>
<body>
  <div class="container">
    <h1>🏷️ Crear Tabla de Categorías - PRODUCCIÓN</h1>

    <?php
    $exitos = [];
    $errores = [];

    try {
      $pdo = db();

      echo "<p><strong>⚙️ Ejecutando migración...</strong></p>";

      // 1. Crear tabla categories
      try {
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            icon TEXT DEFAULT NULL,
            active INTEGER DEFAULT 1,
            display_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
          )
        ");
        $exitos[] = "✅ Tabla 'categories' creada correctamente";
      } catch (Exception $e) {
        $errores[] = "Error al crear tabla: " . $e->getMessage();
      }

      // 2. Verificar si ya hay categorías
      $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
      $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      if ($count > 0) {
        $exitos[] = "ℹ️ Ya existen {$count} categorías en la base de datos";
      } else {
        // 3. Insertar categorías iniciales
        $categorias = [
          ['Ropa y Accesorios', 'fa-tshirt', 1],
          ['Electrónica', 'fa-laptop', 2],
          ['Hogar y Decoración', 'fa-couch', 3],
          ['Libros y Revistas', 'fa-book', 4],
          ['Juguetes y Juegos', 'fa-gamepad', 5],
          ['Deportes y Fitness', 'fa-dumbbell', 6],
          ['Muebles', 'fa-chair', 7],
          ['Electrodomésticos', 'fa-blender', 8],
          ['Herramientas', 'fa-wrench', 9],
          ['Bebés y Niños', 'fa-baby-carriage', 10],
          ['Belleza y Salud', 'fa-spa', 11],
          ['Vehículos y Accesorios', 'fa-car', 12],
          ['Arte y Manualidades', 'fa-palette', 13],
          ['Música e Instrumentos', 'fa-music', 14],
          ['Otros', 'fa-box', 15]
        ];

        $stmt = $pdo->prepare("INSERT INTO categories (name, icon, display_order) VALUES (?, ?, ?)");

        $insertadas = 0;
        foreach ($categorias as $cat) {
          try {
            $stmt->execute($cat);
            $insertadas++;
          } catch (Exception $e) {
            // Ignorar duplicados
          }
        }

        $exitos[] = "✅ {$insertadas} categorías insertadas correctamente";
      }

      // Mostrar resultados
      echo "<h2>✅ Proceso Completado</h2>";

      foreach ($exitos as $msg) {
        echo "<div class='result success'>$msg</div>";
      }

      foreach ($errores as $msg) {
        echo "<div class='result error'>$msg</div>";
      }

      // Mostrar categorías creadas
      echo "<h3>📋 Categorías disponibles en el sistema:</h3>";

      $stmt = $pdo->query("SELECT * FROM categories ORDER BY display_order ASC");
      $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<div class='categories-grid'>";
      foreach ($categorias as $cat) {
        echo "<div class='category-card'>";
        echo "<div class='category-icon'><i class='fas {$cat['icon']}'></i></div>";
        echo "<div class='category-name'>{$cat['name']}</div>";
        echo "</div>";
      }
      echo "</div>";

      echo "<div class='result success' style='margin-top:2rem;'>
        <strong>✅ ¡Todo listo!</strong><br><br>
        Las categorías ya están disponibles en:<br>
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
      error_log('[ejecutar-crear-categorias-PRODUCCION.php] ' . $e->getMessage());
    }
    ?>

  </div>
</body>
</html>
