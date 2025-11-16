# 🔐 Configuración de Login Social (Google & Facebook)

Tu sitio ya tiene implementado el login con Google y Facebook. Solo necesitas configurar las credenciales de las apps.

---

## 📱 1. Configurar Google Sign-In

### Paso 1: Crear proyecto en Google Cloud Console

1. Ve a: https://console.cloud.google.com/
2. Crea un nuevo proyecto o selecciona uno existente
3. Dale un nombre: "COMPRATICA Login"

### Paso 2: Habilitar Google+ API

1. En el menú lateral → **APIs y servicios** → **Biblioteca**
2. Busca "Google+ API"
3. Clic en **Habilitar**

### Paso 3: Crear credenciales OAuth

1. Ve a **APIs y servicios** → **Credenciales**
2. Clic en **Crear credenciales** → **ID de cliente de OAuth 2.0**
3. Tipo de aplicación: **Aplicación web**
4. Nombre: "COMPRATICA Web Login"

### Paso 4: Configurar URLs autorizadas

**Orígenes de JavaScript autorizados:**
```
https://compratica.com
https://www.compratica.com
```

**URIs de redirección autorizadas:**
```
https://compratica.com/login.php
https://www.compratica.com/login.php
```

### Paso 5: Copiar credenciales

Copia:
- **ID de cliente** (Client ID)
- **Secreto de cliente** (Client Secret)

### Paso 6: Agregar a config.php

Edita `/includes/config.php` y agrega:
```php
define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
define('GOOGLE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');
```

---

## 📘 2. Configurar Facebook Login

### Paso 1: Crear App en Facebook

1. Ve a: https://developers.facebook.com/
2. Clic en **Mis Apps** → **Crear app**
3. Tipo: **Consumidor**
4. Nombre: "COMPRATICA Login"
5. Email de contacto: tu email

### Paso 2: Agregar producto Facebook Login

1. En el dashboard de la app
2. Busca **Facebook Login** → Clic en **Configurar**
3. Elige **Web**

### Paso 3: Configurar URLs válidas

1. Ve a **Facebook Login** → **Configuración**
2. **URI de redirección de OAuth válidos:**
```
https://compratica.com/login.php
https://www.compratica.com/login.php
```

### Paso 4: Configurar Dominio de la App

1. Ve a **Configuración** → **Básica**
2. En **Dominios de apps** agrega:
```
compratica.com
```

### Paso 5: Copiar credenciales

En **Configuración** → **Básica**, copia:
- **ID de la aplicación** (App ID)
- **Clave secreta de la aplicación** (App Secret) - Clic en "Mostrar"

### Paso 6: Cambiar a modo producción

1. En la parte superior, cambia de **Desarrollo** a **Activo**
2. Completa los campos requeridos (URL de política de privacidad, etc.)

### Paso 7: Agregar a config.php

Edita `/includes/config.php` y agrega:
```php
define('FACEBOOK_APP_ID', 'TU_APP_ID_AQUI');
define('FACEBOOK_APP_SECRET', 'TU_APP_SECRET_AQUI');
```

---

## ✅ 3. Verificar que funciona

### Probar Google Login

1. Abre https://compratica.com/login.php
2. Deberías ver botón "Continuar con Google"
3. Haz clic y completa el flujo
4. Deberías entrar automáticamente

### Probar Facebook Login

1. En la misma página de login
2. Deberías ver botón "Registrarse con Facebook"
3. Haz clic y completa el flujo
4. Deberías entrar automáticamente

---

## 🔍 Solución de problemas

### Error: redirect_uri_mismatch
**Solución:** Verifica que las URIs de redirección sean EXACTAMENTE iguales en la configuración de Google/Facebook y en tu sitio.

### No aparecen los botones
**Solución:** Verifica que las constantes en `config.php` no estén vacías.

### Error: App not active
**Facebook:** Asegúrate de que la app esté en modo "Activo" (no "Desarrollo").

---

## 📊 Cómo funciona

1. Usuario hace clic en "Continuar con Google" o "Registrarse con Facebook"
2. Es redirigido a Google/Facebook para autorizar
3. Google/Facebook lo devuelve a `/login.php?oauth=google&code=...`
4. El sistema verifica el código con Google/Facebook
5. Obtiene email y nombre del usuario
6. **Si el usuario NO existe:** Se crea automáticamente en la base de datos
7. **Si el usuario YA existe:** Se loguea con su cuenta existente
8. Usuario queda autenticado y es redirigido

---

## 🔒 Seguridad

- Las contraseñas NO se guardan para usuarios OAuth (no las necesitan)
- Los tokens de acceso NO se guardan (solo se usan para obtener datos)
- Se guarda: `oauth_provider` (google/facebook) y `oauth_id` (ID único)
- Los usuarios pueden tener cuenta normal + OAuth simultáneamente

---

## 📝 Notas importantes

- Configura URLs de redirección ANTES de hacer pruebas
- Usa HTTPS (requerido por Google y Facebook)
- Los datos del usuario (email, nombre) se obtienen con su permiso
- Si un usuario se registra con email y luego usa Google con el mismo email, se usan como cuentas separadas (puedes modificar esto si quieres)

---

## 🆘 ¿Necesitas ayuda?

Si tienes problemas configurando, revisa:
1. Que las URLs de redirección sean exactas
2. Que las apps estén en modo activo/producción
3. Que las credenciales estén correctas en `config.php`
4. Los logs en `/logs/login_debug.log` para ver errores específicos
