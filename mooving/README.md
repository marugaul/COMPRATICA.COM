# Integración de Mooving para Emprendedoras

Esta implementación agrega una nueva opción de envío **Mooving** para las emprendedoras de CompraTica, completamente separada e independiente de Uber Direct.

## 🚀 Características

- ✅ **Nueva opción de envío** separada de las existentes (Pickup, Gratis, Express, Uber)
- ✅ **Cotización automática** basada en la dirección del cliente
- ✅ **Integración completa** con el carrito y checkout de emprendedoras
- ✅ **Geolocalización** para facilitar el ingreso de direcciones
- ✅ **Interfaz moderna** con iconos y colores distintivos (morado)
- ✅ **Compatible** con la estructura existente del sistema

## 📁 Archivos Creados/Modificados

### Archivos Nuevos
- `mooving/MovingAPI.php` - Clase principal para integración con API de Mooving
- `mooving/ajax_mooving_quote.php` - Endpoint para obtener cotizaciones
- `mooving/README.md` - Esta documentación
- `migrations/add_mooving_option.sql` - Script SQL para agregar campo
- `setup_mooving_field.php` - Script PHP para migración de BD

### Archivos Modificados
- `includes/shipping_emprendedoras.php` - Agregado soporte para `enable_mooving`
- `api/emp-shipping.php` - Agregado método 'mooving' a las opciones permitidas
- `emprendedoras-carrito.php` - UI para seleccionar Mooving con cotización en tiempo real
- `emprendedoras-checkout.php` - Visualización de envío Mooving en checkout

## 🗄️ Estructura de Base de Datos

### Campo Agregado a `entrepreneur_shipping`
```sql
enable_mooving INTEGER NOT NULL DEFAULT 0
```

### Nueva Tabla `mooving_config`
```sql
CREATE TABLE mooving_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entrepreneur_id INTEGER DEFAULT NULL,
    api_key TEXT NOT NULL,
    api_secret TEXT NOT NULL,
    merchant_id TEXT NOT NULL,
    is_sandbox INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    commission_percentage REAL DEFAULT 15.0,
    created_at TEXT DEFAULT (datetime('now','localtime')),
    updated_at TEXT DEFAULT (datetime('now','localtime')),
    UNIQUE(entrepreneur_id)
);
```

## 🔧 Instalación

### 1. Ejecutar Migración
```bash
php setup_mooving_field.php
```

Esto agregará automáticamente el campo `enable_mooving` a la tabla `entrepreneur_shipping`.

### 2. Configurar API de Mooving (Opcional)

Si ya tienes credenciales de Mooving, puedes configurarlas:

```php
require_once 'mooving/MovingAPI.php';
$pdo = db();
$api = new MovingAPI($pdo);
$api->initConfigTable();

// Insertar configuración
$pdo->prepare("
    INSERT INTO mooving_config
    (entrepreneur_id, api_key, api_secret, merchant_id, is_sandbox, is_active)
    VALUES (NULL, ?, ?, ?, 1, 1)
")->execute([
    'tu_api_key',
    'tu_api_secret',
    'tu_merchant_id'
]);
```

**Nota:** Si no configuras las credenciales, el sistema devolverá un precio estimado fijo de ₡2,500.

## 💻 Uso para Emprendedoras

### Activar Mooving

1. La emprendedora va a su dashboard
2. En configuración de envíos, activa la opción **"Envío Mooving"**
3. El sistema automáticamente mostrará esta opción a sus clientes

### Para los Clientes

1. En el carrito, ven la opción **"Envío Mooving ⭐ Nuevo"** con icono de moto 🏍️
2. Al seleccionarla, pueden:
   - Usar su ubicación actual (botón de geolocalización)
   - Escribir manualmente su dirección
3. El sistema calcula automáticamente el costo en tiempo real
4. El precio se agrega al total del pedido

## 🎨 Diseño Visual

- **Color principal:** Morado (`#8b5cf6`, `#6b21a8`)
- **Icono:** `fa-motorcycle` (moto)
- **Badge:** "⭐ Nuevo" para destacar la nueva opción

## 🔌 API de Mooving

### Clase `MovingAPI`

```php
$mooving = new MovingAPI($pdo, $entrepreneur_id);

// Verificar si está configurado
if ($mooving->isConfigured()) {
    // Obtener cotización
    $quote = $mooving->getQuote(
        ['lat' => 9.9281, 'lng' => -84.0907, 'address' => 'Origen'],
        ['lat' => 9.9350, 'lng' => -84.0850, 'address' => 'Destino'],
        ['weight' => 1.0, 'value' => 5000]
    );

    // $quote = ['ok' => true, 'price' => 2500, 'currency' => 'CRC', ...]
}
```

### Métodos Disponibles

- `getQuote($origin, $destination, $package)` - Obtener cotización
- `createDelivery($quoteData, $orderDetails)` - Crear envío
- `getDeliveryStatus($deliveryId)` - Consultar estado
- `cancelDelivery($deliveryId, $reason)` - Cancelar envío
- `isConfigured()` - Verificar si está configurado

## 🔐 Seguridad

- Las solicitudes a la API de Mooving incluyen firma HMAC-SHA256
- Timestamp para prevenir replay attacks
- Validación de datos en servidor
- Sanitización de inputs del usuario

## 📊 Flujo de Datos

```
Cliente → [Ingresa dirección]
    ↓
JavaScript (emprendedoras-carrito.php)
    ↓
ajax_mooving_quote.php
    ↓
MovingAPI::getQuote()
    ↓
[API de Mooving] (o precio estimado si no está configurado)
    ↓
Respuesta con precio
    ↓
Actualiza UI y sesión
    ↓
Checkout muestra método Mooving
```

## ⚙️ Configuración Avanzada

### Comisión Personalizada

Puedes configurar una comisión por vendedora:

```sql
UPDATE mooving_config
SET commission_percentage = 20.0
WHERE entrepreneur_id = 123;
```

### Modo Sandbox vs Producción

```sql
UPDATE mooving_config
SET is_sandbox = 0  -- 0 = producción, 1 = sandbox
WHERE id = 1;
```

## 🐛 Troubleshooting

### El campo enable_mooving no existe
```bash
php setup_mooving_field.php
```

### Las cotizaciones siempre devuelven ₡2,500
Esto es normal si no has configurado las credenciales de la API. Es un precio estimado por defecto.

### Error "Mooving no está configurado"
Inserta las credenciales en la tabla `mooving_config` o el sistema usará precios estimados.

## 📝 TODO / Mejoras Futuras

- [ ] Panel de administración para configurar credenciales
- [ ] Webhook para actualizar estado de entregas en tiempo real
- [ ] Historial de envíos Mooving por emprendedora
- [ ] Reportes y analytics de uso
- [ ] Integración con sistema de notificaciones

## 🤝 Soporte

Para soporte técnico o preguntas sobre esta integración, contacta al equipo de desarrollo.

---

**Versión:** 1.0.0
**Fecha:** Marzo 2026
**Autor:** Claude Code
