<?php
// Número de WhatsApp para soporte humano (editar aquí)
$support_whatsapp = '50688902814';
$support_whatsapp_msg = urlencode('Hola, necesito ayuda con CompraTica.');
?>
<!-- ═══════════════════════════════════════════
     WIDGET DE SOPORTE / CHATBOT - CompraTica
════════════════════════════════════════════ -->
<style>
/* ── Botón flotante ───────────────────────── */
#ct-chat-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #1e1b4b;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
  z-index: 9999;
  transition: transform .2s, box-shadow .2s;
}
#ct-chat-btn:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 28px rgba(0,0,0,.45);
}
#ct-chat-btn svg { width: 26px; height: 26px; }

/* Badgede notificación */
#ct-chat-badge {
  position: absolute;
  top: 2px; right: 2px;
  width: 14px; height: 14px;
  background: #ef4444;
  border-radius: 50%;
  border: 2px solid #fff;
  display: none;
}

/* ── Panel del chat ───────────────────────── */
#ct-chat-panel {
  position: fixed;
  bottom: 90px;
  right: 24px;
  width: 340px;
  max-width: calc(100vw - 32px);
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 8px 40px rgba(0,0,0,.18);
  z-index: 9998;
  display: none;
  flex-direction: column;
  overflow: hidden;
  font-family: 'Segoe UI', system-ui, sans-serif;
  max-height: 520px;
}
#ct-chat-panel.ct-open { display: flex; }

/* Header */
#ct-chat-header {
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.ct-avatar {
  width: 40px; height: 40px;
  border-radius: 50%;
  background: rgba(255,255,255,.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}
.ct-header-info h4 {
  margin: 0; color: #fff;
  font-size: .95rem; font-weight: 700;
}
.ct-header-info p {
  margin: 2px 0 0; color: rgba(255,255,255,.7);
  font-size: .75rem;
}
.ct-online-dot {
  width: 8px; height: 8px;
  background: #4ade80;
  border-radius: 50%;
  display: inline-block;
  margin-right: 4px;
}
#ct-close-btn {
  margin-left: auto;
  background: none; border: none;
  color: rgba(255,255,255,.7);
  cursor: pointer; font-size: 1.2rem; line-height: 1;
  padding: 4px;
}
#ct-close-btn:hover { color: #fff; }

/* Mensajes */
#ct-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px 14px 8px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #f8f9fc;
}
.ct-msg {
  display: flex;
  flex-direction: column;
  max-width: 85%;
}
.ct-msg.ct-bot { align-self: flex-start; }
.ct-msg.ct-user { align-self: flex-end; }

.ct-bubble {
  padding: 10px 14px;
  border-radius: 16px;
  font-size: .875rem;
  line-height: 1.45;
}
.ct-bot .ct-bubble {
  background: #fff;
  color: #1e293b;
  border-bottom-left-radius: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.ct-user .ct-bubble {
  background: #312e81;
  color: #fff;
  border-bottom-right-radius: 4px;
}

/* Opciones de respuesta rápida */
#ct-options {
  padding: 8px 14px 14px;
  display: flex;
  flex-direction: column;
  gap: 7px;
  background: #f8f9fc;
  border-top: 1px solid #e5e7eb;
}
.ct-opt-btn {
  background: #fff;
  border: 1.5px solid #c7d2fe;
  color: #312e81;
  border-radius: 10px;
  padding: 9px 14px;
  font-size: .845rem;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: background .15s, border-color .15s;
  display: flex; align-items: center; gap: 8px;
}
.ct-opt-btn:hover {
  background: #eef2ff;
  border-color: #818cf8;
}
.ct-opt-btn.ct-primary {
  background: #312e81;
  color: #fff;
  border-color: #312e81;
}
.ct-opt-btn.ct-primary:hover { background: #1e1b4b; }
.ct-opt-btn.ct-human {
  background: #f0fdf4;
  border-color: #86efac;
  color: #166534;
}
.ct-opt-btn.ct-human:hover { background: #dcfce7; }
.ct-opt-btn.ct-back {
  background: #f9fafb;
  border-color: #e5e7eb;
  color: #6b7280;
  font-weight: 500;
}

/* Typing indicator */
.ct-typing { display: flex; gap: 4px; padding: 12px 14px; }
.ct-typing span {
  width: 7px; height: 7px;
  background: #94a3b8;
  border-radius: 50%;
  animation: ct-bounce .9s infinite;
}
.ct-typing span:nth-child(2) { animation-delay: .15s; }
.ct-typing span:nth-child(3) { animation-delay: .3s; }
@keyframes ct-bounce {
  0%,60%,100% { transform: translateY(0); }
  30% { transform: translateY(-5px); }
}
</style>

<!-- Botón flotante -->
<button id="ct-chat-btn" aria-label="Abrir chat de soporte" title="Soporte CompraTica">
  <div id="ct-chat-badge"></div>
  <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
</button>

<!-- Panel del chat -->
<div id="ct-chat-panel" role="dialog" aria-label="Chat de soporte">
  <div id="ct-chat-header">
    <div class="ct-avatar">🛒</div>
    <div class="ct-header-info">
      <h4>Soporte CompraTica</h4>
      <p><span class="ct-online-dot"></span>En línea — respuesta inmediata</p>
    </div>
    <button id="ct-close-btn" aria-label="Cerrar chat">✕</button>
  </div>
  <div id="ct-messages"></div>
  <div id="ct-options"></div>
</div>

<script>
(function(){
  // ── Datos de FAQs ─────────────────────────
  const WHATSAPP = '<?= $support_whatsapp ?>';
  const WA_MSG   = '<?= $support_whatsapp_msg ?>';

  const FAQS = {
    clientes: [
      {
        q: '¿Cómo compro un producto?',
        a: 'Navegá en el catálogo, elegí el producto y hacé clic en <strong>Comprar</strong>. Podés pagar con SINPE Móvil, PayPal o tarjeta. Recibirás una confirmación por correo. 📦'
      },
      {
        q: '¿Cómo pago con SINPE Móvil?',
        a: 'Al finalizar tu compra seleccioná <strong>SINPE Móvil</strong>, transferí al número indicado y subí la captura del comprobante. El vendedor confirmará tu pedido en un máximo de 24 horas. 📲'
      },
      {
        q: '¿Cuánto tarda el envío?',
        a: 'El tiempo de envío lo define cada vendedor. Normalmente es de <strong>1 a 5 días hábiles</strong> dentro de Costa Rica. Algunos ofrecen también retiro en local. 🚚'
      },
      {
        q: '¿Cómo contacto a un vendedor?',
        a: 'En la página de cada producto hay un botón de <strong>WhatsApp</strong> o correo para contactar directamente al vendedor. También podés enviarle un mensaje desde su tienda. 💬'
      },
      {
        q: '¿Cómo reporto un problema con mi compra?',
        a: 'Si tuviste un problema, podés reportarlo respondiendo el correo de confirmación, contactando al vendedor directamente, o escribiéndonos a soporte. ¡Estamos para ayudarte! ✅'
      },
    ],
    vendedores: [
      {
        q: '¿Cómo me registro como Emprendedor/a?',
        a: 'Hacé clic en <strong>Portal Emprendedoras/Emprendedores</strong> en el menú, creá tu cuenta y elegí un plan. En menos de 24 horas tu cuenta estará activa. 🌟'
      },
      {
        q: '¿Cuáles son los planes disponibles?',
        a: 'Tenemos planes <strong>Gratuito, Básico y Premium</strong>. Cada plan varía en cantidad de productos, comisión y funciones. Visitá la página de planes para ver todos los detalles. 📋'
      },
      {
        q: '¿Cómo publico un producto?',
        a: 'Desde tu <strong>Dashboard</strong> hacé clic en <em>Agregar Producto</em>, completá el nombre, descripción, precio, fotos y stock. Tu producto aparecerá en el catálogo de inmediato. 🛍️'
      },
      {
        q: '¿Cómo recibo los pagos?',
        a: 'Los clientes te pagan directamente por <strong>SINPE Móvil, PayPal o tarjeta</strong>. Cuando el cliente sube el comprobante, te llegará una notificación para confirmar el pedido. 💰'
      },
      {
        q: '¿Qué comisión cobra CompraTica?',
        a: 'Depende de tu plan. El plan gratuito tiene una comisión del <strong>10%</strong>, el Básico <strong>7%</strong> y el Premium <strong>5%</strong>. La comisión se aplica solo a ventas realizadas. 📊'
      },
    ]
  };

  // ── Estado ────────────────────────────────
  let state = 'start'; // start | type_menu | clientes | vendedores
  const $panel    = document.getElementById('ct-chat-panel');
  const $btn      = document.getElementById('ct-chat-btn');
  const $close    = document.getElementById('ct-close-btn');
  const $messages = document.getElementById('ct-messages');
  const $options  = document.getElementById('ct-options');
  const $badge    = document.getElementById('ct-chat-badge');

  // ── Helpers ───────────────────────────────
  function addMsg(text, who) {
    const wrap = document.createElement('div');
    wrap.className = 'ct-msg ct-' + who;
    const bub = document.createElement('div');
    bub.className = 'ct-bubble';
    bub.innerHTML = text;
    wrap.appendChild(bub);
    $messages.appendChild(wrap);
    $messages.scrollTop = $messages.scrollHeight;
    return bub;
  }

  function clearOptions() { $options.innerHTML = ''; }

  function addOption(label, icon, cls, onClick) {
    const btn = document.createElement('button');
    btn.className = 'ct-opt-btn ' + (cls || '');
    btn.innerHTML = (icon ? '<span>' + icon + '</span>' : '') + label;
    btn.onclick = onClick;
    $options.appendChild(btn);
    return btn;
  }

  function typing(cb) {
    clearOptions();
    const wrap = document.createElement('div');
    wrap.className = 'ct-msg ct-bot';
    const t = document.createElement('div');
    t.className = 'ct-typing ct-bubble';
    t.innerHTML = '<span></span><span></span><span></span>';
    wrap.appendChild(t);
    $messages.appendChild(wrap);
    $messages.scrollTop = $messages.scrollHeight;
    setTimeout(() => { $messages.removeChild(wrap); cb(); }, 700);
  }

  function showHumanOption() {
    addOption('💬 Hablar con un agente humano', '', 'ct-human', openWhatsApp);
  }

  function openWhatsApp() {
    window.open('https://wa.me/' + WHATSAPP + '?text=' + WA_MSG, '_blank');
  }

  // ── Flujos ────────────────────────────────
  function showStart() {
    state = 'start';
    clearOptions();
    typing(() => {
      addMsg('¡Hola! 👋 Soy el asistente de <strong>CompraTica</strong>. ¿Con qué puedo ayudarte hoy?', 'bot');
      clearOptions();
      addOption('🛍️ Soy Cliente', '', 'ct-primary', () => chooseType('clientes', 'Soy Cliente'));
      addOption('🏪 Soy Vendedor/a', '', 'ct-primary', () => chooseType('vendedores', 'Soy Vendedor/a'));
    });
  }

  function chooseType(type, label) {
    addMsg(label, 'user');
    state = type;
    typing(() => {
      addMsg('Perfecto 😊 Elegí una pregunta frecuente o contactá a un agente:', 'bot');
      showFAQOptions(type);
    });
  }

  function showFAQOptions(type) {
    clearOptions();
    const faqs = FAQS[type];
    faqs.forEach((item, i) => {
      addOption(item.q, '', '', () => answerFAQ(type, i));
    });
    showHumanOption();
    addOption('← Volver al inicio', '', 'ct-back', showStart);
  }

  function answerFAQ(type, i) {
    const item = FAQS[type][i];
    addMsg(item.q, 'user');
    typing(() => {
      addMsg(item.a, 'bot');
      clearOptions();
      typing(() => {
        addMsg('¿Puedo ayudarte con algo más?', 'bot');
        clearOptions();
        addOption('🔍 Ver más preguntas', '', '', () => {
          typing(() => showFAQOptions(type));
        });
        showHumanOption();
        addOption('← Volver al inicio', '', 'ct-back', showStart);
      });
    });
  }

  // ── Abrir / cerrar ────────────────────────
  function openChat() {
    $panel.classList.add('ct-open');
    $badge.style.display = 'none';
    if ($messages.children.length === 0) {
      setTimeout(showStart, 200);
    }
  }

  function closeChat() { $panel.classList.remove('ct-open'); }

  $btn.addEventListener('click', () => {
    $panel.classList.contains('ct-open') ? closeChat() : openChat();
  });
  $close.addEventListener('click', closeChat);

  // Mostrar badge después de 3s para llamar la atención
  setTimeout(() => {
    if (!$panel.classList.contains('ct-open')) {
      $badge.style.display = 'block';
    }
  }, 3000);
})();
</script>
