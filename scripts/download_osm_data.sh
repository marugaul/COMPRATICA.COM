#!/bin/bash
# Script para descargar datos de OpenStreetMap de Costa Rica
# y extraer hoteles, restaurantes y bares

echo "📥 Descargando datos de Costa Rica desde OpenStreetMap..."

# Descargar archivo PBF de Costa Rica
wget -O costa-rica-latest.osm.pbf https://download.geofabrik.de/central-america/costa-rica-latest.osm.pbf

echo "✅ Descarga completada: costa-rica-latest.osm.pbf"
echo "📊 Tamaño aproximado: 35 MB"
echo ""
echo "Próximos pasos:"
echo "1. Instalar osmium-tool: apt-get install osmium-tool"
echo "2. Extraer lugares: osmium tags-filter costa-rica-latest.osm.pbf n/amenity=restaurant,bar n/tourism=hotel -o lugares.osm.pbf"
echo "3. Convertir a CSV con script Python"
