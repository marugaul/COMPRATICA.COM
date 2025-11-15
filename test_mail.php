<?php
require __DIR__.'/includes/mailer.php';
$ok = send_email('marugaul@gmail.com', 'Prueba desde Compratica', '<p>Hola, prueba OK ✅</p>');
echo $ok ? "Enviado. Revisa tu correo (y SPAM)." : "No se pudo enviar, revisa error_log.";
