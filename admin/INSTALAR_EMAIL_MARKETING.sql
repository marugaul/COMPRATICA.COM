-- ============================================================================
-- INSTALADOR COMPLETO - SISTEMA DE EMAIL MARKETING
-- Ejecutar desde: SQL Tools en https://compratica.com/tools/sql_exec.php
-- ============================================================================

USE comprati_marketplace;

-- ============================================================================
-- PASO 1: CREAR TABLAS
-- ============================================================================

-- Tabla de configuraciones SMTP
CREATE TABLE IF NOT EXISTS email_smtp_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Nombre del perfil (Mixtico, CRV-SOFT, Compratica)',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de plantillas de email
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nombre de la plantilla',
    company VARCHAR(50) NOT NULL,
    subject_default VARCHAR(255) NOT NULL,
    html_content MEDIUMTEXT NOT NULL,
    variables TEXT COMMENT 'JSON con variables disponibles',
    thumbnail VARCHAR(255) COMMENT 'Preview de la plantilla',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company (company),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de campañas de email
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nombre de la campaña',
    smtp_config_id INT NOT NULL,
    template_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    source_type ENUM('excel', 'database', 'manual') NOT NULL,
    filter_categories TEXT COMMENT 'JSON con categorías seleccionadas',
    attachment_path VARCHAR(255) COMMENT 'Ruta del archivo adjunto',
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    opened_count INT DEFAULT 0 COMMENT 'Emails abiertos (tracking)',
    clicked_count INT DEFAULT 0 COMMENT 'Links clickeados (tracking)',
    status ENUM('draft', 'scheduled', 'sending', 'completed', 'failed') DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_by INT COMMENT 'ID del usuario admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (smtp_config_id) REFERENCES email_smtp_configs(id) ON DELETE RESTRICT,
    FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de destinatarios (queue)
CREATE TABLE IF NOT EXISTS email_recipients (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    phone VARCHAR(50),
    custom_data TEXT COMMENT 'JSON con datos adicionales',
    status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    error_message TEXT,
    tracking_code VARCHAR(64) UNIQUE COMMENT 'Código único para tracking',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_tracking (tracking_code),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de logs de envío
CREATE TABLE IF NOT EXISTS email_send_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    recipient_id BIGINT NOT NULL,
    campaign_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    smtp_response TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign (campaign_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PASO 2: INSERTAR CONFIGURACIONES SMTP (usuario completará contraseñas)
-- ============================================================================

INSERT IGNORE INTO email_smtp_configs (name, from_email, from_name, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, is_active) VALUES
('Mixtico', 'info@mixtico.net', 'Mixtico Shuttle Service', 'smtp.gmail.com', 587, 'info@mixtico.net', '', 'tls', 1),
('CRV-SOFT', 'contacto@crv-soft.com', 'CRV-SOFT', 'smtp.gmail.com', 587, 'contacto@crv-soft.com', '', 'tls', 1),
('Compratica', 'info@compratica.com', 'CompraTica Costa Rica', 'smtp.gmail.com', 587, 'info@compratica.com', '', 'tls', 1);

-- ============================================================================
-- INSTALACIÓN COMPLETADA
-- ============================================================================

SELECT '✅ TABLAS CREADAS EXITOSAMENTE' AS Estado;
SELECT '✅ CONFIGURACIONES SMTP CREADAS (completar contraseñas en admin)' AS Estado;
SELECT '⚠️ IMPORTANTE: Subir las plantillas HTML manualmente' AS Nota;
SELECT 'Ir a: admin/email_marketing.php?page=templates' AS ProximoPaso;
