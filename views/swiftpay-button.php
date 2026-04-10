<?php
/**
 * views/swiftpay-button.php — Formulario de pago con tarjeta
 * ─────────────────────────────────────────────────────────────────────
 * Include drop-in para aceptar pagos con Visa, Mastercard y Amex.
 * Funciona de forma independiente del frontend que lo incluya.
 *
 * USO BÁSICO:
 *   <?php
 *     $sp_amount      = '15000.00';   // Monto a cobrar (obligatorio)
 *     $sp_currency    = 'CRC';        // 'CRC' o 'USD'
 *     $sp_description = 'Compra #123';
 *     $sp_reference_id    = 123;      // ID de tu orden local
 *     $sp_reference_table = 'orders';
 *     $sp_success_url = '/gracias.php';   // Redirigir al aprobar
 *     $sp_cancel_url  = '/checkout.php';  // Botón cancelar
 *     include __DIR__ . '/views/swiftpay-button.php';
 *   ?>
 */

// ── Variables con defaults ────────────────────────────────────────────
$sp_amount      = $sp_amount      ?? '0.00';
$sp_currency    = strtoupper($sp_currency    ?? 'CRC');
$sp_description = $sp_description ?? 'Pago en CompraTica';
$sp_reference_id    = (int)($sp_reference_id    ?? 0);
$sp_reference_table = (string)($sp_reference_table ?? '');
$sp_sale_id      = (int)($sp_sale_id      ?? 0);
$sp_extra_fields = $sp_extra_fields ?? [];
$sp_success_url  = $sp_success_url  ?? '/';
$sp_cancel_url   = $sp_cancel_url   ?? 'javascript:history.back()';

// Indicador de modo (sandbox / live)
$sp_is_sandbox  = defined('SWIFTPAY_SANDBOX') && SWIFTPAY_SANDBOX;
$sp_currency_symbol = $sp_currency === 'USD' ? '$' : '₡';
$sp_amount_fmt  = $sp_currency_symbol . number_format((float)$sp_amount, 2, '.', ',');

// ID único del widget (permite múltiples instancias en la misma página)
$sp_widget_id   = 'spw_' . substr(md5(uniqid('', true)), 0, 8);
?>

<div id="<?= $sp_widget_id ?>" class="sp-widget">

  <?php if ($sp_is_sandbox): ?>
  <div class="sp-sandbox-badge">
    <span>🔧 MODO PRUEBAS — No se cobran tarjetas reales</span>
  </div>
  <?php endif; ?>

  <!-- ── Logo SwiftPay + logos de marcas ─────────────────────────── -->
  <div class="sp-header">
    <img src="/assets/img/swiftpay-logo.png"
         alt="SwiftPay"
         class="sp-provider-logo"
         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <span class="sp-provider-fallback" style="display:none">
      <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <rect width="100" height="100" rx="16" fill="#0d1b3e"/>
        <polygon points="55,10 30,55 50,55 45,90 70,45 50,45" fill="#3b9bff"/>
      </svg>
      <span style="font-weight:800;color:#0d1b3e;font-size:.85rem;letter-spacing:.02em;">SwiftPay</span>
    </span>
    <span class="sp-header-label">Pago con Tarjeta</span>
  </div>

  <div class="sp-brands">
    <!-- Visa -->
    <svg class="sp-brand-logo" viewBox="0 0 750 471" xmlns="http://www.w3.org/2000/svg" aria-label="Visa">
      <rect width="750" height="471" rx="40" fill="#1a1f71"/>
      <path d="M278 334L311 137h52l-33 197h-52zM524 141c-10-4-26-8-46-8-51 0-87 27-87 65 0 28 25 44 45 53 20 10 27 16 27 25 0 13-16 19-31 19-21 0-32-3-50-11l-7-3-7 43c12 6 34 11 57 11 54 0 89-27 89-67 0-22-14-39-44-53-18-9-30-15-30-25 0-8 10-17 31-17 17 0 30 4 40 8l5 2 7-42zM618 137h-40c-12 0-21 4-26 16l-76 181h54l11-30h66l6 30h48l-43-197zm-63 127l20-56 11-30 6 28 10 58h-47zM233 137l-51 135-5-27c-10-32-40-67-74-84l46 173h55l82-197h-53z" fill="white"/>
      <path d="M152 137H66l-1 5c67 17 112 58 130 107l-19-95c-3-12-13-16-24-17z" fill="#f9a533"/>
    </svg>

    <!-- Mastercard -->
    <svg class="sp-brand-logo" viewBox="0 0 750 471" xmlns="http://www.w3.org/2000/svg" aria-label="Mastercard">
      <rect width="750" height="471" rx="40" fill="#252525"/>
      <circle cx="285" cy="235" r="150" fill="#eb001b"/>
      <circle cx="465" cy="235" r="150" fill="#f79e1b"/>
      <path d="M375 122a150 150 0 0 1 0 226 150 150 0 0 1 0-226z" fill="#ff5f00"/>
    </svg>

    <!-- American Express -->
    <svg class="sp-brand-logo" viewBox="0 0 750 471" xmlns="http://www.w3.org/2000/svg" aria-label="American Express">
      <rect width="750" height="471" rx="40" fill="#2557d6"/>
      <text x="375" y="295" font-family="Arial Black, sans-serif" font-size="130" font-weight="900" fill="white" text-anchor="middle">AMEX</text>
    </svg>

    <div class="sp-secure-badge">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span>Pago 3D Secure</span>
    </div>
  </div>

  <!-- ── Formulario ────────────────────────────────────────────────── -->
  <form id="<?= $sp_widget_id ?>_form" class="sp-form" novalidate autocomplete="off">

    <!-- Número de tarjeta -->
    <div class="sp-field-group">
      <label for="<?= $sp_widget_id ?>_card">Número de tarjeta</label>
      <div class="sp-input-wrap">
        <input
          id="<?= $sp_widget_id ?>_card"
          type="text"
          class="sp-input sp-card-input"
          placeholder="•••• •••• •••• ••••"
          maxlength="19"
          inputmode="numeric"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          title=""
          required>
        <span class="sp-detected-brand" id="<?= $sp_widget_id ?>_detected"></span>
      </div>
    </div>

    <!-- Vencimiento + CVV -->
    <div class="sp-row">
      <div class="sp-field-group">
        <label for="<?= $sp_widget_id ?>_expiry">Vencimiento</label>
        <input
          id="<?= $sp_widget_id ?>_expiry"
          type="text"
          class="sp-input"
          placeholder="MM/AA"
          maxlength="5"
          inputmode="numeric"
          autocomplete="off"
          title=""
          required>
      </div>
      <div class="sp-field-group">
        <label for="<?= $sp_widget_id ?>_cvv">
          CVV
          <span class="sp-cvv-help" title="3 dígitos al dorso de tu tarjeta (Amex: 4 al frente)">?</span>
        </label>
        <input
          id="<?= $sp_widget_id ?>_cvv"
          type="text"
          class="sp-input"
          placeholder="•••"
          maxlength="4"
          inputmode="numeric"
          autocomplete="off"
          title=""
          required>
      </div>
    </div>

    <!-- Mensajes de error/estado -->
    <div id="<?= $sp_widget_id ?>_msg" class="sp-msg" style="display:none"></div>

    <!-- Botón de pago -->
    <button type="submit" class="sp-pay-btn" id="<?= $sp_widget_id ?>_btn">
      <span class="sp-btn-icon">
        <img src="/assets/img/swiftpay-logo.png" alt="" width="22" height="22" style="border-radius:4px;vertical-align:middle;"
             onerror="this.style.display='none'">
      </span>
      <span class="sp-btn-label">Pago con Tarjeta — <?= htmlspecialchars($sp_amount_fmt) ?></span>
      <span class="sp-btn-loading" style="display:none">
        <svg class="sp-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1"/></svg>
        Procesando…
      </span>
    </button>

    <?php if ($sp_cancel_url): ?>
    <a href="<?= htmlspecialchars($sp_cancel_url) ?>" class="sp-cancel-link">Cancelar y volver</a>
    <?php endif; ?>
  </form>

</div><!-- /#sp_widget_id -->

<!-- ── Estilos ─────────────────────────────────────────────────────── -->
<style>
.sp-widget {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.75rem;
  max-width: 420px;
  width: 100%;
  box-shadow: 0 4px 24px rgba(0,0,0,.07);
  box-sizing: border-box;
}
.sp-sandbox-badge {
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 6px;
  padding: .5rem 1rem;
  font-size: .78rem;
  color: #856404;
  font-weight: 600;
  margin-bottom: 1rem;
  text-align: center;
}
.sp-header {
  display: flex;
  align-items: center;
  gap: .65rem;
  margin-bottom: 1.1rem;
  padding-bottom: .9rem;
  border-bottom: 1px solid #e2e8f0;
}
.sp-provider-logo {
  height: 36px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.sp-provider-fallback {
  display: flex;
  align-items: center;
  gap: .4rem;
}
.sp-header-label {
  font-size: 1rem;
  font-weight: 700;
  color: #1a202c;
  letter-spacing: -.01em;
}
.sp-brands {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}
.sp-brand-logo {
  height: 28px;
  border-radius: 5px;
  box-shadow: 0 1px 4px rgba(0,0,0,.15);
  opacity: .85;
  transition: opacity .2s, transform .2s;
}
.sp-brand-logo:hover { opacity: 1; transform: scale(1.05); }
.sp-brand-logo.sp-active { opacity: 1; transform: scale(1.05); box-shadow: 0 0 0 2px #3b82f6; }
.sp-brand-logo.sp-dim    { opacity: .3; }
.sp-secure-badge {
  display: flex;
  align-items: center;
  gap: .3rem;
  margin-left: auto;
  font-size: .75rem;
  color: #27ae60;
  font-weight: 600;
  white-space: nowrap;
}
.sp-field-group {
  display: flex;
  flex-direction: column;
  gap: .35rem;
  margin-bottom: 1rem;
}
.sp-field-group label {
  font-size: .8rem;
  font-weight: 600;
  color: #4a5568;
  display: flex;
  align-items: center;
  gap: .3rem;
}
.sp-cvv-help {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  background: #e2e8f0;
  border-radius: 50%;
  font-size: .65rem;
  color: #4a5568;
  cursor: help;
}
.sp-input-wrap {
  position: relative;
}
.sp-input {
  width: 100%;
  padding: .75rem 1rem;
  border: 1.5px solid #cbd5e0;
  border-radius: 8px;
  font-size: 1rem;
  font-family: 'Courier New', monospace;
  letter-spacing: .05em;
  transition: border-color .2s, box-shadow .2s;
  box-sizing: border-box;
  background: #f8f9fa;
  color: #1a202c;
}
.sp-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.sp-input.sp-error { border-color: #e53e3e; box-shadow: 0 0 0 3px rgba(229,62,62,.12); }
.sp-input.sp-ok    { border-color: #27ae60; }
.sp-card-input { padding-right: 3rem; }
.sp-detected-brand {
  position: absolute;
  right: .75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .05em;
  color: #718096;
}
.sp-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.sp-msg {
  padding: .75rem 1rem;
  border-radius: 8px;
  font-size: .875rem;
  font-weight: 500;
  margin-bottom: 1rem;
  white-space: pre-wrap;
  word-break: break-all;
}
.sp-msg.sp-msg-error   { background: #fff5f5; color: #c53030; border: 1px solid #fc8181; }
.sp-msg.sp-msg-success { background: #f0fff4; color: #276749; border: 1px solid #68d391; }
.sp-msg.sp-msg-info    { background: #ebf8ff; color: #2b6cb0; border: 1px solid #90cdf4; }
.sp-pay-btn {
  width: 100%;
  padding: .9rem 1.5rem;
  background: linear-gradient(135deg, #1a56db 0%, #1e40af 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .6rem;
  transition: filter .2s, transform .1s;
  letter-spacing: .01em;
  margin-top: .25rem;
}
.sp-pay-btn:hover:not(:disabled) { filter: brightness(1.1); transform: translateY(-1px); }
.sp-pay-btn:active:not(:disabled){ transform: translateY(0); }
.sp-pay-btn:disabled { opacity: .7; cursor: not-allowed; }
.sp-spinner {
  animation: sp-spin .8s linear infinite;
}
@keyframes sp-spin { to { transform: rotate(360deg); } }
.sp-cancel-link {
  display: block;
  text-align: center;
  margin-top: .875rem;
  font-size: .82rem;
  color: #718096;
  text-decoration: none;
  transition: color .2s;
}
.sp-cancel-link:hover { color: #e53e3e; }
@media (max-width: 400px) {
  .sp-widget { padding: 1.25rem; border-radius: 10px; }
  .sp-row { grid-template-columns: 1fr; }
}
</style>

<!-- ── Lógica JS ────────────────────────────────────────────────────── -->
<script>
(function () {
  const W   = document.getElementById('<?= $sp_widget_id ?>');
  const frm = document.getElementById('<?= $sp_widget_id ?>_form');
  const inp = {
    card:   document.getElementById('<?= $sp_widget_id ?>_card'),
    expiry: document.getElementById('<?= $sp_widget_id ?>_expiry'),
    cvv:    document.getElementById('<?= $sp_widget_id ?>_cvv'),
  };
  const btn      = document.getElementById('<?= $sp_widget_id ?>_btn');
  const msgBox   = document.getElementById('<?= $sp_widget_id ?>_msg');
  const detected = document.getElementById('<?= $sp_widget_id ?>_detected');
  const logos    = W.querySelectorAll('.sp-brand-logo');

  const CONFIG = {
    chargeUrl:      '/api/swiftpay-charge.php',
    amount:         '<?= htmlspecialchars($sp_amount) ?>',
    currency:       '<?= htmlspecialchars($sp_currency) ?>',
    description:    <?= json_encode($sp_description) ?>,
    referenceId:    <?= (int)$sp_reference_id ?>,
    referenceTable: <?= json_encode($sp_reference_table) ?>,
    saleId:         <?= (int)$sp_sale_id ?>,
    extraFields:    <?= json_encode($sp_extra_fields) ?>,
    successUrl:     <?= json_encode($sp_success_url) ?>,
  };

  // ── Detección de marca ─────────────────────────────────────────
  const BRANDS = [
    { name: 'visa',       pattern: /^4/,          label: 'VISA',    logo: 0 },
    { name: 'mastercard', pattern: /^(5[1-5]|2[2-7])/,label: 'MC',logo: 1 },
    { name: 'amex',       pattern: /^3[47]/,       label: 'AMEX',   logo: 2, cvvLen: 4 },
  ];

  let detectedBrand = null;
  // Valores reales ocultos (PCI: los inputs muestran versión enmascarada al perder foco)
  let _rawCard = '';
  let _rawCvv  = '';

  function detectBrand(num) {
    return BRANDS.find(b => b.pattern.test(num.replace(/\s/g, ''))) || null;
  }

  function updateBrandUI(brand) {
    detectedBrand = brand;
    logos.forEach((logo, i) => {
      if (!brand) { logo.classList.remove('sp-active', 'sp-dim'); return; }
      if (i === brand.logo) { logo.classList.add('sp-active'); logo.classList.remove('sp-dim'); }
      else                  { logo.classList.add('sp-dim');   logo.classList.remove('sp-active'); }
    });
    detected.textContent = brand ? brand.label : '';
    inp.cvv.maxLength    = (brand && brand.cvvLen) ? brand.cvvLen : 3;
  }

  // ── Enmascarado PCI ────────────────────────────────────────────
  function maskCardDisplay(digits) {
    if (digits.length < 6) return digits.replace(/(.{4})/g, '$1 ').trim();
    const first4 = digits.slice(0, 4);
    const last2  = digits.slice(-2);
    const midLen = digits.length - 6;
    const mid    = '•'.repeat(midLen).replace(/(.{4})/g, '$1 ').trim();
    return (first4 + ' ' + mid + ' ' + last2).replace(/\s+/g, ' ').trim();
  }

  // ── Formateo de inputs ─────────────────────────────────────────
  inp.card.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 16);
    _rawCard = v;
    this.value = v.replace(/(.{4})/g, '$1 ').trim();
    updateBrandUI(detectBrand(v));
  });

  inp.card.addEventListener('blur', function () {
    if (_rawCard.length >= 6) this.value = maskCardDisplay(_rawCard);
  });

  // Al hacer foco: limpiar para re-ingreso (nunca exponer dígitos crudos)
  inp.card.addEventListener('focus', function () {
    if (_rawCard) {
      this.value = '';
      _rawCard = '';
      detected.textContent = '';
      updateBrandUI(null);
    }
  });

  inp.expiry.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 4);
    this.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
  });

  // ── CVV: máscara pura con JS (nunca expone dígitos, sin ojo del navegador) ──
  let _cvvExpected = ''; // lo que tenemos en pantalla

  inp.cvv.addEventListener('focus', function () {
    this.value = _cvvExpected = '•'.repeat(_rawCvv.length);
  });

  inp.cvv.addEventListener('keydown', function (e) {
    const maxLen = (detectedBrand && detectedBrand.cvvLen === 4) ? 4 : 3;
    if (e.key === 'Backspace') {
      e.preventDefault();
      _rawCvv = _rawCvv.slice(0, -1);
      this.value = _cvvExpected = '•'.repeat(_rawCvv.length);
    } else if (e.key === 'Delete') {
      e.preventDefault();
      _rawCvv = ''; this.value = _cvvExpected = '';
    } else if (/^\d$/.test(e.key) && _rawCvv.length < maxLen) {
      e.preventDefault();
      _rawCvv += e.key;
      this.value = _cvvExpected = '•'.repeat(_rawCvv.length);
    } else if (!['Tab','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Enter'].includes(e.key)) {
      e.preventDefault();
    }
  });

  // Fallback para teclados móviles (no disparan keydown con el carácter)
  inp.cvv.addEventListener('input', function () {
    if (this.value === _cvvExpected) return; // keydown ya lo manejó
    const maxLen = (detectedBrand && detectedBrand.cvvLen === 4) ? 4 : 3;
    const delta  = this.value.length - _cvvExpected.length;
    if (delta > 0) {
      const newDigits = this.value.replace(/•/g, '').replace(/\D/g, '');
      _rawCvv = (_rawCvv + newDigits).slice(0, maxLen);
    } else if (delta < 0) {
      _rawCvv = _rawCvv.slice(0, Math.max(0, _rawCvv.length + delta));
    }
    this.value = _cvvExpected = '•'.repeat(_rawCvv.length);
  });

  inp.cvv.addEventListener('paste', function (e) {
    e.preventDefault();
    const digits = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    const maxLen = (detectedBrand && detectedBrand.cvvLen === 4) ? 4 : 3;
    _rawCvv = digits.slice(0, maxLen);
    this.value = _cvvExpected = '•'.repeat(_rawCvv.length);
  });
  });

  // ── Limpiar campos sensibles (PCI) ────────────────────────────
  function clearFields() {
    inp.card.value = ''; _rawCard = '';
    inp.expiry.value = '';
    inp.cvv.value  = ''; _rawCvv  = '';
    inp.card.classList.remove('sp-ok', 'sp-error');
    inp.expiry.classList.remove('sp-ok', 'sp-error');
    inp.cvv.classList.remove('sp-ok', 'sp-error');
    detected.textContent = '';
    updateBrandUI(null);
  }

  // ── Helpers UI ────────────────────────────────────────────────
  function showMsg(text, type) {
    msgBox.textContent = text;
    msgBox.className   = 'sp-msg sp-msg-' + type;
    msgBox.style.display = 'block';
  }
  function hideMsg() { msgBox.style.display = 'none'; }

  function setLoading(loading) {
    btn.disabled = loading;
    btn.querySelector('.sp-btn-label').style.display  = loading ? 'none' : '';
    btn.querySelector('.sp-btn-icon').style.display   = loading ? 'none' : '';
    btn.querySelector('.sp-btn-loading').style.display = loading ? '' : 'none';
  }

  function markError(field) {
    field.classList.add('sp-error');
    field.classList.remove('sp-ok');
  }
  function markOk(field) {
    field.classList.add('sp-ok');
    field.classList.remove('sp-error');
  }

  // ── Validación cliente ────────────────────────────────────────
  function validate() {
    let ok = true;
    const cardVal = _rawCard;
    const expiryV = inp.expiry.value.replace('/', '');
    const cvvVal  = _rawCvv;

    if (cardVal.length < 13) { markError(inp.card);   ok = false; } else { markOk(inp.card); }
    if (expiryV.length !== 4){ markError(inp.expiry); ok = false; } else { markOk(inp.expiry); }
    const cvvMin = (detectedBrand && detectedBrand.cvvLen === 4) ? 4 : 3;
    if (cvvVal.length < cvvMin){ markError(inp.cvv);  ok = false; } else { markOk(inp.cvv); }

    return ok;
  }

  // ── Submit ────────────────────────────────────────────────────
  frm.addEventListener('submit', async function (e) {
    e.preventDefault();
    hideMsg();

    if (!validate()) {
      showMsg('Por favor completá todos los campos correctamente.', 'error');
      return;
    }

    setLoading(true);

    const expRaw = inp.expiry.value.replace('/', ''); // MMYY

    // Recoger campos extra del formulario (teléfono, dirección, notas)
    const extraData = {};
    (CONFIG.extraFields || []).forEach(id => {
      const el = document.getElementById(id);
      if (el) extraData[id] = el.value;
    });

    try {
      const res = await fetch(CONFIG.chargeUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
          card_number:     _rawCard,
          expiry:          expRaw,
          cvv:             _rawCvv,
          amount:          CONFIG.amount,
          currency:        CONFIG.currency,
          description:     CONFIG.description,
          reference_id:    CONFIG.referenceId,
          reference_table: CONFIG.referenceTable,
          sale_id:         CONFIG.saleId,
          ...extraData,
        }),
      });

      const data = await res.json();

      // ── 3DS requerido (v2: form POST al ACS) ──────────────────
      if (data.pending_3ds && data.action) {
        showMsg('Redirigiendo a verificación de seguridad 3D Secure…', 'info');
        setTimeout(() => {
          const f = document.createElement('form');
          f.method = 'POST';
          f.action = data.action;
          const addHidden = (name, val) => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = name; i.value = val || '';
            f.appendChild(i);
          };
          addHidden('creq',               data.creq               || '');
          addHidden('threeDSSessionData', data.three_ds_session_data || '');
          document.body.appendChild(f);
          f.submit();
        }, 800);
        return;
      }

      // ── Aprobado ───────────────────────────────────────────────
      if (data.ok) {
        clearFields(); // limpiar antes de redirigir (PCI)
        showMsg('✅ Pago aprobado. Redirigiendo…', 'success');

        const dest = data.redirect_url || CONFIG.successUrl;
        setTimeout(() => {
          window.location.href = dest;
        }, 1200);

        // Fallback: si por algún motivo no redirigió en 5s, bloquear la página
        setTimeout(() => {
          if (document.visibilityState !== 'hidden') {
            document.body.style.pointerEvents = 'none';
            document.body.style.opacity = '0.4';
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML = '<div style="background:#fff;border-radius:14px;padding:2rem 2.5rem;text-align:center;max-width:360px;">'
              + '<div style="font-size:2.5rem;margin-bottom:.5rem;">✅</div>'
              + '<h3 style="margin:0 0 .5rem;color:#2e7d32;">¡Pago aprobado!</h3>'
              + '<p style="color:#555;margin:0 0 1.5rem;font-size:.95rem;">Tu pago fue procesado exitosamente.</p>'
              + '<a href="' + (dest.split('?')[0].replace('order-success.php','') || '/') + '" '
              + 'style="display:inline-block;background:#e53935;color:#fff;padding:.75rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:700;">Volver a la tienda</a>'
              + '</div>';
            document.body.appendChild(overlay);
          }
        }, 5000);
        return;
      }

      // ── Error / declinado ─────────────────────────────────────
      clearFields(); // limpiar siempre al ser declinado (PCI)
      showMsg(data.error || 'El pago fue rechazado. Verificá tus datos.', 'error');

    } catch (err) {
      clearFields();
      showMsg('Error de conexión. Revisá tu internet e intentá de nuevo.', 'error');
    } finally {
      setLoading(false);
    }
  });

})();
</script>
