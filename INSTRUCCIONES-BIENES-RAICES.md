# 🏡 Módulo de Bienes Raíces - CompraTica

## Descripción

Se ha implementado un nuevo módulo de **Bienes Raíces** en el marketplace CompraTica, que permite a los usuarios publicar propiedades (casas, apartamentos, locales comerciales, terrenos, etc.) para venta o alquiler.

## ✅ Archivos Creados

### 1. Página Principal
- **`bienes-raices.php`** - Página que muestra el listado de propiedades con filtros avanzados

### 2. Scripts SQL
- **`sql-scripts-etapas/crear-categorias-bienes-raices.sql`** - Crea las categorías de bienes raíces
- **`sql-scripts-etapas/crear-tabla-precios-publicaciones.sql`** - Crea las tablas de precios y publicaciones

### 3. Actualizaciones
- **`index.php`** - Agregada nueva tarjeta de "Bienes Raíces" en la sección de categorías
- **`assets/css/main.css`** - Agregados estilos para la tarjeta de Bienes Raíces

## 📋 Instalación

### Paso 1: Ejecutar Scripts SQL

Debes ejecutar los siguientes scripts SQL en tu base de datos **EN ESTE ORDEN**:

```bash
# 1. Crear las categorías de bienes raíces
sqlite3 /ruta/a/tu/database.db < sql-scripts-etapas/crear-categorias-bienes-raices.sql

# 2. Crear las tablas de precios y publicaciones
sqlite3 /ruta/a/tu/database.db < sql-scripts-etapas/crear-tabla-precios-publicaciones.sql
```

### Paso 2: Verificar la Instalación

Accede a: `https://compratica.com/bienes-raices`

Si todo está correcto, verás la página de Bienes Raíces con los filtros y el mensaje de "No hay propiedades disponibles" (ya que aún no hay publicaciones).

## 💰 Sistema de Precios Parametrizados

El sistema incluye 3 planes de publicación **completamente parametrizados** en la tabla `listing_pricing`:

| Plan | Duración | Precio USD | Precio CRC |
|------|----------|------------|------------|
| **Gratis 7 días** | 7 días | $0.00 | ₡0 |
| **Plan 30 días** | 30 días | $1.00 | ₡540 |
| **Plan 90 días** | 90 días | $2.00 | ₡1,080 |

### Modificar los Precios

Para cambiar los precios, ejecuta SQL directamente:

```sql
-- Cambiar el precio del plan de 30 días
UPDATE listing_pricing
SET price_usd = 2.00, price_crc = 1080.00
WHERE name = 'Plan 30 días';

-- Cambiar el precio del plan de 90 días
UPDATE listing_pricing
SET price_usd = 5.00, price_crc = 2700.00
WHERE name = 'Plan 90 días';

-- Agregar un nuevo plan
INSERT INTO listing_pricing (name, duration_days, price_usd, price_crc, is_active, description, display_order)
VALUES ('Plan 60 días', 60, 1.50, 810.00, 1, 'Publicación por 60 días', 2);
```

## 📊 Categorías de Bienes Raíces

Se crearon las siguientes categorías (inspiradas en encuentra24.com):

### Casas
- BR: Casas en Venta
- BR: Casas en Alquiler

### Apartamentos
- BR: Apartamentos en Venta
- BR: Apartamentos en Alquiler

### Locales Comerciales
- BR: Locales Comerciales en Venta
- BR: Locales Comerciales en Alquiler

### Oficinas
- BR: Oficinas en Venta
- BR: Oficinas en Alquiler

### Terrenos
- BR: Terrenos en Venta
- BR: Lotes en Venta

### Bodegas
- BR: Bodegas en Venta
- BR: Bodegas en Alquiler

### Quintas y Fincas
- BR: Quintas en Venta
- BR: Fincas en Venta

### Condominios
- BR: Condominios en Venta
- BR: Condominios en Alquiler

### Habitaciones
- BR: Habitaciones en Alquiler

### Otros
- BR: Otros Bienes Raíces

**Nota:** Todas las categorías de Bienes Raíces tienen el prefijo "BR:" para diferenciarlas de las categorías de Venta de Garaje.

## 🔧 Estructura de la Base de Datos

### Tabla: `listing_pricing`
Almacena los planes de precios parametrizados.

```sql
CREATE TABLE listing_pricing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  duration_days INTEGER NOT NULL,
  price_usd REAL NOT NULL,
  price_crc REAL NOT NULL,
  is_active INTEGER DEFAULT 1,
  is_featured INTEGER DEFAULT 0,
  description TEXT,
  display_order INTEGER DEFAULT 0,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now'))
);
```

### Tabla: `real_estate_listings`
Almacena las publicaciones de propiedades.

```sql
CREATE TABLE real_estate_listings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  category_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  description TEXT,
  price REAL NOT NULL,
  currency TEXT DEFAULT 'CRC',
  location TEXT,
  province TEXT,
  canton TEXT,
  district TEXT,
  bedrooms INTEGER DEFAULT 0,
  bathrooms INTEGER DEFAULT 0,
  area_m2 REAL DEFAULT 0,
  parking_spaces INTEGER DEFAULT 0,
  features TEXT,  -- JSON con características
  images TEXT,    -- JSON con URLs de imágenes
  contact_name TEXT,
  contact_phone TEXT,
  contact_email TEXT,
  contact_whatsapp TEXT,
  listing_type TEXT DEFAULT 'sale',  -- 'sale' o 'rent'
  pricing_plan_id INTEGER NOT NULL,
  is_active INTEGER DEFAULT 1,
  is_featured INTEGER DEFAULT 0,
  views_count INTEGER DEFAULT 0,
  start_date TEXT,
  end_date TEXT,
  payment_status TEXT DEFAULT 'pending',  -- 'pending', 'paid', 'free'
  payment_id TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (pricing_plan_id) REFERENCES listing_pricing(id)
);
```

## 🎯 Funcionalidades Implementadas

✅ Página principal de Bienes Raíces
✅ Filtros por:
  - Búsqueda por texto (título, descripción, ubicación)
  - Tipo (Venta/Alquiler)
  - Categoría
  - Provincia
  - Ordenamiento (Recientes, Precio, Área)
✅ Tarjetas de propiedades con:
  - Imagen principal
  - Precio
  - Ubicación
  - Características (habitaciones, baños, área, parqueos)
  - Botón de WhatsApp
  - Botón "Ver detalles"
✅ Sistema de precios parametrizados
✅ Categorías diferenciadas con prefijo "BR:"

## 🚧 Próximos Pasos Recomendados

1. **Crear página de detalle de propiedad** (`propiedad-detalle.php`)
   - Galería de imágenes
   - Descripción completa
   - Mapa de ubicación
   - Formulario de contacto

2. **Crear formulario de publicación**
   - Formulario para que los usuarios publiquen propiedades
   - Upload de imágenes
   - Selección de plan de precios
   - Integración con sistema de pagos

3. **Panel de administración**
   - Gestión de publicaciones
   - Aprobación/rechazo de propiedades
   - Modificación de precios
   - Estadísticas

4. **Sistema de pagos**
   - Integración con SINPE Móvil
   - Integración con PayPal o Stripe
   - Verificación de pagos

## 📞 Soporte

Para cualquier consulta o problema, contacta al desarrollador.

---

**CompraTica** - 100% Costarricense 🇨🇷
