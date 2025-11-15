/* =========================
   COMPRATICA - Estilo Moderno Limpio
   Reemplazo completo de assets/style.css
   ========================= */

:root{
  --bg: #f7f8fa;
  --card: #ffffff;
  --text: #1f2937;         /* gris oscuro */
  --muted: #6b7280;        /* gris medio */
  --line: #e5e7eb;         /* borde claro */
  --accent: #10b981;       /* verde principal */
  --accent-600: #0f9c72;   /* hover verde */
  --accent-700: #0d8b65;   /* active verde */
  --warn-bg: #fef3c7;
  --warn-line: #fde68a;
  --ok-bg: #dcfce7;
  --ok-line: #bbf7d0;
  --radius-lg: 16px;
  --radius-md: 12px;
  --radius-sm: 8px;
  --shadow-1: 0 2px 8px rgba(0,0,0,0.08);
  --shadow-2: 0 6px 16px rgba(0,0,0,0.10);
}

*{ box-sizing: border-box; }

html, body{
  margin:0;
  padding:0;
  background: var(--bg);
  color: var(--text);
  font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;
  line-height: 1.45;
}

/* =========================
   ENCABEZADO NUEVO
   ========================= */
.header{
  position: sticky;
  top: 0;
  z-index: 50;
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding: 12px 18px;
  background: linear-gradient(180deg,#ffffff 0%, #fbfbfb 100%);
  border-bottom: 1px solid var(--line);
  backdrop-filter: saturate(1.1);
}
.logo{
  font-weight: 800;
  letter-spacing: .2px;
  display:flex; gap:10px; align-items:center;
}
.header nav{
  display:flex; gap:10px; flex-wrap:wrap;
}

/* =========================
   CONTENEDOR / LAYOUT
   ========================= */
.container{
  max-width: 1100px;
  margin: 22px auto;
  padding: 0 16px;
}

/* Secciones (opcional, para títulos) */
h1,h2,h3{ margin: 0 0 10px; line-height: 1.25; }
h1{ font-size: 1.8rem; font-weight: 800; }
h2{ font-size: 1.35rem; font-weight: 700; }
h3{ font-size: 1.1rem; font-weight: 700; }

/* =========================
   GRILLAS Y CARDS
   ========================= */
.grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.card{
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  box-shadow: var(--shadow-1);
  transition: transform .18s ease, box-shadow .18s ease;
}
.card:hover{
  transform: translateY(-3px);
  box-shadow: var(--shadow-2);
}

/* Imagen principal dentro de card */
.card img{
  width: 100%;
  height: 200px;
  object-fit: cover;          /* recorte elegante */
  object-position: center;
  border-radius: 12px;
  border: 1px solid #eee;
}

/* Contenedor para asegurar ratio y evitar saltos */
.imgbox{
  width: 100%;
  aspect-ratio: 4/3;
  overflow: hidden;
  border-radius: 12px;
  border: 1px solid #eee;
  background: #fafafa;
}
.imgbox img{
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

/* Thumbs pequeñas (ej: pedidos, comprobantes) */
.thumb{
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid var(--line);
}

/* =========================
   TIPOS Y UTILIDADES
   ========================= */
.small{ font-size: 12px; color: var(--muted); }
.note{ font-size: 12px; color: #374151; }
.price{ font-weight: 800; letter-spacing: .2px; }

/* =========================
   BOTONES
   ========================= */
.btn{
  display: inline-flex;
  align-items:center;
  gap:8px;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: #fff;
  text-decoration: none;
  color: var(--text);
  cursor: pointer;
  transition: background .18s ease, box-shadow .18s ease, transform .06s ease;
  box-shadow: 0 1px 0 rgba(0,0,0,0.02);
}
.btn:hover{ background: #fafafa; box-shadow: var(--shadow-1); }
.btn:active{ transform: translateY(1px); }
.btn.primary{
  background: var(--accent);
  color: #fff;
  border-color: transparent;
}
.btn.primary:hover{ background: var(--accent-600); }
.btn.primary:active{ background: var(--accent-700); }

/* Botón ancho (ej: PayPal/SINPE) */
.paybtn img{ height: 20px; width: auto; vertical-align: middle; }

/* Enlaces de WhatsApp debajo de comprar (si los usas) */
.whats{ display:block; margin-top:8px; }

/* =========================
   FORMULARIOS
   ========================= */
.form{
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  padding: 14px;
  display: grid;
  gap: 10px;
}
.input, select, textarea{
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  background: #fff;
  color: var(--text);
}
.input:focus, select:focus, textarea:focus{
  outline: 3px solid rgba(16,185,129,0.25);
  border-color: var(--accent);
}

/* =========================
   TABLAS
   ========================= */
.table{
  width: 100%;
  border-collapse: collapse;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-1);
}
.table thead th{
  background: #fafafa;
  border-bottom: 1px solid var(--line);
  font-weight: 700;
  font-size: 13px;
  color: #111827;
  padding: 10px 8px;
  text-align: left;
}
.table td{
  padding: 10px 8px;
  border-bottom: 1px solid #f1f3f5;
  vertical-align: top;
  font-size: 14px;
}
.actions{ display:flex; gap:8px; flex-wrap:wrap; }

/* =========================
   ALERTAS
   ========================= */
.alert{
  background: var(--warn-bg);
  border: 1px solid var(--warn-line);
  padding: 10px 12px;
  border-radius: 10px;
}
.success{
  background: var(--ok-bg);
  border: 1px solid var(--ok-line);
  padding: 10px 12px;
  border-radius: 10px;
}

/* =========================
   FOOTER NUEVO
   ========================= */
.site-footer,
footer{
  margin-top: 28px;
  background: #ffffff;
  border-top: 1px solid var(--line);
}
.site-footer .inner,
footer .container{
  max-width: 1100px;
  margin: 0 auto;
  padding: 18px 16px;
  color: var(--muted);
  display:flex;
  flex-wrap:wrap;
  gap: 12px;
  align-items:center;
  justify-content: space-between;
}
.footer-links{
  display:flex; gap:12px; flex-wrap:wrap;
}
.footer-links a{
  color: var(--muted);
  text-decoration: none;
}
.footer-links a:hover{ text-decoration: underline; }

/* =========================
   RESPONSIVE
   ========================= */
@media (max-width: 640px){
  .header{ padding: 10px 14px; }
  .container{ margin: 18px auto; }
  .card img{ height: 180px; }
  .imgbox{ aspect-ratio: 1/1; } /* cuadrado en móvil */
  .btn{ padding: 10px 12px; }
  .header nav{ gap:6px; }
}

/* =========================
   ACCESIBILIDAD / FOCUS LINKS
   ========================= */
a:focus-visible, .btn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible{
  outline: 3px solid rgba(16,185,129,0.35);
  outline-offset: 2px;
  border-radius: 8px;
}




/*NEW*/
/* ===== Enhancements: chips, badge row, mobile tweaks ===== */
.badges-row{
  display:flex;
  gap:8px;
  align-items:center;
  flex-wrap:wrap;
}

/* Chip secundario */
.chip{
  display:inline-flex;
  align-items:center;
  padding: 3px 10px;
  font-size: 12px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background:#fff;
  color: var(--text);
}
.chip-blue   { background: #e8f0fe; color: #1e40af; border-color: #c7d2fe; }
.chip-orange { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
.chip-red    { background: #fee2e2; color: #7f1d1d; border-color: #fecaca; }

/* Mobile finos */
@media (max-width: 640px){
  h1{ font-size: 1.4rem; }
  h3{ font-size: 1.05rem; }
  .badges-row { gap:6px; }
}

