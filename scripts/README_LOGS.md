# Logs de Importación de Empleos

Este documento describe los diferentes archivos de log generados por los scripts de importación.

## Ubicación de Logs

Todos los logs se guardan en: `/logs/`

Puedes verlos en cPanel → File Manager → logs/

## Archivos de Log Disponibles

### 1. `import_bac_telegram.log`
**Importaciones de Costa Rica y LATAM (STEM)**

Fuentes:
- BAC Credomatic (Talento360) — Empleos del banco BAC en Costa Rica
- Telegram (STEMJobsCR + STEMJobsLATAM) — Empleos STEM de canales de Telegram

Script: `scripts/import_bac_telegram.php`

Formato del log:
```
[2026-03-14 22:46:27] ==== Iniciando importación de BAC + Telegram ====
[2026-03-14 22:46:27] Iniciando: BAC Credomatic (Talento360)
[2026-03-14 22:46:29]   BAC Credomatic (Talento360): +0 nuevos, 0 duplicados
[2026-03-14 22:46:29] Iniciando: Telegram (STEMJobsCR + STEMJobsLATAM)
[2026-03-14 22:46:33]   Telegram (STEMJobsCR + STEMJobsLATAM): +2 nuevos, 0 duplicados
[2026-03-14 22:46:33] === TOTAL: +2 insertados | 0 duplicados | 0 errores ===
```

### 2. `import_jobs.log`
**Importaciones de empleos remotos internacionales**

Fuentes:
- Arbeitnow — Empleos remotos globales
- Remotive — Empleos remotos globales
- Jobicy — Empleos remotos globales

Script: `scripts/import_jobs.php`

Formato del log:
```
[2026-03-14 22:30:21] Iniciando: Arbeitnow — Remote Jobs
[2026-03-14 22:30:21]   Arbeitnow — Remote Jobs: +0 nuevos, 100 duplicados
[2026-03-14 22:30:22] Iniciando: Remotive — Remote Jobs
[2026-03-14 22:30:23]   Remotive — Remote Jobs: +0 nuevos, 22 duplicados
[2026-03-14 22:30:24] Iniciando: Jobicy — Remote Jobs
[2026-03-14 22:30:25]   Jobicy — Remote Jobs: +0 nuevos, 50 duplicados
[2026-03-14 22:30:26] === TOTAL: +0 insertados | 172 duplicados | 0 errores ===
```

### 3. `import_bac.log` (detallado)
Log detallado técnico del importador de BAC con información de debugging.

### 4. `import_telegram.log` (detallado)
Log detallado técnico del importador de Telegram con información de debugging.

## Comandos para Ver Logs

### Ver log de BAC + Telegram:
```bash
tail -20 logs/import_bac_telegram.log
```

### Ver log de empleos remotos:
```bash
tail -20 logs/import_jobs.log
```

### Ver todos los logs en tiempo real:
```bash
tail -f logs/import_*.log
```

### Ver solo las últimas importaciones exitosas:
```bash
grep "TOTAL:" logs/import_*.log | tail -10
```

## Ejecución Manual

### Importar solo BAC + Telegram:
```bash
php scripts/import_bac_telegram.php
```

### Importar solo empleos remotos:
```bash
php scripts/import_jobs.php
```

### Importar todo:
```bash
bash scripts/import_all_jobs.sh
```

## Configuración de Cron

Para ejecutar automáticamente cada día:

1. Ve a cPanel → Cron Jobs
2. Agrega un nuevo cron job:

**Diario a las 8:00 AM (BAC + Telegram):**
```
0 8 * * * php /home/TUUSUARIO/public_html/scripts/import_bac_telegram.php
```

**Diario a las 9:00 AM (Empleos remotos):**
```
0 9 * * * php /home/TUUSUARIO/public_html/scripts/import_jobs.php
```

**O ejecutar todo junto a las 8:00 AM:**
```
0 8 * * * bash /home/TUUSUARIO/public_html/scripts/import_all_jobs.sh
```

## Monitoreo

Los logs incluyen:
- ✅ Timestamp de cada operación
- ✅ Fuente de la importación
- ✅ Número de empleos nuevos insertados
- ✅ Número de duplicados omitidos
- ✅ Número de errores
- ✅ Total consolidado

## Problemas Comunes

### No se importa nada de BAC
- BAC requiere scraping web y puede estar bloqueado
- Verifica `logs/import_bac.log` para detalles técnicos
- Es normal que a veces no encuentre empleos

### No se importa nada de Telegram
- Verifica que existe `includes/telegram_config.php`
- Verifica que los canales estén configurados correctamente
- Verifica `logs/import_telegram.log` para detalles

### Muchos duplicados
- Es normal, significa que los empleos ya existen en la base de datos
- El sistema previene duplicados automáticamente

## Soporte

Para más información, consulta:
- `scripts/README_IMPORTADORES.md` — Documentación técnica de importadores
- `INSTRUCCIONES_EMPLEOS.md` — Guía general de empleos
