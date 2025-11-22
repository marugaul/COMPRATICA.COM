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
https://compratica.com/login.php?oauth=google
```

### Paso 5: Copiar credenciales

Copia:
- **ID de cliente** (Client ID) - formato: `123456789-abc...apps.googleusercontent.com`
- **Secreto de cliente** (Client Secret) - formato: `GOCSPX-abc...xyz`

### Paso 6: Agregar a config.local.php

**IMPORTANTE:** Crea el archivo `/includes/config.local.php` (NO edites config.php):

```php
<?php
define('GOOGLE_CLIENT_ID', 'TU_CLIENT_ID_AQUI');
define('GOOGLE_CLIENT_SECRET', 'TU_CLIENT_SECRET_AQUI');
?>
```

**Nota:** Este archivo NO se sube a Git por seguridad (está en .gitignore).

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
https://compratica.com/login.php?oauth=facebook
```

### Paso 4: Configurar Dominio de la App

1. Ve a **Configuración** → **Básica**
2. En **Dominios de apps** agrega:
```
compratica.com
```

### Paso 5: Copiar credenciales

En **Configuración** → **Básica**, copia:
- **ID de la aplicación** (App ID) - número de 15-16 dígitos
- **Clave secreta de la aplicación** (App Secret) - Clic en "Mostrar"

### Paso 6: Cambiar a modo producción

1. En la parte superior, cambia de **Desarrollo** a **Activo** (Producción)
2. Completa los campos requeridos (URL de política de privacidad, etc.)

### Paso 7: Agregar a config.local.php

**IMPORTANTE:** Agrega al mismo archivo `/includes/config.local.php`:

```php
<?php
define('GOOGLE_CLIENT_ID', '...');
define('GOOGLE_CLIENT_SECRET', '...');

// Agrega estas líneas:
define('FACEBOOK_APP_ID', 'TU_APP_ID_AQUI');
define('FACEBOOK_APP_SECRET', 'TU_APP_SECRET_AQUI');
?>
```

---

## ✅ 3. Verificar que funciona

### Paso 1: Verificar credenciales

Primero verifica que las credenciales están correctamente configuradas:

```
https://compratica.com/check_oauth.php
```

Debe mostrar:
- ✓ Google OAuth: Configurado
- ✓ Facebook OAuth: Configurado

Si no están configuradas, revisa que `config.local.php` esté en el servidor.

### Paso 2: Probar Google Login

1. Abre https://compratica.com/login.php
2. Deberías ver botón "Continuar con Google"
3. Haz clic y completa el flujo
4. Deberías entrar automáticamente

### Paso 3: Probar Facebook Login

1. En la misma página de login
2. Deberías ver botón "Continuar con Facebook"
3. Haz clic y completa el flujo
4. Deberías entrar automáticamente

---

## 🔍 Solución de problemas

### No aparecen los botones de Google/Facebook
1. Abre `https://compratica.com/check_oauth.php` para verificar
2. Si muestra "NO configurado", revisa que `config.local.php` exista en `/includes/`
3. Verifica que las credenciales no estén vacías

### Error: redirect_uri_mismatch (Google)
**Causa:** La URI configurada en Google no coincide exactamente.

**Solución:** Debe ser exactamente:
```
https://compratica.com/login.php?oauth=google
```
Sin www., sin espacios, con `?oauth=google` al final.

### Error: URL not allowed (Facebook)
**Causa:** La URI no está autorizada en Facebook.

**Solución:**
1. Ve a Facebook Login → Configuración
2. Agrega exactamente: `https://compratica.com/login.php?oauth=facebook`
3. Verifica que la app esté en modo **Producción** (no Desarrollo)

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

## 🛠️ Herramienta de Diagnóstico

Creamos una herramienta para verificar el estado de OAuth:

```
https://compratica.com/check_oauth.php
```

Esta página te muestra:
- ✅ Si `config.local.php` existe
- ✅ Si Google OAuth está configurado
- ✅ Si Facebook OAuth está configurado
- ✅ Las URIs de redirección correctas
- ⚠️ Qué falta configurar si algo no funciona

**Úsala ANTES de probar el login** para asegurarte que todo está bien.

---

## 🆘 ¿Necesitas ayuda?

Si tienes problemas configurando, revisa en orden:
1. **Verificador:** `https://compratica.com/check_oauth.php`
2. **URLs exactas:** Las URIs deben incluir `?oauth=google` y `?oauth=facebook`
3. **Modo producción:** Apps de Facebook deben estar activas
4. **Archivo correcto:** Usa `/includes/config.local.php` (NO config.php)
5. **Logs:** Revisa `/logs/login_debug.log` para errores específicos
