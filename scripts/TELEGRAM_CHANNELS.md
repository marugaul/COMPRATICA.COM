# 📱 Canales de Telegram para Empleos - Costa Rica

## Configuración Actual

Edita el archivo: `includes/telegram_config.php`

## 🇨🇷 Canales Recomendados de Costa Rica

### Ya configurados ✅
```php
'STEMJobsCR',      // Empleos STEM en Costa Rica
'STEMJobsLATAM',   // Empleos remotos LATAM
```

### Agregar estos canales:

```php
<?php
// Token del bot de Telegram (obtenlo de @BotFather)
define('TELEGRAM_BOT_TOKEN', 'TU_TOKEN_AQUI');

// Canales a importar (sin @)
define('TELEGRAM_CHANNELS', [
    // ✅ Ya configurados
    'STEMJobsCR',           // Empleos STEM Costa Rica
    'STEMJobsLATAM',        // Empleos remotos LATAM

    // 🆕 Nuevos canales de Costa Rica
    'EmpleosCR',            // Empleos generales CR
    'TrabajoCR',            // Trabajos Costa Rica
    'OfertasEmpleoCR',      // Ofertas de empleo CR
    'JobsCR506',            // Empleos CR (código 506)

    // 💼 Empleos Remotos
    'trabajoenremoto',      // Trabajo remoto español
    'remotework',           // Empleos remotos generales
    'RemoteJobsES',         // Empleos remotos español

    // 💻 Tecnología
    'devjobslatam',         // Desarrolladores LATAM
    'ITJobsRemote',         // IT Jobs remotos
    'techJobsLatam',        // Tech Jobs LATAM

    // 🏢 LinkedIn Jobs
    'LinkedInJobsCR',       // Empleos de LinkedIn CR (si existe)
    'LinkedInRemote',       // LinkedIn empleos remotos
]);
?>
```

## 📋 Cómo agregar un canal nuevo:

### 1. Verificar que el canal existe
Busca en Telegram: `@NombreDelCanal`

### 2. Verificar que sea público
Los canales deben ser públicos (con @username)

### 3. Agregar al array
Agrega el nombre **sin el @** al array `TELEGRAM_CHANNELS`

### 4. Probar la importación
```bash
php scripts/import_telegram_jobs.php
```

## 🔍 Encontrar más canales:

### Directorios de canales de Telegram:
- https://www.grupostelegram.net/trabajos-de-costa-rica.html
- https://telemetr.io/en/catalog/costa_rica
- https://t.me/s/ (buscar "empleos costa rica")

### Buscar en Telegram:
1. Abre Telegram
2. Usa el buscador 🔍
3. Escribe: "empleos costa rica" o "jobs CR"
4. Busca canales con el ícono de megáfono 📢

### Palabras clave para buscar:
- `empleos costa rica`
- `trabajos cr`
- `jobs costa rica`
- `linkedin jobs cr`
- `remote jobs latam`
- `empleos tecnologia cr`

## ⚠️ Importante:

1. **Canales públicos**: Solo funcionan canales públicos con @username
2. **Permisos**: Tu bot debe poder leer mensajes del canal
3. **Spam**: No agregues canales de spam o baja calidad
4. **Actualización**: Revisa cada 2-3 meses si los canales siguen activos

## 📊 Estadísticas de importación:

Después de agregar canales, revisa:
```bash
tail -f logs/import_telegram.log
```

## 🚀 Canales LinkedIn específicos:

⚠️ **Nota**: LinkedIn no tiene canales oficiales de Telegram. Los empleos de LinkedIn se agregan mediante:

1. **Scrapers de terceros** - Canales que publican empleos de LinkedIn
2. **Bots automatizados** - Reenvían empleos de LinkedIn a Telegram
3. **Comunidades** - Usuarios comparten empleos de LinkedIn

### Canales que reenvían empleos de LinkedIn:
- `@LinkedInJobsDaily`
- `@LinkedInRemoteJobs`
- `@JobsFromLinkedIn`
- `@LinkedInTechJobs`

**Busca**: "linkedin jobs telegram" en Google o Telegram

---

**Última actualización:** 2026-03-15
