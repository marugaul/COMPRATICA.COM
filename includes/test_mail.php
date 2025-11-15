<?php
require_once __DIR__ . '/includes/mailer.php';
send_email('tu_correo@gmail.com', 'Prueba SMTP Compratica', '<b>Todo bien</b> desde mail.compratica.com');
echo "Correo de prueba enviado.";
