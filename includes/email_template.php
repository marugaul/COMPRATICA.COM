<?php
/**
 * Compratica - Email template wrapper
 * Wraps any HTML content in a branded, responsive email layout.
 *
 * Usage:
 *   email_html($body_html)         → full email HTML string
 *   email_status_badge($status)    → colored pill span
 *   email_table_rows($items)       → styled product rows HTML
 */

function email_html(string $body): string {
    $year = date('Y');
    return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Compratica</title>
  <style type="text/css">
    @media only screen and (max-width:620px){
      .email-container{width:100%!important;padding:0 10px!important;box-sizing:border-box!important}
      .email-body{padding:24px 16px!important}
      .product-table th,.product-table td{padding:8px 6px!important;font-size:13px!important}
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
  <!-- Outer wrapper -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f2f5;padding:32px 0;">
    <tr>
      <td align="center">
        <!-- Card container -->
        <table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0"
               style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.10);">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#b71c1c 0%,#e53935 100%);padding:28px 32px;text-align:center;">
              <img src="https://compratica.com/logoCompratica.jpg"
                   alt="CompraTica"
                   width="auto"
                   style="display:block;margin:0 auto;max-height:52px;width:auto;height:52px;" />
              <br />
              <span style="font-size:12px;color:rgba(255,255,255,.8);letter-spacing:2px;text-transform:uppercase;">
                Marketplace Costarricense
              </span>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td class="email-body" style="padding:32px 40px;color:#333333;">
              ' . $body . '
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 40px;">
              <hr style="border:none;border-top:1px solid #eeeeee;margin:0;" />
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;text-align:center;font-size:12px;color:#999999;background:#fafafa;">
              <p style="margin:0 0 4px;">
                Si tienes dudas, contáctanos en
                <a href="mailto:info@compratica.com" style="color:#e53935;text-decoration:none;">info@compratica.com</a>
              </p>
              <p style="margin:0;">© ' . $year . ' CompraT­ica. Todos los derechos reservados.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

/**
 * Returns a colored status badge span.
 */
function email_status_badge(string $status): string {
    $colors = [
        'Pendiente'         => ['#fff3cd','#856404'],
        'Pendiente de pago' => ['#fff3cd','#856404'],
        'Pagado'            => ['#d4edda','#155724'],
        'En Revisión'       => ['#e2d9f3','#4a235a'],
        'Empacado'          => ['#d1ecf1','#0c5460'],
        'En camino'         => ['#cce5ff','#004085'],
        'Entregado'         => ['#d4edda','#155724'],
        'Cancelado'         => ['#f8d7da','#721c24'],
    ];
    $bg  = $colors[$status][0] ?? '#e2e3e5';
    $txt = $colors[$status][1] ?? '#383d41';
    return '<span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600;background:' . $bg . ';color:' . $txt . ';">'
         . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
         . '</span>';
}

/**
 * Returns styled product table rows HTML.
 * Each $item: ['name'=>'', 'qty'=>1, 'unit_price'=>0, 'line_total'=>0]
 */
function email_product_table(array $items, string $currency = 'CRC'): string {
    $rows = '';
    $i = 0;
    foreach ($items as $it) {
        $bg   = ($i % 2 === 0) ? '#ffffff' : '#f9f9f9';
        $name = htmlspecialchars((string)($it['name'] ?? $it['product_name'] ?? 'Producto'), ENT_QUOTES, 'UTF-8');
        $qty  = isset($it['qty']) ? (int)$it['qty'] : (float)($it['quantity'] ?? 1);
        $unit = (float)($it['unit_price'] ?? $it['price'] ?? 0);
        $line = (float)($it['line_total'] ?? ($qty * $unit));
        $rows .= '<tr style="background:' . $bg . ';">'
               . '<td style="padding:10px 12px;border-bottom:1px solid #eeeeee;font-size:14px;">' . $name . '</td>'
               . '<td style="padding:10px 12px;border-bottom:1px solid #eeeeee;text-align:center;font-size:14px;white-space:nowrap;">' . (is_int($qty) ? $qty : number_format((float)$qty, 0)) . '</td>'
               . '<td style="padding:10px 12px;border-bottom:1px solid #eeeeee;text-align:right;font-size:14px;white-space:nowrap;">' . number_format($unit, 2) . '</td>'
               . '<td style="padding:10px 12px;border-bottom:1px solid #eeeeee;text-align:right;font-size:14px;font-weight:600;white-space:nowrap;">' . number_format($line, 2) . '</td>'
               . '</tr>';
        $i++;
    }

    return '<table class="product-table" width="100%" cellpadding="0" cellspacing="0" border="0"
              style="border-collapse:collapse;border-radius:6px;overflow:hidden;border:1px solid #e0e0e0;margin:16px 0;">'
         . '<thead>'
         . '<tr style="background:#f5f5f5;">'
         . '<th style="padding:10px 12px;text-align:left;font-size:13px;color:#666;font-weight:600;border-bottom:2px solid #e0e0e0;">Producto</th>'
         . '<th style="padding:10px 12px;text-align:center;font-size:13px;color:#666;font-weight:600;border-bottom:2px solid #e0e0e0;">Cant.</th>'
         . '<th style="padding:10px 12px;text-align:right;font-size:13px;color:#666;font-weight:600;border-bottom:2px solid #e0e0e0;">Precio unit.</th>'
         . '<th style="padding:10px 12px;text-align:right;font-size:13px;color:#666;font-weight:600;border-bottom:2px solid #e0e0e0;">Total</th>'
         . '</tr>'
         . '</thead>'
         . '<tbody>' . $rows . '</tbody>'
         . '</table>';
}

/**
 * Returns a styled total summary block.
 */
function email_total_block(float $subtotal, float $tax, float $total, string $currency = 'CRC'): string {
    $html = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">';
    if ($tax > 0) {
        $html .= '<tr>'
               . '<td style="padding:4px 0;font-size:14px;color:#666;">Subtotal</td>'
               . '<td style="padding:4px 0;font-size:14px;color:#666;text-align:right;">' . number_format($subtotal, 2) . ' ' . $currency . '</td>'
               . '</tr>'
               . '<tr>'
               . '<td style="padding:4px 0;font-size:14px;color:#666;">Impuestos</td>'
               . '<td style="padding:4px 0;font-size:14px;color:#666;text-align:right;">' . number_format($tax, 2) . ' ' . $currency . '</td>'
               . '</tr>';
    }
    $html .= '<tr>'
           . '<td style="padding:12px 0 4px;font-size:16px;font-weight:700;color:#333;border-top:2px solid #e0e0e0;">Total</td>'
           . '<td style="padding:12px 0 4px;font-size:18px;font-weight:700;color:#b71c1c;text-align:right;border-top:2px solid #e0e0e0;">'
           . number_format($total, 2) . ' ' . $currency . '</td>'
           . '</tr>'
           . '</table>';
    return $html;
}
