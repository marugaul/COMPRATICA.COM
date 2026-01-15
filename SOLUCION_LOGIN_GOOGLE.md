# 🔧 Solución: Login con Google no funciona

## ✅ Lo que ya está configurado

1. ✅ Archivo `includes/config.local.php` con credenciales de Google
2. ✅ Client ID: `634257401014-qg6celuakdk75cabn5ucth4ie3tigqpb.apps.googleusercontent.com`
3. ✅ Client Secret configurado
4. ✅ Código de OAuth en `login.php` funcionando

## 🔍 Problemas identificados y soluciones

### Paso 1: Verificar configuración en Google Cloud Console

**🎯 Acción requerida:**

1. Ir a: https://console.cloud.google.com/apis/credentials
2. Buscar el Client ID: `634257401014-qg6celuakdk75cabn5ucth4ie3tigqpb.apps.googleusercontent.com`
3. Hacer clic en editar
4. En **"URIs de redirección autorizadas"** debe estar EXACTAMENTE:

```
https://compratica.com/login.php?oauth=google
https://www.compratica.com/login.php?oauth=google
```

**⚠️ IMPORTANTE:** Debe incluir AMBAS versiones (con www y sin www)

5. Guardar cambios

### Paso 2: Verificar OAuth Consent Screen

1. En Google Cloud Console, ir a: **OAuth consent screen**
2. Verificar que:
   - Estado: **Publicado** (no en testing)
   - O si está en testing, tu email debe estar en "Test users"
3. Verificar que los **scopes** incluyan:
   - `.../auth/userinfo.email`
   - `.../auth/userinfo.profile`

### Paso 3: Configurar base de datos

Ejecutar el script en el navegador:

```
https://compratica.com/setup_oauth_db.php
```

Este script agregará las columnas necesarias a la tabla `users`:
- `oauth_provider` (VARCHAR)
- `oauth_id` (VARCHAR)

### Paso 4: Probar el login

1. Abrir: https://compratica.com/test_google_oauth.php
2. Hacer clic en "🔗 Probar Login con Google"
3. Si aparece un error, copiar el mensaje exacto

## 🚨 Errores comunes y soluciones

### Error: "redirect_uri_mismatch"

**Causa:** La URI de redirección no coincide

**Solución:**
1. Verificar que en Google Cloud Console esté configurada EXACTAMENTE:
   ```
   https://compratica.com/login.php?oauth=google
   ```
2. Sin espacios antes/después
3. Con https (no http)
4. Con el signo de interrogación y el parámetro oauth=google

### Error: "access_denied"

**Causa:** La aplicación está en modo testing y tu email no está autorizado

**Solución:**
1. Ir a "OAuth consent screen" en Google Cloud Console
2. Agregar tu email en "Test users"
3. O cambiar el estado a "Published"

### Error: "invalid_client"

**Causa:** Client ID o Secret incorrectos

**Solución:**
1. Verificar que las credenciales en `config.local.php` sean las correctas
2. Generar nuevas credenciales si es necesario

## 📝 Checklist completo

- [ ] Credenciales en `config.local.php` ✅ (ya configurado)
- [ ] URI de redirección en Google Cloud Console (verificar)
- [ ] OAuth Consent Screen configurado (verificar)
- [ ] Columnas OAuth en base de datos (ejecutar setup_oauth_db.php)
- [ ] Probar login en navegador

## 🧪 Scripts de diagnóstico disponibles

1. **test_google_oauth.php** - Diagnóstico completo del sistema OAuth
2. **setup_oauth_db.php** - Configurar base de datos para OAuth
3. **check_oauth.php** - Verificar estado de credenciales

## 🆘 Si sigue sin funcionar

1. Revisar los logs en: `/logs/login_debug.log`
2. Activar el modo de desarrollo en el navegador (F12 → Console)
3. Intentar hacer login y copiar cualquier error que aparezca
4. Verificar que el servidor permita conexiones HTTPS salientes (curl)

## 📞 Siguiente paso

Ejecuta en tu navegador en producción:
```
https://compratica.com/setup_oauth_db.php
```

Luego prueba:
```
https://compratica.com/test_google_oauth.php
```

Y comparte cualquier error que veas.
