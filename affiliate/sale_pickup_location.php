<?php
declare(strict_types=1);
/**
 * affiliate/sale_pickup_location.php
 * Muestra SIEMPRE: resumen + formulario (prellenado) + mapa (Google / Leaflet fallback).
 * Mantiene cambios mínimos y corrige error de sintaxis.
 */

// Activa estos 2 para depurar si fuera necesario:
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
aff_require_login();

$pdo    = db();
$aff_id = (int)($_SESSION['aff_id'] ?? 0);

// ---------- Utilidades flash ----------
function flash_set(string $key, string $val): void {
    $_SESSION[$key] = $val;
}
function flash_get(string $key): string {
    $v = (string)($_SESSION[$key] ?? '');
    if ($v !== '') unset($_SESSION[$key]);
    return $v;
}

// ---------- sale_id ----------
$sale_id = (int)($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
if ($sale_id <= 0) {
    flash_set('error', 'Falta el parámetro sale_id');
    header('Location: sales.php');
    exit;
}

// Verificar que el espacio pertenece al afiliado
$stmt = $pdo->prepare("SELECT id, title FROM sales WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$sale_id, $aff_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    flash_set('error', 'Espacio no encontrado o no tienes permiso');
    header('Location: sales.php');
    exit;
}

// ---------- Guardar (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_location') {
    try {
        $address             = trim($_POST['address'] ?? '');
        $address_line2       = trim($_POST['address_line2'] ?? '');
        $city                = trim($_POST['city'] ?? '');
        $state               = trim($_POST['state'] ?? '');
        $country             = trim($_POST['country'] ?? '') ?: 'Costa Rica';
        $postal_code         = trim($_POST['postal_code'] ?? '');
        $lat                 = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
        $lng                 = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
        $contact_name        = trim($_POST['contact_name'] ?? '');
        $contact_phone       = trim($_POST['contact_phone'] ?? '');
        $pickup_instructions = trim($_POST['pickup_instructions'] ?? '');

        if ($address === '' || $contact_name === '' || $contact_phone === '') {
            throw new Exception('Completa los campos obligatorios (Dirección, Nombre y Teléfono).');
        }

        // ¿Existe registro activo?
        $stmt = $pdo->prepare("SELECT id FROM sale_pickup_locations WHERE sale_id = ? AND affiliate_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$sale_id, $aff_id]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $sql = "UPDATE sale_pickup_locations
                       SET address=:address, address_line2=:address_line2, city=:city, state=:state, country=:country,
                           postal_code=:postal_code, lat=:lat, lng=:lng, contact_name=:contact_name,
                           contact_phone=:contact_phone, pickup_instructions=:pickup_instructions,
                           is_active=1, updated_at=datetime('now')
                     WHERE id=:id";
            $ok = $pdo->prepare($sql)->execute([
                ':address'              => $address,
                ':address_line2'        => $address_line2,
                ':city'                 => $city,
                ':state'                => $state,
                ':country'              => $country,
                ':postal_code'          => $postal_code,
                ':lat'                  => $lat,
                ':lng'                  => $lng,
                ':contact_name'         => $contact_name,
                ':contact_phone'        => $contact_phone,
                ':pickup_instructions'  => $pickup_instructions,
                ':id'                   => $existingId
            ]);
        } else {
            $sql = "INSERT INTO sale_pickup_locations
                        (sale_id, affiliate_id, address, address_line2, city, state, country, postal_code,
                         lat, lng, contact_name, contact_phone, pickup_instructions, is_active, created_at, updated_at)
                    VALUES
                        (:sale_id, :affiliate_id, :address, :address_line2, :city, :state, :country, :postal_code,
                         :lat, :lng, :contact_name, :contact_phone, :pickup_instructions, 1, datetime('now'), datetime('now'))";
            $ok = $pdo->prepare($sql)->execute([
                ':sale_id'              => $sale_id,
                ':affiliate_id'         => $aff_id,
                ':address'              => $address,
                ':address_line2'        => $address_line2,
                ':city'                 => $city,
                ':state'                => $state,
                ':country'              => $country,
                ':postal_code'          => $postal_code,
                ':lat'                  => $lat,
                ':lng'                  => $lng,
                ':contact_name'         => $contact_name,
                ':contact_phone'        => $contact_phone,
                ':pickup_instructions'  => $pickup_instructions
            ]);
        }

        if (empty($ok)) {
            throw new Exception('No se pudo guardar la ubicación. Intenta de nuevo.');
        }

        flash_set('success', 'Ubicación de retiro guardada correctamente.');
        // PRG para evitar reenvío y mantener vista limpia:
        header('Location: ' . basename(__FILE__) . '?sale_id=' . $sale_id . '&_=' . time());
        exit;

    } catch (Exception $e) {
        flash_set('error', $e->getMessage());
        header('Location: ' . basename(__FILE__) . '?sale_id=' . $sale_id . '&_=' . time());
        exit;
    }
}

// ---------- Cargar ubicación actual ----------
$stmt = $pdo->prepare("SELECT * FROM sale_pickup_locations WHERE sale_id = ? AND affiliate_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$sale_id, $aff_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// ---------- Mensajes flash ----------
$success = flash_get('success');
$error   = flash_get('error');

// ---------- API KEY Google Maps ----------
$MAPS_API_KEY = '';
if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY) {
    $MAPS_API_KEY = (string)GOOGLE_MAPS_API_KEY;
} elseif (getenv('GOOGLE_MAPS_API_KEY')) {
    $MAPS_API_KEY = (string)getenv('GOOGLE_MAPS_API_KEY');
} elseif (!empty($CONFIG['GOOGLE_MAPS_API_KEY'])) {
    $MAPS_API_KEY = (string)$CONFIG['GOOGLE_MAPS_API_KEY'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ubicación de retiro • <?= htmlspecialchars($sale['title'] ?? ('#'.$sale_id)) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;margin:0;background:#f7f8fa;color:#111}
  .container{max-width:980px;margin:0 auto;padding:16px}
  .card{background:#fff;border:1px solid #eaeaea;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.03);margin-top:14px}
  .card-header{padding:14px 16px;border-bottom:1px solid #efefef;font-weight:700}
  .card-body{padding:16px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:800px){.grid{grid-template-columns:1fr}}
  .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
  .form-group label{font-size:.95rem;color:#334155;font-weight:600}
  .required{color:#e11d48}
  .form-group input,.form-group textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:15px}
  .form-group input:focus,.form-group textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
  .map-wrap{height:420px;border:1px solid #ddd;border-radius:10px;position:relative;background:#f7f7f7;margin-bottom:10px}
  #gmap,#lmap{width:100%;height:100%;border-radius:10px}
  .maps-error{position:absolute;inset:0;display:none;align-items:center;justify-content:center;text-align:center;padding:16px;background:#fff;border-radius:10px;border:1px solid #fca5a5;z-index:2}
  .maps-error.visible{display:flex}
  .alert{padding:12px;border-radius:8px;margin-bottom:12px}
  .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
  .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
  .btns{display:flex;gap:10px;margin-top:10px}
  .btn{padding:10px 14px;border-radius:8px;border:1px solid #e5e7eb;background:#f3f4f6;color:#111827;cursor:pointer;font-weight:700;text-decoration:none}
  .btn.primary{background:#111827;color:#fff;border-color:#111827}
  .summary{border:1px dashed #cbd5e1;border-radius:10px;padding:12px;margin:0 0 16px;background:#fafcff}
  .summary h5{margin:0 0 8px;font-size:1rem;color:#0f172a}
  .summary .row{display:grid;grid-template-columns:160px 1fr;gap:8px;font-size:.95rem;color:#334155}
  hr.sep{border:none;border-top:1px solid #e5e7eb;margin:14px 0}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin="anonymous">
</head>
<body>
<div class="container">
  <div class="card">
    <div class="card-header">
      <i class="fas fa-store"></i> Ubicación de retiro para: <strong><?= htmlspecialchars($sale['title'] ?? ('#'.$sale_id)) ?></strong>
    </div>
    <div class="card-body">

      <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if (!empty($location)): ?>
        <div class="summary">
          <h5><i class="fas fa-info-circle"></i> Resumen actual</h5>
          <div class="row"><div><strong>Dirección:</strong></div><div><?= htmlspecialchars($location['address'] ?? '') ?></div></div>
          <?php if (!empty($location['address_line2'])): ?>
            <div class="row"><div><strong>Complemento:</strong></div><div><?= htmlspecialchars($location['address_line2']) ?></div></div>
          <?php endif; ?>
          <div class="row"><div><strong>Ciudad/Provincia:</strong></div><div><?= htmlspecialchars(($location['city'] ?? '') . (!empty($location['state']) ? ', '.$location['state'] : '')) ?></div></div>
          <div class="row"><div><strong>País / CP:</strong></div><div><?= htmlspecialchars(($location['country'] ?? 'Costa Rica') . (!empty($location['postal_code']) ? ' · '.$location['postal_code'] : '')) ?></div></div>
          <div class="row"><div><strong>Coordenadas:</strong></div><div><?= htmlspecialchars(($location['lat'] ?? '') . (!empty($location['lng']) ? ', '.$location['lng'] : '')) ?></div></div>
          <div class="row"><div><strong>Contacto:</strong></div><div><?= htmlspecialchars(($location['contact_name'] ?? '') . (!empty($location['contact_phone']) ? ' · '.$location['contact_phone'] : '')) ?></div></div>
        </div>
      <?php endif; ?>

      <hr class="sep">

      <form method="post" id="form">
        <input type="hidden" name="action" value="save_location">
        <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
        <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($location['lat'] ?? '') ?>">
        <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($location['lng'] ?? '') ?>">

        <div class="form-group">
          <label>Dirección completa <span class="required">*</span></label>
          <input type="text" name="address" id="address" value="<?= htmlspecialchars($location['address'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Complemento</label>
          <input type="text" name="address_line2" id="address_line2" value="<?= htmlspecialchars($location['address_line2'] ?? '') ?>">
        </div>

        <div class="grid">
          <div class="form-group">
            <label>Ciudad / Cantón</label>
            <input type="text" name="city" id="city" value="<?= htmlspecialchars($location['city'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Provincia</label>
            <input type="text" name="state" id="state" value="<?= htmlspecialchars($location['state'] ?? '') ?>">
          </div>
        </div>

        <div class="grid">
          <div class="form-group">
            <label>Código postal</label>
            <input type="text" name="postal_code" id="postal_code" value="<?= htmlspecialchars($location['postal_code'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>País</label>
            <input type="text" name="country" id="country" value="<?= htmlspecialchars($location['country'] ?? 'Costa Rica') ?>">
          </div>
        </div>

        <div class="form-group">
          <label><i class="fas fa-map"></i> Ubicación en el mapa</label>
          <div class="map-wrap">
            <div id="gmap"></div>
            <div id="lmap" style="display:none"></div>
            <div class="maps-error" id="mapsError">
              <div>
                <div class="msg"><i class="fas fa-ban"></i> No se pudo cargar Google Maps</div>
                <div class="hint">Se mostrará un mapa alternativo (OpenStreetMap).</div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid">
          <div class="form-group">
            <label>Nombre de contacto <span class="required">*</span></label>
            <input type="text" name="contact_name" value="<?= htmlspecialchars($location['contact_name'] ?? $_SESSION['aff_name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Teléfono de contacto <span class="required">*</span></label>
            <input type="tel" name="contact_phone" value="<?= htmlspecialchars($location['contact_phone'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Instrucciones especiales</label>
          <textarea name="pickup_instructions" rows="3"><?= htmlspecialchars($location['pickup_instructions'] ?? '') ?></textarea>
        </div>

        <div class="btns">
          <a href="sales.php" class="btn"><i class="fas fa-arrow-left"></i> Volver</a>
          <button type="submit" class="btn primary"><i class="fas fa-save"></i> Guardar</button>
        </div>
      </form>

    </div>
  </div>
</div>

<script>
// Validación básica del teléfono
document.getElementById('form').addEventListener('submit', function(e){
  var tel = (document.querySelector('input[name="contact_phone"]') || { value: '' }).value.trim();
  if (tel && !/^\+?[0-9\s\-()]+$/.test(tel)) {
    e.preventDefault();
    alert('Ingresa un teléfono válido');
  }
});
</script>

<?php if ($MAPS_API_KEY): ?>
<script async src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($MAPS_API_KEY) ?>&loading=async&libraries=marker,geocoding"></script>
<?php endif; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin="anonymous"></script>

<script>
(function(){
  var hasApiKey = <?= $MAPS_API_KEY ? 'true' : 'false' ?>;
  var gDiv = document.getElementById('gmap');
  var lDiv = document.getElementById('lmap');
  var err  = document.getElementById('mapsError');

  function setVal(n,v){ var el=document.querySelector('input[name="'+n+'"]'); if(el){ el.value=(v??''); } }
  function preset(){
    var lat=parseFloat((document.getElementById('lat')||{}).value);
    var lng=parseFloat((document.getElementById('lng')||{}).value);
    if(!isNaN(lat)&&!isNaN(lng)) return {lat:lat,lng:lng};
    return null;
  }
  function showErr(){ if(err) err.classList.add('visible'); }
  function hideErr(){ if(err) err.classList.remove('visible'); }

  // ----- Google Maps -----
  async function initGoogle(){
    if(!hasApiKey) throw new Error('no-key');
    await new Promise(function(res,rej){
      var t=Date.now();
      (function check(){
        if (window.google && google.maps && google.maps.importLibrary) return res();
        if (Date.now()-t>8000) return rej(new Error('timeout'));
        setTimeout(check,60);
      })();
    });

    const { Map } = await google.maps.importLibrary('maps');
    const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
    const { Geocoder } = await google.maps.importLibrary('geocoding');

    hideErr();
    gDiv.style.display='block';
    lDiv.style.display='none';

    var geocoder = new Geocoder();
    var p = preset();
    var c = p || {lat:9.9281,lng:-84.0907};

    var map = new Map(gDiv, {
      center:c,
      zoom:p?16:13,
      mapTypeControl:false,
      streetViewControl:false,
      fullscreenControl:true
    });

    var mk = new AdvancedMarkerElement({
      map:map,
      position:c,
      gmpDraggable:true
    });

    function applyPos(ll){
      map.setCenter(ll); map.setZoom(16);
      mk.position = ll;
      setVal('lat', Number(ll.lat).toFixed(6));
      setVal('lng', Number(ll.lng).toFixed(6));
      geocoder.geocode({location:ll}, function(rs,st){
        if(st==='OK' && rs && rs[0]){
          var r=rs[0], a=r.address_components||[];
          function pick(types){
            for(var i=0;i<a.length;i++){
              var ok = types.every(function(t){ return a[i].types.indexOf(t)!==-1; });
              if (ok) return a[i].long_name;
            }
            return '';
          }
          var formatted=r.formatted_address||'';
          if(formatted) setVal('address', formatted);
          var city = pick(['locality']) || pick(['sublocality','sublocality_level_1']) || pick(['administrative_area_level_2']);
          var state= pick(['administrative_area_level_1']);
          var code = pick(['postal_code']);
          if(city) setVal('city', city);
          if(state) setVal('state', state);
          if(code) setVal('postal_code', code);
        }
      });
    }

    mk.addListener('dragend', function(ev){
      var ll = ev.latLng;
      applyPos({lat: ll.lat(), lng: ll.lng()});
    });

    if(!p && navigator.geolocation){
      navigator.geolocation.getCurrentPosition(function(pos){
        applyPos({lat:pos.coords.latitude, lng:pos.coords.longitude});
      }, function(){}, {enableHighAccuracy:true,timeout:8000});
    } else if(p){
      applyPos(p);
    }
  }

  // ----- Leaflet -----
  function initLeaflet(){
    showErr();
    gDiv.style.display='none';
    lDiv.style.display='block';
    if (typeof L==='undefined') return;

    var p = preset();
    var c = p || {lat:9.9281,lng:-84.0907};

    var map = L.map('lmap').setView([c.lat,c.lng], p?16:13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom:19,
      attribution:'&copy; OpenStreetMap'
    }).addTo(map);

    var mk = L.marker([c.lat,c.lng], {draggable:true}).addTo(map);

    function applyPos(ll){
      map.setView([ll.lat,ll.lng],16);
      mk.setLatLng([ll.lat,ll.lng]);
      setVal('lat', Number(ll.lat).toFixed(6));
      setVal('lng', Number(ll.lng).toFixed(6));
      fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+ll.lat+'&lon='+ll.lng)
        .then(r=>r.json()).then(function(d){
          if(!d) return;
          if(d.display_name) setVal('address', d.display_name);
          var a=d.address||{};
          if(a.city||a.town||a.village) setVal('city', a.city||a.town||a.village);
          if(a.state) setVal('state', a.state);
          if(a.postcode) setVal('postal_code', a.postcode);
        }).catch(function(){});
    }

    mk.on('dragend', function(){
      var ll = mk.getLatLng();
      applyPos({lat:ll.lat, lng:ll.lng});
    });

    if(!p && navigator.geolocation){
      navigator.geolocation.getCurrentPosition(function(pos){
        applyPos({lat:pos.coords.latitude, lng:pos.coords.longitude});
      }, function(){}, {enableHighAccuracy:true,timeout:8000});
    } else if(p){
      applyPos(p);
    }
  }

  (async function(){
    try { await initGoogle(); }
    catch(e){ initLeaflet(); }
  })();
})();
</script>
</body>
</html>
