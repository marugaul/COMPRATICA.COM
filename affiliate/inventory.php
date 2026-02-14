<?php
/**
 * Generador de Inventario de Productos para Afiliados
 * Permite generar PDF, Excel y enviar por correo
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
require_once __DIR__ . '/../includes/mailer.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];
$msg = '';
$error = '';

// Obtener espacios del afiliado
$sales_stmt = $pdo->prepare("SELECT id, title FROM sales WHERE affiliate_id = ? ORDER BY created_at DESC");
$sales_stmt->execute([$aff_id]);
$my_sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    $selected_sales = $_POST['sales'] ?? [];
    $recipient_email = trim($_POST['email'] ?? '');

    if (empty($selected_sales)) {
      throw new Exception('Por favor, seleccioná al menos un espacio.');
    }

    // Obtener productos de los espacios seleccionados
    $placeholders = implode(',', array_fill(0, count($selected_sales), '?'));
    $query = "
      SELECT p.*, s.title AS sale_title
      FROM products p
      JOIN sales s ON s.id = p.sale_id
      WHERE p.sale_id IN ($placeholders) AND p.affiliate_id = ?
      ORDER BY s.id, p.id
    ";
    $params = array_merge($selected_sales, [$aff_id]);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
      throw new Exception('No se encontraron productos en los espacios seleccionados.');
    }

    // Generar según el tipo de acción
    if ($action === 'pdf') {
      generatePDF($products, $_SESSION['aff_name'] ?? 'Afiliado');
      exit;
    } elseif ($action === 'excel') {
      generateExcel($products, $_SESSION['aff_name'] ?? 'Afiliado');
      exit;
    } elseif ($action === 'email') {
      if (empty($recipient_email)) {
        throw new Exception('Por favor, ingresá un correo electrónico válido.');
      }
      if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico no es válido.');
      }

      // Generar PDF y enviarlo por correo
      $pdf_file = generatePDFFile($products, $_SESSION['aff_name'] ?? 'Afiliado');
      sendInventoryEmail($recipient_email, $_SESSION['aff_name'] ?? 'Afiliado', $pdf_file);
      @unlink($pdf_file); // Eliminar archivo temporal

      $msg = "Inventario enviado exitosamente a {$recipient_email}";
    }

  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

/**
 * Genera PDF y lo envía al navegador para descarga
 */
function generatePDF($products, $affiliate_name) {
  $app_url = defined('APP_URL') ? APP_URL : 'https://compratica.com';

  // Verificar si TCPDF está disponible
  $tcpdf_path = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
  if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;
    generatePDFWithTCPDF($products, $affiliate_name, $app_url);
    return;
  }

  // Si no hay TCPDF, generar HTML optimizado para imprimir a PDF
  generatePrintableHTML($products, $affiliate_name, $app_url);
}

/**
 * Genera PDF usando TCPDF
 */
function generatePDFWithTCPDF($products, $affiliate_name, $app_url) {
  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

  // Configuración del documento
  $pdf->SetCreator('COMPRATICA.COM');
  $pdf->SetAuthor($affiliate_name);
  $pdf->SetTitle('Inventario de Productos');
  $pdf->SetSubject('Inventario');

  // Quitar header/footer por defecto
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);

  // Márgenes
  $pdf->SetMargins(15, 15, 15);
  $pdf->SetAutoPageBreak(true, 15);

  // Agregar página
  $pdf->AddPage();

  // Header con logo
  $pdf->SetFont('helvetica', 'B', 20);
  $pdf->SetTextColor(0, 43, 127); // Azul CR
  $pdf->Cell(0, 10, 'COMPRATICA.COM', 0, 1, 'C');

  $pdf->SetFont('helvetica', '', 12);
  $pdf->SetTextColor(100, 100, 100);
  $pdf->Cell(0, 6, 'Inventario de Productos', 0, 1, 'C');

  $pdf->Ln(5);

  // Línea separadora
  $pdf->SetDrawColor(0, 43, 127);
  $pdf->SetLineWidth(0.5);
  $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
  $pdf->Ln(5);

  // Información
  $pdf->SetFont('helvetica', '', 10);
  $pdf->SetTextColor(0, 0, 0);
  $pdf->Cell(40, 5, 'Afiliado:', 0, 0);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 5, $affiliate_name, 0, 1);

  $pdf->SetFont('helvetica', '', 10);
  $pdf->Cell(40, 5, 'Fecha:', 0, 0);
  $pdf->Cell(0, 5, date('d/m/Y H:i:s'), 0, 1);

  $pdf->Cell(40, 5, 'Total Productos:', 0, 0);
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->Cell(0, 5, count($products), 0, 1);

  $pdf->Ln(5);

  // Tabla de productos
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetFillColor(0, 43, 127);
  $pdf->SetTextColor(255, 255, 255);

  // Headers
  $pdf->Cell(45, 7, 'Espacio', 1, 0, 'L', true);
  $pdf->Cell(60, 7, 'Producto', 1, 0, 'L', true);
  $pdf->Cell(25, 7, 'Precio', 1, 0, 'C', true);
  $pdf->Cell(20, 7, 'Stock', 1, 0, 'C', true);
  $pdf->Cell(30, 7, 'Estado', 1, 1, 'C', true);

  // Datos
  $pdf->SetFont('helvetica', '', 8);
  $pdf->SetTextColor(0, 0, 0);
  $fill = false;

  foreach ($products as $p) {
    $price = ($p['currency'] === 'USD' ? '$' : '₡') . number_format((float)$p['price'], $p['currency'] === 'USD' ? 2 : 0);
    $stock = (int)$p['stock'];
    $status = $stock > 0 ? "Disponible ($stock)" : 'Sin stock';

    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);

    $pdf->Cell(45, 6, substr($p['sale_title'], 0, 20), 1, 0, 'L', true);
    $pdf->Cell(60, 6, substr($p['name'], 0, 35), 1, 0, 'L', true);
    $pdf->Cell(25, 6, $price, 1, 0, 'C', true);
    $pdf->Cell(20, 6, $stock, 1, 0, 'C', true);

    if ($stock > 0) {
      $pdf->SetTextColor(39, 174, 96); // Verde
    } else {
      $pdf->SetTextColor(231, 76, 60); // Rojo
    }
    $pdf->Cell(30, 6, $status, 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);

    $fill = !$fill;
  }

  // Footer
  $pdf->Ln(10);
  $pdf->SetFont('helvetica', 'I', 8);
  $pdf->SetTextColor(150, 150, 150);
  $pdf->Cell(0, 5, 'Generado por COMPRATICA.COM - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
  $pdf->Cell(0, 5, $app_url, 0, 1, 'C');

  // Output
  $pdf->Output('inventario_' . date('Ymd_His') . '.pdf', 'D');
}

/**
 * Genera HTML optimizado para imprimir a PDF (fallback)
 */
function generatePrintableHTML($products, $affiliate_name, $app_url) {
  header('Content-Type: text/html; charset=utf-8');
  header('Content-Disposition: inline; filename="inventario_' . date('Ymd_His') . '.html"');

  echo '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Inventario - ' . htmlspecialchars($affiliate_name) . '</title>
  <style>
    @media print {
      body { margin: 0; padding: 20mm; }
      .no-print { display: none; }
    }
    body {
      font-family: Arial, sans-serif;
      color: #333;
      max-width: 210mm;
      margin: 0 auto;
      padding: 20px;
    }
    .header {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 3px solid #002b7f;
      padding-bottom: 20px;
    }
    .header h1 {
      color: #002b7f;
      margin: 10px 0;
      font-size: 28px;
    }
    .header p {
      color: #666;
      margin: 5px 0;
      font-size: 14px;
    }
    .info {
      margin-bottom: 20px;
      font-size: 12px;
      background: #f5f5f5;
      padding: 15px;
      border-radius: 5px;
    }
    .info p { margin: 5px 0; }
    .info strong { color: #002b7f; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      font-size: 11px;
    }
    th {
      background: #002b7f;
      color: white;
      padding: 10px 8px;
      text-align: left;
      font-size: 11px;
      font-weight: bold;
    }
    td {
      padding: 8px;
      border-bottom: 1px solid #ddd;
      font-size: 10px;
    }
    tr:nth-child(even) { background: #f9f9f9; }
    tr:hover { background: #f5f5f5; }
    .status-available { color: #27ae60; font-weight: bold; }
    .status-sold { color: #e74c3c; font-weight: bold; }
    .footer {
      margin-top: 30px;
      padding-top: 15px;
      border-top: 1px solid #ddd;
      text-align: center;
      font-size: 10px;
      color: #666;
    }
    .print-btn {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #002b7f;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: bold;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .print-btn:hover {
      background: #001a4d;
    }
    @page { margin: 20mm; }
  </style>
</head>
<body>
  <button class="print-btn no-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>

  <div class="header">
    <h1>🇨🇷 COMPRATICA.COM</h1>
    <p>Inventario de Productos</p>
  </div>

  <div class="info">
    <p><strong>Afiliado:</strong> ' . htmlspecialchars($affiliate_name) . '</p>
    <p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>
    <p><strong>Total de productos:</strong> ' . count($products) . '</p>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 25%">Espacio</th>
        <th style="width: 30%">Producto</th>
        <th style="width: 15%">Precio</th>
        <th style="width: 10%">Stock</th>
        <th style="width: 20%">Estado</th>
      </tr>
    </thead>
    <tbody>';

  foreach ($products as $p) {
    $price = ($p['currency'] === 'USD' ? '$' : '₡') . number_format((float)$p['price'], $p['currency'] === 'USD' ? 2 : 0);
    $stock = (int)$p['stock'];
    $status = $stock > 0 ? '<span class="status-available">✓ Disponible (' . $stock . ' unidades)</span>' : '<span class="status-sold">✗ Sin stock</span>';
    $product_url = $app_url . '/store.php?sale_id=' . (int)$p['sale_id'] . '#product-' . (int)$p['id'];

    echo '<tr>
      <td><strong>' . htmlspecialchars($p['sale_title']) . '</strong></td>
      <td>' . htmlspecialchars($p['name']) . '</td>
      <td style="text-align: center"><strong>' . $price . '</strong></td>
      <td style="text-align: center">' . $stock . '</td>
      <td style="text-align: center">' . $status . '</td>
    </tr>';
  }

  echo '</tbody>
  </table>

  <div class="footer">
    <p><strong>COMPRATICA.COM</strong> - El marketplace 100% costarricense</p>
    <p>Generado el ' . date('d/m/Y \a \l\a\s H:i:s') . '</p>
    <p>' . htmlspecialchars($app_url) . '</p>
  </div>

  <script>
    // Auto-abrir diálogo de impresión
    window.addEventListener("load", function() {
      setTimeout(function() {
        window.print();
      }, 500);
    });
  </script>
</body>
</html>';
  exit;
}

/**
 * Genera PDF y lo guarda en archivo temporal
 */
function generatePDFFile($products, $affiliate_name) {
  $app_url = defined('APP_URL') ? APP_URL : 'https://compratica.com';

  // Generar HTML temporal
  $html = generateHTMLContent($products, $affiliate_name, $app_url);
  $temp_file = sys_get_temp_dir() . '/inventory_' . uniqid() . '.html';
  file_put_contents($temp_file, $html);

  return $temp_file;
}

/**
 * Genera contenido HTML para email
 */
function generateHTMLContent($products, $affiliate_name, $app_url) {
  $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Inventario</title></head><body>';
  $html .= '<h2>Inventario de Productos - ' . htmlspecialchars($affiliate_name) . '</h2>';
  $html .= '<p>Fecha: ' . date('d/m/Y H:i:s') . '</p>';
  $html .= '<p>Total de productos: ' . count($products) . '</p>';
  $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
  $html .= '<tr><th>Espacio</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Link</th></tr>';

  foreach ($products as $p) {
    $price = ($p['currency'] === 'USD' ? '$' : '₡') . number_format((float)$p['price'], $p['currency'] === 'USD' ? 2 : 0);
    $stock = (int)$p['stock'];
    $status = $stock > 0 ? "Disponible ($stock)" : 'Sin stock';
    $product_url = $app_url . '/store.php?sale_id=' . (int)$p['sale_id'] . '#product-' . (int)$p['id'];

    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($p['sale_title']) . '</td>';
    $html .= '<td>' . htmlspecialchars($p['name']) . '</td>';
    $html .= '<td>' . $price . '</td>';
    $html .= '<td>' . $stock . '</td>';
    $html .= '<td>' . $status . '</td>';
    $html .= '<td><a href="' . $product_url . '">Ver</a></td>';
    $html .= '</tr>';
  }

  $html .= '</table></body></html>';
  return $html;
}

/**
 * Genera Excel (CSV mejorado)
 */
function generateExcel($products, $affiliate_name) {
  $app_url = defined('APP_URL') ? APP_URL : 'https://compratica.com';

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="inventario_' . date('Ymd_His') . '.csv"');

  // BOM para Excel UTF-8
  echo "\xEF\xBB\xBF";

  $output = fopen('php://output', 'w');

  // Información del encabezado
  fputcsv($output, ['INVENTARIO DE PRODUCTOS - COMPRATICA.COM']);
  fputcsv($output, ['Afiliado:', $affiliate_name]);
  fputcsv($output, ['Fecha:', date('d/m/Y H:i:s')]);
  fputcsv($output, ['Total Productos:', count($products)]);
  fputcsv($output, []); // Línea vacía

  // Encabezados de tabla
  fputcsv($output, ['Espacio', 'Producto', 'Descripción', 'Precio', 'Moneda', 'Stock', 'Estado', 'Link Directo']);

  foreach ($products as $p) {
    $price = number_format((float)$p['price'], $p['currency'] === 'USD' ? 2 : 0, '.', '');
    $stock = (int)$p['stock'];
    $status = $stock > 0 ? "Disponible ({$stock} unidades)" : 'Sin stock';
    $product_url = $app_url . '/store.php?sale_id=' . (int)$p['sale_id'] . '#product-' . (int)$p['id'];

    fputcsv($output, [
      $p['sale_title'],
      $p['name'],
      $p['description'] ?? '',
      $price,
      $p['currency'],
      $stock,
      $status,
      $product_url
    ]);
  }

  fclose($output);
  exit;
}

/**
 * Envía inventario por correo
 */
function sendInventoryEmail($to, $affiliate_name, $pdf_file) {
  $subject = "Inventario de Productos - " . htmlspecialchars($affiliate_name);

  $body = "
    <h2>Inventario de Productos</h2>
    <p>Hola,</p>
    <p>Adjunto encontrarás el inventario completo de productos de <strong>" . htmlspecialchars($affiliate_name) . "</strong>.</p>
    <p>Este documento incluye:</p>
    <ul>
      <li>Lista completa de productos</li>
      <li>Precios y disponibilidad</li>
      <li>Links directos para comprar</li>
    </ul>
    <p>Generado el: " . date('d/m/Y H:i:s') . "</p>
    <hr>
    <p style='font-size: 12px; color: #666;'>COMPRATICA.COM - Tu marketplace de confianza</p>
  ";

  // Enviar correo con adjunto
  return sendEmailWithAttachment($to, $subject, $body, $pdf_file);
}

/**
 * Función helper para enviar email con adjunto
 */
function sendEmailWithAttachment($to, $subject, $body, $attachment_path) {
  // Usar PHPMailer si está disponible
  if (function_exists('sendEmail')) {
    // Implementar con la función existente
    return sendEmail($to, $subject, $body);
  }

  // Fallback simple con mail()
  $boundary = md5(time());
  $headers = "From: " . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@compratica.com') . "\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

  $message = "--{$boundary}\r\n";
  $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
  $message .= $body . "\r\n";

  if (file_exists($attachment_path)) {
    $file_content = file_get_contents($attachment_path);
    $file_content = chunk_split(base64_encode($file_content));
    $file_name = basename($attachment_path);

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: application/octet-stream; name=\"{$file_name}\"\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$file_name}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= $file_content . "\r\n";
  }

  $message .= "--{$boundary}--";

  return mail($to, $subject, $message, $headers);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generador de Inventario - <?= htmlspecialchars(APP_NAME ?? 'Compratica') ?></title>
  <link rel="stylesheet" href="../assets/style.css?v=24">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #2c3e50;
      --accent: #3498db;
      --success: #27ae60;
      --danger: #e74c3c;
      --warning: #f39c12;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-800: #1f2937;
    }

    body {
      background: var(--gray-50);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .container {
      max-width: 900px;
      margin: 2rem auto;
      padding: 2rem;
    }

    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid var(--gray-200);
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.5rem;
      margin: 0 0 1.5rem 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .checkbox-list {
      display: grid;
      gap: 0.75rem;
      margin: 1.5rem 0;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem;
      background: var(--gray-50);
      border-radius: 8px;
      border: 2px solid var(--gray-200);
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .checkbox-item:hover {
      border-color: var(--accent);
      background: white;
    }

    .checkbox-item input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .checkbox-item label {
      flex: 1;
      cursor: pointer;
      font-weight: 500;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--gray-800);
    }

    .form-group input[type="email"] {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .form-group input[type="email"]:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    .action-buttons {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-top: 2rem;
    }

    .btn {
      padding: 0.875rem 1.5rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.9375rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.625rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-pdf {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    }

    .btn-pdf:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
    }

    .btn-excel {
      background: linear-gradient(135deg, #27ae60, #229954);
      color: white;
      box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
    }

    .btn-excel:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
    }

    .btn-email {
      background: linear-gradient(135deg, var(--accent), #2980b9);
      color: white;
      box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    }

    .btn-email:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    .alert {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert-success {
      background: rgba(39, 174, 96, 0.1);
      border: 1px solid rgba(39, 174, 96, 0.3);
      border-left: 4px solid var(--success);
      color: #155724;
    }

    .alert-error {
      background: rgba(231, 76, 60, 0.1);
      border: 1px solid rgba(231, 76, 60, 0.3);
      border-left: 4px solid var(--danger);
      color: #721c24;
    }

    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: #666;
    }

    .empty-state i {
      font-size: 4rem;
      color: var(--gray-200);
      margin-bottom: 1rem;
    }

    .info-box {
      background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
      border-left: 4px solid var(--accent);
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .info-box p {
      margin: 0.5rem 0;
      color: #666;
      font-size: 0.9rem;
    }

    .info-box strong {
      color: var(--primary);
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    Panel de Afiliado
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="products.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);">
      <i class="fas fa-box"></i>
      <span>Productos</span>
    </a>
  </nav>
</header>

<div class="container">
  <h2 style="margin-bottom: 2rem; color: #2c3e50;">
    <i class="fas fa-file-invoice"></i> Generador de Inventario
  </h2>

  <?php if ($msg): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <span><?= htmlspecialchars($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-triangle"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($my_sales)): ?>
    <div class="card">
      <div class="empty-state">
        <i class="fas fa-store-alt-slash"></i>
        <h3>No tenés espacios creados</h3>
        <p>Primero debes crear al menos un espacio de venta para generar inventarios.</p>
        <a href="sales.php" class="btn btn-excel" style="margin-top: 1rem;">
          <i class="fas fa-plus-circle"></i>
          Crear Espacio
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="info-box">
      <p><i class="fas fa-info-circle"></i> <strong>Cómo funciona:</strong></p>
      <p>• <strong>PDF:</strong> Se abrirá una ventana lista para imprimir o guardar como PDF</p>
      <p>• <strong>Excel:</strong> Se descargará un archivo CSV que podés abrir en Excel o Google Sheets</p>
      <p>• <strong>Email:</strong> Se enviará el inventario por correo electrónico con el archivo adjunto</p>
    </div>

    <form method="post" id="inventoryForm">
      <div class="card">
        <h3><i class="fas fa-store-alt"></i> Seleccioná los Espacios</h3>
        <p style="color: #666; margin-bottom: 1rem;">Marcá los espacios que querés incluir en el inventario:</p>

        <div class="checkbox-list">
          <?php foreach ($my_sales as $sale): ?>
            <div class="checkbox-item">
              <input type="checkbox" name="sales[]" value="<?= (int)$sale['id'] ?>" id="sale_<?= (int)$sale['id'] ?>">
              <label for="sale_<?= (int)$sale['id'] ?>">
                <strong><?= htmlspecialchars($sale['title']) ?></strong>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-cog"></i> Opciones de Generación</h3>

        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i> Correo electrónico (opcional - solo para enviar por email)
          </label>
          <input type="email" name="email" id="email" placeholder="ejemplo@correo.com">
          <small style="color: #666; display: block; margin-top: 0.5rem;">
            Solo completá este campo si querés enviar el inventario por correo. Si solo querés descargarlo, dejalo vacío.
          </small>
        </div>

        <div class="action-buttons">
          <button type="submit" name="action" value="pdf" class="btn btn-pdf">
            <i class="fas fa-file-pdf"></i>
            Generar PDF
          </button>
          <button type="submit" name="action" value="excel" class="btn btn-excel">
            <i class="fas fa-file-excel"></i>
            Descargar CSV (Excel)
          </button>
          <button type="submit" name="action" value="email" class="btn btn-email">
            <i class="fas fa-paper-plane"></i>
            Enviar por Correo
          </button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
// Validar que al menos un checkbox esté marcado
document.getElementById('inventoryForm')?.addEventListener('submit', function(e) {
  const checkboxes = document.querySelectorAll('input[name="sales[]"]:checked');
  if (checkboxes.length === 0) {
    e.preventDefault();
    alert('Por favor, seleccioná al menos un espacio.');
    return false;
  }

  // Si la acción es enviar por correo, validar que haya email
  if (e.submitter.value === 'email') {
    const email = document.getElementById('email').value.trim();
    if (!email) {
      e.preventDefault();
      alert('Por favor, ingresá un correo electrónico para enviar el inventario.');
      return false;
    }
  }

  return true;
});
</script>
</body>
</html>
