<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db(); $ok=[]; $err=[];

function colset($pdo, $table){
  try {
    $rows = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) { $out[] = $r['name']; }
    return $out;
  } catch (Exception $e) { return []; }
}

function nowts(){ return date('Y-m-d H:i:s'); }

// ----- Core tables -----
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS affiliates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE,
    email TEXT NOT NULL,
    phone TEXT,
    password_hash TEXT NOT NULL,
    avatar TEXT,
    is_active INTEGER DEFAULT 1,
    fee_pct REAL DEFAULT 0.10,
    created_at TEXT,
    updated_at TEXT
  )");
  $ok[]="affiliates OK";
}catch(Exception $e){ $err[]=$e->getMessage(); }

try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    cover_image TEXT,
    start_at TEXT NOT NULL,
    end_at TEXT NOT NULL,
    is_active INTEGER DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id)
  )");
  $ok[]="sales OK";
}catch(Exception $e){ $err[]=$e->getMessage(); }

// ----- settings (manejo flexible de columnas) -----
$settingsCols = colset($pdo, 'settings');
if (empty($settingsCols)) {
  // No existe la tabla → la creamos con key/val
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
      `key` TEXT PRIMARY KEY,
      `val` TEXT
    )");
    $ok[]="settings creada (key/val)";
    $settingsCols = ['key','val'];
  } catch (Exception $e) { $err[]=$e->getMessage(); }
} else {
  // Existe la tabla → nos adaptamos a su esquema
  $ok[]="settings OK (esquema existente: ".implode(', ',$settingsCols).")";
  // Si no hay key/val, intentamos añadirlas
  if (!in_array('key',$settingsCols) && !in_array('val',$settingsCols)) {
    // Caso típico: name/value
    if (in_array('name',$settingsCols) && in_array('value',$settingsCols)) {
      $ok[]="settings usará name/value";
    } else {
      // Añadimos columnas compatibles
      try {
        if (!in_array('key',$settingsCols))  { $pdo->exec("ALTER TABLE settings ADD COLUMN `key` TEXT");  $ok[]="settings.key agregado"; }
        if (!in_array('val',$settingsCols))  { $pdo->exec("ALTER TABLE settings ADD COLUMN `val` TEXT");  $ok[]="settings.val agregado"; }
        $settingsCols = colset($pdo,'settings');
      } catch (Exception $e) { $err[]=$e->getMessage(); }
    }
  } else {
    // Tiene key o val parcial → completamos si falta alguno
    try {
      if (!in_array('key',$settingsCols)) { $pdo->exec("ALTER TABLE settings ADD COLUMN `key` TEXT"); $ok[]="settings.key agregado"; }
      if (!in_array('val',$settingsCols)) { $pdo->exec("ALTER TABLE settings ADD COLUMN `val` TEXT"); $ok[]="settings.val agregado"; }
      $settingsCols = colset($pdo,'settings');
    } catch (Exception $e) { /* puede fallar en esquemas muy antiguos, seguimos */ }
  }
}

function settings_seed($pdo, $settingsCols){
  // Intentamos en este orden: (key,val) -> (name,value)
  try {
    if (in_array('key',$settingsCols) && in_array('val',$settingsCols)) {
      // Evita duplicar clave si ya existe
      $st = $pdo->prepare("SELECT 1 FROM settings WHERE `key`=? LIMIT 1");
      $st->execute(['SALE_FEE_CRC']);
      if (!$st->fetchColumn()) {
        $pdo->prepare("INSERT INTO settings(`key`,`val`) VALUES (?,?)")->execute(['SALE_FEE_CRC','2000']);
      }
      return "settings seeded (key/val)";
    } elseif (in_array('name',$settingsCols) && in_array('value',$settingsCols)) {
      $st = $pdo->prepare("SELECT 1 FROM settings WHERE name=? LIMIT 1");
      $st->execute(['SALE_FEE_CRC']);
      if (!$st->fetchColumn()) {
        $pdo->prepare("INSERT INTO settings(name,value) VALUES (?,?)")->execute(['SALE_FEE_CRC','2000']);
      }
      return "settings seeded (name/value)";
    } else {
      return "settings sin seed (esquema desconocido)";
    }
  } catch (Exception $e) {
    return "seed error: ".$e->getMessage();
  }
}

$ok[] = settings_seed($pdo, $settingsCols);

// ----- sale_fees -----
try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS sale_fees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    affiliate_id INTEGER NOT NULL,
    sale_id INTEGER NOT NULL,
    amount_crc REAL NOT NULL,
    amount_usd REAL NOT NULL,
    exrate_used REAL NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pendiente',
    proof_file TEXT,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
  )");
  $ok[]="sale_fees OK";
} catch(Exception $e){ $err[]=$e->getMessage(); }

// ----- columnas en products y orders -----
try{
  $cols=$pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
  $has=false; foreach($cols as $c){ if($c['name']==='affiliate_id') $has=true; }
  if(!$has){ $pdo->exec("ALTER TABLE products ADD COLUMN affiliate_id INTEGER DEFAULT NULL"); $ok[]="products.affiliate_id agregado"; }
  else { $ok[]="products.affiliate_id ya existía"; }
} catch(Exception $e){ $err[]=$e->getMessage(); }

try{
  $cols=$pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
  $ha=$hf=$hs=$hsale=false;
  foreach($cols as $c){
    if($c['name']==='affiliate_id') $ha=true;
    if($c['name']==='platform_fee') $hf=true;
    if($c['name']==='seller_amount') $hs=true;
    if($c['name']==='sale_id') $hsale=true;
  }
  if(!$ha)    $pdo->exec("ALTER TABLE orders ADD COLUMN affiliate_id INTEGER DEFAULT NULL");
  if(!$hsale) $pdo->exec("ALTER TABLE orders ADD COLUMN sale_id INTEGER DEFAULT NULL");
  if(!$hf)    $pdo->exec("ALTER TABLE orders ADD COLUMN platform_fee REAL DEFAULT 0");
  if(!$hs)    $pdo->exec("ALTER TABLE orders ADD COLUMN seller_amount REAL DEFAULT 0");
  $ok[]="orders columnas OK";
} catch(Exception $e){ $err[]=$e->getMessage(); }

// ----- seed afiliado base (si aplica) -----
try{
  $cnt=$pdo->query("SELECT COUNT(1) FROM affiliates")->fetchColumn();
  if(!$cnt){
    $name=APP_NAME.' — Casa 31 Monserrat';
    $slug='casa-31-monserrat';
    $email=ADMIN_EMAIL;
    $phone=SINPE_PHONE;
    $pass=password_hash('cambiar123', PASSWORD_DEFAULT);
    $now=nowts();
    $pdo->prepare("INSERT INTO affiliates (name,slug,email,phone,password_hash,fee_pct,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$name,$slug,$email,$phone,$pass,0.10,$now,$now]);
    $ok[]="Afiliado base creado (user: $email / pass: cambiar123)";
  } else {
    $ok[]="Afiliados existentes — no seed";
  }
} catch (Exception $e) { $err[]=$e->getMessage(); }

// ----- salida -----
header('Content-Type: text/plain; charset=utf-8');
echo "MIGRACIÓN\n\nOK:\n- ".implode("\n- ",$ok)."\n\nERRORES:\n- ".(empty($err)?'ninguno':implode("\n- ",$err));
