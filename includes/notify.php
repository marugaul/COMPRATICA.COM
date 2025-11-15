
<?php
require_once __DIR__ . '/config.php';

function smtp_send($to, $subject, $html){
    $host = SMTP_HOST; $port = SMTP_PORT; $user = SMTP_USER; $pass = SMTP_PASS; $secure = strtolower(SMTP_SECURE);
    $from = NOTIFY_FROM_EMAIL ?: 'no-reply@localhost';

    $boundary = md5(uniqid(time()));
    $headers = "From: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n" . strip_tags($html) . "\r\n";
    $body .= "--{$boundary}\r\nContent-Type: text/html; charset=utf-8\r\n\r\n{$html}\r\n--{$boundary}--\r\n";

    $fp = @fsockopen(($secure==='ssl'?'ssl://':'').$host, $port, $errno, $errstr, 15);
    if(!$fp) return false;
    $read = function() use ($fp){ return fgets($fp, 515); };
    $write = function($cmd) use ($fp){ fputs($fp, $cmd."\r\n"); };

    $read(); // banner
    $write("EHLO ".$host); $read();
    if($secure==='tls'){ $write("STARTTLS"); $read(); stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $write("EHLO ".$host); $read(); }
    $write("AUTH LOGIN"); $read(); $write(base64_encode($user)); $read(); $write(base64_encode($pass)); $read();
    $write("MAIL FROM:<{$from}>"); $read(); $write("RCPT TO:<{$to}>"); $read(); $write("DATA"); $read();
    fputs($fp, "Subject: ".$subject."\r\n".$headers."\r\n".$body."\r\n.\r\n");
    $read(); $write("QUIT"); fclose($fp);
    return true;
}

function send_email($to, $subject, $html) {
    if (SMTP_ENABLED && SMTP_HOST && SMTP_PORT && SMTP_USER && SMTP_PASS) {
        return smtp_send($to, "=?UTF-8?B?".base64_encode($subject)."?=", $html);
    }
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . (NOTIFY_FROM_EMAIL ?: 'no-reply@localhost') . "\r\n";
    return @mail($to, "=?UTF-8?B?".base64_encode($subject)."?=", $html, $headers);
}
function send_sms($to, $text) { return false; }

function email_order_created($order, $product) {
    $sym = ($product['currency']==='USD')?'$':'₡';
    $total = number_format((float)$product['price'] * (int)$order['qty'], $sym==='$'?2:0, ',', '.');
    $html = "<h2>Pedido recibido #".$order['id']."</h2>";
    $html .= "<p><strong>Producto:</strong> ".htmlspecialchars($product['name'])."</p>";
    $html .= "<p><strong>Cantidad:</strong> ".$order['qty']." | <strong>Total:</strong> ".$sym.$total."</p>";
    $html .= "<p><strong>Cliente:</strong> ".htmlspecialchars($order['buyer_email'])." | ".htmlspecialchars($order['buyer_phone'])."</p>";
    $html .= "<p><strong>Residencia:</strong> ".htmlspecialchars($order['residency'])."</p>";
    if (!empty($order['note'])) $html .= "<p><strong>Nota:</strong> ".nl2br(htmlspecialchars($order['note']))."</p>";
    $html .= "<p style='font-size:12px;color:#666'>Gracias por tu compra en ".APP_NAME.".</p>";
    return $html;
}
function email_payment_confirmed($order, $product) {
    $sym = ($product['currency']==='USD')?'$':'₡';
    $total = number_format((float)$product['price'] * (int)$order['qty'], $sym==='$'?2:0, ',', '.');
    $html = "<h2>Pago confirmado #".$order['id']."</h2>";
    $html .= "<p><strong>Producto:</strong> ".htmlspecialchars($product['name'])."</p>";
    $html .= "<p><strong>Cantidad:</strong> ".$order['qty']." | <strong>Total:</strong> ".$sym.$total."</p>";
    $html .= "<p>Tu pago fue verificado. Pronto coordinaremos la entrega.</p>";
    $html .= "<p style='font-size:12px;color:#666'>".APP_NAME."</p>";
    return $html;
}
