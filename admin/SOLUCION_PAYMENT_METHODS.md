# 🔧 Solución: Error "no such column: payment_methods"

## Problema
```
❌ Error al actualizar: SQLSTATE[HY000]: General error: 1 no such column: payment_methods
```

Este error ocurre porque falta la columna `payment_methods` en la tabla `listing_pricing`.

## ✅ Soluciones Disponibles

### Opción 1: Script PHP (RECOMENDADO) 👍

**La forma más fácil y segura:**

1. Abre tu navegador
2. Ve a: `https://compratica.com/admin/fix_payment_methods.php`
3. El script ejecutará automáticamente el fix
4. Verás un mensaje de confirmación cuando termine

**Ventajas:**
- ✅ Interfaz visual
- ✅ Maneja errores automáticamente
- ✅ Muestra el resultado en tiempo real
- ✅ No requiere acceso SSH

---

### Opción 2: Script de Terminal (Para usuarios avanzados)

**Si tienes acceso SSH:**

```bash
cd /home/comprati/public_html/admin
./ejecutar_fix_payment_methods.sh
```

**Ventajas:**
- ✅ Ejecución rápida desde la terminal
- ✅ Útil para automatización
- ✅ Detecta automáticamente la base de datos

---

### Opción 3: SQL Manual (Solo si las anteriores fallan)

**Si necesitas ejecutar el SQL manualmente:**

```bash
cd /home/comprati/public_html
sqlite3 data.sqlite < admin/fix_payment_methods.sql
```

O ejecuta directamente:

```sql
ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';

UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 1;
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 2;
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 3;
```

---

## 📋 Verificar que el Fix Funcionó

Después de ejecutar cualquiera de las opciones anteriores:

1. Ve a: `https://compratica.com/admin/diagnose_bienes_raices.php`
2. Deberías ver: **✅ AMBAS COLUMNAS EXISTEN**
3. Prueba actualizar un plan en: `https://compratica.com/admin/bienes_raices_config.php`

---

## 📁 Archivos Creados

```
admin/
├── fix_payment_methods.php           # Script PHP con interfaz visual
├── fix_payment_methods.sql            # SQL para ejecutar manualmente
├── ejecutar_fix_payment_methods.sh   # Script de terminal
└── SOLUCION_PAYMENT_METHODS.md       # Este archivo (instrucciones)
```

---

## ❓ Preguntas Frecuentes

### ¿Puedo ejecutar el fix varias veces?

**Sí**, es completamente seguro. Si la columna ya existe, el script te lo informará y no hará cambios.

### ¿Se perderán datos?

**No**. Este script solo AGREGA una columna nueva. No elimina ni modifica datos existentes.

### ¿Qué hace exactamente?

El script:
1. Agrega la columna `payment_methods` a la tabla `listing_pricing`
2. Establece el valor por defecto: `'sinpe,paypal'`
3. Actualiza los planes existentes con este valor

### ¿Qué pasa si ya ejecuté la migración antes?

Si ya ejecutaste `add_configurable_plan_fields.sql` pero solo se agregó `max_photos`, este script agregará la columna faltante `payment_methods`.

---

## 🆘 Si Nada Funciona

Si después de intentar todas las opciones el error persiste:

1. Verifica que estás usando la base de datos correcta:
   ```bash
   php admin/diagnose_bienes_raices.php
   ```

2. Revisa los permisos del archivo de base de datos:
   ```bash
   ls -la data.sqlite
   chmod 644 data.sqlite
   ```

3. Contacta al desarrollador con:
   - El mensaje de error completo
   - La salida de `diagnose_bienes_raices.php`
   - La versión de PHP (`php -v`)

---

## 📝 Notas Técnicas

- **Base de datos**: SQLite (`data.sqlite`)
- **Tabla afectada**: `listing_pricing`
- **Columna agregada**: `payment_methods TEXT DEFAULT 'sinpe,paypal'`
- **Valores permitidos**:
  - `'sinpe'` - Solo SINPE Móvil
  - `'paypal'` - Solo PayPal
  - `'sinpe,paypal'` - Ambos métodos

---

**Fecha de creación**: 2026-02-27
**Autor**: Claude AI
**Versión**: 1.0
