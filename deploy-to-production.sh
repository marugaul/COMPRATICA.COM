#!/bin/bash
# Script para desplegar cambios a producción
# Ejecuta este script en tu servidor de producción

echo "================================"
echo "🚀 DESPLIEGUE A PRODUCCIÓN"
echo "================================"
echo ""

# 1. Hacer pull de los últimos cambios
echo "📥 1. Obteniendo últimos cambios..."
git pull origin claude/casual-greeting-0XTiR
if [ $? -ne 0 ]; then
    echo "❌ Error al hacer git pull"
    exit 1
fi
echo "✅ Cambios obtenidos"
echo ""

# 2. Ejecutar migraciones
echo "🔄 2. Ejecutando migraciones..."

# Migración para max_photos en listing_pricing
if [ -f "migrations/run_migration.php" ]; then
    echo "   - Ejecutando migración de max_photos..."
    php migrations/run_migration.php
fi

# Migración para pricing_plan_id en job_listings
if [ -f "migrations/add_pricing_plan_to_job_listings.php" ]; then
    echo "   - Ejecutando migración de pricing_plan_id..."
    php migrations/add_pricing_plan_to_job_listings.php
fi

echo "✅ Migraciones completadas"
echo ""

# 3. Limpiar caché de OPcache
echo "🧹 3. Limpiando caché..."
if [ -f "admin/clear_production_cache.php" ]; then
    php admin/clear_production_cache.php > /tmp/cache_clear.log 2>&1
    if grep -q "TODO RESUELTO\|TODO FUNCIONA" /tmp/cache_clear.log; then
        echo "✅ Caché limpiado exitosamente"
    else
        echo "⚠️  Verifica manualmente: admin/clear_production_cache.php"
    fi
else
    echo "⚠️  Archivo de limpieza de caché no encontrado"
fi
echo ""

echo "================================"
echo "✅ DESPLIEGUE COMPLETADO"
echo "================================"
echo ""
echo "📋 Próximos pasos:"
echo "   1. Verifica: https://tu-sitio.com/admin/clear_production_cache.php"
echo "   2. Prueba: admin/servicios_config.php"
echo "   3. Prueba: admin/empleos_config.php"
echo "   4. Prueba: admin/bienes_raices_config.php"
echo ""
