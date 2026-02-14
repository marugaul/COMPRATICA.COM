# 🔧 Solución: Google OAuth en Bienes Raíces

## 🎯 Problema Identificado

El sistema de login con Google en el módulo de **Bienes Raíces** no funciona. El usuario hace clic en "Continuar con Google", se redirige a Google, pero después no puede iniciar sesión.

## 🔍 Causa Raíz

El sitio COMPRATICA.COM tiene **DOS sistemas de OAuth separados**:

1. **OAuth Principal** (usuarios regulares): `/login.php?oauth=google`
2. **OAuth Bienes Raíces** (agentes inmobiliarios): `/real-estate/oauth-callback.php`

**El problema:** En Google Cloud Console solo está configurada la URI del OAuth principal, pero NO la URI del OAuth de Bienes Raíces.

## ✅ Solución Paso a Paso

### Paso 1: Configurar Google Cloud Console

Debes agregar las URIs de redirección del módulo de Bienes Raíces:

1. Ve a [Google Cloud Console - Credenciales](https://console.cloud.google.com/apis/credentials)

2. Busca el Client ID:
   ```
   634257401014-qg6celuakdk75cabn5ucth4ie3tigqpb.apps.googleusercontent.com
   ```

3. Haz clic en editar (ícono de lápiz)

4. En **"URIs de redirección autorizadas"**, agrega ESTAS DOS URIs:
   ```
   https://compratica.com/real-estate/oauth-callback.php
   https://www.compratica.com/real-estate/oauth-callback.php
   ```

5. **IMPORTANTE:** Las URIs existentes del OAuth principal DEBEN permanecer:
   ```
   https://compratica.com/login.php?oauth=google
   https://www.compratica.com/login.php?oauth=google
   ```

6. Guarda los cambios

7. **Espera 5 minutos** para que los cambios se propaguen en los servidores de Google

### Paso 2: Verificar la Configuración

Ejecuta el script de diagnóstico para verificar que todo está configurado correctamente:

```
https://compratica.com/test-real-estate-oauth.php
```

Este script verificará:
- ✅ Credenciales de Google configuradas
- ✅ Archivos de OAuth presentes
- ✅ Tabla de base de datos real_estate_agents existe
- ✅ Sesiones PHP habilitadas
- ⚠️ URIs de redirección requeridas

### Paso 3: Probar el Login

1. Ve a: `https://compratica.com/real-estate/login.php`
2. Haz clic en "Continuar con Google"
3. Selecciona tu cuenta de Google
4. Autoriza la aplicación
5. Deberías ser redirigido al dashboard de Bienes Raíces

### Paso 4: Ver Logs (si hay errores)

Si el login falla, puedes ver los logs detallados aquí:

```
https://compratica.com/view-oauth-logs.php
```

Los logs incluirán:
- Parámetros recibidos de Google
- Estados de sesión
- Errores de API
- Respuestas de Google
- Stack traces de excepciones

También puedes ver el archivo de log directamente:
```
/logs/real_estate_oauth.log
```

## 🛠️ Herramientas Creadas

### 1. `test-real-estate-oauth.php`
**Qué hace:** Diagnóstico completo del sistema OAuth para Bienes Raíces

**Características:**
- Verifica credenciales de Google
- Comprueba archivos necesarios
- Valida estructura de base de datos
- Muestra URIs de redirección requeridas
- Instrucciones paso a paso para configurar Google Cloud Console

**URL:** `https://compratica.com/test-real-estate-oauth.php`

### 2. `view-oauth-logs.php`
**Qué hace:** Visualizador de logs en tiempo real

**Características:**
- Muestra todos los eventos de OAuth
- Colorea errores, éxitos e info
- Muestra contexto completo (parámetros, respuestas, etc.)
- Permite limpiar logs
- Actualización en tiempo real

**URL:** `https://compratica.com/view-oauth-logs.php`

### 3. Logging Mejorado en `oauth-callback.php`
**Qué hace:** Registra automáticamente todos los eventos

**Se registra:**
- ✅ Inicio del callback
- ✅ Verificación de estado de seguridad
- ✅ Código de autorización recibido
- ✅ Intercambio de tokens con Google
- ✅ Información del usuario recibida
- ✅ Login exitoso / Registro exitoso
- ❌ Todos los errores con contexto completo

**Ubicación del log:** `/logs/real_estate_oauth.log`

## 🚨 Errores Comunes y Soluciones

### Error: "redirect_uri_mismatch"

**Mensaje completo:**
```
Error de configuración OAuth. El redirect URI no coincide.
```

**Causa:** La URI de redirección no está configurada en Google Cloud Console

**Solución:**
1. Sigue el Paso 1 arriba
2. Asegúrate de que las URIs estén EXACTAMENTE como se indica
3. Sin espacios antes/después
4. Con `https://` (no `http://`)
5. Espera 5 minutos después de guardar

### Error: "access_denied"

**Mensaje completo:**
```
Acceso denegado. No autorizaste el acceso a tu cuenta de Google.
```

**Causa:** El usuario canceló la autorización en Google

**Solución:** Vuelve a intentar y asegúrate de hacer clic en "Permitir" en Google

**Otra posible causa:** La aplicación está en modo "Testing" y tu email no está en la lista de usuarios de prueba

**Solución:**
1. Ve a "OAuth consent screen" en Google Cloud Console
2. Agrega tu email en "Test users"
3. O cambia el estado de la aplicación a "Published"

### Error: "invalid_client"

**Mensaje completo:**
```
Credenciales de Google inválidas.
```

**Causa:** Client ID o Secret incorrectos

**Solución:**
1. Verifica que las credenciales en `/includes/config.local.php` sean correctas
2. Compara con las credenciales en Google Cloud Console
3. Si es necesario, genera nuevas credenciales

### Error: "La tabla de agentes no existe"

**Mensaje completo:**
```
Error de configuración: La tabla de agentes no existe.
```

**Causa:** La base de datos no está inicializada

**Solución:**
Ejecuta el script de instalación:
```
php instalar-bienes-raices-agentes.php
```

## 📋 Checklist de Verificación

Antes de usar el OAuth de Bienes Raíces, verifica:

- [ ] Credenciales en `/includes/config.local.php` configuradas
- [ ] URIs de redirección en Google Cloud Console (AMBAS versiones con y sin www)
- [ ] OAuth Consent Screen configurado en Google Cloud Console
- [ ] Tabla `real_estate_agents` existe en la base de datos
- [ ] Archivos OAuth presentes: `oauth-start.php`, `oauth-callback.php`
- [ ] Directorio `/logs` existe y tiene permisos de escritura
- [ ] Sesiones PHP habilitadas

## 🔄 Flujo Completo de OAuth

Para entender cómo funciona:

1. Usuario en `/real-estate/login.php` → Clic en "Continuar con Google"
2. Redirige a `/real-estate/oauth-start.php`
3. Se genera un token de seguridad (CSRF) y se guarda en sesión
4. Redirige a Google (`accounts.google.com`)
5. Usuario autoriza la aplicación
6. Google redirige a `/real-estate/oauth-callback.php?code=XXX&state=YYY`
7. Se verifica el estado de seguridad
8. Se intercambia el código por tokens de acceso
9. Se obtiene información del usuario (email, nombre)
10. Se busca si el email ya existe en `real_estate_agents`
    - **Si existe:** Inicia sesión
    - **Si no existe:** Crea nuevo agente
11. Se inicia sesión automáticamente
12. Redirige a `/real-estate/dashboard.php`

Cada paso se registra en `/logs/real_estate_oauth.log`

## 🎓 Diferencia entre OAuth Principal y OAuth de Bienes Raíces

### OAuth Principal (`/login.php`)
- **Usuarios:** Clientes regulares del sitio
- **Tabla:** `users` (con columnas `oauth_provider` y `oauth_id`)
- **Redirect URI:** `/login.php?oauth=google`
- **Dashboard:** Depende del rol del usuario

### OAuth Bienes Raíces (`/real-estate/oauth-start.php`)
- **Usuarios:** Agentes inmobiliarios
- **Tabla:** `real_estate_agents`
- **Redirect URI:** `/real-estate/oauth-callback.php`
- **Dashboard:** `/real-estate/dashboard.php`

**Ambos sistemas son independientes** pero usan las mismas credenciales de Google (mismo Client ID y Secret).

## 🆘 Si Sigue Sin Funcionar

1. Ejecuta el diagnóstico: `https://compratica.com/test-real-estate-oauth.php`
2. Intenta hacer login
3. Ve los logs: `https://compratica.com/view-oauth-logs.php`
4. Busca el error más reciente
5. Compara con la sección "Errores Comunes y Soluciones"
6. Si el error persiste, copia el contenido del log completo

## 📞 Próximos Pasos

1. **Ejecuta el diagnóstico:**
   ```
   https://compratica.com/test-real-estate-oauth.php
   ```

2. **Configura Google Cloud Console** siguiendo el Paso 1

3. **Prueba el login:**
   ```
   https://compratica.com/real-estate/login.php
   ```

4. **Si hay errores, ve los logs:**
   ```
   https://compratica.com/view-oauth-logs.php
   ```

## 📝 Resumen de Archivos Modificados/Creados

### Archivos Creados
- ✅ `/test-real-estate-oauth.php` - Herramienta de diagnóstico
- ✅ `/view-oauth-logs.php` - Visualizador de logs
- ✅ `/SOLUCION_GOOGLE_OAUTH_BIENES_RAICES.md` - Esta documentación

### Archivos Modificados
- ✅ `/real-estate/oauth-callback.php` - Agregado logging detallado
- ✅ `/logs/` - Directorio creado para logs

### Archivos Existentes (sin cambios)
- `/real-estate/oauth-start.php`
- `/real-estate/login.php`
- `/real-estate/register.php`
- `/includes/config.oauth.php`
- `/includes/config.local.php`

---

**¡Todo listo!** El sistema de OAuth para Bienes Raíces ahora tiene diagnóstico completo y logging detallado para facilitar la depuración. Solo falta configurar las URIs de redirección en Google Cloud Console.
