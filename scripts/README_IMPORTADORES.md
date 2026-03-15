# Importadores de Empleos - Guía

## Estado Actual

### ✅ Telegram - FUNCIONA
**Estado**: Operativo
**Empleos importados**: 41
**Script**: `scripts/import_telegram_jobs.php`

El importador de Telegram funciona correctamente y obtiene empleos de:
- @STEMJobsCR (Empleos STEM en Costa Rica)
- @STEMJobsLATAM (Empleos remotos en Latinoamérica)

**Ejecución**:
```bash
php scripts/import_telegram_jobs.php
```

**Nota**: El script solo importa empleos **nuevos**. Si no encuentra empleos nuevos, es porque ya procesó todos los mensajes disponibles en los canales.

### ❌ BAC Credomatic - NO FUNCIONA
**Estado**: Bloqueado
**Empleos importados**: 0
**Script**: `scripts/import_bac_jobs.php`

**Problema**:
El sitio de BAC Talento360 (https://talento360.csod.com) usa una aplicación de página única (SPA) que:
1. Carga empleos dinámicamente con JavaScript
2. Requiere autenticación JWT generada en el navegador
3. Tiene protección anti-scraping

**Alternativas**:
1. **Contactar a BAC**: Preguntar si tienen un RSS feed oficial o API pública
2. **Agregar empleos manualmente**: Desde el admin de CompraTica
3. **Usar otras fuentes**: Telegram tiene buenos empleos de bancos y empresas grandes

## Agregar Más Fuentes de Empleos

### Canales de Telegram Recomendados

Edita `includes/telegram_config.php` y agrega más canales:

```php
define('TELEGRAM_CHANNELS', [
    'STEMJobsCR',           // Empleos STEM Costa Rica
    'STEMJobsLATAM',        // Empleos remotos LATAM
    'empleos_tech_cr',      // Empleos tech CR (si existe)
    'trabajoscr',           // Empleos generales CR (si existe)
]);
```

### Otras Fuentes Públicas

1. **LinkedIn Jobs** - Requiere scraping con precaución
2. **Indeed Costa Rica** - Tiene RSS feeds
3. **Computrabajo** - Tiene RSS feeds
4. **TeColoco** - API o scraping web

## Automatización con CRON

Para ejecutar los importadores automáticamente cada día:

1. Edita el crontab:
```bash
crontab -e
```

2. Agrega esta línea (ejecuta diario a las 6 AM):
```cron
0 6 * * * /usr/bin/php /home/user/COMPRATICA.COM/scripts/import_telegram_jobs.php >> /home/user/COMPRATICA.COM/logs/cron_import.log 2>&1
```

3. Para ejecutar cada 6 horas:
```cron
0 */6 * * * /usr/bin/php /home/user/COMPRATICA.COM/scripts/import_telegram_jobs.php >> /home/user/COMPRATICA.COM/logs/cron_import.log 2>&1
```

## Verificar Empleos Importados

```bash
# Ver empleos por fuente
php -r "
require 'includes/db.php';
\$pdo = db();
\$stmt = \$pdo->query(\"
    SELECT
        CASE
            WHEN import_source LIKE 'Telegram%' THEN 'Telegram'
            ELSE 'Otros'
        END as fuente,
        COUNT(*) as total
    FROM job_listings
    WHERE import_source IS NOT NULL
    GROUP BY fuente
\");
while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo \$row['fuente'] . ': ' . \$row['total'] . \" empleos\n\";
}
"
```

## Logs

- **Telegram**: `logs/import_telegram.log`
- **BAC**: `logs/import_bac.log`
- **Estado de Telegram**: `logs/telegram_state.json`

## Soporte

Si tienes problemas:
1. Revisa los logs en `logs/`
2. Verifica que `includes/telegram_config.php` existe
3. Ejecuta manualmente: `php scripts/import_telegram_jobs.php`
4. Revisa que el usuario bot existe en la base de datos (email: bot@compratica.com)
