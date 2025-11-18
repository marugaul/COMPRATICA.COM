# Configuración de Google OAuth para Registro de Afiliados

## ✅ Implementación Completa

Se agregó integración con Google OAuth en la página de registro de afiliados (`affiliate/register.php`).

---

## 🔧 Configuración Requerida en Google Cloud Console

Para que funcione correctamente, debes agregar la siguiente URI en Google Cloud Console:

### 1. Ir a Google Cloud Console
https://console.cloud.google.com/apis/credentials

### 2. Seleccionar el proyecto
**Project ID:** `compratica`

### 3. Editar las credenciales OAuth 2.0
Buscar el Client ID que configuraste (comienza con `634257401014-...`)

### 4. Agregar URI de redirección

**IMPORTANTE**: Agregar esta nueva URI a la lista de "URIs de redirección autorizadas":

```
https://compratica.com/affiliate/register.php
```

**URI ya existente (mantenerla):**
```
https://compratica.com/login.php
```

**Resultado final - URIs autorizadas:**
- `https://compratica.com/login.php` (login de clientes)
- `https://compratica.com/affiliate/register.php` (registro de afiliados) ← **NUEVA**

### 5. Guardar cambios

Hacer clic en "GUARDAR" en Google Cloud Console.

---

## 🎯 Cómo Funciona

### Para Nuevos Afiliados:
1. Van a `https://compratica.com/affiliate/register.php`
2. Ven el botón "Registrarse con Google"
3. Hacen clic y se autentican con Google
4. Automáticamente:
   - Se crea la cuenta de afiliado
   - Se activa la cuenta
   - Se inicia sesión
   - Se redirige al dashboard
   - Se envían emails de confirmación

### Para Afiliados Existentes:
Si alguien ya tiene cuenta (mismo email), simplemente inicia sesión sin crear duplicados.

---

## 📋 Campos Creados Automáticamente

Cuando alguien se registra con Google:
- **Nombre**: Se obtiene de Google (profile.name)
- **Email**: Se obtiene de Google (verified email)
- **Teléfono**: Queda vacío (Google no lo proporciona)
- **Contraseña**: Se genera aleatoriamente (no la necesitan para OAuth)
- **Activo**: Se activa automáticamente

---

## 🔒 Seguridad

- ✅ Usa OAuth 2.0 estándar de Google
- ✅ No almacena contraseñas de Google
- ✅ Verifica emails (Google ya lo hizo)
- ✅ Genera contraseñas seguras aleatorias
- ✅ Previene duplicados por email
- ✅ Sesión segura con session_start()

---

## 🎨 Diseño del Botón

El botón usa el diseño oficial de Google:
- Logo SVG de Google (colores oficiales)
- Fondo blanco con borde gris
- Hover con sombra sutil
- Texto: "Registrarse con Google"
- Separador visual: "o registrate con email"

---

## 📧 Emails Enviados

### Al Afiliado:
```
Asunto: ✅ Bienvenido a COMPRATICA.COM
Contenido:
- Saludo personalizado
- Confirmación de registro con Google
- Enlace al dashboard
- Información de contacto
```

### Al Admin:
```
Asunto: [Afiliados] Nuevo registro con Google
Contenido:
- Nombre del afiliado
- Email
- ID generado
```

---

## 🧪 Prueba del Flujo

1. Ve a: `https://compratica.com/affiliate/register.php`
2. Haz clic en "Registrarse con Google"
3. Selecciona tu cuenta de Google
4. Acepta los permisos (email y profile)
5. Deberías ser redirigido al dashboard de afiliados

---

## ❗ Solución de Problemas

### Error: "redirect_uri_mismatch"
**Causa**: La URI no está autorizada en Google Cloud Console
**Solución**: Verifica que agregaste `https://compratica.com/affiliate/register.php`

### Error: "Configuración OAuth incompleta"
**Causa**: Credenciales no están en config.local.php
**Solución**: Verifica que existe `/includes/config.local.php` con:
```php
<?php
define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID_AQUI.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');
```

### No aparece el botón de Google
**Causa**: GOOGLE_CLIENT_ID está vacío
**Solución**: Revisa config.local.php y config.php

---

## 📝 Archivos Modificados

- `affiliate/register.php`: Código OAuth + Botón Google + Estilos CSS

---

## 🚀 Próximos Pasos

1. ✅ Agregar URI en Google Cloud Console
2. ✅ Probar el flujo completo
3. ✅ Verificar emails de confirmación
4. ✅ Confirmar que la sesión inicia correctamente

---

**Fecha de implementación**: Noviembre 2025
**Versión**: 1.0
