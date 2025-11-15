-- ============================================
-- TABLAS PARA SISTEMA DE ENVÍOS CON UBER
-- ============================================

-- Tabla para direcciones de pickup por ESPACIO/SALE
CREATE TABLE IF NOT EXISTS sale_pickup_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL,
    affiliate_id INTEGER NOT NULL,
    
    -- Dirección completa
    address TEXT NOT NULL,
    address_line2 TEXT, -- Apartamento, piso, etc.
    city TEXT NOT NULL,
    state TEXT NOT NULL,
    country TEXT DEFAULT 'Costa Rica',
    postal_code TEXT,
    
    -- Coordenadas (para Uber API)
    lat REAL,
    lng REAL,
    
    -- Información de contacto para pickup
    contact_name TEXT NOT NULL,
    contact_phone TEXT NOT NULL,
    pickup_instructions TEXT, -- ej: "Tocar timbre azul, preguntar por Juan"
    
    -- Control
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
);

-- Tabla para gestión de envíos Uber
CREATE TABLE IF NOT EXISTS uber_deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    order_number TEXT NOT NULL,
    sale_id INTEGER NOT NULL,
    affiliate_id INTEGER NOT NULL,
    pickup_location_id INTEGER,
    
    -- Direcciones de pickup
    pickup_address TEXT NOT NULL,
    pickup_address_line2 TEXT,
    pickup_city TEXT,
    pickup_state TEXT,
    pickup_postal_code TEXT,
    pickup_lat REAL,
    pickup_lng REAL,
    pickup_contact_name TEXT,
    pickup_contact_phone TEXT,
    pickup_instructions TEXT,
    
    -- Direcciones de entrega
    delivery_address TEXT NOT NULL,
    delivery_address_line2 TEXT,
    delivery_city TEXT,
    delivery_state TEXT,
    delivery_postal_code TEXT,
    delivery_lat REAL,
    delivery_lng REAL,
    delivery_contact_name TEXT,
    delivery_contact_phone TEXT,
    delivery_instructions TEXT,
    
    -- Datos de Uber API
    uber_quote_id TEXT,
    uber_delivery_id TEXT,
    uber_tracking_url TEXT,
    uber_courier_name TEXT,
    uber_courier_phone TEXT,
    uber_courier_img TEXT,
    uber_courier_vehicle TEXT,
    uber_courier_license_plate TEXT,
    
    -- Costos
    uber_base_cost REAL DEFAULT 0,
    uber_currency TEXT DEFAULT 'CRC',
    platform_commission REAL DEFAULT 0,
    total_shipping_cost REAL DEFAULT 0,
    commission_percentage REAL DEFAULT 15, -- Porcentaje de comisión de la plataforma
    
    -- Estados del delivery
    status TEXT DEFAULT 'pending', 
    -- pending: esperando confirmación de pago
    -- quoted: cotización obtenida de Uber
    -- confirmed: pago confirmado, listo para crear delivery
    -- scheduled: delivery creado en Uber, esperando conductor
    -- courier_assigned: conductor asignado
    -- picked_up: conductor recogió el paquete
    -- in_transit: en camino al destino
    -- delivered: entregado exitosamente
    -- cancelled: cancelado
    -- failed: falló
    
    -- Tiempos estimados y reales
    quoted_at TEXT,
    confirmed_at TEXT,
    scheduled_at TEXT,
    courier_assigned_at TEXT,
    estimated_pickup_time TEXT,
    actual_pickup_time TEXT,
    estimated_delivery_time TEXT,
    actual_delivery_time TEXT,
    
    -- Información adicional
    delivery_notes TEXT,
    cancellation_reason TEXT,
    failure_reason TEXT,
    
    -- Metadata de Uber (JSON)
    uber_quote_response TEXT, -- Respuesta completa de cotización
    uber_delivery_response TEXT, -- Respuesta completa de creación
    uber_webhook_data TEXT, -- Datos de webhooks
    
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (pickup_location_id) REFERENCES sale_pickup_locations(id) ON DELETE SET NULL
);

-- Tabla para configuración de Uber (credenciales por afiliado o global)
CREATE TABLE IF NOT EXISTS uber_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER, -- NULL = configuración global/admin
    
    -- Credenciales Uber Direct API
    client_id TEXT,
    client_secret TEXT,
    customer_id TEXT, -- ID de cliente en Uber
    
    -- Configuración
    is_sandbox INTEGER DEFAULT 1, -- 1 = sandbox, 0 = production
    commission_percentage REAL DEFAULT 15.0,
    
    -- Tokens (se auto-renuevan)
    access_token TEXT,
    token_expires_at TEXT,
    
    -- Control
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
);

-- Índices para mejor performance
CREATE INDEX IF NOT EXISTS idx_uber_deliveries_order ON uber_deliveries(order_id);
CREATE INDEX IF NOT EXISTS idx_uber_deliveries_sale ON uber_deliveries(sale_id);
CREATE INDEX IF NOT EXISTS idx_uber_deliveries_status ON uber_deliveries(status);
CREATE INDEX IF NOT EXISTS idx_uber_deliveries_affiliate ON uber_deliveries(affiliate_id);
CREATE INDEX IF NOT EXISTS idx_uber_deliveries_order_number ON uber_deliveries(order_number);
CREATE INDEX IF NOT EXISTS idx_pickup_locations_sale ON sale_pickup_locations(sale_id);
CREATE INDEX IF NOT EXISTS idx_pickup_locations_active ON sale_pickup_locations(is_active);
CREATE UNIQUE INDEX IF NOT EXISTS idx_pickup_locations_sale_unique ON sale_pickup_locations(sale_id) WHERE is_active = 1;

-- Insertar configuración global de Uber (placeholder - completar con tus credenciales)
INSERT OR IGNORE INTO uber_config (id, affiliate_id, is_sandbox, commission_percentage) 
VALUES (1, NULL, 1, 15.0);

-- ============================================
-- VISTAS ÚTILES
-- ============================================

-- Vista para ver deliveries con información completa
CREATE VIEW IF NOT EXISTS v_uber_deliveries_full AS
SELECT 
    ud.*,
    o.status as order_status,
    o.grand_total as order_total,
    o.buyer_name,
    o.buyer_email,
    o.buyer_phone,
    s.title as sale_title,
    a.name as affiliate_name,
    a.email as affiliate_email,
    spl.address as pickup_location_address
FROM uber_deliveries ud
LEFT JOIN orders o ON o.id = ud.order_id
LEFT JOIN sales s ON s.id = ud.sale_id
LEFT JOIN affiliates a ON a.id = ud.affiliate_id
LEFT JOIN sale_pickup_locations spl ON spl.id = ud.pickup_location_id;

-- Vista para estadísticas de envíos por afiliado
CREATE VIEW IF NOT EXISTS v_uber_stats_by_affiliate AS
SELECT 
    affiliate_id,
    COUNT(*) as total_deliveries,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status IN ('pending', 'quoted', 'confirmed') THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('scheduled', 'courier_assigned', 'picked_up', 'in_transit') THEN 1 ELSE 0 END) as active,
    ROUND(AVG(uber_base_cost), 2) as avg_uber_cost,
    ROUND(AVG(platform_commission), 2) as avg_commission,
    ROUND(SUM(platform_commission), 2) as total_commission_earned
FROM uber_deliveries
GROUP BY affiliate_id;