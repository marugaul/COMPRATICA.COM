# Sistema de Email Marketing - CompraTica

Sistema completo de envío masivo de correos electrónicos con tracking y anti-spam.

## Características

- ✅ Envío masivo desde Excel, base de datos o entrada manual
- ✅ 3 plantillas profesionales pre-configuradas (Mixtico, CRV-SOFT, CompraTica)
- ✅ Tracking de aperturas, clicks y cancelaciones
- ✅ Configuración SMTP múltiple
- ✅ Medidas anti-spam (SPF/DKIM, rate limiting)
- ✅ Soporte para adjuntos (imágenes, PDFs)
- ✅ Dashboard con estadísticas y reportes
- ✅ Benchmarks de la industria

## Instalación

### 1. Instalar Dependencias

```bash
cd /home/user/COMPRATICA.COM
composer install
```

Esto instalará:
- PHPMailer (envío SMTP)
- PhpSpreadsheet (procesamiento de Excel)

### 2. Crear Base de Datos

Ejecutar el script SQL en MySQL:

```bash
mysql -u [usuario] -p comprati_marketplace < admin/setup_email_marketing.sql
```

O desde phpMyAdmin:
- Importar archivo: `admin/setup_email_marketing.sql`

Esto creará 5 tablas:
- `email_smtp_configs` - Configuraciones SMTP
- `email_templates` - Plantillas HTML
- `email_campaigns` - Campañas de envío
- `email_recipients` - Destinatarios por campaña
- `email_send_logs` - Logs de envío

### 3. Configurar Permisos

```bash
chmod 755 uploads/email_attachments
chown www-data:www-data uploads/email_attachments
```

### 4. Configurar SMTP

Acceder a: `https://compratica.com/admin/email_marketing.php?page=smtp-config`

Configurar las 3 cuentas de correo:
1. **Mixtico** - mixtico.net
2. **CRV-SOFT** - www.crv-soft.com
3. **CompraTica** - compratica.com

Para cada una configurar:
- Email remitente
- Nombre remitente
- Servidor SMTP
- Puerto (587 para TLS recomendado)
- Usuario SMTP
- Contraseña SMTP
- Encriptación (TLS/SSL/None)

### 5. Insertar Plantillas

Las plantillas HTML están en `admin/email_templates/`:
- `mixtico_template.html`
- `crv_soft_template.html`
- `compratica_template.html`

Cargarlas desde: `https://compratica.com/admin/email_marketing.php?page=templates`

## Uso

### Acceso al Sistema

URL: `https://compratica.com/admin/email_marketing.php`

Requiere login como administrador.

### Crear Nueva Campaña

1. Ir a "Nueva Campaña"
2. Seleccionar origen de datos:
   - **Excel**: Subir archivo con columnas: nombre, telefono, correo
   - **Base de Datos**: Seleccionar categorías (hoteles, restaurantes, bares, etc.)
   - **Manual**: Ingresar uno por uno

3. Configurar campaña:
   - Nombre de campaña
   - Cuenta SMTP (Mixtico, CRV-SOFT o CompraTica)
   - Plantilla HTML
   - Asunto del correo
   - Adjuntos (opcional)

4. Click en "Crear Campaña"

### Enviar Campaña

1. Ir a "Campañas"
2. Click en "Enviar" en la campaña deseada
3. El sistema enviará en batches de 50 emails
4. Delay de 2 segundos entre emails (anti-spam)
5. La página se auto-recargará hasta completar

### Ver Reportes

URL: `https://compratica.com/admin/email_marketing.php?page=reports`

Estadísticas disponibles:
- Total enviados
- Tasa de apertura
- Tasa de clicks
- Tasa de éxito
- Comparación con benchmarks de industria
- Top campañas por apertura

## Prevención de SPAM

### Configuración DNS Requerida

Para evitar que los correos vayan a spam, configurar:

#### SPF (Sender Policy Framework)

Agregar registro TXT en DNS:

```
v=spf1 include:_spf.google.com ~all
```

(Ajustar según proveedor SMTP)

#### DKIM (DomainKeys Identified Mail)

Configurar claves DKIM en servidor de correo.

Para Gmail/Google Workspace:
1. Admin Console → Apps → Gmail → Authenticate email
2. Generate new record
3. Agregar registro TXT en DNS

### Buenas Prácticas

- ✅ Solo enviar a contactos con permiso explícito
- ✅ Incluir link de cancelación en todos los emails
- ✅ Evitar palabras spam: GRATIS, URGENTE, !!!
- ✅ No usar exceso de mayúsculas
- ✅ Mantener lista limpia (remover rebotes)
- ✅ Verificar dominio en proveedor SMTP
- ✅ Usar nombre y email remitente real
- ✅ Ratio texto/imagen balanceado

## Tracking

### Aperturas

Sistema inyecta pixel invisible de 1x1 en cada email:

```html
<img src="https://compratica.com/admin/email_track.php?t={tracking_code}" width="1" height="1">
```

Cuando el destinatario abre el email, el pixel se carga y registra la apertura.

### Clicks

Todos los links son reescritos para pasar por sistema de tracking:

```
Original: https://mixtico.net/contacto
Tracking: https://compratica.com/admin/email_track.php?t={code}&a=click&url=...
```

### Cancelaciones

Link de cancelación incluido en footer de cada plantilla:

```
{unsubscribe_link}
```

## Variables de Plantilla

Disponibles en todas las plantillas:

- `{nombre}` - Nombre del destinatario
- `{email}` - Email del destinatario
- `{tracking_pixel}` - Pixel de tracking (auto-inyectado)
- `{unsubscribe_link}` - Link de cancelación
- `{campaign_id}` - ID de la campaña

## Proveedores SMTP Recomendados

### Gmail / Google Workspace
- Host: `smtp.gmail.com`
- Puerto: `587`
- Encriptación: TLS
- Límite: 500 emails/día (Gmail), 2000/día (Workspace)

### SendGrid
- Host: `smtp.sendgrid.net`
- Puerto: `587`
- Encriptación: TLS
- Límite: 100 emails/día (gratis), ilimitado (pago)

### Mailgun
- Host: `smtp.mailgun.org`
- Puerto: `587`
- Encriptación: TLS
- Límite: 10,000 emails/mes (gratis)

### cPanel/WHM
- Host: `mail.sudominio.com`
- Puerto: `587` o `465`
- Encriptación: TLS o SSL
- Límite: Según hosting (típicamente 500-1000/hora)

## Estructura de Archivos

```
admin/
├── email_marketing.php              # Panel principal con router
├── email_marketing_api.php          # API backend (CRUD)
├── email_sender.php                 # Motor de envío PHPMailer
├── email_track.php                  # Tracking opens/clicks
├── email_marketing_send.php         # Interface de envío batch
├── setup_email_marketing.sql        # Schema de base de datos
├── email_marketing/
│   ├── dashboard.php                # Dashboard estadísticas
│   ├── new_campaign.php             # Crear campaña
│   ├── campaigns.php                # Listar campañas
│   ├── templates.php                # Gestionar plantillas
│   ├── smtp_config.php              # Configurar SMTP
│   └── reports.php                  # Reportes y analytics
└── email_templates/
    ├── mixtico_template.html        # Plantilla Mixtico
    ├── crv_soft_template.html       # Plantilla CRV-SOFT
    └── compratica_template.html     # Plantilla CompraTica
```

## Troubleshooting

### Los emails van a SPAM

1. Verificar configuración SPF en DNS
2. Configurar DKIM
3. Verificar dominio en proveedor SMTP
4. Revisar contenido del email (evitar palabras spam)
5. Calentar IP (enviar pocos emails inicialmente)

### Errores de conexión SMTP

1. Verificar host y puerto correctos
2. Verificar usuario y contraseña
3. Verificar que el servidor permite SMTP externo
4. Revisar firewall (permitir puerto 587/465)

### Timeout al enviar

1. Reducir batch_size en `email_marketing_send.php` (línea 48)
2. Aumentar max_execution_time en php.ini
3. Usar envío en background (futuro)

### No se registran aperturas

1. Verificar que email_track.php es accesible públicamente
2. Algunos clientes bloquean imágenes por defecto
3. Emails de texto plano no pueden trackear aperturas

## Seguridad

- ✅ Autenticación de admin requerida
- ✅ Passwords SMTP almacenados en BD (considerar encriptación)
- ✅ Validación de inputs
- ✅ Protección SQL injection (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ CSRF protection (pendiente tokens)

## Roadmap

Mejoras futuras:
- [ ] A/B testing de asuntos
- [ ] Programación de envíos
- [ ] Segmentación avanzada
- [ ] Reportes por periodo
- [ ] Exportar reportes CSV/PDF
- [ ] Templates con editor visual
- [ ] Integración con webhooks
- [ ] API REST para integración externa

## Soporte

Para dudas o problemas contactar al equipo de desarrollo.

---

**Versión:** 1.0.0
**Fecha:** 2025-01-19
**Autor:** Claude Code
