"""
Script para procesar datos de OpenStreetMap y extraer
hoteles, restaurantes y bares de Costa Rica
"""

import subprocess
import json
import csv
import os

def extract_places_from_osm():
    """
    Extrae lugares de interés del archivo OSM usando overpass
    """

    # Query Overpass API para Costa Rica
    overpass_query = """
    [out:json][timeout:120];
    area["name"="Costa Rica"]->.a;
    (
      node["amenity"="restaurant"](area.a);
      node["amenity"="bar"](area.a);
      node["tourism"="hotel"](area.a);
      node["tourism"="guest_house"](area.a);
      way["amenity"="restaurant"](area.a);
      way["amenity"="bar"](area.a);
      way["tourism"="hotel"](area.a);
    );
    out center;
    """

    print("🌍 Consultando Overpass API de OpenStreetMap...")
    print("⏱️  Esto puede tomar 1-2 minutos...")

    # Usar Overpass API
    import requests

    overpass_url = "http://overpass-api.de/api/interpreter"
    response = requests.get(overpass_url, params={'data': overpass_query})

    if response.status_code == 200:
        data = response.json()
        print(f"✅ Descargados {len(data['elements'])} lugares")
        return data['elements']
    else:
        print(f"❌ Error: {response.status_code}")
        return []

def process_places(places):
    """
    Procesa los lugares y extrae información relevante
    """
    processed = []

    for place in places:
        tags = place.get('tags', {})

        # Extraer información
        item = {
            'nombre': tags.get('name', 'Sin nombre'),
            'tipo': tags.get('amenity') or tags.get('tourism', 'unknown'),
            'direccion': tags.get('addr:street', '') + ' ' + tags.get('addr:housenumber', ''),
            'ciudad': tags.get('addr:city', ''),
            'provincia': tags.get('addr:province', ''),
            'telefono': tags.get('phone', ''),
            'email': tags.get('email', ''),
            'website': tags.get('website', ''),
            'lat': place.get('lat') or place.get('center', {}).get('lat', ''),
            'lon': place.get('lon') or place.get('center', {}).get('lon', ''),
        }

        processed.append(item)

    return processed

def save_to_csv(places, filename='lugares_costa_rica.csv'):
    """
    Guarda los lugares en un archivo CSV
    """
    if not places:
        print("⚠️  No hay datos para guardar")
        return

    print(f"💾 Guardando {len(places)} lugares en {filename}...")

    with open(filename, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=places[0].keys())
        writer.writeheader()
        writer.writerows(places)

    print(f"✅ Archivo guardado: {filename}")

    # Estadísticas
    with_phone = sum(1 for p in places if p['telefono'])
    with_email = sum(1 for p in places if p['email'])
    with_website = sum(1 for p in places if p['website'])

    print("\n📊 Estadísticas:")
    print(f"   Total lugares: {len(places)}")
    print(f"   Con teléfono: {with_phone} ({with_phone/len(places)*100:.1f}%)")
    print(f"   Con email: {with_email} ({with_email/len(places)*100:.1f}%)")
    print(f"   Con website: {with_website} ({with_website/len(places)*100:.1f}%)")

def create_sql_import(csv_file, table_name='lugares_comerciales'):
    """
    Genera script SQL para importar a MySQL
    """
    sql_file = 'import_lugares.sql'

    sql = f"""-- Crear tabla para lugares comerciales
CREATE TABLE IF NOT EXISTS {table_name} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo ENUM('restaurant', 'bar', 'hotel', 'guest_house', 'unknown') DEFAULT 'unknown',
    direccion VARCHAR(500),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    telefono VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_ciudad (ciudad),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Importar datos desde CSV
-- Ejecutar desde MySQL:
-- LOAD DATA LOCAL INFILE '{csv_file}'
-- INTO TABLE {table_name}
-- FIELDS TERMINATED BY ','
-- ENCLOSED BY '"'
-- LINES TERMINATED BY '\\n'
-- IGNORE 1 ROWS
-- (nombre, tipo, direccion, ciudad, provincia, telefono, email, website, latitud, longitud);
"""

    with open(sql_file, 'w', encoding='utf-8') as f:
        f.write(sql)

    print(f"✅ Script SQL generado: {sql_file}")

if __name__ == "__main__":
    print("🏨 Extractor de Hoteles, Restaurantes y Bares de Costa Rica")
    print("=" * 60)

    # Paso 1: Extraer datos de OSM
    places = extract_places_from_osm()

    if places:
        # Paso 2: Procesar datos
        processed = process_places(places)

        # Paso 3: Guardar CSV
        save_to_csv(processed)

        # Paso 4: Generar SQL
        create_sql_import('lugares_costa_rica.csv')

        print("\n✅ Proceso completado!")
        print("\nPróximos pasos:")
        print("1. Revisa el archivo lugares_costa_rica.csv")
        print("2. Ejecuta import_lugares.sql en tu base de datos")
        print("3. Para emails faltantes, usa web scraping en los websites")
