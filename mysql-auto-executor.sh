#!/bin/bash
#
# ============================================
# MYSQL AUTO-EXECUTOR - Sistema Permanente
# ============================================
# Este script se ejecuta via CRON cada minuto
# y ejecuta automáticamente cualquier archivo
# SQL que encuentre en la carpeta pendientes/
#
# USO:
# 1. Sube un archivo .sql a: mysql-pendientes/
# 2. El cron lo detecta y ejecuta automáticamente
# 3. El archivo se mueve a: mysql-ejecutados/
# 4. Se genera un log en: mysql-logs/
# ============================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PENDIENTES_DIR="$SCRIPT_DIR/mysql-pendientes"
EJECUTADOS_DIR="$SCRIPT_DIR/mysql-ejecutados"
LOGS_DIR="$SCRIPT_DIR/mysql-logs"

# Crear directorios si no existen
mkdir -p "$PENDIENTES_DIR"
mkdir -p "$EJECUTADOS_DIR"
mkdir -p "$LOGS_DIR"

# Credenciales MySQL
DB_HOST="localhost"
DB_USER="comprati_places_user"
DB_PASS="Marden7i/"
DB_NAME="comprati_marketplace"

# Función para ejecutar SQL
ejecutar_sql() {
    local archivo=$1
    local nombre=$(basename "$archivo")
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    # LOGS DESHABILITADOS - solo mantener último log
    local log_file="$LOGS_DIR/ultimo-ejecutado.log"

    echo "========================================"
    echo "MySQL Auto-Executor"
    echo "========================================"
    echo "Archivo: $nombre"
    echo "Fecha: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "========================================"
    echo ""

    # Ejecutar SQL y guardar solo en último log (sobrescribe)
    {
        echo "========================================"
        echo "MySQL Auto-Executor - Última Ejecución"
        echo "========================================"
        echo "Archivo: $nombre"
        echo "Fecha: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "========================================"
        echo ""
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$archivo" 2>&1
        echo ""
        echo "========================================"
    } > "$log_file" 2>&1

    local exit_code=${PIPESTATUS[0]}

    echo ""
    echo "========================================"

    if [ $exit_code -eq 0 ]; then
        echo "✅ EJECUTADO EXITOSAMENTE"

        # Mover a ejecutados
        mv "$archivo" "$EJECUTADOS_DIR/${timestamp}_${nombre}"
        echo "📁 Archivo movido a: mysql-ejecutados/${timestamp}_${nombre}"
    else
        echo "❌ ERROR EN LA EJECUCIÓN (código: $exit_code)"
        echo "⚠️  Archivo permanece en mysql-pendientes/"
    fi

    echo "========================================"

    return $exit_code
}

# Buscar archivos SQL pendientes
archivos_encontrados=0

for archivo in "$PENDIENTES_DIR"/*.sql "$PENDIENTES_DIR"/*.txt; do
    # Verificar si el archivo existe (el glob puede no matchear nada)
    [ -e "$archivo" ] || continue

    archivos_encontrados=$((archivos_encontrados + 1))

    echo "🔍 Detectado: $(basename "$archivo")"
    ejecutar_sql "$archivo"
    echo ""
done

# Si no hay archivos, salir silenciosamente
if [ $archivos_encontrados -eq 0 ]; then
    exit 0
fi

echo "✅ Procesados $archivos_encontrados archivo(s)"
