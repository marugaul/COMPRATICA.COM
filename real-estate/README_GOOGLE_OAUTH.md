# Integración de Google OAuth para Bienes Raíces

## Descripción
Esta integración permite a los agentes de bienes raíces registrarse e iniciar sesión usando su cuenta de Google, creando un usuario único en el sistema.

## Archivos Creados

### 1. `/real-estate/oauth-start.php`
- Inicia el flujo de OAuth con Google
- Genera un estado de seguridad (CSRF protection)
- Redirige al usuario a la página de autorización de Google

### 2. `/real-estate/oauth-callback.php`
- Recibe el código de autorización de Google
- Intercambia el código por tokens de acceso
- Obtiene la información del usuario (email, nombre)
- Crea o vincula el agente en la tabla `real_estate_agents`
- Inicia sesión automáticamente

### 3. Modificaciones en `/real-estate/register.php`
- Agregado botón "Continuar con Google"
- Incluye el logo de Google oficial
- Separador visual entre OAuth y registro tradicional

### 4. Modificaciones en `/real-estate/login.php`
- Agregado botón "Continuar con Google"
- Mismo diseño consistente con el registro

### 5. Modificaciones en `/real-estate/dashboard.php`
- Mensaje de bienvenida para nuevos usuarios de Google
- Mensaje de confirmación de inicio de sesión

## Instalación

### Prerequisitos
Antes de usar el módulo de Bienes Raíces, debes ejecutar el script de instalación para crear las tablas necesarias:

```bash
php instalar-bienes-raices-agentes.php
```

Este script creará:
- Tabla `real_estate_agents` con todos los campos necesarios
- Columna `agent_id` en la tabla `real_estate_listings`
- Índices apropiados

**IMPORTANTE**: Si ves el error "no such table: real_estate_agents", es porque no se ejecutó este script de instalación.

## Configuración de Google Cloud Console

### URIs de redirección autorizadas
Debe configurarse EXACTAMENTE esta URI en Google Cloud Console:

```
https://compratica.com/real-estate/oauth-callback.php
```

### Credenciales
Las credenciales están configuradas en `/includes/config.local.php`:
- `GOOGLE_CLIENT_ID`: ID del cliente de Google
- `GOOGLE_CLIENT_SECRET`: Secreto del cliente de Google

## Flujo de Registro con Google

1. Usuario hace clic en "Continuar con Google"
2. Se redirige a `/real-estate/oauth-start.php`
3. Se genera un estado de seguridad y se guarda en sesión
4. Se redirige a Google para autorización
5. Usuario autoriza la aplicación en Google
6. Google redirige a `/real-estate/oauth-callback.php` con código
7. Se intercambia el código por tokens
8. Se obtiene información del usuario de Google
9. Se verifica si el email ya existe en `real_estate_agents`
   - Si existe: inicia sesión
   - Si no existe: crea nuevo agente
10. Se inicia sesión automáticamente
11. Se redirige al dashboard

## Base de Datos

### Tabla: `real_estate_agents`
Los usuarios de Google se crean con:
- `name`: Nombre obtenido de Google
- `email`: Email verificado de Google
- `phone`: Vacío (puede completarlo después)
- `password_hash`: Vacío (no necesita contraseña)
- `is_active`: 1 (activo automáticamente)
- `created_at`: Fecha actual

## Seguridad

- **CSRF Protection**: Se usa un token de estado aleatorio en cada flujo
- **Verificación de estado**: El callback verifica que el estado coincida
- **Email verificado**: Google garantiza que el email está verificado
- **Sin contraseña**: Los usuarios de Google no necesitan contraseña
- **Sesiones seguras**: Se regenera el ID de sesión después del login

## Usuario Único

Los usuarios registrados con Google y los registrados con email tradicional comparten la misma tabla `real_estate_agents`. La diferencia es:
- **Usuarios tradicionales**: Tienen `password_hash` con un hash bcrypt
- **Usuarios de Google**: Tienen `password_hash` vacío

Esto permite que:
- Un usuario pueda registrarse con Google
- Luego pueda agregar una contraseña tradicional si lo desea
- Un usuario puede registrarse con email y luego vincular su cuenta de Google

## Testing

Para probar la integración:
1. Ir a `https://compratica.com/real-estate/register.php`
2. Hacer clic en "Continuar con Google"
3. Seleccionar una cuenta de Google
4. Autorizar la aplicación
5. Verificar que se cree el usuario y se redirija al dashboard

## Posibles Mejoras Futuras

- [ ] Permitir vincular cuenta de Google a cuenta existente
- [ ] Permitir desvincular cuenta de Google
- [ ] Agregar más proveedores OAuth (Facebook, Apple)
- [ ] Mostrar en el perfil cómo se registró el usuario
- [ ] Permitir agregar contraseña a cuentas de Google
