#!/bin/bash
#
# ============================================
# LIMPIADOR DE LOGS MYSQL
# ============================================
# Este script elimina todos los logs antiguos
# generados por mysql-auto-executor.sh
# ============================================

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
LOGS_DIR="$SCRIPT_DIR/mysql-logs"

echo "========================================"
echo "🧹 Limpiador de Logs MySQL"
echo "========================================"
echo ""

# Contar logs antes de eliminar
total_logs=$(find "$LOGS_DIR" -type f -name "*.log" ! -name "ultimo-ejecutado.log" 2>/dev/null | wc -l)

if [ "$total_logs" -eq 0 ]; then
    echo "✅ No hay logs antiguos para eliminar"
    echo ""
    exit 0
fi

echo "📊 Logs encontrados: $total_logs"
echo ""
echo "🗑️  Eliminando logs antiguos..."

# Eliminar todos los logs excepto ultimo-ejecutado.log
find "$LOGS_DIR" -type f -name "*.log" ! -name "ultimo-ejecutado.log" -delete

echo "✅ Logs eliminados exitosamente"
echo ""

# Verificar espacio liberado
if [ -d "$LOGS_DIR" ]; then
    espacio=$(du -sh "$LOGS_DIR" 2>/dev/null | cut -f1)
    echo "📁 Espacio actual en mysql-logs/: $espacio"
fi

echo ""
echo "========================================"
echo "✅ Limpieza completada"
echo "========================================"
