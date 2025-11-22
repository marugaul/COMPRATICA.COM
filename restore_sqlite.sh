#!/bin/bash
# Script para restaurar data.sqlite desde el backup
# Ejecutar SOLO en el servidor (con acceso a /home/comprati)

SOURCE="/home/comprati/compratica_repo/data.sqlite"
DEST="/home/comprati/public_html/data.sqlite"

echo "🔄 Restaurando base de datos SQLite..."

if [ ! -f "$SOURCE" ]; then
    echo "❌ ERROR: No se encuentra el archivo backup en: $SOURCE"
    exit 1
fi

echo "✓ Archivo backup encontrado: $SOURCE"
echo "  Tamaño: $(du -h "$SOURCE" | cut -f1)"

if [ -f "$DEST" ]; then
    echo "⚠️  Ya existe data.sqlite en destino. Creando backup..."
    cp "$DEST" "$DEST.bak.$(date +%Y%m%d_%H%M%S)"
    echo "✓ Backup creado: $DEST.bak.*"
fi

echo "📋 Copiando archivo..."
cp "$SOURCE" "$DEST"

if [ $? -eq 0 ]; then
    chmod 644 "$DEST"
    echo "✅ Base de datos restaurada exitosamente en: $DEST"
    echo "  Tamaño: $(du -h "$DEST" | cut -f1)"

    # Verificar contenido
    echo ""
    echo "📊 Verificando tablas..."
    sqlite3 "$DEST" ".tables" 2>/dev/null || echo "⚠️  No se pudo verificar (sqlite3 no disponible)"
else
    echo "❌ ERROR al copiar el archivo"
    exit 1
fi

echo ""
echo "✓ Proceso completado. Verifica que el sitio funcione correctamente."
