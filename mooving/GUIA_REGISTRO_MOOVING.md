# 📘 Guía de Registro Corporativo en Mooving

Esta guía te ayudará a crear una cuenta empresarial en Mooving para CompraTica y obtener las credenciales necesarias.

## 🎯 Objetivo

Obtener credenciales de API para integrar Mooving en CompraTica y ganar comisión por cada envío que las emprendedoras usen.

## 📋 Requisitos Previos

- ✅ Cédula jurídica de CompraTica
- ✅ Documentos legales de la empresa
- ✅ Cuenta bancaria empresarial
- ✅ Correo electrónico corporativo

---

## 🚀 Paso 1: Registro en Mooving

### Opción A: Mooving Costa Rica (Si existe)
1. Visita: https://www.mooving.cr o https://www.mooving.com
2. Busca la sección "Para Empresas" o "Business"
3. Haz clic en "Registrarse" o "Sign Up"

### Opción B: Contacto Directo
Si Mooving no tiene presencia en Costa Rica:
1. Contacta servicios de entrega similares en CR:
   - **Hugo App** (https://hugoapp.com)
   - **PedidosYa Envíos**
   - **Uber Direct** (ya implementado)
   - **Delivery Hero** (PedidosYa corporativo)

2. O integra con proveedores regionales:
   - **99Minutos** (México/Latam)
   - **Shippify** (Latam)
   - **Pickit** (Guatemala/Latam)

---

## 📝 Paso 2: Completar Formulario de Registro

Información que te pedirán:

### Datos de la Empresa
- **Nombre Legal:** CompraTica S.A. (o el que corresponda)
- **Cédula Jurídica:** [Tu número]
- **Dirección Fiscal:** [Tu dirección]
- **Teléfono:** [Teléfono de contacto]
- **Email Corporativo:** admin@compratica.com

### Datos del Contacto
- **Nombre:** [Administrador]
- **Cargo:** Gerente General / CEO
- **Email:** [Email personal]
- **Teléfono:** [Teléfono personal]

### Información del Negocio
- **Tipo de Negocio:** Marketplace / Plataforma E-commerce
- **Volumen Estimado:** [Ej: 50-200 envíos/mes]
- **Tipo de Productos:** Variados (artesanías, alimentos, ropa, etc.)
- **Zonas de Operación:** Todo Costa Rica

---

## 🔑 Paso 3: Solicitar Acceso al API

### Una vez aprobada tu cuenta:

1. **Accede al Developer Portal**
   - Inicia sesión en tu cuenta Mooving
   - Busca sección "API" o "Developers" o "Integraciones"

2. **Solicita Credenciales**
   - Haz clic en "Crear nueva aplicación" o "New API Key"
   - Nombre de la aplicación: `CompraTica - Marketplace`
   - Descripción: `Integración de envíos para emprendedoras`

3. **Obtendrás 3 credenciales:**

   ```
   ✅ API Key (Public)
      Ejemplo: mooving_live_pk_1234567890abcdef

   ✅ API Secret (Private)
      Ejemplo: mooving_live_sk_abcdef1234567890
      ⚠️ ¡NUNCA LA COMPARTAS! Guárdala en lugar seguro

   ✅ Merchant ID
      Ejemplo: MERCH_COMPRATICA_CR_001
   ```

---

## 🧪 Paso 4: Configurar Ambiente Sandbox

**IMPORTANTE:** Antes de usar en producción, prueba en Sandbox

1. Solicita credenciales de **Sandbox** (ambiente de pruebas)
   ```
   API Key Sandbox: mooving_test_pk_...
   API Secret Sandbox: mooving_test_sk_...
   Merchant ID: MERCH_TEST_...
   ```

2. Configura en CompraTica con la opción **"Modo Sandbox"** activada

3. Prueba con envíos de ejemplo (no se cobran)

---

## ⚙️ Paso 5: Configurar en CompraTica

1. **Accede al Panel de Admin**
   - Ve a: `admin/mooving-config.php`

2. **Ingresa las credenciales:**
   - API Key: `mooving_test_pk_...` (sandbox) o `mooving_live_pk_...` (producción)
   - API Secret: `mooving_test_sk_...` (sandbox) o `mooving_live_sk_...` (producción)
   - Merchant ID: `MERCH_TEST_...` (sandbox) o `MERCH_COMPRATICA_...` (producción)

3. **Configura tu comisión:**
   - Ejemplo: 15% sobre cada envío
   - Esto significa que si Mooving cobra ₡2,000, tú cobras ₡2,300 al cliente
   - Tu ganancia: ₡300 por envío

4. **Activa el modo correcto:**
   - ☑️ **Sandbox activado**: Para pruebas
   - ☐ **Sandbox desactivado**: Para producción real

5. **Haz clic en "Guardar Configuración"**

---

## 💰 Paso 6: Configurar Método de Pago

### Cómo funciona el flujo de dinero:

1. **Cliente paga a la emprendedora:** ₡2,300 (envío incluido)
2. **CompraTica le paga a Mooving:** ₡2,000 (costo real del envío)
3. **CompraTica retiene:** ₡300 (tu comisión del 15%)

### Opciones de pago a Mooving:

**Opción A: Prepago (Recomendada para empezar)**
- Depositas saldo en tu cuenta Mooving
- Cada envío descuenta del saldo
- Recargas cuando se agota

**Opción B: Facturación Mensual**
- Mooving te factura al final del mes
- Pagas todas las entregas juntas
- Requiere volumen alto

**Opción C: Pago por Envío**
- Cada envío se cobra a tu tarjeta
- Útil para volúmenes bajos

---

## 📊 Paso 7: Monitoreo y Reportes

### Dashboard de Mooving
- Inicia sesión en Mooving
- Revisa el dashboard de envíos
- Ve estadísticas, costos, entregas completadas

### En CompraTica
- Los envíos se registran automáticamente
- Puedes ver cuántos envíos Mooving se usaron
- Calcula tus ganancias por comisión

---

## 🎓 Preguntas Frecuentes

### ¿Cuánto cobra Mooving por envío?
Depende de la distancia, pero generalmente:
- **0-5 km:** ₡1,500 - ₡2,500
- **5-10 km:** ₡2,500 - ₡4,000
- **10+ km:** ₡4,000+

### ¿Cuánto puedo cobrar de comisión?
Lo que quieras. Recomendamos:
- **10-15%** competitivo
- **15-20%** estándar
- **20-25%** premium (si ofreces valor extra)

### ¿Qué pasa si hay un problema con el envío?
- Contacta al soporte de Mooving
- Ellos manejan reembolsos y reclamos
- Tu comisión se ajusta automáticamente

### ¿Puedo cambiar la comisión después?
Sí, en cualquier momento desde `admin/mooving-config.php`

---

## 🆘 Soporte

### Soporte de Mooving
- Email: support@mooving.com
- Teléfono: [Según país]
- Chat: En el dashboard

### Soporte Técnico CompraTica
- Revisa la documentación: `mooving/README.md`
- Verifica configuración: `admin/mooving-config.php`

---

## ✅ Checklist Final

Antes de activar en producción:

- [ ] Cuenta empresarial verificada en Mooving
- [ ] Credenciales de API obtenidas
- [ ] Probado en modo Sandbox exitosamente
- [ ] Método de pago configurado
- [ ] Comisión definida
- [ ] Credenciales de producción ingresadas
- [ ] Modo Sandbox DESACTIVADO
- [ ] Servicio marcado como ACTIVO
- [ ] Primera entrega de prueba exitosa

---

## 🎉 ¡Listo!

Una vez completados todos los pasos:

1. Las emprendedoras verán la opción **"Envío Mooving"** en sus configuraciones
2. Los clientes podrán elegir Mooving al comprar
3. Tú ganas comisión por cada envío
4. Mooving maneja toda la logística

**¡A vender! 🚀**

---

**Última actualización:** Marzo 2026
