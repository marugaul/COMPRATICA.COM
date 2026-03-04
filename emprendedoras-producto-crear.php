<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: login.php?redirect=emprendedoras-dashboard.php');
    exit;
}

$userId = $_SESSION['uid'];
$userName = $_SESSION['name'] ?? 'Usuario';

$pdo = db();

// Verificar suscripción activa
$stmt = $pdo->prepare("
    SELECT s.*, p.max_products
    FROM entrepreneur_subscriptions s
    JOIN entrepreneur_plans p ON s.plan_id = p.id
    WHERE s.user_id = ? AND s.status = 'active'
    LIMIT 1
");
$stmt->execute([$userId]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header('Location: emprendedoras-planes.php');
    exit;
}

// Verificar límite de productos
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM entrepreneur_products WHERE user_id = ?");
$stmt->execute([$userId]);
$productCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

if ($subscription['max_products'] > 0 && $productCount >= $subscription['max_products']) {
    header('Location: emprendedoras-dashboard.php?error=limit_reached');
    exit;
}

// Obtener categorías
$categories = $pdo->query("
    SELECT * FROM entrepreneur_categories
    WHERE is_active = 1
    ORDER BY display_order
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener métodos de pago del usuario
$stmt = $pdo->prepare("SELECT * FROM affiliate_payment_methods WHERE affiliate_id = ?");
$stmt->execute([$userId]);
$paymentMethods = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $weight = floatval($_POST['weight_kg'] ?? 0);
    $acceptsSinpe = isset($_POST['accepts_sinpe']) ? 1 : 0;
    $acceptsPaypal = isset($_POST['accepts_paypal']) ? 1 : 0;
    $sinpePhone = trim($_POST['sinpe_phone'] ?? '');
    $paypalEmail = trim($_POST['paypal_email'] ?? '');
    $shippingAvailable = isset($_POST['shipping_available']) ? 1 : 0;
    $pickupAvailable = isset($_POST['pickup_available']) ? 1 : 0;
    $pickupLocation = trim($_POST['pickup_location'] ?? '');

    // Validaciones
    if (empty($name)) {
        $error = 'El nombre del producto es obligatorio';
    } elseif ($price <= 0) {
        $error = 'El precio debe ser mayor a 0';
    } elseif (!$acceptsSinpe && !$acceptsPaypal) {
        $error = 'Debes aceptar al menos un método de pago';
    } elseif ($acceptsSinpe && empty($sinpePhone)) {
        $error = 'Debes proporcionar un número de teléfono SINPE';
    } elseif ($acceptsPaypal && empty($paypalEmail)) {
        $error = 'Debes proporcionar un correo de PayPal';
    } else {
        try {
            // Manejar subida de imágenes (simplificado - en producción usar upload real)
            $images = [];
            for ($i = 1; $i <= 5; $i++) {
                if (isset($_FILES["image_$i"]) && $_FILES["image_$i"]['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/uploads/emprendedoras/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    $ext = pathinfo($_FILES["image_$i"]['name'], PATHINFO_EXTENSION);
                    $filename = 'product_' . $userId . '_' . time() . '_' . $i . '.' . $ext;
                    $destination = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES["image_$i"]['tmp_name'], $destination)) {
                        $images[$i] = '/uploads/emprendedoras/' . $filename;
                    }
                }
            }

            // Insertar producto
            $stmt = $pdo->prepare("
                INSERT INTO entrepreneur_products (
                    user_id, category_id, name, description, price, stock, sku,
                    image_1, image_2, image_3, image_4, image_5,
                    weight_kg, accepts_sinpe, accepts_paypal,
                    sinpe_phone, paypal_email,
                    shipping_available, pickup_available, pickup_location,
                    is_active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    1, datetime('now'), datetime('now')
                )
            ");

            $stmt->execute([
                $userId, $categoryId, $name, $description, $price, $stock, $sku,
                $images[1] ?? null, $images[2] ?? null, $images[3] ?? null, $images[4] ?? null, $images[5] ?? null,
                $weight, $acceptsSinpe, $acceptsPaypal,
                $sinpePhone, $paypalEmail,
                $shippingAvailable, $pickupAvailable, $pickupLocation
            ]);

            $success = '¡Producto creado exitosamente!';
            header('refresh:2;url=emprendedoras-dashboard.php');

        } catch (Exception $e) {
            $error = 'Error al crear el producto: ' . $e->getMessage();
        }
    }
}

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}
$isLoggedIn = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto | Emprendedoras</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .form-header {
            margin-bottom: 30px;
        }
        .form-header h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }
        .form-header p {
            color: #666;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .image-upload-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .image-upload-box {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .image-upload-box:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .image-upload-box i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        .image-upload-box input[type="file"] {
            display: none;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .section-title {
            font-size: 1.3rem;
            color: #333;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h1><i class="fas fa-plus-circle"></i> Crear Nuevo Producto</h1>
                <p>Completa la información de tu producto para comenzar a vender</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="section-title">Información Básica</div>

                <div class="form-group">
                    <label>Nombre del Producto *</label>
                    <input type="text" name="name" required placeholder="Ej: Café Orgánico Tarrazú">
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description" placeholder="Describe tu producto, sus características y beneficios..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="category_id" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>SKU (opcional)</label>
                        <input type="text" name="sku" placeholder="Código del producto">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Precio (CRC) *</label>
                        <input type="number" name="price" required min="1" step="1" placeholder="5000">
                    </div>

                    <div class="form-group">
                        <label>Cantidad en Stock</label>
                        <input type="number" name="stock" min="0" value="0" placeholder="10">
                    </div>
                </div>

                <div class="form-group">
                    <label>Peso (kg) - para envíos</label>
                    <input type="number" name="weight_kg" min="0" step="0.01" placeholder="0.5">
                </div>

                <div class="section-title">Imágenes del Producto</div>
                <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">
                    Agrega hasta 5 imágenes de tu producto (la primera será la imagen principal)
                </p>

                <div class="image-upload-group">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="image-upload-box">
                            <i class="fas fa-camera"></i>
                            <div>Imagen <?php echo $i; ?></div>
                            <input type="file" name="image_<?php echo $i; ?>" accept="image/*" capture="camera">
                        </label>
                    <?php endfor; ?>
                </div>

                <div class="section-title">Métodos de Pago Aceptados</div>

                <div class="checkbox-group" style="margin-bottom: 15px;">
                    <input type="checkbox" id="accepts_sinpe" name="accepts_sinpe" value="1" <?php echo $paymentMethods && $paymentMethods['active_sinpe'] ? 'checked' : ''; ?>>
                    <label for="accepts_sinpe" style="margin: 0;">
                        <i class="fas fa-mobile-alt"></i> SINPE Móvil
                    </label>
                </div>

                <div class="form-group">
                    <label>Número de teléfono SINPE</label>
                    <input type="tel" name="sinpe_phone" placeholder="8888-8888" value="<?php echo htmlspecialchars($paymentMethods['sinpe_phone'] ?? ''); ?>">
                </div>

                <div class="checkbox-group" style="margin-bottom: 15px;">
                    <input type="checkbox" id="accepts_paypal" name="accepts_paypal" value="1" <?php echo $paymentMethods && $paymentMethods['active_paypal'] ? 'checked' : ''; ?>>
                    <label for="accepts_paypal" style="margin: 0;">
                        <i class="fab fa-paypal"></i> PayPal
                    </label>
                </div>

                <div class="form-group">
                    <label>Correo de PayPal</label>
                    <input type="email" name="paypal_email" placeholder="tu@email.com" value="<?php echo htmlspecialchars($paymentMethods['paypal_email'] ?? ''); ?>">
                </div>

                <div class="section-title">Opciones de Entrega</div>

                <div class="checkbox-group" style="margin-bottom: 15px;">
                    <input type="checkbox" id="shipping_available" name="shipping_available" value="1" checked>
                    <label for="shipping_available" style="margin: 0;">
                        <i class="fas fa-shipping-fast"></i> Ofrezco envío a domicilio
                    </label>
                </div>

                <div class="checkbox-group" style="margin-bottom: 15px;">
                    <input type="checkbox" id="pickup_available" name="pickup_available" value="1" checked>
                    <label for="pickup_available" style="margin: 0;">
                        <i class="fas fa-store"></i> Ofrezco retiro en persona
                    </label>
                </div>

                <div class="form-group">
                    <label>Ubicación para retiro (opcional)</label>
                    <input type="text" name="pickup_location" placeholder="Ej: San José, Barrio Escalante">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Crear Producto
                </button>

                <p style="text-align: center; margin-top: 20px;">
                    <a href="emprendedoras-dashboard.php" style="color: #667eea;">
                        <i class="fas fa-arrow-left"></i> Volver al dashboard
                    </a>
                </p>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
