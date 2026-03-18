# 🔧 Instrucciones para Configurar el Cron de Importación

## ❌ Problema Detectado

El cron está configurado pero **NO está ejecutando correctamente** el script de importación.

**Evidencia:**
- Los logs muestran `Content-type: text/html; charset=UTF-8` → esto indica que el cron intenta ejecutar como web
- Hay 216 empleos desde hace 3 días (sin actualizaciones)
- Al ejecutar manualmente el script, **SÍ funciona** (importó 154 nuevos empleos)

---

## ✅ Soluciones

### **Opción 1: Cron Shell (Recomendado para VPS/Dedicado)**

Si tienes acceso SSH completo, usa este comando en cPanel → Cron Jobs:

```bash
0 8,18 * * * /home/user/COMPRATICA.COM/scripts/cron_wrapper.sh
```

**Explicación:**
- `0 8,18 * * *` = Ejecuta a las 8:00 AM y 6:00 PM todos los días
- El wrapper automáticamente detecta la ruta de PHP y configura el entorno

---

### **Opción 2: Cron Web (Recomendado para cPanel/Shared Hosting)**

Si tu cron muestra headers HTML o no tienes acceso shell, usa el endpoint web:

#### 1. Obtén tu clave secreta

Ejecuta en SSH:
```bash
cd /home/user/COMPRATICA.COM
php -r "echo 'CLAVE: ' . ('compratica_cron_2024_' . md5('change_this_secret')) . \"\n\";"
```

#### 2. Configura el cron en cPanel

Ve a **cPanel → Cron Jobs** y agrega:

**Con wget:**
```bash
0 8,18 * * * wget -q -O /dev/null "https://compratica.com/cron_import.php?key=compratica_cron_2024_c96a32e9e65e4dad331a186fac2c3672"
```

**Con curl:**
```bash
0 8,18 * * * curl -s "https://compratica.com/cron_import.php?key=compratica_cron_2024_c96a32e9e65e4dad331a186fac2c3672"
```

⚠️ **IMPORTANTE:** Reemplaza `TU_CLAVE_AQUI` con la clave generada en el paso 1.

---

### **Opción 3: Cron PHP Directo (cPanel básico)**

Si las anteriores no funcionan, usa PHP directo:

```bash
0 8,18 * * * /usr/bin/php /home/user/COMPRATICA.COM/scripts/cron_import_all.php
```

O intenta con rutas alternativas de PHP en tu servidor:
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php82/root/usr/bin/php`
- `/opt/cpanel/ea-php81/root/usr/bin/php`

---

## 🔍 Verificar que Funciona

### 1. Ejecutar manualmente

```bash
cd /home/user/COMPRATICA.COM
php scripts/cron_import_all.php
```

Deberías ver:
```
╔════════════════════════════════════════════════════════════╗
║        COMPRATICA - IMPORTACIÓN AUTOMÁTICA DE EMPLEOS     ║
╚════════════════════════════════════════════════════════════╝

▶ Ejecutando: APIs Remotas (Arbeitnow, Remotive, Jobicy)
✅ APIs Remotas completado en X.Xs
   📊 +XX nuevos, XX duplicados

⏱️  Duración total: X.Xs
```

### 2. Revisar logs

```bash
tail -f logs/cron.log
tail -f logs/cron_import_all.log
tail -f logs/import_jobs.log
```

### 3. Verificar importaciones en la base de datos

```bash
php -r "require 'includes/db.php'; echo 'Empleos activos: ' . db()->query('SELECT COUNT(*) FROM job_listings WHERE is_active=1')->fetchColumn() . \"\n\";"
```

---

## 📊 Frecuencia Recomendada

```
# 2 veces al día (8 AM y 6 PM)
0 8,18 * * * [COMANDO]

# 4 veces al día (cada 6 horas)
0 */6 * * * [COMANDO]

# Una vez al día (8 AM)
0 8 * * * [COMANDO]
```

---

## 🐛 Troubleshooting

### El cron muestra "Content-type: text/html"
→ Usa **Opción 2** (Cron Web)

### Error: "php: command not found"
→ Especifica la ruta completa de PHP:
```bash
which php  # encuentra la ruta
/usr/bin/php scripts/cron_import_all.php
```

### Error: "Permission denied"
→ Da permisos de ejecución:
```bash
chmod +x scripts/cron_wrapper.sh
chmod +x scripts/cron_import_all.php
```

### No se crean logs
→ Crea el directorio:
```bash
mkdir -p logs
chmod 755 logs
```

### El cron ejecuta pero no importa nada
→ Revisa los logs de errores:
```bash
cat logs/cron_import_all.log
cat logs/import_jobs.log
```

---

## ✅ Status Actual (2026-03-18)

- ✅ Script de importación funciona correctamente
- ✅ Puede importar ~150 nuevos empleos por ejecución
- ✅ Logs directory creado
- ✅ Wrapper script creado
- ✅ Endpoint web creado
- ❌ Cron no está configurado correctamente (pendiente)

**Próximo paso:** Configurar el cron siguiendo las instrucciones arriba.
