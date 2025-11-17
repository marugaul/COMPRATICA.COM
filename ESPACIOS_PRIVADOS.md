# 🔒 Espacios Privados - Documentación

## Funcionalidad Implementada

Se agregó la capacidad de crear **espacios privados** que requieren un código de acceso de 6 dígitos para que los clientes puedan ver los productos.

---

## Para Afiliados

### Cómo crear un espacio privado

1. Ir a **Panel de Afiliados** → **Mis Espacios**
2. Al crear un nuevo espacio, verás la sección **"🔒 Configuración de privacidad"**
3. Marca el checkbox **"Espacio privado"**
4. El sistema generará automáticamente un código de 6 dígitos (o puedes escribir el tuyo)
5. **Guarda el código** - los clientes lo necesitarán para acceder
6. Completa el resto del formulario normalmente y crea el espacio

### Características

- **Código de 6 dígitos**: Solo números, fácil de compartir por WhatsApp, SMS, etc.
- **Generación automática**: El sistema sugiere un código aleatorio
- **Personalizable**: Puedes escribir tu propio código
- **Seguridad**: Los productos NO son visibles sin el código correcto

### Ejemplo de uso

**Caso típico**: Venta de garaje exclusiva para vecinos del condominio

1. Creas el espacio marcándolo como privado
2. Código generado: `847293`
3. Compartes el código en el grupo de WhatsApp del condominio
4. Solo las personas con el código pueden ver y comprar los productos

---

## Para Clientes

### Cómo acceder a un espacio privado

1. El vendedor te proporcionará:
   - Link al espacio (ej: `compratica.com/store.php?sale_id=123`)
   - Código de 6 dígitos (ej: `847293`)

2. Al abrir el link, verás una pantalla de acceso:
   - 🔒 Icono de candado
   - Campo para ingresar el código
   - Diseño elegante y claro

3. Ingresa el código de 6 dígitos
4. Si es correcto, accedes inmediatamente a los productos
5. Si es incorrecto, recibes un mensaje de error y puedes reintentar

### Persistencia del acceso

- Una vez que ingresas el código correcto, **queda guardado en tu sesión**
- No necesitas volver a ingresarlo mientras navegues en el sitio
- Si cierras el navegador, deberás ingresarlo nuevamente

---

## Instalación / Migración de Base de Datos

**IMPORTANTE**: Antes de usar esta funcionalidad, debes ejecutar el script de migración para agregar los campos necesarios a la base de datos.

### Opción 1: Desde el navegador (Recomendado)

```
https://compratica.com/tools/add_private_spaces.php
```

**Requisitos**: Solo admin o localhost pueden ejecutarlo

### Opción 2: Desde cPanel / Terminal

Si tienes acceso a sqlite3:

```bash
cd /home/tu_usuario/public_html
sqlite3 data.sqlite < tools/add_private_spaces.sql
```

### Verificación

El script mostrará:
- ✓ Columna `is_private` agregada
- ✓ Columna `access_code` agregada
- Lista de todos los campos de la tabla `sales`

---

## Estructura de Base de Datos

### Campos agregados a la tabla `sales`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `is_private` | INTEGER | 0 = público (default), 1 = privado |
| `access_code` | TEXT | Código de 6 dígitos numéricos (solo si is_private = 1) |

### Ejemplo de registro:

```sql
id: 5
title: "Venta Vecinos Condominio"
is_private: 1
access_code: "847293"
is_active: 1
```

---

## Archivos Modificados

### 1. `affiliate/sales.php`
- Formulario de creación con opción de espacio privado
- Validación de código de 6 dígitos
- JavaScript para generar código automático
- Procesamiento PHP para guardar configuración

### 2. `store.php`
- Validación de acceso antes de mostrar productos
- Gestión de sesión para códigos válidos
- Redirección a formulario de acceso si no autorizado

### 3. `views/access_form.php` (NUEVO)
- Formulario elegante de ingreso de código
- Validación en tiempo real
- Mensajes de error claros
- Diseño responsive y moderno

### 4. `tools/add_private_spaces.php` (NUEVO)
- Script de migración de base de datos
- Verificación de columnas existentes
- Logs detallados del proceso

### 5. `tools/add_private_spaces.sql` (NUEVO)
- Script SQL puro para migración manual
- Para usar con sqlite3 directamente

---

## Flujo de Funcionamiento

```
1. Afiliado crea espacio privado
   ↓
2. Sistema guarda is_private=1 y access_code="123456"
   ↓
3. Cliente intenta acceder a store.php?sale_id=X
   ↓
4. Sistema detecta is_private=1
   ↓
5. ¿Código en sesión?
   NO → Muestra formulario (views/access_form.php)
   SÍ → Verifica código
       ↓
       Correcto → Muestra productos
       Incorrecto → Error y vuelve a formulario
```

---

## Seguridad

- ✅ Validación de código en servidor (no solo JavaScript)
- ✅ Código almacenado en sesión PHP cifrada
- ✅ Validación de formato: exactamente 6 dígitos numéricos
- ✅ Logs de acceso en `logs/store_debug.log`
- ✅ Sin bypass posible (validación antes de cargar productos)

---

## Logging y Debug

Todos los eventos se registran en `logs/store_debug.log`:

```
[2025-11-17 22:00:00] PRIVATE_SPACE_DETECTED | {"sale_id":5}
[2025-11-17 22:00:05] ACCESS_CODE_SUBMITTED | {"code_length":6}
[2025-11-17 22:00:05] ACCESS_GRANTED | {"sale_id":5}
```

Útil para:
- Debugging de problemas de acceso
- Monitoreo de intentos fallidos
- Auditoría de seguridad

---

## Soporte

Para problemas o preguntas:
- Revisar logs en `logs/store_debug.log`
- Verificar que la migración se ejecutó correctamente
- Contactar al administrador del sistema

---

**Fecha de implementación**: Noviembre 2025
**Versión**: 1.0
