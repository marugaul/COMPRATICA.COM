-- ============================================
-- TEST RAPIDO DEL SISTEMA CRON
-- Si ves esta tabla en tu BD = CRON FUNCIONA
-- ============================================

CREATE TABLE IF NOT EXISTS cron_test_resultado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensaje VARCHAR(255) NOT NULL,
    timestamp_ejecucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cron_test_resultado (mensaje) VALUES
('CRON EJECUTADO EXITOSAMENTE!'),
('Fecha y hora de ejecución registrada en timestamp_ejecucion');

-- ============================================
-- Verifica en phpMyAdmin:
-- Base de datos: comprati_marketplace
-- Tabla: cron_test_resultado
-- ============================================
