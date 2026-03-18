#!/bin/bash
# ============================================================================
# scripts/cron_wrapper.sh
# ============================================================================
# Wrapper para ejecutar el importador de empleos desde cron
# Asegura que el entorno esté correctamente configurado
# ============================================================================

# Cambiar al directorio del proyecto
cd "$(dirname "$0")/.." || exit 1

# Detectar la ruta de PHP
PHP_BIN=$(which php 2>/dev/null)
if [ -z "$PHP_BIN" ]; then
    # Intentar rutas comunes en cPanel/shared hosting
    for path in /usr/bin/php /usr/local/bin/php /opt/cpanel/ea-php82/root/usr/bin/php /opt/cpanel/ea-php81/root/usr/bin/php; do
        if [ -x "$path" ]; then
            PHP_BIN="$path"
            break
        fi
    done
fi

if [ -z "$PHP_BIN" ]; then
    echo "[ERROR] No se encontró PHP" >> logs/cron_error.log
    exit 1
fi

# Crear directorio de logs si no existe
mkdir -p logs

# Ejecutar el script de importación
echo "========================================" >> logs/cron.log
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando importación" >> logs/cron.log
echo "PHP: $PHP_BIN" >> logs/cron.log
echo "PWD: $(pwd)" >> logs/cron.log
echo "========================================" >> logs/cron.log

$PHP_BIN scripts/cron_import_all.php >> logs/cron.log 2>&1
EXIT_CODE=$?

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Finalizado con código: $EXIT_CODE" >> logs/cron.log
echo "" >> logs/cron.log

exit $EXIT_CODE
