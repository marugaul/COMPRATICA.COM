#!/bin/bash
# Script para importar SOLO empleos de BAC y Telegram
# Ejecutar: bash scripts/import_bac_telegram.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/../logs/import_bac_telegram.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Función para log
log_msg() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

# Crear directorio de logs si no existe
mkdir -p "$SCRIPT_DIR/../logs"

log_msg "==== INICIO: Importación BAC + Telegram ===="

# 1. Importar empleos del BAC
log_msg "Iniciando: BAC Credomatic (Talento360)"
php "$SCRIPT_DIR/import_bac_jobs.php" 2>&1 | tee -a "$LOG_FILE"

log_msg ""

# 2. Importar empleos de Telegram
log_msg "Iniciando: Telegram (STEMJobsCR + STEMJobsLATAM)"
php "$SCRIPT_DIR/import_telegram_jobs.php" 2>&1 | tee -a "$LOG_FILE"

log_msg ""
log_msg "==== FIN: Importación completada ===="
log_msg ""
