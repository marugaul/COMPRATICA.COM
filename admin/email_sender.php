<?php
/**
 * Email Sender con PHPMailer
 * Sistema de envío con prevención de spam, tracking y validación
 */

// Cargar PHPMailer desde la ubicación correcta
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';
require_once __DIR__ . '/../includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailSender {
    private $smtp_config;
    private $pdo;
    private $template_engine;

    public function __construct($smtp_config, $pdo = null) {
        $this->smtp_config = $smtp_config;
        $this->pdo = $pdo;
    }

    /**
     * Verificar si un email está en la blacklist
     */
    private function isBlacklisted($email) {
        if (!$this->pdo) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM email_blacklist WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            // Si la tabla no existe, continuar normalmente
            return false;
        }
    }

    /**
     * Enviar email individual
     */
    public function send($recipient, $template_html, $subject, $tracking_code = null, $attachment = null) {
        // VERIFICAR BLACKLIST ANTES DE ENVIAR
        if ($this->isBlacklisted($recipient['email'])) {
            return [
                'success' => false,
                'message' => 'Email en blacklist (desuscrito)',
                'smtp_response' => 'Blocked by blacklist'
            ];
        }

        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['smtp_username'];
            $mail->Password = $this->smtp_config['smtp_password'];
            $mail->Port = $this->smtp_config['smtp_port'];

            // Encriptación
            if ($this->smtp_config['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtp_config['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            // Configuración anti-spam
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPKeepAlive = true;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Headers anti-spam (SPF, DKIM simulation)
            $mail->XMailer = ' '; // Ocultar PHPMailer version
            $mail->addCustomHeader('X-Priority', '3'); // Normal priority
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');
            $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());

            // Remitente
            $mail->setFrom(
                $this->smtp_config['from_email'],
                $this->smtp_config['from_name']
            );

            // Destinatario
            $mail->addAddress($recipient['email'], $recipient['name'] ?? '');

            // Reply-To para mejor deliverability
            $mail->addReplyTo($this->smtp_config['from_email'], $this->smtp_config['from_name']);

            // Adjunto (si existe)
            if ($attachment && file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;

            // Personalizar template
            $html_body = $this->personalizeTemplate($template_html, $recipient, $tracking_code);

            // Agregar tracking pixel
            if ($tracking_code) {
                $tracking_pixel = $this->getTrackingPixelURL($tracking_code);
                $html_body = str_replace('{tracking_pixel}', $tracking_pixel, $html_body);
                $html_body = str_replace('{unsubscribe_link}', $this->getUnsubscribeURL($tracking_code), $html_body);

                // Agregar tracking a links
                $html_body = $this->addClickTracking($html_body, $tracking_code);
            }

            $mail->Body = $html_body;

            // Versión texto plano (para mejor deliverability)
            $mail->AltBody = $this->htmlToText($html_body);

            // Enviar
            $result = $mail->send();

            return [
                'success' => true,
                'message' => 'Email enviado correctamente',
                'smtp_response' => $mail->ErrorInfo
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $mail->ErrorInfo,
                'smtp_response' => $mail->ErrorInfo
            ];
        }
    }

    /**
     * Personalizar template con datos del destinatario
     */
    private function personalizeTemplate($template, $recipient, $tracking_code = null) {
        $html = $template;

        // Variables básicas
        $html = str_replace('{nombre}', $recipient['name'] ?? 'Estimado/a', $html);
        $html = str_replace('{email}', $recipient['email'], $html);
        $html = str_replace('{telefono}', $recipient['phone'] ?? '', $html);
        $html = str_replace('{empresa}', $recipient['custom_data']['business_name'] ?? '', $html);

        // Campaign ID
        if ($tracking_code) {
            $html = str_replace('{campaign_id}', substr($tracking_code, 0, 8), $html);
        }

        return $html;
    }

    /**
     * Agregar tracking de clicks a todos los links
     */
    private function addClickTracking($html, $tracking_code) {
        // Buscar todos los enlaces
        $pattern = '/<a\s+(?:[^>]*?\s+)?href="([^"]*)"([^>]*)>/i';

        $html = preg_replace_callback($pattern, function($matches) use ($tracking_code) {
            $original_url = $matches[1];

            // No trackear unsubscribe y tracking pixel
            if (strpos($original_url, 'unsubscribe') !== false ||
                strpos($original_url, 'tracking_pixel') !== false ||
                strpos($original_url, '{') !== false) {
                return $matches[0];
            }

            // Crear URL de tracking
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $track_url = $base_url . "/admin/email_track.php?action=click&code=" . $tracking_code . "&url=" . urlencode($original_url);

            return '<a href="' . $track_url . '"' . $matches[2] . '>';
        }, $html);

        return $html;
    }

    /**
     * Obtener URL del tracking pixel
     */
    private function getTrackingPixelURL($tracking_code) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . "/admin/email_track.php?action=open&code=" . $tracking_code;
    }

    /**
     * Obtener URL de desuscripción
     */
    private function getUnsubscribeURL($tracking_code) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . "/admin/email_track.php?action=unsubscribe&code=" . $tracking_code;
    }

    /**
     * Convertir HTML a texto plano
     */
    private function htmlToText($html) {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Probar conexión SMTP
     */
    public function testConnection() {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['smtp_username'];
            $mail->Password = $this->smtp_config['smtp_password'];
            $mail->Port = $this->smtp_config['smtp_port'];

            if ($this->smtp_config['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->smtp_config['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
            $mail->Debugoutput = function($str, $level) {
                // Silenciar debug output
            };

            // Intentar conectar
            $mail->smtpConnect();
            $mail->smtpClose();

            return [
                'success' => true,
                'message' => 'Conexión SMTP exitosa'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $mail->ErrorInfo
            ];
        }
    }

    /**
     * Enviar campaña completa con rate limiting
     */
    public function sendCampaign($campaign_id, $batch_size = 50, $delay_seconds = 2) {
        if (!$this->pdo) {
            throw new Exception('PDO connection required for batch sending');
        }

        // Obtener campaña
        $campaign = $this->pdo->query("SELECT * FROM email_campaigns WHERE id = $campaign_id")->fetch();

        if (!$campaign) {
            throw new Exception('Campaña no encontrada');
        }

        // Obtener template
        $template = $this->pdo->query("SELECT * FROM email_templates WHERE id = {$campaign['template_id']}")->fetch();

        if (!$template) {
            throw new Exception('Template no encontrado');
        }

        // Marcar campaña como enviando
        $this->pdo->exec("UPDATE email_campaigns SET status = 'sending', started_at = NOW() WHERE id = $campaign_id");

        // Obtener destinatarios pendientes
        $stmt = $this->pdo->query("
            SELECT * FROM email_recipients
            WHERE campaign_id = $campaign_id AND status = 'pending'
            ORDER BY id ASC
            LIMIT $batch_size
        ");

        $sent_count = 0;
        $failed_count = 0;

        while ($recipient = $stmt->fetch()) {
            // Enviar email
            $result = $this->send(
                [
                    'email' => $recipient['email'],
                    'name' => $recipient['name'],
                    'phone' => $recipient['phone'],
                    'custom_data' => $recipient['custom_data'] ? json_decode($recipient['custom_data'], true) : []
                ],
                $template['html_content'],
                $campaign['subject'],
                $recipient['tracking_code'],
                $campaign['attachment_path'] ? __DIR__ . '/../uploads/email_attachments/' . $campaign['attachment_path'] : null
            );

            // Actualizar estado
            if ($result['success']) {
                $this->pdo->prepare("
                    UPDATE email_recipients
                    SET status = 'sent', sent_at = NOW()
                    WHERE id = ?
                ")->execute([$recipient['id']]);

                $sent_count++;
            } else {
                $this->pdo->prepare("
                    UPDATE email_recipients
                    SET status = 'failed', error_message = ?
                    WHERE id = ?
                ")->execute([$result['message'], $recipient['id']]);

                $failed_count++;
            }

            // Log
            $this->pdo->prepare("
                INSERT INTO email_send_logs
                (recipient_id, campaign_id, email, subject, status, error_message, smtp_response)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $recipient['id'],
                $campaign_id,
                $recipient['email'],
                $campaign['subject'],
                $result['success'] ? 'success' : 'failed',
                $result['message'],
                $result['smtp_response']
            ]);

            // Rate limiting - delay para evitar ser marcado como spam
            if ($delay_seconds > 0) {
                sleep($delay_seconds);
            }
        }

        // Actualizar contadores de campaña
        $this->pdo->exec("
            UPDATE email_campaigns
            SET sent_count = sent_count + $sent_count,
                failed_count = failed_count + $failed_count
            WHERE id = $campaign_id
        ");

        // Verificar si terminó
        $pending = $this->pdo->query("
            SELECT COUNT(*) FROM email_recipients
            WHERE campaign_id = $campaign_id AND status = 'pending'
        ")->fetchColumn();

        if ($pending == 0) {
            $this->pdo->exec("
                UPDATE email_campaigns
                SET status = 'completed', completed_at = NOW()
                WHERE id = $campaign_id
            ");
        }

        return [
            'sent' => $sent_count,
            'failed' => $failed_count,
            'pending' => $pending
        ];
    }
}
?>
