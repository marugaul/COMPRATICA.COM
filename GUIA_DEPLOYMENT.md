# 🚀 Guía Rápida: Cómo Hacer tu Sitio Visible en Internet

## Opción 1: GitHub Pages (Más Fácil y GRATIS) ⭐ RECOMENDADO

### Paso 1: Crear Repositorio en GitHub

1. Ve a [github.com](https://github.com)
2. Click en "New repository" (botón verde)
3. Nombre: `compratica` (o el que prefieras)
4. Marca como "Public"
5. Click en "Create repository"

### Paso 2: Subir tus Archivos

Opción A - Desde la Terminal (si tienes git instalado):

```bash
# Navega a tu carpeta del proyecto
cd /home/user/COMPRATICA.COM

# Inicializa git (si no lo has hecho)
git init

# Agrega todos los archivos
git add index.html logo.png producto*.jpg qr_sinpe*.png sitemap.xml robots.txt

# Crea tu primer commit
git commit -m "Initial commit - Compratica marketplace"

# Conecta con GitHub (reemplaza TU_USUARIO con tu nombre de usuario)
git remote add origin https://github.com/TU_USUARIO/compratica.git

# Sube los archivos
git branch -M main
git push -u origin main
```

Opción B - Desde la Web (más fácil):

1. En GitHub, click en "uploading an existing file"
2. Arrastra estos archivos:
   - index.html
   - logo.png
   - producto1.jpg, producto2.jpg, producto3.jpg
   - qr_sinpe_producto1.png, qr_sinpe_producto2.png, qr_sinpe_producto3.png
   - sitemap.xml
   - robots.txt
3. Click en "Commit changes"

### Paso 3: Activar GitHub Pages

1. En tu repositorio, ve a **Settings** (⚙️ arriba a la derecha)
2. En el menú izquierdo, busca **Pages**
3. En "Source", selecciona **main** branch
4. Click en **Save**
5. Espera 1-2 minutos
6. Tu sitio estará en: `https://tu_usuario.github.io/compratica/`

### Paso 4: Conectar tu Dominio compratica.com

1. En GitHub Pages (Settings > Pages):
   - En "Custom domain", escribe: `compratica.com`
   - Click en **Save**

2. En tu proveedor de dominio (donde compraste compratica.com):

   **Configuración DNS:**
   ```
   Tipo: A
   Host: @ (o dejar vacío)
   Value: 185.199.108.153
   TTL: Auto o 3600

   Tipo: A
   Host: @
   Value: 185.199.109.153

   Tipo: A
   Host: @
   Value: 185.199.110.153

   Tipo: A
   Host: @
   Value: 185.199.111.153

   Tipo: CNAME
   Host: www
   Value: tu_usuario.github.io
   TTL: Auto o 3600
   ```

3. Espera 5-30 minutos para que los DNS se propaguen

4. Vuelve a GitHub Pages y marca "Enforce HTTPS" (SSL gratis)

### ✅ ¡Listo! Tu sitio estará en https://compratica.com

---

## Opción 2: Netlify (También GRATIS, Súper Fácil)

### Método Drag & Drop (Sin código)

1. Ve a [netlify.com](https://www.netlify.com/)
2. Click en "Sign up" y crea cuenta (usa tu cuenta de GitHub)
3. Una vez dentro, arrastra toda tu carpeta del proyecto a Netlify
4. ¡Listo! Tu sitio estará en `https://random-name.netlify.app`

### Conectar tu Dominio:

1. En Netlify, ve a **Site settings**
2. Click en **Domain management**
3. Click en **Add custom domain**
4. Escribe: `compratica.com`
5. Netlify te dará instrucciones DNS específicas
6. Configura esos DNS en tu proveedor de dominio

### Con Git (Actualización Automática):

```bash
# Sube tu proyecto a GitHub primero (ver Opción 1)

# Luego en Netlify:
# 1. Click en "New site from Git"
# 2. Conecta con GitHub
# 3. Selecciona tu repositorio
# 4. Click en "Deploy site"
```

**Ventaja:** Cada vez que hagas cambios y los subas a GitHub, Netlify actualizará tu sitio automáticamente.

---

## Opción 3: Vercel (GRATIS, Ideal para Proyectos Modernos)

### Instalación y Deploy:

```bash
# Instala Vercel CLI
npm install -g vercel

# Navega a tu proyecto
cd /home/user/COMPRATICA.COM

# Deploy (primera vez)
vercel

# Sigue las instrucciones:
# - Set up and deploy? Y
# - Which scope? (tu cuenta)
# - Link to existing project? N
# - What's your project's name? compratica
# - In which directory is your code located? ./
# - Want to override settings? N

# Tu sitio estará en: https://compratica.vercel.app

# Para conectar tu dominio:
vercel domains add compratica.com
```

---

## Opción 4: Hosting Tradicional Costa Rica

### Proveedores Recomendados:

1. **HostDime Costa Rica** - https://www.hostdime.cr/
   - Plan básico: ~$10/mes
   - Soporte en español
   - Servidor en Costa Rica

2. **ICE Hosting** - https://www.ice.go.cr/
   - Plan desde ~$5/mes
   - Ente costarricense

3. **Racsa Cloud** - https://www.racsa.co.cr/
   - Plan desde ~$8/mes
   - Soporte local

### Pasos Generales:

1. **Contratar Plan:**
   - Compra un plan de hosting
   - Recibirás credenciales de cPanel y FTP

2. **Acceder a cPanel:**
   - URL: `https://tudominio.com/cpanel` o similar
   - Usuario y contraseña que te enviaron

3. **Subir Archivos:**

   **Opción A - File Manager (cPanel):**
   - En cPanel, abre "File Manager"
   - Ve a `public_html` o `www`
   - Click en "Upload"
   - Sube todos tus archivos
   - ¡Listo!

   **Opción B - FileZilla (FTP):**
   - Descarga FileZilla: https://filezilla-project.org/
   - Abre FileZilla
   - Conecta:
     - Host: ftp.tudominio.com
     - Usuario: (te lo dio el hosting)
     - Contraseña: (te la dio el hosting)
     - Puerto: 21
   - Arrastra tus archivos a `/public_html/`

4. **Configurar Dominio:**
   - En cPanel > Domains
   - Agrega `compratica.com`
   - Configura DNS si es necesario

---

## Checklist Post-Deployment

Una vez que tu sitio esté en línea:

### Inmediato (Hoy):

- [ ] Verifica que https://compratica.com carga correctamente
- [ ] Prueba que todos los links funcionan
- [ ] Verifica que las imágenes se vean bien
- [ ] Prueba en móvil
- [ ] Prueba los botones de WhatsApp

### Primera Semana:

- [ ] Configura Google Search Console
  - https://search.google.com/search-console
  - Verifica propiedad (ya tienes el meta tag)
  - Envía sitemap: https://compratica.com/sitemap.xml

- [ ] Configura Google Analytics
  - https://analytics.google.com
  - Crea propiedad
  - Agrega código de tracking al `<head>` de index.html

- [ ] Prueba velocidad
  - https://pagespeed.web.dev/
  - Objetivo: Score > 90

- [ ] Verifica SEO
  - https://search.google.com/test/rich-results
  - Debe mostrar tus productos

- [ ] Prueba en móvil
  - https://search.google.com/test/mobile-friendly

### Optimizaciones Continuas:

- [ ] Optimiza imágenes (comprime con TinyPNG.com)
- [ ] Agrega más productos
- [ ] Crea contenido de blog
- [ ] Comparte en redes sociales
- [ ] Monitorea Analytics semanalmente

---

## Solución de Problemas Comunes

### "Mi sitio no carga"
- Espera 5-30 minutos después de configurar DNS
- Verifica que los archivos estén en `public_html` o raíz
- Revisa que index.html esté en minúsculas

### "Las imágenes no se ven"
- Verifica que las imágenes estén en la misma carpeta que index.html
- Revisa que los nombres coincidan (producto1.jpg vs Producto1.jpg)
- Asegúrate de que las imágenes se hayan subido

### "HTTPS no funciona"
- En GitHub Pages: activa "Enforce HTTPS"
- En Netlify/Vercel: es automático
- En hosting tradicional: compra certificado SSL (Let's Encrypt es gratis)

### "Mi dominio no funciona"
- Espera hasta 48 horas para propagación DNS
- Verifica configuración DNS con: https://www.whatsmydns.net/
- Asegúrate de que los registros A y CNAME estén correctos

### "Google no me encuentra"
- Espera 1-4 semanas después de enviar sitemap
- Publica en redes sociales para acelerar indexación
- Crea backlinks (enlaces desde otros sitios)

---

## Siguiente Paso: Promoción

Una vez que tu sitio esté en línea:

1. **Comparte en Redes Sociales:**
   ```
   🎉 ¡Visita nuestro nuevo marketplace!
   Compra productos de calidad con SINPE QR
   👉 https://compratica.com
   #CostaRica #VentaGaraje #SINPE
   ```

2. **Publica en Grupos de Facebook:**
   - "Compra Venta Costa Rica"
   - "Venta Garaje CR"
   - "Emprendedores Costa Rica"

3. **Crea Perfiles de Negocio:**
   - Facebook Page
   - Instagram Business
   - Google My Business

4. **Envía a Directorios:**
   - Google My Business
   - Páginas Amarillas
   - Encuentra24

---

## Recursos Útiles

### Herramientas de Testing:
- **PageSpeed Insights:** https://pagespeed.web.dev/
- **Mobile-Friendly Test:** https://search.google.com/test/mobile-friendly
- **Rich Results Test:** https://search.google.com/test/rich-results
- **DNS Propagation:** https://www.whatsmydns.net/
- **SSL Checker:** https://www.sslshopper.com/ssl-checker.html

### Optimización de Imágenes:
- **TinyPNG:** https://tinypng.com (gratis)
- **Squoosh:** https://squoosh.app (gratis, de Google)
- **Compressor.io:** https://compressor.io (gratis)

### Monitoreo:
- **Google Search Console:** https://search.google.com/search-console
- **Google Analytics:** https://analytics.google.com
- **Uptime Robot:** https://uptimerobot.com (monitorea si tu sitio está caído)

---

## Costos Aproximados

### Gratis:
- ✅ GitHub Pages
- ✅ Netlify
- ✅ Vercel
- ✅ SSL (en todos los anteriores)
- ✅ Google Search Console
- ✅ Google Analytics

### De Pago (Opcional):
- Hosting tradicional CR: $5-15/mes
- Dominio .com: $10-15/año (ya lo tienes)
- Google Ads: $5-50/día (para aparecer primero inmediatamente)
- Servicio de email profesional: $6/mes (Google Workspace)

---

## Próximos Pasos Recomendados

1. **Hoy:** Sube tu sitio a GitHub Pages o Netlify
2. **Mañana:** Configura Google Search Console y Analytics
3. **Esta Semana:** Optimiza imágenes y crea perfiles en redes sociales
4. **Este Mes:** Publica 5 artículos de blog y consigue backlinks

---

## ¿Necesitas Ayuda?

Si tienes problemas:

1. **Revisa esta guía** paso a paso
2. **Busca en Google:** "cómo [tu problema] github pages"
3. **Documentación oficial:**
   - GitHub Pages: https://pages.github.com/
   - Netlify: https://docs.netlify.com/
   - Vercel: https://vercel.com/docs

4. **Comunidades:**
   - Stack Overflow (en inglés)
   - Foros de WordPress/webdev en español
   - Grupos de Facebook de emprendedores CR

---

**¡Éxito con tu lanzamiento!** 🚀

Tu sitio ya está optimizado para SEO. Ahora solo falta ponerlo en línea y comenzar a promocionarlo.

Recuerda: El mejor momento para lanzar fue ayer. El segundo mejor momento es hoy.

---

**Última actualización:** Marzo 2026
