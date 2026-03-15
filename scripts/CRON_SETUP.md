# 📅 Configuración de Cron para Importación Automática

## 🎯 Script Principal: `cron_import_all.php`

Este script ejecuta **SOLO las fuentes que funcionan**:

✅ **APIs Remotas** (Arbeitnow, Remotive, Jobicy)
✅ **Telegram** (STEMJobsCR, STEMJobsLATAM)
❌ **BAC** (Deshabilitado - no funciona)

---

## 🔧 Configuración en cPanel

### 1. Acceder a Cron Jobs
1. Ingresa a tu **cPanel**
2. Busca **"Cron Jobs"** en el buscador
3. Haz clic en **"Cron Jobs"**

### 2. Agregar Cron Job (2 veces al día)

**Ejecutar a las 8:00 AM y 6:00 PM todos los días:**

```
Minuto:   0
Hora:     8,18
Día:      *
Mes:      *
Día sem:  *
```

**Comando:**
```bash
cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
```

### 3. Alternativa: Línea completa de cron

Si tu cPanel permite pegar la línea completa:

```cron
0 8,18 * * *  cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
```

---

## 📊 Horarios Sugeridos

### Opción 1: 2 veces al día (Recomendado)
```cron
# 8:00 AM y 6:00 PM
0 8,18 * * *  cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
```

### Opción 2: 3 veces al día (Más frecuente)
```cron
# 8:00 AM, 2:00 PM y 8:00 PM
0 8,14,20 * * *  cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
```

### Opción 3: 1 vez al día (Menos frecuente)
```cron
# Solo 8:00 AM
0 8 * * *  cd /home/comprati/public_html && /usr/bin/php scripts/cron_import_all.php >> logs/cron.log 2>&1
```

---

## 📝 Verificar Logs

### Ver log principal
```bash
tail -f logs/cron_import_all.log
```

### Ver últimas 50 líneas
```bash
tail -50 logs/cron_import_all.log
```

### Ver log de errores del cron
```bash
tail -f logs/cron.log
```

---

## ✅ Verificar que Funciona

### Ejecutar manualmente para probar
```bash
cd /home/comprati/public_html
php scripts/cron_import_all.php
```

Deberías ver algo como:
```
╔════════════════════════════════════════════════════════════╗
║        COMPRATICA - IMPORTACIÓN AUTOMÁTICA DE EMPLEOS     ║
╚════════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▶ Ejecutando: APIs Remotas (Arbeitnow, Remotive, Jobicy)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ APIs Remotas completado en 5.2s
   📊 +12 nuevos empleos

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▶ Ejecutando: Telegram (STEMJobsCR, STEMJobsLATAM)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Telegram completado en 3.1s
   📊 +5 nuevos empleos

╔════════════════════════════════════════════════════════════╗
║                    RESUMEN DE IMPORTACIÓN                  ║
╚════════════════════════════════════════════════════════════╝

⏱️  Duración total: 8.3s
📊 Scripts ejecutados: 2/2 exitosos

✅ Remote apis: +12 nuevos empleos
✅ Telegram: +5 nuevos empleos

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏁 Importación finalizada
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 🚨 Solución de Problemas

### Error: "php: command not found"
Usa la ruta completa de PHP:
```bash
/usr/bin/php scripts/cron_import_all.php
# o
/usr/local/bin/php scripts/cron_import_all.php
```

Para encontrar la ruta correcta:
```bash
which php
```

### Error: Telegram no configurado
Crea el archivo `includes/telegram_config.php`:
```php
<?php
// Token del bot de Telegram (obtenlo de @BotFather)
define('TELEGRAM_BOT_TOKEN', 'TU_TOKEN_AQUI');

// Canales a importar (sin @)
define('TELEGRAM_CHANNELS', [
    'STEMJobsCR',      // Empleos CR
    'STEMJobsLATAM',   // Empleos remotos LATAM
]);
?>
```

### El cron no se ejecuta
1. Verifica que el usuario del cron sea correcto
2. Revisa los logs: `logs/cron.log`
3. Verifica permisos: `chmod +x scripts/cron_import_all.php`

---

## 📌 Importante

- ✅ **NO ejecuta BAC** (no funciona, está excluido)
- ✅ Maneja errores automáticamente
- ✅ Logs consolidados en un solo archivo
- ✅ No duplica empleos (deduplicación por URL)
- ✅ Ejecuta solo lo que funciona

---

## 🔍 Scripts Individuales (por si necesitas)

Si quieres ejecutar solo una fuente específica:

```bash
# Solo APIs remotas
php scripts/import_jobs.php

# Solo Telegram
php scripts/import_telegram_jobs.php
```

---

**Última actualización:** 2026-03-15
