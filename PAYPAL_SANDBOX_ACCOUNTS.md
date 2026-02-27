# Cuentas de Sandbox de PayPal

## Cuenta Empresarial (Business Account)
- **URL**: https://sandbox.paypal.com
- **Email**: sb-ttcma47147404@business.example.com
- **Password**: UcI1fL>$

**Uso**: Esta es la cuenta que recibirá los pagos de prueba.

## Cuenta Personal (Personal Account)
- **URL**: https://sandbox.paypal.com
- **Email**: sb-asmqf47327019@personal.example.com
- **Password**: ]GWh&2ax

**Uso**: Esta es la cuenta para hacer pagos de prueba.

---

## Próximos Pasos: Obtener Credenciales de API

Para que el sistema funcione, necesitas obtener el **Client ID** y **Secret** de PayPal:

### 1. Accede al Developer Dashboard
   - Ve a: https://developer.paypal.com/dashboard/
   - Inicia sesión con la cuenta empresarial de arriba

### 2. Crear una App
   - Ve a **Apps & Credentials**
   - Asegúrate de estar en modo **Sandbox**
   - Haz clic en **Create App**
   - Ponle un nombre (ej: "Compratica Sandbox")
   - Selecciona la cuenta empresarial asociada
   - Haz clic en **Create App**

### 3. Copiar Credenciales
   - Una vez creada la app, verás:
     - **Client ID** (público)
     - **Secret** (privado - haz clic en "Show" para verlo)

### 4. Actualizar config.php
   - Abre `/includes/config.php`
   - Actualiza las líneas 44-45 con tus credenciales:

   ```php
   define('PAYPAL_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
   define('PAYPAL_SECRET', 'TU_SECRET_AQUI');
   ```

---

## Cómo Probar

1. Asegúrate de que `PAYPAL_MODE` esté en `'sandbox'` en `/includes/config.php`
2. Ve a cualquier página de pago del sitio
3. Selecciona pagar con PayPal
4. Inicia sesión con la cuenta personal (sb-asmqf47327019@personal.example.com)
5. Completa el pago
6. El dinero aparecerá en la cuenta empresarial (sb-ttcma47147404@business.example.com)

---

## Seguridad

⚠️ **IMPORTANTE**: Estas son credenciales de SANDBOX (pruebas). No uses dinero real.

Para producción:
1. Cambia `PAYPAL_MODE` a `'live'`
2. Obtén credenciales de producción desde la pestaña **Live** en PayPal Developer
3. Usa tu cuenta real de PayPal business
