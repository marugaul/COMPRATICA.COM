<?php
/**
 * admin/email_marketing/importar_excel_bd.php
 * Importar contactos desde Excel/CSV a la tabla importa_excel
 */
$config = require __DIR__ . '/../../config/database.php';
$pdo_mysql = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
    $config['username'], $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Detectar si PhpSpreadsheet está disponible
$phpspreadsheet_ok = false;
foreach ([__DIR__.'/../../vendor/autoload.php', __DIR__.'/../../../vendor/autoload.php'] as $_ap) {
    if (file_exists($_ap)) { require_once $_ap; break; }
}
$phpspreadsheet_ok = class_exists('\PhpOffice\PhpSpreadsheet\IOFactory');

// Cargar tipos de correo
$tipos = $pdo_mysql->query("SELECT id, nombre FROM tipos_correo ORDER BY nombre")->fetchAll();

// Estadísticas rápidas
$total   = (int)$pdo_mysql->query("SELECT COUNT(*) FROM importa_excel")->fetchColumn();
$porTipo = $pdo_mysql->query("
    SELECT t.nombre, COUNT(i.id) AS cnt
    FROM tipos_correo t
    LEFT JOIN importa_excel i ON i.tipo_correo_id = t.id
    GROUP BY t.id, t.nombre
")->fetchAll();
?>

<div class="container-fluid px-4">

  <!-- Cabecera -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-1"><i class="fas fa-file-excel text-success"></i> Importar desde Excel / CSV</h4>
      <small class="text-muted">Subí un archivo Excel u OpenOffice y mapeá las columnas a la base de datos de contactos.</small>
    </div>
    <div class="text-end">
      <span class="badge bg-primary fs-6"><?= number_format($total) ?> contactos totales</span>
    </div>
  </div>

  <!-- Stats por tipo -->
  <?php if (!empty($porTipo)): ?>
  <div class="row g-3 mb-4">
    <?php foreach ($porTipo as $pt): ?>
    <div class="col-auto">
      <div class="card border-0 shadow-sm text-center px-4 py-2">
        <div class="fw-bold fs-5"><?= number_format($pt['cnt']) ?></div>
        <small class="text-muted"><?= htmlspecialchars($pt['nombre']) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- ── Columna izquierda: Formulario de importación ── -->
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <i class="fas fa-upload"></i> <strong>Importar archivo</strong>
        </div>
        <div class="card-body">

          <!-- Paso 1: Subir archivo y previsualizar columnas -->
          <div id="step1">
            <div class="alert alert-info py-2">
              <i class="fas fa-info-circle"></i>
              Subí el archivo. El sistema detectará las columnas automáticamente para que elijas cuál corresponde a cada campo.
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Archivo Excel / CSV / ODS</label>
              <?php if ($phpspreadsheet_ok): ?>
              <input type="file" id="fileInput" class="form-control" accept=".xlsx,.xls,.csv,.ods">
              <div class="form-text">Formatos: .xlsx, .xls, .csv, .ods — Máx. 10 MB</div>
              <?php else: ?>
              <input type="file" id="fileInput" class="form-control" accept=".csv">
              <div class="alert alert-warning mt-2 mb-0 py-2">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Solo CSV disponible.</strong> El servidor no tiene PhpSpreadsheet instalado.
                Exporta tu Excel como <strong>.csv</strong> desde Excel → Archivo → Guardar como → CSV UTF-8.
              </div>
              <?php endif; ?>
            </div>

            <button class="btn btn-success" id="btnPreview" onclick="previewFile()">
              <i class="fas fa-eye"></i> Previsualizar columnas
            </button>
          </div>

          <!-- Paso 2: Mapeo de columnas (oculto hasta step1 completado) -->
          <div id="step2" style="display:none">
            <hr>
            <h6 class="fw-bold text-success"><i class="fas fa-columns"></i> Paso 2 — Mapeá las columnas</h6>
            <p class="text-muted small">Seleccioná qué columna del archivo corresponde a cada campo. Dejá en "— No importar —" los que no aplican.</p>

            <div id="previewInfo" class="alert alert-secondary py-2 small mb-3"></div>

            <form id="importForm">
              <div class="row g-3 mb-3">
                <?php
                $campos = [
                    'cedula'    => ['Cédula',    'fas fa-id-card'],
                    'nombre'    => ['Nombre',    'fas fa-user'],
                    'correo'    => ['Correo',    'fas fa-envelope'],
                    'telefono'  => ['Teléfono',  'fas fa-phone'],
                    'direccion' => ['Dirección', 'fas fa-map-marker-alt'],
                ];
                foreach ($campos as $field => [$label, $icon]): ?>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">
                    <i class="<?= $icon ?> text-secondary me-1"></i><?= $label ?>
                    <?= $field === 'correo' ? '<span class="text-danger">*</span>' : '' ?>
                  </label>
                  <select name="col_<?= $field ?>" id="col_<?= $field ?>" class="form-select form-select-sm col-map">
                    <option value="">— No importar —</option>
                  </select>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold"><i class="fas fa-tags text-secondary me-1"></i> Tipo de Correo <span class="text-danger">*</span></label>
                <select name="tipo_correo_id" id="tipoCorreo" class="form-select" required>
                  <option value="">Seleccioná el tipo...</option>
                  <?php foreach ($tipos as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Todos los contactos de esta importación quedarán clasificados con este tipo.</div>
              </div>

              <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="skipDuplicates" checked>
                <label class="form-check-label small" for="skipDuplicates">
                  Omitir correos duplicados (ya existentes en la BD)
                </label>
              </div>

              <div id="importProgress" style="display:none" class="mb-3">
                <div class="progress" style="height:22px">
                  <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                       style="width:0%">0%</div>
                </div>
                <small id="progressText" class="text-muted"></small>
              </div>

              <div id="importResult" style="display:none"></div>

              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="resetWizard()">
                  <i class="fas fa-redo"></i> Nuevo archivo
                </button>
                <button type="button" class="btn btn-success" id="btnImport" onclick="doImport()">
                  <i class="fas fa-database"></i> Importar a la BD
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Columna derecha: Vista previa de filas + Gestión tipos ── -->
    <div class="col-lg-5">

      <!-- Previsualización de datos -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <i class="fas fa-table"></i> <strong>Vista previa</strong>
          <span id="previewCount" class="badge bg-secondary ms-2" style="display:none"></span>
        </div>
        <div class="card-body p-0" style="max-height:320px;overflow:auto">
          <div id="previewTable" class="text-center text-muted py-4 small">
            Subí un archivo para ver una vista previa.
          </div>
        </div>
      </div>

      <!-- Gestión de tipos de correo -->
      <div class="card shadow-sm">
        <div class="card-header">
          <i class="fas fa-tags"></i> <strong>Tipos de Correo</strong>
        </div>
        <div class="card-body">
          <ul class="list-group list-group-flush mb-3" id="tiposList">
            <?php foreach ($tipos as $t): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
              <span><?= htmlspecialchars($t['nombre']) ?></span>
              <button class="btn btn-xs btn-outline-danger py-0 px-1"
                      onclick="deleteTipo(<?= $t['id'] ?>, this)"
                      title="Eliminar tipo">
                <i class="fas fa-times"></i>
              </button>
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="input-group input-group-sm">
            <input type="text" id="nuevoTipo" class="form-control" placeholder="Nuevo tipo...">
            <button class="btn btn-outline-success" onclick="addTipo()">
              <i class="fas fa-plus"></i> Agregar
            </button>
          </div>
        </div>
      </div>

    </div><!-- /col -->
  </div><!-- /row -->

  <!-- ── Tabla de contactos importados ── -->
  <div class="card shadow-sm mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-address-book"></i> <strong>Contactos importados</strong></span>
      <div class="d-flex gap-2">
        <select id="filtroTipo" class="form-select form-select-sm" style="width:160px" onchange="loadContacts()">
          <option value="">Todos los tipos</option>
          <?php foreach ($tipos as $t): ?>
          <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" id="filtroTexto" class="form-control form-control-sm" style="width:180px"
               placeholder="Buscar..." oninput="loadContacts()">
        <button class="btn btn-sm btn-outline-danger" onclick="deleteSelected()"
                id="btnDeleteSelected" style="display:none">
          <i class="fas fa-trash"></i> Eliminar selección
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive" style="max-height:400px;overflow:auto">
        <table class="table table-sm table-hover mb-0" id="contactsTable">
          <thead class="table-light sticky-top">
            <tr>
              <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
              <th>Cédula</th><th>Nombre</th><th>Correo</th>
              <th>Teléfono</th><th>Dirección</th><th>Tipo</th><th>Fecha</th><th></th>
            </tr>
          </thead>
          <tbody id="contactsBody">
            <tr><td colspan="9" class="text-center text-muted py-3">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer text-muted small" id="contactsFooter"></div>
  </div>

</div><!-- /container -->

<script>
let parsedData   = [];   // filas del archivo (array de arrays)
let parsedHeader = [];   // encabezados detectados

// ── Previsualización del archivo ───────────────────────────────────────
async function previewFile() {
  const file = document.getElementById('fileInput').files[0];
  if (!file) { alert('Seleccioná un archivo primero.'); return; }

  const btn = document.getElementById('btnPreview');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

  const fd = new FormData();
  fd.append('file', file);
  fd.append('action', 'preview');

  try {
    const r  = await fetch('/admin/email_marketing_importar_excel_api.php', { method: 'POST', body: fd });
    const d  = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error al procesar archivo');

    parsedData   = d.rows;
    parsedHeader = d.headers;

    // Poblar selects de columnas
    document.querySelectorAll('.col-map').forEach(sel => {
      const cur = sel.name.replace('col_', '');
      sel.innerHTML = '<option value="">— No importar —</option>'
        + parsedHeader.map((h, i) =>
            `<option value="${i}" ${autoGuess(cur, h) ? 'selected' : ''}>${h}</option>`
          ).join('');
    });

    // Info
    document.getElementById('previewInfo').textContent =
      `Archivo: ${file.name} · ${parsedHeader.length} columnas · ${d.total_rows} filas`;

    // Renderizar tabla de preview (primeras 5 filas)
    renderPreview(parsedHeader, parsedData.slice(0, 5), d.total_rows);

    document.getElementById('step2').style.display = '';
    document.getElementById('previewCount').textContent = d.total_rows + ' filas';
    document.getElementById('previewCount').style.display = '';

  } catch(e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-eye"></i> Previsualizar columnas';
  }
}

// Auto-detectar columna según nombre
function autoGuess(field, header) {
  const h = header.toLowerCase();
  const maps = {
    cedula:    ['cedula','cédula','ci','identificacion','rut'],
    nombre:    ['nombre','name','names','apellido','apellidos','nombre completo'],
    correo:    ['correo','email','e-mail','mail','emails'],
    telefono:  ['telefono','teléfono','tel','phone','celular','movil'],
    direccion: ['direccion','dirección','address','domicilio'],
  };
  return (maps[field] || []).some(k => h.includes(k));
}

function renderPreview(headers, rows, total) {
  const div = document.getElementById('previewTable');
  if (!rows.length) { div.innerHTML = '<div class="p-3 text-muted">Sin datos</div>'; return; }
  let html = '<table class="table table-sm table-bordered mb-0" style="font-size:.78rem"><thead class="table-success"><tr>'
    + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead><tbody>';
  rows.forEach(row => {
    html += '<tr>' + row.map(c => `<td>${c ?? ''}</td>`).join('') + '</tr>';
  });
  html += `</tbody></table><div class="p-2 text-muted small">Mostrando 5 de ${total} filas</div>`;
  div.innerHTML = html;
}

// ── Importar ───────────────────────────────────────────────────────────
async function doImport() {
  const tipo = document.getElementById('tipoCorreo').value;
  if (!tipo) { alert('Seleccioná el tipo de correo.'); return; }

  const colMap = {};
  document.querySelectorAll('.col-map').forEach(sel => {
    if (sel.value !== '') colMap[sel.name.replace('col_', '')] = parseInt(sel.value);
  });

  if (colMap.correo === undefined) {
    alert('Debés mapear al menos la columna Correo.');
    return;
  }

  const skipDup = document.getElementById('skipDuplicates').checked;
  const btn = document.getElementById('btnImport');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';

  const progressWrap = document.getElementById('importProgress');
  const progressBar  = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  progressWrap.style.display = '';

  const BATCH = 200;
  let imported = 0, skipped = 0, errors = 0;
  const total  = parsedData.length;

  for (let i = 0; i < parsedData.length; i += BATCH) {
    const batch = parsedData.slice(i, i + BATCH);
    const pct   = Math.round(((i + batch.length) / total) * 100);
    progressBar.style.width = pct + '%';
    progressBar.textContent = pct + '%';
    progressText.textContent = `Procesando ${Math.min(i + BATCH, total)} de ${total}...`;

    const fd = new FormData();
    fd.append('action', 'import_batch');
    fd.append('rows',       JSON.stringify(batch));
    fd.append('col_map',    JSON.stringify(colMap));
    fd.append('tipo_correo_id', tipo);
    fd.append('skip_dup',   skipDup ? '1' : '0');

    const r = await fetch('/admin/email_marketing_importar_excel_api.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) { imported += d.imported; skipped += d.skipped; errors += d.errors; }
  }

  progressBar.classList.remove('progress-bar-animated');
  progressBar.style.width = '100%';

  const res = document.getElementById('importResult');
  res.style.display = '';
  res.innerHTML = `
    <div class="alert alert-success py-2 mt-2">
      <i class="fas fa-check-circle"></i>
      <strong>Importación completa</strong> —
      ${imported} importados, ${skipped} duplicados omitidos, ${errors} errores.
    </div>`;

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-database"></i> Importar a la BD';
  loadContacts();
}

function resetWizard() {
  parsedData = []; parsedHeader = [];
  document.getElementById('fileInput').value = '';
  document.getElementById('step2').style.display = 'none';
  document.getElementById('previewTable').innerHTML = '<div class="p-4 text-muted text-center small">Subí un archivo para ver una vista previa.</div>';
  document.getElementById('importResult').style.display = 'none';
  document.getElementById('importProgress').style.display = 'none';
  document.getElementById('previewCount').style.display = 'none';
}

// ── Cargar contactos ───────────────────────────────────────────────────
async function loadContacts() {
  const tipo  = document.getElementById('filtroTipo').value;
  const texto = document.getElementById('filtroTexto').value;
  const tbody = document.getElementById('contactsBody');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr>';

  const fd = new FormData();
  fd.append('action', 'list');
  fd.append('tipo', tipo);
  fd.append('q', texto);

  const r = await fetch('/admin/email_marketing_importar_excel_api.php', { method: 'POST', body: fd });
  const d = await r.json();

  if (!d.ok || !d.rows.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Sin registros</td></tr>';
    document.getElementById('contactsFooter').textContent = '';
    return;
  }

  tbody.innerHTML = d.rows.map(row => `
    <tr>
      <td><input type="checkbox" class="row-check" value="${row.id}" onchange="updateDeleteBtn()"></td>
      <td>${row.cedula || '—'}</td>
      <td>${row.nombre || '—'}</td>
      <td>${row.correo || '—'}</td>
      <td>${row.telefono || '—'}</td>
      <td title="${row.direccion || ''}">${(row.direccion||'').substring(0,30) || '—'}</td>
      <td><span class="badge bg-info">${row.tipo_nombre || '—'}</span></td>
      <td>${row.fecha_ingreso ? row.fecha_ingreso.substring(0,10) : '—'}</td>
      <td>
        <button class="btn btn-xs btn-outline-danger py-0 px-1"
                onclick="deleteContact(${row.id}, this)" title="Eliminar">
          <i class="fas fa-times"></i>
        </button>
      </td>
    </tr>`).join('');

  document.getElementById('contactsFooter').textContent =
    `Mostrando ${d.rows.length} de ${d.total} registros`;
}

function toggleAll(cb) {
  document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
  updateDeleteBtn();
}
function updateDeleteBtn() {
  const any = document.querySelectorAll('.row-check:checked').length > 0;
  document.getElementById('btnDeleteSelected').style.display = any ? '' : 'none';
}

async function deleteContact(id, btn) {
  if (!confirm('¿Eliminar este contacto?')) return;
  btn.disabled = true;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
  await fetch('/admin/email_marketing_importar_excel_api.php', { method:'POST', body:fd });
  loadContacts();
}

async function deleteSelected() {
  const ids = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
  if (!ids.length || !confirm(`¿Eliminar ${ids.length} contacto(s)?`)) return;
  const fd = new FormData(); fd.append('action','delete_many'); fd.append('ids', ids.join(','));
  await fetch('/admin/email_marketing_importar_excel_api.php', { method:'POST', body:fd });
  loadContacts();
}

// ── Gestión tipos de correo ────────────────────────────────────────────
async function addTipo() {
  const inp = document.getElementById('nuevoTipo');
  const nombre = inp.value.trim();
  if (!nombre) return;
  const fd = new FormData(); fd.append('action','add_tipo'); fd.append('nombre', nombre);
  const r = await fetch('/admin/email_marketing_importar_excel_api.php', { method:'POST', body:fd });
  const d = await r.json();
  if (d.ok) {
    // Agregar a la lista y al select
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center px-0 py-1';
    li.innerHTML = `<span>${nombre}</span>
      <button class="btn btn-xs btn-outline-danger py-0 px-1"
              onclick="deleteTipo(${d.id}, this)">
        <i class="fas fa-times"></i></button>`;
    document.getElementById('tiposList').appendChild(li);
    const opt = new Option(nombre, d.id);
    document.getElementById('tipoCorreo').appendChild(opt.cloneNode(true));
    document.getElementById('filtroTipo').appendChild(opt);
    inp.value = '';
  } else { alert(d.error || 'Error'); }
}

async function deleteTipo(id, btn) {
  if (!confirm('¿Eliminar este tipo? Los contactos asociados perderán su clasificación.')) return;
  const fd = new FormData(); fd.append('action','delete_tipo'); fd.append('id', id);
  const r  = await fetch('/admin/email_marketing_importar_excel_api.php', { method:'POST', body:fd });
  const d  = await r.json();
  if (d.ok) { btn.closest('li').remove(); loadContacts(); }
  else alert(d.error || 'Error');
}

// Cargar contactos al iniciar
loadContacts();
</script>
