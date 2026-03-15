#!/bin/bash
# Script para importar empleos de todas las fuentes automáticamente
# Ejecutar con: bash scripts/import_all_jobs.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/../logs"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] ==== Iniciando importación automática de empleos ===="

# Crear directorio de logs si no existe
mkdir -p "$LOG_DIR"

# 1. Importar empleos de BAC + Telegram (Costa Rica y LATAM)
echo "[$TIMESTAMP] Ejecutando importador de BAC + Telegram..."
php "$SCRIPT_DIR/import_bac_telegram.php" 2>&1

# 2. Importar empleos remotos internacionales
echo "[$TIMESTAMP] Ejecutando importador de empleos remotos..."
php "$SCRIPT_DIR/import_jobs.php" 2>&1

echo "[$TIMESTAMP] ==== Importación completada ===="
echo ""
