# 🤖 Sistema de Importación Automática de Empleos

## 📋 Instalación (Ejecutar UNA vez)

### Paso 1: Ejecutar el SQL en tu base de datos

**Opción A - Desde cPanel (phpLiteAdmin):**
1. Ve a cPanel → File Manager
2. Abre `data/compratica.db` con phpLiteAdmin
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de `migrations/setup_import_jobs_system.sql`
5. Haz clic en "Ejecutar"

**Opción B - Desde SSH:**
```bash
cd /home/TUUSUARIO/public_html
sqlite3 data/compratica.db < migrations/setup_import_jobs_system.sql
```

**Opción C - Desde el navegador (SQL Tools):**
1. Ve a `https://compratica.com/tools/sql_exec.php`
2. Copia y pega el contenido de `migrations/setup_import_jobs_system.sql`
3. Haz clic en "Ejecutar"

### Paso 2: Verificar la instalación

Ejecuta este SQL para verificar:

```sql
-- 1. Verificar usuario bot
SELECT id, email, name FROM users WHERE email = 'bot@compratica.com';
-- Debe devolver: id, bot@compratica.com, CompraTica Empleos

-- 2. Verificar columnas nuevas en job_listings
PRAGMA table_info(job_listings);
-- Debe incluir: import_source, source_url

-- 3. Verificar tabla job_import_log existe
SELECT COUNT(*) FROM job_import_log;
-- Debe devolver: 0 (sin errores)
```

## 🚀 Uso

### Acceder al Panel de Importación

```
https://compratica.com/admin/import_jobs.php
```

### Ejecutar Importación Manual

1. Accede al panel
2. Selecciona la fuente:
   - **Empleos Remotos** (recomendado) - Arbeitnow, Remotive, Jobicy
   - **Todas las fuentes** - Importa de todas
3. Haz clic en "Importar ahora"
4. Observa el progreso en tiempo real

### Configurar Importación Automática (Cron)

**En cPanel → Cron Jobs:**

```bash
# Cada día a las 6 AM
0 6 * * * php /home/TUUSUARIO/public_html/scripts/import_jobs.php >> /home/TUUSUARIO/public_html/logs/import_jobs.log 2>&1

# Cada 12 horas (6 AM y 6 PM) - MÁS EMPLEOS FRESCOS
0 6,18 * * * php /home/TUUSUARIO/public_html/scripts/import_jobs.php >> /home/TUUSUARIO/public_html/logs/import_jobs.log 2>&1
```

**Reemplaza `TUUSUARIO` con tu usuario de cPanel** (ejemplo: `comprati`)

## 📊 ¿Qué Hace el Sistema?

### Fuentes de Empleos (GRATIS, sin API key):

1. **Arbeitnow** - Empleos remotos de tecnología
2. **Remotive** - Empleos remotos globales
3. **Jobicy** - Empleos remotos de desarrollo

### Características:

✅ **Sin duplicados** - Verifica por URL única
✅ **Expiración automática** - Empleos se desactivan a los 30 días
✅ **Usuario bot** - Se publican bajo "CompraTica Empleos"
✅ **Log detallado** - Cada importación se registra
✅ **Gestión por fuente** - Activa/desactiva empleos por origen

## 🔍 Diagnóstico de Problemas

### Problema: "Usuario bot no existe"

**Causa:** El usuario bot no se creó correctamente

**Solución:**
```sql
INSERT OR IGNORE INTO users (email, name, password_hash, role, is_active)
VALUES ('bot@compratica.com', 'CompraTica Empleos', '$2y$10$dummy', 'user', 1);
```

### Problema: "Log vacío" o "No importa nada"

**Diagnóstico paso a paso:**

1. **Verificar que las tablas existen:**
```sql
SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%job%';
```

2. **Verificar permisos del directorio logs:**
```bash
chmod 755 logs/
touch logs/import_jobs.log
chmod 666 logs/import_jobs.log
```

3. **Ejecutar importación en modo test:**
```bash
php scripts/import_jobs.php --dry-run
```

4. **Ver errores PHP:**
```bash
php scripts/import_jobs.php 2>&1 | tail -50
```

### Problema: "Error de cURL" o "No se pudo descargar"

**Causa:** Servidor bloqueando conexiones externas

**Solución:**
```bash
# Verificar que cURL funciona
php -r "echo function_exists('curl_init') ? 'cURL OK' : 'cURL NO disponible';"

# Probar descarga manual
curl -I https://www.arbeitnow.com/api/job-board-api
```

## 📁 Archivos del Sistema

```
migrations/
  └─ setup_import_jobs_system.sql     ← Migración SQL (ejecutar una vez)

scripts/
  └─ import_jobs.php                  ← Script de importación (cron lo ejecuta)

admin/
  ├─ import_jobs.php                  ← Panel de administración
  └─ import_runner.php                ← Endpoint de streaming

logs/
  └─ import_jobs.log                  ← Log de importaciones (se crea automático)
```

## 🎯 Flujo de Trabajo

```
┌─────────────────┐
│   CRON Job      │  Ejecuta cada 6-12 horas
│  (Automático)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ import_jobs.php │  Descarga empleos de APIs
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Base de Datos  │  Inserta empleos nuevos
│  job_listings   │  Omite duplicados
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   import_log    │  Registra estadísticas
└─────────────────┘
```

## 🔗 Enlaces Útiles

- **Panel Admin:** `https://compratica.com/admin/import_jobs.php`
- **Ver Empleos:** `https://compratica.com/empleos.php`
- **SQL Tools:** `https://compratica.com/tools/sql_exec.php`

## 📞 Soporte

Si algo no funciona:
1. Revisa el log: `logs/import_jobs.log`
2. Verifica el panel: `admin/import_jobs.php`
3. Ejecuta el SQL de verificación (ver arriba)
4. Prueba en modo `--dry-run` primero
