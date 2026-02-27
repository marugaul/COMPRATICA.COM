# 🚀 Guía de Despliegue a Producción

## Cambios Recientes

### Migraciones de Base de Datos
1. **`max_photos` y `payment_methods`** en `listing_pricing` (Bienes Raíces)
2. **`pricing_plan_id`** en `job_listings` (Empleos y Servicios)

---

## 📋 Despliegue en Producción

### Opción 1: Script Automático (Recomendado)

En tu servidor de producción (`/home/comprati/public_html/`):

```bash
cd /home/comprati/public_html
chmod +x deploy-to-production.sh
./deploy-to-production.sh
```

### Opción 2: Manual

#### Paso 1: Obtener cambios
```bash
cd /home/comprati/public_html
git pull origin claude/casual-greeting-0XTiR
```

#### Paso 2: Ejecutar migraciones
```bash
# Migración 1: max_photos para Bienes Raíces
php migrations/run_migration.php

# Migración 2: pricing_plan_id para Empleos y Servicios
php migrations/add_pricing_plan_to_job_listings.php
```

#### Paso 3: Limpiar caché
Desde el navegador, ejecuta:
```
https://compratica.com/admin/clear_production_cache.php
```

---

## ✅ Verificación

Después del despliegue, verifica que funcionen:

1. **Bienes Raíces:** https://compratica.com/admin/bienes_raices_config.php
2. **Servicios:** https://compratica.com/admin/servicios_config.php
3. **Empleos:** https://compratica.com/admin/empleos_config.php

Todos deben cargar sin errores de "no such column".

---

## 🐛 Si hay problemas

1. **Error "no such column":**
   - Ejecuta: `https://compratica.com/admin/clear_production_cache.php`
   - Verifica que las migraciones se ejecutaron correctamente

2. **Error de permisos:**
   ```bash
   chmod 664 data.sqlite
   chown www-data:www-data data.sqlite
   ```

3. **Caché persistente:**
   - Reinicia PHP-FPM: `sudo systemctl restart php-fpm`
   - O reinicia Apache: `sudo systemctl restart apache2`

---

## 📊 Archivos Nuevos

- `admin/clear_production_cache.php` - Limpia OPcache y verifica BD
- `admin/clear_cache_and_test.php` - Diagnóstico de max_photos
- `admin/test_max_photos.php` - Test de columnas de bienes raíces
- `migrations/run_migration.php` - Migración de max_photos
- `migrations/add_pricing_plan_to_job_listings.php` - Migración de pricing_plan_id

---

## 🔍 Verificar Migraciones

```sql
-- Verificar listing_pricing (Bienes Raíces)
PRAGMA table_info(listing_pricing);
-- Debe incluir: max_photos, payment_methods

-- Verificar job_listings (Empleos y Servicios)
PRAGMA table_info(job_listings);
-- Debe incluir: pricing_plan_id
```
