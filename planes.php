<?php
/**
 * planes.php — Página de Planes y Precios de CompraTica
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planes y Precios | CompraTica</title>
  <meta name="description" content="Conocé todos los planes de CompraTica para emprendedores, venta de garaje, servicios, empleos y bienes raíces. Desde gratis.">
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/compratica-header.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f8fafc;color:#111827;}

    /* ── HERO ────────────────────────────────────────────────────────────────── */
    .planes-hero{
      background:linear-gradient(135deg,#3730a3 0%,#6d28d9 50%,#be185d 100%);
      padding:5rem 1.5rem 4rem;
      text-align:center;
      color:#fff;
    }
    .planes-hero h1{font-size:clamp(1.8rem,4vw,3rem);font-weight:800;margin-bottom:.75rem;}
    .planes-hero p{font-size:1.1rem;opacity:.88;max-width:560px;margin:0 auto 2rem;line-height:1.6;}
    .planes-hero-pills{display:flex;justify-content:center;flex-wrap:wrap;gap:.6rem;}
    .planes-hero-pills span{
      background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
      border-radius:30px;padding:.35rem .9rem;font-size:.82rem;font-weight:600;
      backdrop-filter:blur(6px);
    }

    /* ── CONTENEDOR ──────────────────────────────────────────────────────────── */
    .planes-wrap{max-width:1180px;margin:0 auto;padding:3.5rem 1.5rem 5rem;}

    /* ── SECCIÓN ─────────────────────────────────────────────────────────────── */
    .plan-section{margin-bottom:3.5rem;}
    .plan-section-header{
      display:flex;align-items:center;gap:1rem;
      margin-bottom:1.5rem;padding-bottom:.75rem;
      border-bottom:3px solid;
    }
    .plan-section-icon{
      width:48px;height:48px;border-radius:12px;
      display:flex;align-items:center;justify-content:center;
      font-size:1.3rem;color:#fff;flex-shrink:0;
    }
    .plan-section-header h2{font-size:1.4rem;font-weight:700;color:#111827;}
    .plan-section-header p{font-size:.88rem;color:#6b7280;margin-top:.2rem;}
    .plan-cards-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.25rem;}

    /* ── TARJETA DE PLAN ─────────────────────────────────────────────────────── */
    .plan-card{
      background:#fff;border-radius:16px;
      border:2px solid #e5e7eb;
      padding:1.5rem 1.25rem 1.25rem;
      display:flex;flex-direction:column;
      transition:transform .2s,box-shadow .2s,border-color .2s;
      position:relative;overflow:hidden;
    }
    .plan-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.1);}
    .plan-card.featured{border-width:2px;}

    .plan-badge{
      position:absolute;top:14px;right:14px;
      font-size:.65rem;font-weight:700;
      padding:.2rem .55rem;border-radius:20px;letter-spacing:.05em;color:#fff;
    }

    .plan-price-block{margin:1rem 0 .75rem;}
    .plan-price-main{font-size:1.8rem;font-weight:800;line-height:1;}
    .plan-price-period{font-size:.82rem;color:#9ca3af;font-weight:400;margin-left:.15rem;}
    .plan-price-annual{font-size:.78rem;color:#16a34a;font-weight:600;margin-top:.3rem;}

    .plan-name{font-size:1rem;font-weight:700;color:#111827;margin-bottom:.25rem;}
    .plan-desc{font-size:.82rem;color:#6b7280;line-height:1.5;margin-bottom:.75rem;}

    .plan-features{list-style:none;flex:1;}
    .plan-features li{
      font-size:.82rem;color:#374151;
      padding:.28rem 0;display:flex;align-items:flex-start;gap:.5rem;
    }
    .plan-features li i{font-size:.7rem;margin-top:.25rem;flex-shrink:0;}

    .plan-cta{
      display:block;margin-top:1rem;padding:.65rem 1rem;
      border-radius:10px;text-align:center;
      font-size:.85rem;font-weight:700;text-decoration:none;
      transition:opacity .18s,transform .18s;
    }
    .plan-cta:hover{opacity:.88;transform:translateY(-1px);}
    .plan-note{font-size:.75rem;color:#9ca3af;text-align:center;margin-top:.5rem;line-height:1.4;}

    /* Colores por sección */
    .sec-emprendedor .plan-section-header{border-color:#667eea;}
    .sec-emprendedor .plan-section-icon{background:linear-gradient(135deg,#667eea,#764ba2);}
    .sec-emprendedor .plan-features li i{color:#667eea;}
    .sec-emprendedor .plan-card.featured{border-color:#667eea;box-shadow:0 0 0 4px rgba(102,126,234,.1);}
    .sec-emprendedor .plan-cta{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}

    .sec-garaje .plan-section-header{border-color:#f97316;}
    .sec-garaje .plan-section-icon{background:linear-gradient(135deg,#f97316,#ea580c);}
    .sec-garaje .plan-features li i{color:#f97316;}
    .sec-garaje .plan-card.featured{border-color:#f97316;box-shadow:0 0 0 4px rgba(249,115,22,.1);}
    .sec-garaje .plan-cta{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;}

    .sec-servicios .plan-section-header{border-color:#2563eb;}
    .sec-servicios .plan-section-icon{background:linear-gradient(135deg,#2563eb,#1d4ed8);}
    .sec-servicios .plan-features li i{color:#2563eb;}
    .sec-servicios .plan-card.featured{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.1);}
    .sec-servicios .plan-cta{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;}

    .sec-empleos .plan-section-header{border-color:#7c3aed;}
    .sec-empleos .plan-section-icon{background:linear-gradient(135deg,#7c3aed,#6d28d9);}
    .sec-empleos .plan-features li i{color:#7c3aed;}
    .sec-empleos .plan-card.featured{border-color:#7c3aed;box-shadow:0 0 0 4px rgba(124,58,237,.1);}
    .sec-empleos .plan-cta{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;}

    .sec-bienes .plan-section-header{border-color:#059669;}
    .sec-bienes .plan-section-icon{background:linear-gradient(135deg,#059669,#047857);}
    .sec-bienes .plan-features li i{color:#059669;}
    .sec-bienes .plan-card.featured{border-color:#059669;box-shadow:0 0 0 4px rgba(5,150,105,.1);}
    .sec-bienes .plan-cta{background:linear-gradient(135deg,#059669,#047857);color:#fff;}

    /* Gratis badge */
    .badge-free{background:#16a34a;}
    .badge-popular{background:#2563eb;}
    .badge-premium{background:#b45309;}
    .badge-value{background:#0d9488;}
    .badge-flex{background:#0d9488;}

    /* Precio gratis */
    .price-free{color:#16a34a;}

    /* Barra de pagos */
    .payments-bar{
      background:#fff;border:1px solid #e5e7eb;border-radius:14px;
      padding:1.25rem 1.5rem;text-align:center;
      margin-top:3rem;
    }
    .payments-bar p{font-size:.88rem;color:#6b7280;margin-bottom:.6rem;}
    .payments-logos{display:flex;align-items:center;justify-content:center;gap:1rem;flex-wrap:wrap;}
    .payments-logos span{
      background:#f3f4f6;border-radius:8px;padding:.35rem .8rem;
      font-size:.8rem;font-weight:700;color:#374151;letter-spacing:.03em;
    }

    @media(max-width:640px){
      .plan-cards-row{grid-template-columns:1fr;}
      .planes-hero{padding:3.5rem 1rem 3rem;}
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- HERO -->
<div class="planes-hero">
  <h1><i class="fas fa-tags" style="margin-right:.5rem;"></i>Planes y Precios</h1>
  <p>Elegí la modalidad que mejor se adapta a tu negocio. Todos los planes incluyen pagos seguros.</p>
  <div class="planes-hero-pills">
    <span><i class="fas fa-check"></i> SINPE Móvil</span>
    <span><i class="fas fa-check"></i> PayPal</span>
    <span><i class="fas fa-credit-card"></i> Visa / Mastercard / Amex</span>
    <span><i class="fas fa-lock"></i> Pagos seguros</span>
  </div>
</div>

<div class="planes-wrap">

  <!-- ── EMPRENDEDORES ──────────────────────────────────────────────────────── -->
  <div class="plan-section sec-emprendedor">
    <div class="plan-section-header">
      <div class="plan-section-icon"><i class="fas fa-store"></i></div>
      <div>
        <h2>Emprendedores</h2>
        <p>Publicá tu tienda con productos y servicios en el catálogo de CompraTica</p>
      </div>
    </div>
    <div class="plan-cards-row">

      <div class="plan-card">
        <span class="plan-badge badge-free">GRATIS</span>
        <div class="plan-name">Plan Gratuito</div>
        <div class="plan-desc">Para empezar a vender sin compromiso</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">₡0</span>
          <span class="plan-price-period">/ mes</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Hasta 5 productos publicados</li>
          <li><i class="fas fa-check-circle"></i> Perfil de emprendedor en catálogo</li>
          <li><i class="fas fa-check-circle"></i> Pagos vía SINPE, PayPal y tarjeta</li>
          <li><i class="fas fa-check-circle"></i> Soporte básico por correo</li>
        </ul>
        <a href="emprendedoras-planes.php" class="plan-cta">Comenzar gratis</a>
      </div>

      <div class="plan-card featured">
        <span class="plan-badge badge-popular">RECOMENDADO</span>
        <div class="plan-name">Plan Emprendedor/a</div>
        <div class="plan-desc">Para quienes quieren crecer en serio</div>
        <div class="plan-price-block">
          <span class="plan-price-main">₡9.900</span>
          <span class="plan-price-period">/ mes</span>
          <div class="plan-price-annual"><i class="fas fa-tag"></i> ₡99.000 / año — ahorrás 2 meses</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Hasta 50 productos publicados</li>
          <li><i class="fas fa-check-circle"></i> Estadísticas avanzadas de ventas</li>
          <li><i class="fas fa-check-circle"></i> Sin comisiones por venta</li>
          <li><i class="fas fa-check-circle"></i> Soporte prioritario</li>
          <li><i class="fas fa-check-circle"></i> Pagos vía SINPE, PayPal y tarjeta</li>
        </ul>
        <a href="emprendedoras-planes.php" class="plan-cta">Elegir este plan</a>
      </div>

      <div class="plan-card">
        <span class="plan-badge badge-premium">PREMIUM</span>
        <div class="plan-name">Plan Premium</div>
        <div class="plan-desc">Para negocios en pleno crecimiento</div>
        <div class="plan-price-block">
          <span class="plan-price-main">₡19.900</span>
          <span class="plan-price-period">/ mes</span>
          <div class="plan-price-annual"><i class="fas fa-tag"></i> ₡199.000 / año — ahorrás 2 meses</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Productos ilimitados</li>
          <li><i class="fas fa-check-circle"></i> Estadísticas avanzadas</li>
          <li><i class="fas fa-check-circle"></i> Soporte VIP (24 h)</li>
          <li><i class="fas fa-check-circle"></i> Sin comisiones por venta</li>
          <li><i class="fas fa-check-circle"></i> Destacado en catálogo principal</li>
        </ul>
        <a href="emprendedoras-planes.php" class="plan-cta">Elegir Premium</a>
      </div>

      <div class="plan-card">
        <span class="plan-badge badge-flex">FLEXIBLE</span>
        <div class="plan-name">Plan por Comisión</div>
        <div class="plan-desc">Sin cuota mensual, solo pagás cuando vendés</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">₡0</span>
          <span class="plan-price-period">cuota mensual</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Sin pago mensual fijo</li>
          <li><i class="fas fa-check-circle"></i> 10% comisión solo cuando vendés</li>
          <li><i class="fas fa-check-circle"></i> Productos ilimitados</li>
          <li><i class="fas fa-check-circle"></i> Pagos vía SINPE, PayPal y tarjeta</li>
          <li><i class="fas fa-check-circle"></i> Ideal si vendés ocasionalmente</li>
        </ul>
        <a href="https://wa.me/50688888888" class="plan-cta">Consultar por WhatsApp</a>
        <p class="plan-note">Habilitación bajo solicitud</p>
      </div>

    </div>
  </div>

  <!-- ── VENTA DE GARAJE ────────────────────────────────────────────────────── -->
  <div class="plan-section sec-garaje">
    <div class="plan-section-header">
      <div class="plan-section-icon"><i class="fas fa-tags"></i></div>
      <div>
        <h2>Venta de Garaje</h2>
        <p>Vendé artículos de segunda mano con tu tienda personal — sin costo mensual</p>
      </div>
    </div>
    <div class="plan-cards-row">

      <div class="plan-card featured" style="max-width:400px;">
        <span class="plan-badge badge-free">SIEMPRE GRATIS</span>
        <div class="plan-name">Cuenta Gratuita</div>
        <div class="plan-desc">Abrí tu tienda de garaje en minutos</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">₡0</span>
          <span class="plan-price-period">siempre</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Tienda personal con URL propia</li>
          <li><i class="fas fa-check-circle"></i> Artículos ilimitados publicados</li>
          <li><i class="fas fa-check-circle"></i> Galería de fotos por artículo</li>
          <li><i class="fas fa-check-circle"></i> Pedidos con SINPE o PayPal</li>
          <li><i class="fas fa-check-circle"></i> Panel de inventario y stock</li>
          <li><i class="fas fa-check-circle"></i> Reporte PDF de inventario</li>
          <li><i class="fas fa-check-circle"></i> Link directo por artículo</li>
        </ul>
        <a href="affiliate/register.php" class="plan-cta">Crear mi tienda</a>
        <p class="plan-note">10% de comisión sobre cada venta procesada por CompraTica</p>
      </div>

    </div>
  </div>

  <!-- ── SERVICIOS ──────────────────────────────────────────────────────────── -->
  <div class="plan-section sec-servicios">
    <div class="plan-section-header">
      <div class="plan-section-icon"><i class="fas fa-briefcase"></i></div>
      <div>
        <h2>Servicios Profesionales</h2>
        <p>Publicá tu servicio y conectá con clientes en todo Costa Rica</p>
      </div>
    </div>
    <div class="plan-cards-row">

      <div class="plan-card">
        <span class="plan-badge badge-free">GRATIS</span>
        <div class="plan-name">Prueba Gratis</div>
        <div class="plan-desc">Probá la plataforma sin costo</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">$0</span>
          <span class="plan-price-period">/ 7 días</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 7 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 2 fotos del servicio</li>
          <li><i class="fas fa-check-circle"></i> Visible en buscador</li>
        </ul>
        <a href="servicios" class="plan-cta">Publicar servicio</a>
      </div>

      <div class="plan-card featured">
        <span class="plan-badge badge-popular">POPULAR</span>
        <div class="plan-name">Plan 30 días</div>
        <div class="plan-desc">Un mes completo de exposición</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$0,75</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡405</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 30 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 4 fotos del servicio</li>
          <li><i class="fas fa-check-circle"></i> Visible en buscador</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
        </ul>
        <a href="servicios" class="plan-cta">Elegir 30 días</a>
      </div>

      <div class="plan-card">
        <span class="plan-badge badge-value">MEJOR VALOR</span>
        <div class="plan-name">Plan 60 días</div>
        <div class="plan-desc">El doble de exposición, ahorrás un 20%</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$1,25</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡675 — ahorrás 20%</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 60 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 6 fotos del servicio</li>
          <li><i class="fas fa-check-circle"></i> Visible en buscador</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
          <li><i class="fas fa-check-circle"></i> Mejor relación costo-duración</li>
        </ul>
        <a href="servicios" class="plan-cta">Elegir 60 días</a>
      </div>

    </div>
  </div>

  <!-- ── EMPLEOS ────────────────────────────────────────────────────────────── -->
  <div class="plan-section sec-empleos">
    <div class="plan-section-header">
      <div class="plan-section-icon"><i class="fas fa-user-tie"></i></div>
      <div>
        <h2>Empleos</h2>
        <p>Publicá tu oferta laboral y encontrá al candidato ideal</p>
      </div>
    </div>
    <div class="plan-cards-row">

      <div class="plan-card">
        <span class="plan-badge badge-free">GRATIS</span>
        <div class="plan-name">Prueba Gratis</div>
        <div class="plan-desc">14 días sin costo para probar</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">$0</span>
          <span class="plan-price-period">/ 14 días</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 14 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 2 fotos o imágenes</li>
          <li><i class="fas fa-check-circle"></i> Visible en bolsa de empleos</li>
          <li><i class="fas fa-check-circle"></i> Postulaciones por correo</li>
        </ul>
        <a href="empleos" class="plan-cta">Publicar empleo</a>
      </div>

      <div class="plan-card featured">
        <span class="plan-badge badge-popular">POPULAR</span>
        <div class="plan-name">Plan 30 días</div>
        <div class="plan-desc">Un mes completo de visibilidad</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$0,50</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡270</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 30 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 3 fotos o imágenes</li>
          <li><i class="fas fa-check-circle"></i> Visible en bolsa de empleos</li>
          <li><i class="fas fa-check-circle"></i> Postulaciones por correo</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
        </ul>
        <a href="empleos" class="plan-cta">Elegir 30 días</a>
      </div>

      <div class="plan-card">
        <span class="plan-badge badge-value">MEJOR VALOR</span>
        <div class="plan-name">Plan 60 días</div>
        <div class="plan-desc">Más tiempo para encontrar al indicado</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$0,80</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡432 — ahorrás 20%</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 60 días</li>
          <li><i class="fas fa-check-circle"></i> Hasta 4 fotos o imágenes</li>
          <li><i class="fas fa-check-circle"></i> Visible en bolsa de empleos</li>
          <li><i class="fas fa-check-circle"></i> Postulaciones por correo</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
        </ul>
        <a href="empleos" class="plan-cta">Elegir 60 días</a>
      </div>

    </div>
  </div>

  <!-- ── BIENES RAÍCES ──────────────────────────────────────────────────────── -->
  <div class="plan-section sec-bienes">
    <div class="plan-section-header">
      <div class="plan-section-icon"><i class="fas fa-home"></i></div>
      <div>
        <h2>Bienes Raíces</h2>
        <p>Publicá tu propiedad en venta o alquiler y llegá a compradores en toda CR</p>
      </div>
    </div>
    <div class="plan-cards-row">

      <div class="plan-card">
        <span class="plan-badge badge-free">GRATIS</span>
        <div class="plan-name">Prueba Gratis</div>
        <div class="plan-desc">Probá sin comprometerte</div>
        <div class="plan-price-block">
          <span class="plan-price-main price-free">$0</span>
          <span class="plan-price-period">/ 7 días</span>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 7 días</li>
          <li><i class="fas fa-check-circle"></i> Fotos del inmueble incluidas</li>
          <li><i class="fas fa-check-circle"></i> Visible en catálogo</li>
          <li><i class="fas fa-check-circle"></i> Contacto directo con interesados</li>
        </ul>
        <a href="bienes-raices" class="plan-cta">Publicar propiedad</a>
      </div>

      <div class="plan-card featured">
        <span class="plan-badge badge-popular">POPULAR</span>
        <div class="plan-name">Plan 30 días</div>
        <div class="plan-desc">Un mes de máxima visibilidad</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$1,00</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡540</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 30 días</li>
          <li><i class="fas fa-check-circle"></i> Galería de fotos del inmueble</li>
          <li><i class="fas fa-check-circle"></i> Mapa de ubicación integrado</li>
          <li><i class="fas fa-check-circle"></i> Visible en catálogo</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
        </ul>
        <a href="bienes-raices" class="plan-cta">Elegir 30 días</a>
      </div>

      <div class="plan-card">
        <span class="plan-badge badge-value">MEJOR VALOR</span>
        <div class="plan-name">Plan 90 días</div>
        <div class="plan-desc">3 meses de exposición, ahorrás un 33%</div>
        <div class="plan-price-block">
          <span class="plan-price-main">$2,00</span>
          <span class="plan-price-period">/ publicación</span>
          <div class="plan-price-annual">aprox. ₡1.080 — ahorrás 33%</div>
        </div>
        <ul class="plan-features">
          <li><i class="fas fa-check-circle"></i> Publicación activa 90 días</li>
          <li><i class="fas fa-check-circle"></i> Galería de fotos del inmueble</li>
          <li><i class="fas fa-check-circle"></i> Mapa de ubicación integrado</li>
          <li><i class="fas fa-check-circle"></i> Máxima exposición — 3 meses</li>
          <li><i class="fas fa-check-circle"></i> SINPE, PayPal o tarjeta</li>
        </ul>
        <a href="bienes-raices" class="plan-cta">Elegir 90 días</a>
      </div>

    </div>
  </div>

  <!-- MÉTODOS DE PAGO -->
  <div class="payments-bar">
    <p><strong>Todos los planes aceptan los siguientes métodos de pago</strong></p>
    <div class="payments-logos">
      <span>SINPE Móvil</span>
      <span>PayPal</span>
      <span style="color:#1a1f71;">VISA</span>
      <span style="color:#eb001b;">Mastercard</span>
      <span style="color:#2e77bc;">American Express</span>
    </div>
    <p style="margin-top:.75rem;font-size:.8rem;">
      ¿Tenés dudas? <a href="https://wa.me/50688888888" style="color:#16a34a;font-weight:600;">Escribinos por WhatsApp</a>
    </p>
  </div>

</div><!-- /planes-wrap -->

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
