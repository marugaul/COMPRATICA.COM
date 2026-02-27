# Configuración de PayPal para Bienes Raíces, Empleos y Servicios

## Credenciales de PayPal

Para activar los pagos con PayPal en producción o sandbox, necesitas configurar tus credenciales en `/includes/config.php`.

### Agregar a config.php:

```php
// =========================
// PayPal Configuration
// =========================

// Para Sandbox (Desarrollo):
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' o 'live'
define('PAYPAL_CLIENT_ID', 'TU_CLIENT_ID_DE_SANDBOX');
define('PAYPAL_SECRET', 'TU_SECRET_DE_SANDBOX');

// Para Producción:
// define('PAYPAL_MODE', 'live');
// define('PAYPAL_CLIENT_ID', 'TU_CLIENT_ID_DE_PRODUCCION');
// define('PAYPAL_SECRET', 'TU_SECRET_DE_PRODUCCION');
```

## Cómo obtener las credenciales

1. Ve a [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/)
2. Inicia sesión con tu cuenta de PayPal
3. Ve a **Apps & Credentials**
4. Selecciona **Sandbox** o **Live** según el modo
5. Crea una nueva app o usa una existente
6. Copia:
   - **Client ID**
   - **Secret**

## Configuración del Webhook (Opcional pero recomendado)

Para recibir notificaciones automáticas de pagos completados:

1. En PayPal Developer Dashboard, ve a **Webhooks**
2. Crea un nuevo webhook con la URL:
   - `https://tudominio.com/api/paypal-webhook.php`
3. Selecciona estos eventos:
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.REFUNDED`
4. Copia el **Webhook ID**
5. Agrega a config.php:
   ```php
   define('PAYPAL_WEBHOOK_ID', 'TU_WEBHOOK_ID');
   ```

## URLs de API

El sistema usa automáticamente las URLs correctas según el modo:

- **Sandbox**: `https://api-m.sandbox.paypal.com`
- **Live**: `https://api-m.paypal.com`

## Flujo de Pago

### Con PayPal (Automático):
1. Usuario crea publicación y selecciona plan de pago
2. Usuario hace clic en "Pagar con PayPal"
3. PayPal SDK abre modal de pago
4. Usuario completa el pago
5. **Sistema activa automáticamente la publicación**
6. Usuario recibe confirmación

### Con SINPE Móvil (Manual):
1. Usuario crea publicación y selecciona plan de pago
2. Usuario ve instrucciones de transferencia SINPE
3. Usuario sube comprobante de pago
4. **Publicación queda pendiente de revisión**
5. Admin aprueba el pago
6. Publicación se activa

## Estructura de Base de Datos

### Tablas de Precios (Pricing):
- `listing_pricing` - Planes de bienes raíces
- `job_pricing` - Planes de empleos
- `service_pricing` - Planes de servicios

### Tablas de Publicaciones:
- `real_estate_listings` - Bienes raíces
- `job_listings` - Empleos
- `service_listings` - Servicios

### Tablas de Pagos:
- `payment_history` - Historial de todos los pagos
- `payment_receipts` - Comprobantes SINPE subidos

## Archivos Clave

### Frontend (Páginas de pago):
- `/real-estate/payment-selection.php`
- `/jobs/payment-selection.php`
- `/services/payment-selection.php`

### Backend (API):
- `/api/process-paypal-payment.php` - Procesa pagos PayPal
- `/api/upload-sinpe-receipt.php` - Sube comprobantes SINPE
- `/api/paypal-webhook.php` - Recibe webhooks de PayPal (opcional)

### Admin:
- `/admin/bienes_raices_config.php` - Configurar planes de bienes raíces
- `/admin/empleos_config.php` - Configurar planes de empleos
- `/admin/servicios_config.php` - Configurar planes de servicios

## Testing en Sandbox

1. Usa credenciales de sandbox en config.php
2. Crea cuentas de prueba en PayPal Sandbox:
   - Una cuenta de negocios (para recibir pagos)
   - Varias cuentas personales (para hacer pagos de prueba)
3. Usa tarjetas de prueba de PayPal para los pagos

## Seguridad

- ✅ El sistema verifica pagos server-side con la API de PayPal
- ✅ No se confía en datos del cliente sin verificar
- ✅ Tokens CSRF en todos los formularios
- ✅ Validación de permisos en todas las operaciones
- ✅ Archivos de comprobantes se guardan con nombres aleatorios

## Monedas y Tipos de Cambio

- PayPal cobra en **USD**
- SINPE cobra en **CRC (Colones)**
- Los planes tienen ambos precios configurables
- El usuario ve ambos montos en la página de pago
