<?php
// includes/mailer.php  (UTF-8 sin BOM, sin espacios antes de <?php)

if (!defined('FROM_EMAIL')) {
  // Ajusta a tu correo del dominio
  define('FROM_EMAIL', 'no-reply@compratica.com');
}
if (!defined('FROM_NAME')) {
  define('FROM_NAME', 'Compratica Marketplace');
}

/**
 * Envía correo HTML usando mail() del hosting.
 * Devuelve true/false y registra log en error_log y en includes/mail_test.log
 */
function send_email(string $to, string $subject, string $html): bool {
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from = sprintf('%s <%s>', FROM_NAME, FROM_EMAIL);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // En algunos hostings conviene forzar Return-Path con -f
    $ok = @mail($to, $encodedSubject, $html, $headers, "-f".FROM_EMAIL);

    // Log básico
    $log = "[send_email] To={$to} Subject={$subject} Result=" . ($ok ? "OK" : "FAIL") . " From={$from}";
    error_log($log);

    // Copia del mensaje para inspección
    @file_put_contents(__DIR__ . '/mail_test.log',
        date('Y-m-d H:i:s') . " {$log}\n{$html}\n\n",
        FILE_APPEND
    );

    return $ok;
}

/* === Alias de compatibilidad: send_mail -> send_email === */
if (!function_exists('send_mail')) {
    function send_mail(string $to, string $subject, string $html, ?string $from = null): bool {
        // Ignoramos $from opcional para mantener el mismo remitente centralizado
        return send_email($to, $subject, $html);
    }
}
