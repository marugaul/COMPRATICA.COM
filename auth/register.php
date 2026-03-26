<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { safe_redirect('/', '/'); }
csrf_check();

$pdo = db();
$pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, name TEXT, phone TEXT, password_hash TEXT, status TEXT DEFAULT 'active', created_at TEXT DEFAULT (datetime('now')));
");

$name  = trim((string)($_POST['name'] ?? ''));
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$pass  = (string)($_POST['password'] ?? '');

$accept_terms = (string)($_POST['accept_terms'] ?? '');
if(!$name || !$email || strlen($pass)<8){
  safe_redirect('/checkout.php?signup=invalid', '/');
}
if($accept_terms !== '1'){
  safe_redirect('/checkout.php?signup=no_terms', '/');
}

try{
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo->prepare("INSERT INTO users(name,email,password_hash,status) VALUES(?,?,?, 'active')")->execute([$name,$email,$hash]);
  $uid = (int)$pdo->lastInsertId();

  // Registrar aceptación de T&C
  try {
    $tcStmt = $pdo->prepare("SELECT version FROM terms_conditions WHERE type='cliente' AND is_active=1 LIMIT 1");
    $tcStmt->execute();
    $tcRow = $tcStmt->fetch(PDO::FETCH_ASSOC);
    $tcVersion = $tcRow['version'] ?? '1.0';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare("INSERT INTO terms_acceptances (user_table, user_id, terms_type, version, ip_address) VALUES ('users', ?, 'cliente', ?, ?)")
        ->execute([$uid, $tcVersion, $ip]);
  } catch (Throwable $e) { error_log('[register.php] T&C record failed: '.$e->getMessage()); }

  session_regenerate_id(true);
  $_SESSION['uid'] = $uid;
  safe_redirect('/checkout.php?signup=ok', '/');
}catch(Throwable $e){
  // si el correo existe
  safe_redirect('/checkout.php?signup=exists', '/');
}
