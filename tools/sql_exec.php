<?php
/**
 * SQL EXECUTOR — Admin
 * - Login simple
 * - Editor de SQL con historial (SQLite)
 * - Muestra resultados de SELECT, PRAGMA, EXPLAIN, SHOW, WITH
 * - Historial: Guardar, Cargar en editor, Eliminar (con CSRF)
 * - Botón 🧹 Limpiar (SQL + etiqueta + autosave)
 * - Anti-cache, muestra "SQL ejecutado", ejecuta solo la primera sentencia y quita comentarios
 */

require_once __DIR__ . '/../includes/db.php';
session_start();

/* ===== Anti-cache: evitar resultados viejos del navegador ===== */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

/* ====== CONFIG ====== */
define('ADMIN_USER', 'mugarte');    // cámbialo
define('ADMIN_PASS', 'Marden7i/');  // cámbiala
/* ==================== */

// (Opcional) habilitar temporalmente para depuración de error 500
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: sql_exec.php');
  exit;
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  die('❌ Error conectando a la base de datos: ' . htmlspecialchars($e->getMessage()));
}

$msg  = '';
$html = '';

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_sql_exec'])) {
  $_SESSION['csrf_sql_exec'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_sql_exec'];

/* ===== Helpers de Historial (SQLite y MySQL compat) ===== */
function ensureHistoryTable(PDO $pdo): void {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'sqlite') {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tools_sql_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NULL,
        sql_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    ");
  } else {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tools_sql_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(255) NULL,
        sql_text LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB
    ");
  }
}

function saveHistory(PDO $pdo, string $sqlText, ?string $label = null): void {
  if (trim($sqlText) === '') return;
  $stmt = $pdo->prepare("INSERT INTO tools_sql_history (label, sql_text) VALUES (:label, :sql_text)");
  $stmt->execute([':label' => $label ?: null, ':sql_text' => $sqlText]);
}

function loadHistory(PDO $pdo, int $id): ?array {
  $stmt = $pdo->prepare("SELECT id, label, sql_text, created_at FROM tools_sql_history WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function deleteHistory(PDO $pdo, int $id): void {
  $stmt = $pdo->prepare("DELETE FROM tools_sql_history WHERE id = :id");
  $stmt->execute([':id' => $id]);
}

function listHistory(PDO $pdo, int $limit = 25): array {
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  if ($driver === 'sqlite') {
    $sql = "SELECT id, label, created_at, substr(sql_text,1,120) AS preview
            FROM tools_sql_history
            ORDER BY id DESC
            LIMIT :lim";
  } else {
    $sql = "SELECT id, label, created_at, SUBSTRING(sql_text,1,120) AS preview
            FROM tools_sql_history
            ORDER BY id DESC
            LIMIT :lim";
  }
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ===== Utils para normalizar y detección de sentencia ===== */
function stripSqlComments(string $sql): string {
  // Quita comentarios de línea -- ... y /* ... */
  $sql = preg_replace('/--.*$/m', '', $sql);
  $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
  return trim($sql);
}

function firstToken(string $sql): string {
  $clean = ltrim($sql);
  $token = strtoupper(strtok($clean, " \t\r\n("));
  return $token ?: '';
}

// Devuelve solo la primera sentencia (hasta el primer ; si existe)
function firstStatement(string $sql): string {
  $pos = strpos($sql, ';');
  if ($pos !== false) {
    return trim(substr($sql, 0, $pos));
  }
  return trim($sql);
}

/* ========== NO LOGUEADO: mostrar login y validar credenciales ========== */
if (empty($_SESSION['sql_exec_logged'])) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_login'])) {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
      $_SESSION['sql_exec_logged'] = true;
      header('Location: sql_exec.php'); // refresca a la vista de SQL
      exit;
    } else {
      $msg = '❌ Usuario o contraseña incorrectos.';
    }
  }
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>SQL Executor — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#f9fafb;margin:0}
      .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.08);width:320px}
      h1{font-size:20px;margin:0 0 14px}
      input{width:100%;padding:10px;margin:6px 0 10px;border-radius:8px;border:1px solid #d1d5db}
      button{width:100%;background:#2563eb;color:#fff;padding:10px;border:none;border-radius:8px;cursor:pointer}
      button:hover{background:#1d4ed8}
      .msg{margin-bottom:10px;padding:10px;border-radius:8px;background:#fef2f2;color:#991b1b;border-left:5px solid #dc2626}
      .hint{color:#6b7280;font-size:12px;margin-top:8px}
    </style>
  </head>
  <body>
    <form class="card" method="post">
      <h1>🔒 SQL Executor</h1>
      <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <input type="text" name="user" placeholder="Usuario" required>
      <input type="password" name="pass" placeholder="Contraseña" required>
      <button name="do_login" value="1">Entrar</button>
      <div class="hint">Acceso restringido</div>
    </form>
  </body>
  </html>
  <?php
  exit;
}

/* ========= LOGUEADO: preparar tabla de historial y manejar acciones ========= */
try {
  ensureHistoryTable($pdo);
} catch (Throwable $e) {
  die('❌ Error creando tabla tools_sql_history: ' . htmlspecialchars($e->getMessage()));
}

/* Acciones GET seguras (CARGAR / ELIMINAR) */
if (isset($_GET['action'], $_GET['id'], $_GET['csrf']) && hash_equals($CSRF, $_GET['csrf'])) {
  $id = (int) $_GET['id'];
  if ($_GET['action'] === 'delete') {
    try {
      deleteHistory($pdo, $id);
      $msg = '✅ Query eliminado del historial.';
    } catch (Throwable $e) {
      $msg = '❌ Error eliminando: ' . htmlspecialchars($e->getMessage());
    }
  } elseif ($_GET['action'] === 'load') {
    $row = loadHistory($pdo, $id);
    if ($row) {
      $_POST['sql'] = $row['sql_text']; // precarga en textarea
      $msg = '✅ Query cargado desde historial.';
    } else {
      $msg = '⚠️ No se encontró el registro solicitado.';
    }
  }
}

/* Manejo de POST: ejecutar, guardar manual, etc. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf']) && hash_equals($CSRF, $_POST['csrf'])) {

  // Guardar manualmente al historial (sin ejecutar)
  if (isset($_POST['save_sql'])) {
    $sqlToSave = trim($_POST['sql'] ?? '');
    $label     = trim($_POST['label'] ?? '');
    if ($sqlToSave === '') {
      $msg = '⚠️ No hay SQL para guardar.';
    } else {
      try {
        saveHistory($pdo, $sqlToSave, $label ?: null);
        $msg = '✅ Query guardado en historial.';
      } catch (Throwable $e) {
        $msg = '❌ Error guardando historial: ' . htmlspecialchars($e->getMessage());
      }
    }
  }

  // Ejecutar SQL (con opción de autosave)
  if (isset($_POST['run_sql'])) {
    $sql = trim($_POST['sql'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $autosave = !empty($_POST['autosave']);

    if ($sql === '') {
      $msg = '⚠️ Ingresa una consulta SQL.';
    } else {
      try {
        $start = microtime(true);

        // Normaliza y detecta correctamente la sentencia a ejecutar
        $originalSql = $sql;
        $sqlNoComments = stripSqlComments($originalSql);
        $toExecute = firstStatement($sqlNoComments);     // ejecutamos solo la primera sentencia visible
        $first = firstToken($toExecute);
        $shouldFetch = in_array($first, ['SELECT','PRAGMA','EXPLAIN','SHOW','WITH'], true);

        $rows = [];
        $executedSql = $toExecute; // para mostrar exactamente lo ejecutado

        if ($toExecute === '') {
          $msg = '⚠️ El SQL quedó vacío tras quitar comentarios o no se detectó sentencia ejecutable.';
        } else {
          if ($shouldFetch) {
            $stmt = $pdo->query($toExecute);
            if ($stmt instanceof PDOStatement) {
              $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
          } else {
            // Intentar query() primero: si hay cursor (columnCount>0), mostramos; si no, exec()
            $res = $pdo->query($toExecute);
            if ($res instanceof PDOStatement && $res->columnCount() > 0) {
              $rows = $res->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
              $pdo->exec($toExecute);
            }
          }

          $elapsed = round((microtime(true) - $start) * 1000, 2);

          // Muestra exactamente qué se ejecutó
          $htmlHeader  = "<div style='margin-top:8px;padding:8px;border:1px dashed #d1d5db;border-radius:8px;background:#fafafa'>";
          $htmlHeader .= "<div style='font-size:12px;color:#6b7280;margin-bottom:6px'>SQL ejecutado:</div>";
          $htmlHeader .= "<pre style='white-space:pre-wrap;margin:0'>".htmlspecialchars($executedSql)."</pre>";
          $htmlHeader .= "</div>";

          if (!empty($rows)) {
            $cols = array_keys($rows[0]);
            $t  = "<div style='overflow:auto;margin-top:10px'><table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;min-width:600px'>";
            $t .= "<tr style='background:#f3f4f6'>";
            foreach ($cols as $c) $t .= "<th>".htmlspecialchars($c)."</th>";
            $t .= "</tr>";
            foreach ($rows as $r) {
              $t .= "<tr>";
              foreach ($cols as $c) $t .= "<td>".htmlspecialchars((string)($r[$c] ?? '')) . "</td>";
              $t .= "</tr>";
            }
            $t .= "</table></div>";
            $html = $htmlHeader . $t;
            $msg = "✅ Resultado mostrado ({$elapsed} ms, ".count($rows)." filas)";
          } else {
            $html = $htmlHeader;
            $msg = "✅ SQL ejecutado ({$elapsed} ms, sin filas)";
          }

          if ($autosave) {
            saveHistory($pdo, $originalSql, $label ?: null); // guardamos lo que escribió tal cual
            $msg .= "<br>💾 También se guardó en el historial.";
          }
        }
      } catch (Throwable $e) {
        $msg = "❌ <b>Error:</b> ".htmlspecialchars($e->getMessage());
      }
    }
  }
}

// Valor retenido del textarea
$prev_sql_default = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;";
$prev_sql = isset($_POST['sql']) ? (string)$_POST['sql'] : $prev_sql_default;

// Historial para la UI
try {
  $history = listHistory($pdo, 25);
} catch (Throwable $e) {
  $history = [];
  $msg = $msg ?: ('❌ Error listando historial: ' . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>SQL Executor — Admin Tools</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:sans-serif;background:#f9fafb;margin:0;padding:24px}
  .wrap{max-width:1100px;margin:0 auto;background:#fff;padding:22px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.08)}
  h1{margin:0 0 10px}
  .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
  .btn{background:#2563eb;color:#fff;padding:9px 14px;border:none;border-radius:8px;text-decoration:none;cursor:pointer}
  .btn:hover{background:#1d4ed8}
  .btn-gray{background:#9ca3af}
  textarea{width:100%;height:200px;font-family:monospace;padding:10px;border-radius:8px;border:1px solid #d1d5db}
  .grid{display:grid;grid-template-columns:1fr 330px;gap:18px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
  .run{margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .msg{margin-top:14px;padding:12px;border-radius:8px}
  .ok{background:#ecfdf5;color:#065f46;border-left:5px solid #16a34a}
  .err{background:#fef2f2;color:#991b1b;border-left:5px solid #dc2626}
  .hint{color:#6b7280;font-size:12px;margin-top:6px}
  .hist-item{border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-bottom:8px}
  .hist-head{display:flex;justify-content:space-between;gap:8px;align-items:center}
  .hist-title{font-weight:600}
  .hist-actions a{font-size:12px;margin-left:8px;text-decoration:none}
  .muted{color:#6b7280;font-size:12px}
  input[type="text"]{width:100%;padding:9px;border-radius:8px;border:1px solid #d1d5db}
  label{font-size:12px;color:#374151}
</style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1>🛠️ SQL Executor</h1>
      <a class="btn btn-gray" href="?logout=1">Salir</a>
    </div>

    <div class="grid">
      <!-- Columna izquierda: Editor -->
      <div class="card">
        <form method="post" id="form-sql" action="">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
          <label for="label">Etiqueta (opcional)</label>
          <input id="label" type="text" name="label" placeholder="Ej: Migración imágenes, limpieza de stock..." value="<?= htmlspecialchars($_POST['label'] ?? '') ?>">
          <label for="sql" style="display:block;margin-top:8px">SQL</label>
          <textarea id="sql" name="sql" placeholder="Pega aquí tu SQL..."><?= htmlspecialchars($prev_sql) ?></textarea>

          <div class="run">
            <button type="submit" class="btn" name="run_sql" value="1">Ejecutar SQL</button>
            <label style="display:flex;align-items:center;gap:6px">
              <input id="autosave" type="checkbox" name="autosave" value="1" <?= !empty($_POST['autosave']) ? 'checked' : '' ?>>
              Guardar este SQL tras ejecutar
            </label>
            <button type="submit" class="btn" name="save_sql" value="1">💾 Guardar en historial</button>

            <!-- Botón limpiar -->
            <button class="btn btn-gray" type="button" id="btn-clear-sql">🧹 Limpiar</button>
          </div>
        </form>

        <?php if($msg): ?>
          <div class="msg <?= (strpos($msg,'❌')!==false?'err':'ok') ?>"><?= $msg ?></div>
          <?= $html ?>
        <?php endif; ?>

        <div class="hint">
          Ejemplos: <code>PRAGMA table_info(affiliate_payment_methods);</code> ·
          <code>SELECT * FROM pragma_table_info('affiliate_payment_methods');</code><br>
          El historial guarda únicamente el texto del query.
        </div>
      </div>

      <!-- Columna derecha: Historial -->
      <div class="card">
        <h3 style="margin:0 0 10px">🗃️ Historial de Queries (últimos 25)</h3>
        <?php if (!$history): ?>
          <div class="muted">Aún no has guardado queries.</div>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <div class="hist-item">
              <div class="hist-head">
                <div class="hist-title">
                  <?= htmlspecialchars($h['label'] ?: 'Sin etiqueta') ?>
                </div>
                <div class="hist-actions">
                  <a class="btn btn-gray" style="padding:4px 8px"
                     href="?action=load&id=<?= (int)$h['id'] ?>&csrf=<?= urlencode($CSRF) ?>">Cargar</a>
                  <a class="btn" style="background:#ef4444;padding:4px 8px"
                     href="?action=delete&id=<?= (int)$h['id'] ?>&csrf=<?= urlencode($CSRF) ?>"
                     onclick="return confirm('¿Eliminar este query del historial?');">Eliminar</a>
                </div>
              </div>
              <div class="muted">#<?= (int)$h['id'] ?> — <?= htmlspecialchars($h['created_at']) ?></div>
              <div class="muted" style="margin-top:6px;white-space:pre-wrap">
                <?= htmlspecialchars($h['preview']) ?><?= (strlen($h['preview'])>=120?'…':'') ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Script: limpiar SQL + etiqueta + autosave -->
  <script>
    (function(){
      const btn = document.getElementById('btn-clear-sql');
      if (!btn) return;
      const textarea = document.getElementById('sql');
      const label = document.getElementById('label');
      const autosave = document.getElementById('autosave');

      btn.addEventListener('click', function(){
        if (textarea) textarea.value = '';
        if (label) label.value = '';
        if (autosave) autosave.checked = false;
        if (textarea) textarea.focus();
      });
    })();
  </script>
</body>
</html>
