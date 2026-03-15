#!/bin/bash
# Script para configurar el CRON job de importación de empleos

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== Configurador de CRON para Importación de Empleos ==="
echo ""
echo "Este script configurará un CRON job para ejecutar el importador de empleos automáticamente."
echo ""

# Detectar el intérprete de PHP
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "❌ Error: PHP no encontrado. Instala PHP primero."
    exit 1
fi

echo "✓ PHP encontrado en: $PHP_PATH"
echo ""

# Mostrar opciones de frecuencia
echo "Selecciona la frecuencia de ejecución:"
echo "1) Cada 6 horas (recomendado)"
echo "2) Cada 12 horas"
echo "3) Una vez al día (6:00 AM)"
echo "4) Dos veces al día (6:00 AM y 6:00 PM)"
echo "5) Manual (no configurar CRON)"
echo ""
read -p "Opción (1-5): " OPTION

case $OPTION in
    1)
        CRON_SCHEDULE="0 */6 * * *"
        DESCRIPTION="cada 6 horas"
        ;;
    2)
        CRON_SCHEDULE="0 */12 * * *"
        DESCRIPTION="cada 12 horas"
        ;;
    3)
        CRON_SCHEDULE="0 6 * * *"
        DESCRIPTION="diariamente a las 6:00 AM"
        ;;
    4)
        CRON_SCHEDULE="0 6,18 * * *"
        DESCRIPTION="dos veces al día (6:00 AM y 6:00 PM)"
        ;;
    5)
        echo "No se configurará CRON. Ejecuta manualmente:"
        echo "  bash $SCRIPT_DIR/import_all_jobs.sh"
        exit 0
        ;;
    *)
        echo "❌ Opción inválida"
        exit 1
        ;;
esac

# Crear línea de CRON
CRON_JOB="$CRON_SCHEDULE $SCRIPT_DIR/import_all_jobs.sh >> $SCRIPT_DIR/../logs/cron_import.log 2>&1"

echo ""
echo "Se agregará la siguiente línea al crontab:"
echo "$CRON_JOB"
echo ""
read -p "¿Continuar? (s/n): " CONFIRM

if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
    echo "Cancelado."
    exit 0
fi

# Agregar al crontab
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

echo ""
echo "✓ CRON job configurado exitosamente!"
echo "  Frecuencia: $DESCRIPTION"
echo "  Log: $SCRIPT_DIR/../logs/cron_import.log"
echo ""
echo "Para ver el crontab actual:"
echo "  crontab -l"
echo ""
echo "Para ejecutar manualmente:"
echo "  bash $SCRIPT_DIR/import_all_jobs.sh"
echo ""
