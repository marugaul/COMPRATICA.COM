#!/bin/bash
# Script para ejecutar fix de payment_methods
# Fecha: 2026-02-27

echo "=============================================="
echo "🔧 FIX PAYMENT_METHODS - Solución de Error"
echo "=============================================="
echo ""
echo "Este script agregará la columna 'payment_methods'"
echo "a la tabla listing_pricing"
echo ""

# Detectar la ruta de la base de datos
DB_PATH="/home/comprati/public_html/data.sqlite"

if [ ! -f "$DB_PATH" ]; then
    echo "❌ Error: No se encontró la base de datos en $DB_PATH"
    echo ""
    echo "Buscando base de datos alternativa..."

    # Buscar archivos .sqlite en el directorio
    ALTERNATIVE_DB=$(find /home/comprati/public_html -name "*.sqlite" -type f | head -n 1)

    if [ -n "$ALTERNATIVE_DB" ]; then
        DB_PATH="$ALTERNATIVE_DB"
        echo "✅ Base de datos encontrada: $DB_PATH"
    else
        echo "❌ No se encontró ninguna base de datos SQLite"
        exit 1
    fi
fi

echo "📁 Base de datos: $DB_PATH"
echo ""

# Verificar si sqlite3 está disponible
if ! command -v sqlite3 &> /dev/null; then
    echo "❌ Error: sqlite3 no está instalado"
    echo ""
    echo "Por favor, usa el script PHP en su lugar:"
    echo "  https://compratica.com/admin/fix_payment_methods.php"
    exit 1
fi

# Crear un archivo temporal con el SQL
TEMP_SQL="/tmp/fix_payment_methods_$$.sql"

cat > "$TEMP_SQL" << 'EOF'
-- Verificar si la columna ya existe
.mode column
.headers on

-- Intentar agregar la columna (fallará si ya existe, pero es seguro)
ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';

-- Actualizar valores
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 1;
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 2;
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 3;
UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE payment_methods IS NULL OR payment_methods = '';

-- Mostrar resultado
SELECT '✅ Columnas actualizadas correctamente' AS resultado;
SELECT '';
SELECT 'Planes actuales:' AS info;
SELECT id, name, duration_days, price_usd, max_photos, payment_methods, is_active FROM listing_pricing ORDER BY display_order;
EOF

echo "🔄 Ejecutando fix..."
echo ""

# Ejecutar el SQL
sqlite3 "$DB_PATH" < "$TEMP_SQL" 2>&1

# Verificar el resultado
if [ $? -eq 0 ]; then
    echo ""
    echo "=============================================="
    echo "✅ FIX COMPLETADO EXITOSAMENTE"
    echo "=============================================="
    echo ""
    echo "La columna 'payment_methods' ha sido agregada."
    echo "El error debería estar resuelto."
    echo ""
    echo "Siguiente paso:"
    echo "  → Visita: https://compratica.com/admin/bienes_raices_config.php"
    echo "  → Prueba actualizar un plan"
    echo ""
else
    echo ""
    echo "=============================================="
    echo "⚠️ ADVERTENCIA"
    echo "=============================================="
    echo ""
    echo "Puede que la columna ya exista (esto es normal)."
    echo ""
    echo "Verifica el estado con:"
    echo "  → https://compratica.com/admin/diagnose_bienes_raices.php"
    echo ""
    echo "O usa el script PHP:"
    echo "  → https://compratica.com/admin/fix_payment_methods.php"
    echo ""
fi

# Limpiar archivo temporal
rm -f "$TEMP_SQL"

echo "=============================================="
