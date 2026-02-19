# Sistema de Moderación de Imágenes

Este sistema permite validar automáticamente las imágenes subidas para detectar contenido inapropiado (pornografía, violencia, gore, etc.).

## 🎯 Características Implementadas

### ✅ Sistema de Carga de Imágenes
- **Drag & Drop**: Arrastra y suelta imágenes directamente
- **Botón de selección**: Selecciona múltiples archivos
- **Preview en tiempo real**: Vista previa antes de guardar
- **URLs directas**: Mantiene la opción de pegar URLs (Google Drive, Imgur, Dropbox)

### 📊 Límites por Plan
- **Plan Gratis (7 días)**: Máximo **3 fotos**
- **Plan 30 días**: Máximo **5 fotos**
- **Plan 90 días**: Máximo **8 fotos**

### 🛡️ Validación de Contenido
- Detección de pornografía y contenido sexual
- Detección de violencia
- Detección de gore/contenido sangriento
- Detección de contenido ofensivo

### ⚙️ Validación Técnica
- Tamaño máximo: 10MB por imagen
- Formatos permitidos: JPG, PNG, GIF, WebP
- Optimización automática (redimensiona imágenes muy grandes)
- Compresión automática para reducir tamaño

---

## 🔧 Configuración de Moderación de Contenido

El sistema funciona de dos maneras:

### Opción 1: Validación Básica (Por defecto)
Si no configuras nada, el sistema usa **validación básica**:
- ✅ Verifica que sea una imagen válida
- ✅ Valida el tipo de archivo
- ✅ Valida el tamaño
- ❌ NO detecta contenido inapropiado

### Opción 2: Moderación Automática con Sightengine (Recomendado)

#### Paso 1: Crear cuenta en Sightengine

1. Ve a [https://sightengine.com/](https://sightengine.com/)
2. Haz clic en "Sign Up" (Registrarse)
3. Completa el formulario de registro
4. **No necesitas tarjeta de crédito**

#### Paso 2: Obtener credenciales API

1. Inicia sesión en tu cuenta de Sightengine
2. Ve al Dashboard
3. Encontrarás tus credenciales:
   - **API User**: Tu ID de usuario (ejemplo: `123456789`)
   - **API Secret**: Tu clave secreta (ejemplo: `ABC123xyz`)

#### Paso 3: Configurar en el sitio

**Opción A: Archivo config.local.php (Recomendado)**

Crea o edita el archivo `/includes/config.local.php` y agrega:

```php
<?php
// Sightengine API - Moderación de Imágenes
define('SIGHTENGINE_API_USER', 'TU_API_USER_AQUI');
define('SIGHTENGINE_API_SECRET', 'TU_API_SECRET_AQUI');
```

**Opción B: Variables de entorno**

Si usas variables de entorno, agrega:

```bash
export SIGHTENGINE_API_USER="TU_API_USER_AQUI"
export SIGHTENGINE_API_SECRET="TU_API_SECRET_AQUI"
```

#### Paso 4: Verificar funcionamiento

1. Ve a crear una nueva propiedad
2. Intenta subir una imagen
3. Revisa la consola del navegador (F12)
4. Si ves "Moderación de contenido activa", está funcionando
5. Si ves "Moderación de contenido no configurada", revisa tus credenciales

---

## 📋 Plan Gratuito de Sightengine

El plan gratuito incluye:
- ✅ **2,000 operaciones/mes** (2,000 imágenes)
- ✅ Sin tarjeta de crédito requerida
- ✅ Detección de nudez, violencia, gore, drogas
- ✅ Detección de contenido ofensivo
- ✅ API estable y rápida

**¿Qué pasa si se superan las 2,000 imágenes/mes?**
- El sistema automáticamente vuelve a usar validación básica
- Las imágenes se subirán normalmente
- Se registrará un warning en los logs

---

## 🔍 ¿Cómo funciona la detección?

Cuando un usuario sube una imagen:

1. **Validación técnica**:
   - Se verifica que sea una imagen real
   - Se valida el formato (JPG, PNG, GIF, WebP)
   - Se verifica el tamaño (máx. 10MB)

2. **Moderación de contenido** (si Sightengine está configurado):
   - Se envía la imagen a Sightengine API
   - Se analiza el contenido en ~1-2 segundos
   - Se obtiene un score de probabilidad (0-1)

3. **Decisión**:
   - Si el score supera 0.5 (50%) en cualquier categoría prohibida → **RECHAZADA**
   - Si el score es menor → **APROBADA**

### Categorías detectadas:

| Categoría | Descripción | Umbral |
|-----------|-------------|--------|
| Sexual Activity | Contenido sexual explícito | 50% |
| Sexual Display | Desnudez sexual | 50% |
| Erotica | Contenido erótico | 50% |
| Violence | Violencia | 50% |
| Gore | Contenido gore/sangriento | 50% |
| Offensive | Contenido ofensivo | 50% |

---

## 📁 Archivos del Sistema

```
/real-estate/
├── upload-image.php               # API endpoint para subir imágenes
├── includes/
│   └── image-moderation.php       # Clase de moderación de contenido
├── uploads/                       # Directorio de imágenes subidas
│   └── .gitignore                 # Ignora imágenes en git
├── create-listing.php             # Formulario de creación (actualizado)
├── edit-listing.php               # Formulario de edición (actualizado)
└── MODERACION_IMAGENES.md         # Esta guía

/includes/
└── config.php                     # Configuración general (actualizado)
```

---

## 🚨 Seguridad y Privacidad

- Las imágenes se envían a Sightengine solo para análisis
- Sightengine NO almacena las imágenes permanentemente
- El análisis se hace en tiempo real
- Los resultados se descartan después del análisis
- Las imágenes se guardan en tu servidor local

---

## 🐛 Resolución de Problemas

### Problema: "Moderación de contenido no configurada"

**Solución**:
1. Verifica que `config.local.php` existe en `/includes/`
2. Verifica que las constantes estén bien definidas
3. Verifica que no haya espacios extra en las credenciales
4. Reinicia el servidor web si es necesario

### Problema: "API returned HTTP 403"

**Solución**:
- Tus credenciales son incorrectas
- Verifica API User y API Secret en tu dashboard de Sightengine

### Problema: "API returned HTTP 429"

**Solución**:
- Superaste el límite de 2,000 operaciones/mes
- El sistema automáticamente usará validación básica
- Considera actualizar tu plan de Sightengine si necesitas más operaciones

### Problema: Las imágenes no se suben

**Solución**:
1. Verifica permisos del directorio `/real-estate/uploads/` (debe ser 755)
2. Verifica que el servidor tenga espacio en disco
3. Verifica los logs de PHP en `/var/log/apache2/error.log` (o similar)

---

## 📊 Monitoreo

Para ver el estado de la moderación:

1. Abre la consola del navegador (F12)
2. Sube una imagen
3. Busca mensajes como:
   - `✅ "Moderación de contenido activa"` - Todo funcionando
   - `⚠️ "Moderación de contenido no configurada"` - Usando validación básica

Los intentos de subir contenido inapropiado se registran en los logs del servidor:
```
error_log("Imagen rechazada por moderación - Agente: X - Razón: Y")
```

---

## 💡 Notas Finales

- La validación básica es suficiente para sitios pequeños de confianza
- Sightengine es recomendado para sitios públicos o con muchos usuarios
- El sistema es completamente transparente para el usuario
- Los límites de fotos se aplican independientemente de la moderación
- Las URLs de Google Drive, Imgur, etc. se mantienen sin cambios

---

## 📞 Soporte

Si tienes problemas:
1. Revisa esta guía
2. Verifica los logs del servidor
3. Verifica la consola del navegador
4. Contacta al desarrollador del sitio
