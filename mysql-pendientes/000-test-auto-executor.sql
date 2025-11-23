-- ============================================
-- PRUEBA DEL SISTEMA AUTO-EXECUTOR
-- Esta tabla se creará automáticamente
-- para verificar que el sistema funciona
-- ============================================

CREATE TABLE IF NOT EXISTS test_auto_executor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensaje VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar registro de prueba
INSERT INTO test_auto_executor (mensaje) VALUES
('✅ Sistema MySQL Auto-Executor funcionando correctamente!');

-- ============================================
-- Si ves esta tabla en tu BD = ÉXITO
-- ============================================
