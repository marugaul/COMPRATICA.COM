# 📋 Cómo Usar el Importador de Empleos

## ✅ Estado Actual

**41 empleos activos** importados de Telegram
Visibles en: https://compratica.com/empleos.php

---

## 🚀 Actualizar Empleos Manualmente

Ejecuta este comando cuando quieras buscar empleos nuevos:

```bash
bash scripts/actualizar_empleos.sh
```

O directamente:

```bash
php scripts/import_telegram_jobs.php
```

---

## ⏰ Automatizar con CRON (Recomendado)

Para que se actualice automáticamente cada día:

### Opción 1: Configuración Rápida

```bash
# Editar crontab
crontab -e

# Agregar esta línea (ejecuta diariamente a las 6 AM)
0 6 * * * cd /home/user/COMPRATICA.COM && bash scripts/actualizar_empleos.sh >> logs/empleos.log 2>&1
```

### Opción 2: Otras Frecuencias

**Cada 6 horas:**
```cron
0 */6 * * * cd /home/user/COMPRATICA.COM && bash scripts/actualizar_empleos.sh >> logs/empleos.log 2>&1
```

**Dos veces al día (6 AM y 6 PM):**
```cron
0 6,18 * * * cd /home/user/COMPRATICA.COM && bash scripts/actualizar_empleos.sh >> logs/empleos.log 2>&1
```

**Cada 12 horas:**
```cron
0 */12 * * * cd /home/user/COMPRATICA.COM && bash scripts/actualizar_empleos.sh >> logs/empleos.log 2>&1
```

---

## 📊 Ver Empleos Importados

```bash
php scripts/check_jobs.php
```

---

## 📝 Logs

Los logs se guardan en:
- `logs/import_telegram.log` - Log del importador
- `logs/empleos.log` - Log del CRON (si está configurado)

Ver últimas 20 líneas del log:
```bash
tail -20 logs/import_telegram.log
```

---

## 🔧 Canales de Telegram Configurados

Editados en `includes/telegram_config.php`:

- **STEMJobsCR** - Empleos STEM Costa Rica ⭐
- **STEMJobsLATAM** - Empleos remotos LATAM ⭐
- empleosti - Empleos TI Costa Rica
- empleoscr506 - Empleos generales CR
- remoteworkcr - Trabajo remoto CR

---

## ❓ FAQ

**¿Por qué dice "0 mensajes nuevos"?**
Porque ya importó todos los empleos disponibles. El script solo importa empleos que aún no están en la base de datos.

**¿Cómo agregar más canales?**
Edita `includes/telegram_config.php` y agrega el nombre del canal (sin @) en el array `TELEGRAM_CHANNELS`.

**¿Los empleos se borran solos?**
No, permanecen hasta que los desactives manualmente desde el admin.

**¿Puedo ejecutarlo varias veces?**
Sí, no hay problema. El script detecta duplicados y no los vuelve a importar.

---

## 📞 Soporte

Si hay problemas, revisa:
1. El log: `logs/import_telegram.log`
2. Que existe el usuario bot: `bot@compratica.com` en la base de datos
3. Que los canales de Telegram sean públicos
