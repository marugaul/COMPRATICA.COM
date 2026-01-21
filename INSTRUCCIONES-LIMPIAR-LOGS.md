# 🧹 Instrucciones para Limpiar Logs MySQL

## Problema
El cron `mysql-auto-executor.sh` estaba generando logs infinitos que llenaban el disco.

## Solución Aplicada
1. **mysql-auto-executor.sh** - Ya modificado para NO acumular logs
2. **limpiar-logs-cron.php** - Script para eliminar logs antiguos existentes

---

## 📋 Opción 1: Ejecutar desde CRON (Recomendado)

### Agregar este cron TEMPORAL (se ejecuta UNA sola vez):

```bash
# Editar crontab desde cPanel o Plesk:
# Agregar esta línea para que se ejecute una sola vez:

# Ejecutar ahora (ajusta la hora actual + 2 minutos)
25 15 * * * /usr/bin/php /home/comprati/public_html/limpiar-logs-cron.php

# O ejecutar a medianoche hoy:
0 0 * * * /usr/bin/php /home/comprati/public_html/limpiar-logs-cron.php
```

**IMPORTANTE:** Después de que se ejecute, **ELIMINA** esta línea del cron. Solo necesita ejecutarse UNA vez.

### Rutas comunes según hosting:
- **cPanel**: `/home/TUUSUARIO/public_html/limpiar-logs-cron.php`
- **Plesk**: `/var/www/vhosts/compratica.com/httpdocs/limpiar-logs-cron.php`
- **DirectAdmin**: `/home/TUUSUARIO/domains/compratica.com/public_html/limpiar-logs-cron.php`

---

## 📋 Opción 2: Ejecutar desde el Navegador

### Visita esta URL en tu navegador:

```
https://compratica.com/limpiar-logs.php
```

El script se ejecutará automáticamente y te mostrará los resultados en pantalla.

---

## ✅ Verificación

Después de ejecutar el script, verifica en el administrador de archivos:
- La carpeta `mysql-logs/` debe tener solo 1 archivo: `ultimo-ejecutado.log`
- Todos los archivos con formato `20251123_XXXXXX_*.log` deben estar eliminados

---

## 🔒 Seguridad

**Después de usar el script:**
1. Elimina el archivo `limpiar-logs.php` del servidor (si usaste opción 2)
2. Elimina el cron temporal (si usaste opción 1)

---

## 📊 Resultado Esperado

**ANTES:**
```
mysql-logs/
├── 20251123_105002_001-crear-tabla-lugares-comerciales.sql.log
├── 20251123_105501_000-test-auto-executor.sql.log
├── 20251123_105501_001-crear-tabla-lugares-comerciales.sql.log
├── 20251123_110002_000-test-auto-executor.sql.log
├── ... (cientos de logs)
```

**DESPUÉS:**
```
mysql-logs/
└── ultimo-ejecutado.log
```

---

## ❓ Preguntas Frecuentes

### ¿Se eliminarán logs importantes?
No. El script **NUNCA** toca:
- `ultimo-ejecutado.log` (se mantiene para referencia)
- Logs de otros servicios
- Archivos en otras carpetas

### ¿Puedo ejecutarlo varias veces?
Sí, es seguro ejecutarlo múltiples veces. Si no hay logs antiguos, simplemente dirá que no hay nada que eliminar.

### ¿El problema se repetirá?
No. El `mysql-auto-executor.sh` ya está modificado para NO acumular más logs.

---

## 📞 Soporte

Si tienes dudas o problemas, contacta al desarrollador.
