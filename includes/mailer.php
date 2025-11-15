<?php
/**
 * includes/mailer.php
 * Envío de correos con PHPMailer (sin Composer) usando SMTP autenticado (SSL/TLS)
 * Autor: Marco Ugarte - Compratica.com
 */

// ============================================
// INCLUSIÓN MANUAL DE PHPMailer
// ============================================
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================
// CONFIGURACIÓN GLOBAL
// ============================================
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'info@compratica.com');
if (!defined('FROM_NAME'))  define('FROM_NAME',  'Compratica Marketplace');

/**
 * Envía correo HTML mediante SMTP autenticado
 *
 * @param string $to Destinatario
 * @param string $subject Asunto del correo
 * @param string $html Contenido HTML
 * @param string|null $replyTo Correo de respuesta (afiliado, opcional)
 * @param string|null $replyName Nombre de quien responde (opcional)
 * @return bool true si se envió correctamente, false si falló
 */
function send_email(string $to, string $subject, string $html, ?string $replyTo = null, ?string $replyName = null): bool {
    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'mail.compratica.com';    // Servidor SMTP (según cPanel)
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@compratica.com';    // Usuario SMTP
        $mail->Password   = 'Marden7i/';     // ⚠️ Reemplaza con tu contraseña real
        $mail->Port       = 465;                      // Puerto SSL/TLS (465 recomendado)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Cifrado SSL
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // --- REMITENTE ---
        $mail->setFrom(FROM_EMAIL, FROM_NAME);

        // --- REPLY-TO (correo del afiliado o vendedor) ---
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo, $replyName ?: 'Vendedor Compratica');
        } else {
            $mail->addReplyTo(FROM_EMAIL, FROM_NAME);
        }

        // --- DESTINATARIO ---
        $mail->addAddress($to);

        // --- CONTENIDO ---
        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        // --- ENVÍO ---
        $mail->send();

        // --- LOG ---
        $log = sprintf("[SMTP OK] To=%s | Subject=%s | From=%s", $to, $subject, FROM_EMAIL);
        error_log($log);
        @file_put_contents(__DIR__ . '/mail_test.log', date('Y-m-d H:i:s') . " $log\n", FILE_APPEND);

        return true;

    } catch (Exception $e) {
        $error = sprintf("[SMTP FAIL] To=%s | Error=%s", $to, $mail->ErrorInfo);
        error_log($error);
        @file_put_contents(__DIR__ . '/mail_test.log', date('Y-m-d H:i:s') . " $error\n", FILE_APPEND);
        return false;
    }
}

/**
 * Alias de compatibilidad
 */
if (!function_exists('send_mail')) {
    function send_mail(string $to, string $subject, string $html, ?string $from = null): bool {
        return send_email($to, $subject, $html);
    }
}
