<?php
declare(strict_types=1);
/**
 * affiliates/sale_pickup_location.php
 * Configurar dirección de pickup para un espacio (sale)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();

$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Obtener sale_id
$sale_id = (int)($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
if ($sale_id <= 0) {
    $_SESSION['error'] = "Espacio no especificado";
    header('Location: sales.php');
    exit;
}

// Verificar que el espacio pertenece al afiliado
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$sale_id, $aff_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    $_SESSION['error'] = "Espacio no encontrado o no tienes permiso";
    header('Location: sales.php');
    exit;
}

// Obtener ubicación actual si existe
$stmt = $pdo->prepare("SELECT * FROM sale_pickup_locations WHERE sale_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$sale_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'save_location') {
        try {
            $address = trim($_POST['address'] ?? '');
            $address_line2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            $contact_name = trim($_POST['contact_name'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $pickup_instructions = trim($_POST['pickup_instructions'] ?? '');
            $lat = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
            $lng = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;
            
            if (!$address || !$city || !$state || !$contact_name || !$contact_phone) {
                throw new Exception("Por favor completa todos los campos obligatorios");
            }
            
            $pdo->beginTransaction();
            
            if ($location) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE sale_pickup_locations 
                    SET address = ?,
                        address_line2 = ?,
                        city = ?,
                        state = ?,
                        postal_code = ?,
                        lat = ?,
                        lng = ?,
                        contact_name = ?,
                        contact_phone = ?,
                        pickup_instructions = ?,
                        updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([
                    $address, $address_line2, $city, $state, $postal_code,
                    $lat, $lng, $contact_name, $contact_phone, $pickup_instructions,
                    $location['id']
                ]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("
                    INSERT INTO sale_pickup_locations (
                        sale_id, affiliate_id, address, address_line2, city, state, postal_code,
                        lat, lng, contact_name, contact_phone, pickup_instructions
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sale_id, $aff_id, $address, $address_line2, $city, $state, $postal_code,
                    $lat, $lng, $contact_name, $contact_phone, $pickup_instructions
                ]);
            }
            
            $pdo->commit();
            $success = "✅ Ubicación de recogida guardada exitosamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM sale_pickup_locations WHERE sale_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$sale_id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ✅ Forzar UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
ini_set('default_charset', 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configurar Ubicación de Recogida - <?= htmlspecialchars($sale['title'] ?? '') ?></title>
  <link rel="stylesheet" href="../assets/style.css?v=23">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .form-section {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    .form-section h3 {
      margin-top: 0;
      color: #2c3e50;
      border-bottom: 2px solid #3498db;
      padding-bottom: 0.5rem;
      margin-bottom: 1.5rem;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #555;
    }
    .form-group label .required {
      color: #e74c3c;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 1rem;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #3498db;
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    .map-container {
      height: 400px;
      background: #f0f0f0;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #999;
      border: 1px solid #ddd;
      margin-bottom: 1rem;
    }
    .alert {
      padding: 1rem;
      border-radius: 4px;
      margin-bottom: 1rem;
    }
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    .btn-group {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }
    .btn-primary {
      flex: 1;
      padding: 1rem;
      background: linear-gradient(135deg, #3498db, #2980b9);
      color: white;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      font-size: 1rem;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .btn-secondary {
      flex: 1;
      padding: 1rem;
      background: #95a5a6;
      color: white;
      border: none;
      border-radius: 4px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      font-size: 1rem;
    }
    .help-text {
      font-size: 0.9rem;
      color: #777;
      margin-top: 0.25rem;
    }
    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<header class="header">
  <div class="logo">🚚 Configurar Ubicación de Recogida</div>
  <nav>
    <a class="btn" href="sales.php">← Volver a Mis Espacios</a>
  </nav>
</header>

<div class="container">
  <div class="form-section">
    <h3>
      <i class="fas fa-map-marker-alt"></i> 
      Ubicación de Recogida para: <strong><?= htmlspecialchars($sale['title'] ?? '') ?></strong>
    </h3>
    
    <p style="color: #666; margin-bottom: 1.5rem;">
      <i class="fas fa-info-circle"></i> 
      Esta es la dirección donde el conductor de Uber recogerá los productos cuando un cliente elija envío por Uber.
    </p>
    
    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" id="locationForm">
      <input type="hidden" name="action" value="save_location">
      <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
      <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($location['lat'] ?? '') ?>">
      <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($location['lng'] ?? '') ?>">
      
      <!-- Dirección -->
      <div class="form-group">
        <label>
          Dirección completa <span class="required">*</span>
        </label>
        <input 
          type="text" 
          name="address" 
          id="address"
          value="<?= htmlspecialchars($location['address'] ?? '') ?>"
          placeholder="Ej: Avenida Central, Calle 5"
          required
        >
        <div class="help-text">Dirección exacta del lugar de recogida</div>
      </div>
      
      <div class="form-group">
        <label>
          Apartamento, oficina, piso (opcional)
        </label>
        <input 
          type="text" 
          name="address_line2"
          value="<?= htmlspecialchars($location['address_line2'] ?? '') ?>"
          placeholder="Ej: Edificio A, Piso 3, Oficina 301"
        >
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>
            Ciudad <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="city"
            value="<?= htmlspecialchars($location['city'] ?? 'San José') ?>"
            required
          >
        </div>
        
        <div class="form-group">
          <label>
            Provincia <span class="required">*</span>
          </label>
          <select name="state" required style="width:100%;padding:0.75rem;border:1px solid #ddd;border-radius:4px;">
            <?php
            $provinces = ['San José', 'Alajuela', 'Cartago', 'Heredia', 'Guanacaste', 'Puntarenas', 'Limón'];
            $selected_state = $location['state'] ?? 'San José';
            foreach ($provinces as $prov) {
              $sel = ($prov === $selected_state) ? 'selected' : '';
              echo "<option value=\"".htmlspecialchars($prov)."\" $sel>".htmlspecialchars($prov)."</option>";
            }
            ?>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label>
          Código Postal (opcional)
        </label>
        <input 
          type="text" 
          name="postal_code"
          value="<?= htmlspecialchars($location['postal_code'] ?? '') ?>"
          placeholder="10101"
        >
      </div>
      
      <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
      
      <!-- Información de contacto -->
      <h4 style="color: #2c3e50; margin-bottom: 1rem;">
        <i class="fas fa-user"></i> Información de Contacto para Recogida
      </h4>
      
      <div class="form-row">
        <div class="form-group">
          <label>
            Nombre de contacto <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="contact_name"
            value="<?= htmlspecialchars($location['contact_name'] ?? $_SESSION['aff_name'] ?? '') ?>"
            placeholder="Nombre de la persona que entregará el paquete"
            required
          >
        </div>
        
        <div class="form-group">
          <label>
            Teléfono de contacto <span class="required">*</span>
          </label>
          <input 
            type="tel" 
            name="contact_phone"
            value="<?= htmlspecialchars($location['contact_phone'] ?? '') ?>"
            placeholder="+506 8888-8888"
            required
          >
          <div class="help-text">Incluye código de país (+506)</div>
        </div>
      </div>
      
      <div class="form-group">
        <label>
          Instrucciones especiales para el conductor (opcional)
        </label>
        <textarea 
          name="pickup_instructions"
          rows="3"
          placeholder="Ej: Tocar timbre azul, preguntar por Juan, estacionar en la parte de atrás"
        ><?= htmlspecialchars($location['pickup_instructions'] ?? '') ?></textarea>
        <div class="help-text">Información adicional que ayude al conductor a encontrar el lugar</div>
      </div>
      
      <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
      
      <!-- Mapa -->
      <h4 style="color: #2c3e50; margin-bottom: 1rem;">
        <i class="fas fa-map"></i> Ubicación en el Mapa (Opcional)
      </h4>
      <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem;">
        Si conoces las coordenadas exactas, ingrésalas aquí. Esto mejorará la precisión del envío.
      </p>
      
      <div class="map-container">
        <div style="text-align: center;">
          <i class="fas fa-map-marked-alt" style="font-size: 3rem; margin-bottom: 1rem;"></i>
          <p>Mapa de Google Maps (integración opcional)</p>
          <p style="font-size: 0.9rem; color: #999;">
            Por ahora, puedes ingresar las coordenadas manualmente si las conoces
          </p>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>Latitud (opcional)</label>
          <input 
            type="text" 
            name="lat"
            value="<?= htmlspecialchars($location['lat'] ?? '') ?>"
            placeholder="9.9281"
          >
        </div>
        
        <div class="form-group">
          <label>Longitud (opcional)</label>
          <input 
            type="text" 
            name="lng"
            value="<?= htmlspecialchars($location['lng'] ?? '') ?>"
            placeholder="-84.0907"
          >
        </div>
      </div>
      
      <div class="btn-group">
        <a href="sales.php" class="btn-secondary">
          <i class="fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="btn-primary">
          <i class="fas fa-save"></i> Guardar Ubicación
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Validación básica del formulario
document.getElementById('locationForm').addEventListener('submit', function(e) {
  const phone = document.querySelector('input[name="contact_phone"]').value.trim();
  
  // Validar formato de teléfono (básico)
  if (phone && !phone.match(/^\+?[0-9\s\-()]+$/)) {
    e.preventDefault();
    alert('Por favor ingresa un número de teléfono válido');
    return false;
  }
});
</script>
</body>
</html>