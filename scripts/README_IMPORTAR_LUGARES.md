# 🏨 Importador de Lugares Comerciales

Sistema para importar hoteles, restaurantes y bares de Costa Rica desde fuentes gratuitas.

## 🎯 Opciones Disponibles

### Opción 1: Interfaz Web (MÁS FÁCIL) ⭐

**URL:** `https://compratica.com/admin/import_lugares_comerciales.php`

**Pasos:**
1. Accede a la URL desde tu navegador
2. Click en "Crear Tabla" (Paso 1)
3. Click en "Descargar Datos" (Paso 2) - espera 1-2 minutos
4. Click en "Ver Estadísticas" para ver los resultados

**Datos obtenidos:**
- ✅ Nombres de lugares
- ✅ Tipos y categorías (TODAS las categorías comerciales)
- ✅ Direcciones completas (calle, ciudad, provincia, código postal)
- ✅ Teléfonos (cuando disponible)
- ✅ Emails (cuando disponible)
- ✅ Websites
- ✅ Redes sociales (Facebook, Instagram)
- ✅ Horarios de apertura
- ✅ Coordenadas GPS
- ✅ Características (WiFi, Parking, Delivery, Acceso discapacidad)
- ✅ Capacidad y estrellas (hoteles)
- ✅ Tags completos en JSON

### Opción 2: Script Python

**Requisitos:**
```bash
pip install requests
```

**Ejecución:**
```bash
cd /home/user/COMPRATICA/scripts
python3 extract_osm_places.py
```

**Resultado:**
- Archivo CSV: `lugares_costa_rica.csv`
- Script SQL: `import_lugares.sql`

### Opción 3: Descarga Manual + Procesamiento

**Descargar datos:**
```bash
cd /home/user/COMPRATICA/scripts
bash download_osm_data.sh
```

## 📊 Cobertura de Datos

Basado en OpenStreetMap, esperamos:

| Dato | Cobertura Estimada |
|------|-------------------|
| Nombre | ~95% |
| Tipo/Categoría | 100% |
| Dirección | ~70% |
| Ciudad/Provincia | ~80% |
| Teléfono | ~40% |
| Email | ~15% |
| Website | ~30% |
| Facebook | ~10% |
| Instagram | ~5% |
| Horarios | ~25% |
| WiFi/Parking | ~20% |
| GPS | 100% |

## 🏢 Categorías Incluidas

El sistema ahora importa **TODAS** las categorías comerciales:

### 🍽️ Gastronomía
- Restaurantes, Bares, Cafés
- Fast Food, Pubs, Heladerías
- Food Courts, Biergardens

### 🏨 Alojamiento
- Hoteles, Moteles, Guest Houses
- Hostels, Apartamentos, Chalets

### 🛍️ Tiendas
- TODAS las tiendas (supermercados, ropa, electrónica, etc.)
- Más de 100 tipos diferentes

### 🏥 Servicios
- Bancos, Farmacias, Clínicas
- Dentistas, Hospitales, Veterinarias
- Gasolineras, Car Wash, Rent-a-Car

### 🎭 Entretenimiento
- Cines, Teatros, Discotecas
- Casinos, Centros de Arte
- Centros Deportivos, Gimnasios
- Piscinas, Marinas, Golf

### 🎨 Turismo
- Atracciones, Museos, Galerías
- Miradores, Parques Temáticos
- Zoológicos, Acuarios

### 💼 Oficinas
- Servicios profesionales
- Agencias, Consultoras
- Abogados, Contadores

### 🎓 Educación
- Escuelas, Colegios, Universidades
- Academias de idiomas
- Autoescuelas

### 💅 Belleza y Bienestar
- Salones de belleza, Peluquerías
- Spas, Masajes
- Cosméticos

## 🔍 Fuentes de Datos

### 1. OpenStreetMap (Gratis - Usado)
- **Datos:** Todos los puntos de interés en Costa Rica
- **Actualización:** Diaria
- **Licencia:** Open Database License
- **Limitación:** Emails limitados

### 2. Google Places API (Freemium)
- **Crédito gratis:** $200/mes
- **Datos:** Más completos, incluyendo ratings
- **Limitación:** Emails no siempre disponibles

### 3. Bases Comerciales (Pago)
- **Ventaja:** Emails verificados
- **Costo:** Variable según proveedor

## 🚀 Completar Emails Faltantes

Para los lugares sin email, puedes:

### Opción A: Web Scraping de Websites
Si tienen website, puedes extraer el email:

```python
import requests
from bs4 import BeautifulSoup
import re

def extract_email_from_website(url):
    try:
        response = requests.get(url, timeout=10)
        soup = BeautifulSoup(response.text, 'html.parser')

        # Buscar emails en el HTML
        emails = re.findall(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', response.text)

        return emails[0] if emails else None
    except:
        return None
```

### Opción B: Google Places API
```php
// Consultar Google Places API para obtener emails
$api_key = 'TU_API_KEY';
$place_name = 'Hotel Costa Rica San Jose';

$url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=" .
       urlencode($place_name) . "&inputtype=textquery&fields=formatted_address,name,email&key=$api_key";

$response = file_get_contents($url);
$data = json_decode($response);
```

### Opción C: Búsqueda Manual Asistida
Crear un panel de admin para buscar manualmente y completar datos faltantes.

## 📋 Estructura de la Tabla (EXPANDIDA)

```sql
CREATE TABLE lugares_comerciales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    tipo VARCHAR(100),                    -- restaurant, hotel, shop, etc.
    categoria VARCHAR(100),               -- amenity, tourism, shop, office, leisure
    subtipo VARCHAR(100),                 -- cuisine, shop_type, etc.
    descripcion TEXT,
    direccion VARCHAR(500),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    codigo_postal VARCHAR(20),
    telefono VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    facebook VARCHAR(255),                -- NUEVO
    instagram VARCHAR(255),               -- NUEVO
    horario TEXT,                         -- NUEVO: horarios de apertura
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    osm_id BIGINT,
    osm_type VARCHAR(10),
    capacidad INT,                        -- NUEVO
    estrellas TINYINT,                    -- NUEVO: para hoteles
    wifi BOOLEAN DEFAULT FALSE,           -- NUEVO
    parking BOOLEAN DEFAULT FALSE,        -- NUEVO
    discapacidad_acceso BOOLEAN,          -- NUEVO
    tarjetas_credito BOOLEAN,             -- NUEVO
    delivery BOOLEAN,                     -- NUEVO
    takeaway BOOLEAN,                     -- NUEVO
    tags_json TEXT,                       -- NUEVO: todos los tags OSM
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_categoria (categoria),
    INDEX idx_ciudad (ciudad),
    INDEX idx_provincia (provincia),
    INDEX idx_email (email),
    INDEX idx_osm_id (osm_id),
    FULLTEXT idx_nombre (nombre, descripcion)  -- Búsqueda de texto completo
);
```

## 🔧 Mantenimiento

### Actualizar Datos
Los datos de OpenStreetMap se actualizan constantemente. Recomendamos actualizar cada mes:

1. Ve a `import_lugares_comerciales.php`
2. Click en "Descargar Datos"
3. Los registros existentes se actualizarán automáticamente

### Exportar a CSV
```sql
SELECT * FROM lugares_comerciales
INTO OUTFILE '/tmp/lugares_export.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n';
```

## 📈 Próximos Pasos Sugeridos

1. **Completar emails faltantes:**
   - Usar web scraping en los websites
   - Buscar manualmente los más importantes
   - Usar Google Places API para los que tengan crédito

2. **Segmentar base de datos:**
   - Por provincia (para campañas locales)
   - Por tipo (hoteles, restaurantes, bares)
   - Por si tienen email (para marketing directo)

3. **Integrar con Email Marketing:**
   - Crear categoría "Lugares Comerciales"
   - Permitir enviar campañas segmentadas
   - Tracking de apertura por tipo de negocio

## 🆘 Soporte

- **Overpass API Docs:** https://wiki.openstreetmap.org/wiki/Overpass_API
- **Google Places API:** https://developers.google.com/maps/documentation/places
- **OpenStreetMap Costa Rica:** https://wiki.openstreetmap.org/wiki/Costa_Rica

## ⚖️ Licencia y Uso

Los datos de OpenStreetMap están bajo licencia ODbL. Debes:
- ✅ Dar crédito a OpenStreetMap contributors
- ✅ Compartir modificaciones bajo la misma licencia
- ✅ No usar para spam (respeta GDPR y leyes locales)

**Nota:** Para uso comercial de emails, asegúrate de cumplir con leyes de privacidad y obtener consentimiento.
