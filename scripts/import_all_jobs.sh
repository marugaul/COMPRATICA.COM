#!/bin/bash
# Script para importar empleos de todas las fuentes automáticamente
# Ejecutar con: bash scripts/import_all_jobs.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/../logs"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] ==== Iniciando importación automática de empleos ===="

# Crear directorio de logs si no existe
mkdir -p "$LOG_DIR"

# 1. Importar empleos de Telegram
echo "[$TIMESTAMP] Ejecutando importador de Telegram..."
php "$SCRIPT_DIR/import_telegram_jobs.php" 2>&1

# 2. Intentar importar de BAC (experimental)
# echo "[$TIMESTAMP] Ejecutando importador de BAC..."
# php "$SCRIPT_DIR/import_bac_jobs.php" 2>&1

# 3. Agregar más importadores aquí según sea necesario
# php "$SCRIPT_DIR/import_indeed_jobs.php" 2>&1
# php "$SCRIPT_DIR/import_computrabajo_jobs.php" 2>&1

echo "[$TIMESTAMP] ==== Importación completada ===="
echo ""
