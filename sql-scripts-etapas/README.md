# 📋 Scripts SQL - Mejoras Venta de Garaje

## 🎯 Resumen

Este directorio contiene los scripts SQL necesarios para implementar mejoras en la página de venta de garaje en 2 etapas independientes.

---

## 📦 ETAPA 1: Búsqueda y Filtros

### Campos que se agregan a la tabla `sales`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `location` | TEXT | Dirección o zona de la venta (ej: "Monserrat, San José") |
| `cover_image2` | TEXT | Segunda imagen de portada (para carousel) |
| `description` | TEXT | Descripción corta para mostrar en las cards |
| `tags` | TEXT | Categorías en formato JSON (ej: `["Ropa", "Electrónica"]`) |

### Funcionalidades que permite:

✅ Barra de búsqueda por título o afiliado
✅ Filtros por estado (En vivo, Próximas, Todas)
✅ Ordenamiento (fecha inicio, más recientes, finalizando pronto)
✅ Mostrar ubicación en cada card
✅ Contador de productos por venta
✅ Vista previa de productos en hover
✅ Categorías/tags en las cards

### 📝 Cómo ejecutar ETAPA 1:

#### Opción A: Desde herramienta SQL del hosting

1. Abre tu herramienta SQL (phpMyAdmin, Adminer, etc.)
2. Selecciona la base de datos `comprati_marketplace`
3. Ve a la pestaña "SQL" o "Ejecutar SQL"
4. Copia y pega el contenido de: `etapa1-mejoras-venta-garaje.sql`
5. Haz clic en "Ejecutar" o "Go"

#### Opción B: Desde terminal (si tienes acceso SSH)

```bash
sqlite3 /ruta/a/marketplace.db < etapa1-mejoras-venta-garaje.sql
```

### 🔙 Rollback ETAPA 1:

Si algo sale mal o quieres revertir:

1. Ejecuta el archivo: `etapa1-rollback.sql`
2. Las columnas quedarán pero con valores NULL
3. No afectará el funcionamiento anterior de la app

---

## 📦 ETAPA 2: Funcionalidades Avanzadas

### Campos que se agregan a la tabla `sales`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `latitude` | REAL | Latitud para Google Maps |
| `longitude` | REAL | Longitud para Google Maps |
| `show_in_map` | INTEGER | 1=mostrar en mapa, 0=ocultar (default: 1) |

### Funcionalidades que permite:

✅ Historial del vendedor (ventas anteriores del afiliado)
✅ Barra de progreso visual del tiempo transcurrido
✅ Mapa de ubicaciones con Google Maps
✅ Banner promocional para invitar a publicar

### 📝 Cómo ejecutar ETAPA 2:

**IMPORTANTE:** Solo ejecutar después de que ETAPA 1 esté funcionando correctamente.

Mismo proceso que Etapa 1:
1. Abre herramienta SQL
2. Ejecuta: `etapa2-mejoras-venta-garaje.sql`

### 🔙 Rollback ETAPA 2:

Ejecuta: `etapa2-rollback.sql`

---

## ⚠️ IMPORTANTE

### Antes de ejecutar en PRODUCCIÓN:

1. **Haz backup de la base de datos**
   ```bash
   # Si tienes SSH:
   sqlite3 marketplace.db ".backup backup-$(date +%Y%m%d).db"

   # O desde el hosting:
   # Descarga una copia de marketplace.db
   ```

2. **Prueba primero en desarrollo/staging**
   - Ejecuta los scripts en ambiente de prueba
   - Verifica que la app funciona correctamente
   - Luego ejecuta en producción

3. **Orden de ejecución:**
   ```
   Desarrollo:
   1. etapa1-mejoras-venta-garaje.sql
   2. Probar app
   3. etapa2-mejoras-venta-garaje.sql
   4. Probar app

   Producción:
   1. Backup BD
   2. etapa1-mejoras-venta-garaje.sql
   3. Verificar que funciona
   4. etapa2-mejoras-venta-garaje.sql
   5. Verificar que funciona
   ```

---

## 🔍 Verificar que se ejecutó correctamente

Ejecuta esta consulta para ver las nuevas columnas:

```sql
PRAGMA table_info(sales);
```

Deberías ver las columnas nuevas en el resultado.

---

## 📞 Soporte

Si tienes problemas:
1. Verifica que seleccionaste la base de datos correcta
2. Verifica que tienes permisos de ALTER TABLE
3. Ejecuta el rollback si algo falla
4. Contacta soporte

---

## 📁 Archivos en este directorio

```
sql-scripts-etapas/
├── README.md (este archivo)
├── etapa1-mejoras-venta-garaje.sql (EJECUTAR PRIMERO)
├── etapa1-rollback.sql (rollback etapa 1)
├── etapa2-mejoras-venta-garaje.sql (EJECUTAR SEGUNDO)
└── etapa2-rollback.sql (rollback etapa 2)
```

---

## ✅ Checklist de Implementación

### Etapa 1:
- [ ] Hacer backup de base de datos
- [ ] Ejecutar `etapa1-mejoras-venta-garaje.sql` en desarrollo
- [ ] Verificar columnas con `PRAGMA table_info(sales)`
- [ ] Subir archivos PHP actualizados
- [ ] Probar en navegador
- [ ] Si todo OK, ejecutar en producción
- [ ] Si falla, ejecutar `etapa1-rollback.sql`

### Etapa 2:
- [ ] Verificar que Etapa 1 funciona
- [ ] Hacer backup de base de datos
- [ ] Ejecutar `etapa2-mejoras-venta-garaje.sql` en desarrollo
- [ ] Verificar columnas con `PRAGMA table_info(sales)`
- [ ] Subir archivos PHP actualizados
- [ ] Probar en navegador
- [ ] Si todo OK, ejecutar en producción
- [ ] Si falla, ejecutar `etapa2-rollback.sql`
