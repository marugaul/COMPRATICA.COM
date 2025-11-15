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

if(!$name || !$email || strlen($pass)<8){
  safe_redirect('/checkout.php?signup=invalid', '/');
}

try{
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo->prepare("INSERT INTO users(name,email,password_hash,status) VALUES(?,?,?, 'active')")->execute([$name,$email,$hash]);
  $uid = (int)$pdo->lastInsertId();
  session_regenerate_id(true);
  $_SESSION['uid'] = $uid;
  safe_redirect('/checkout.php?signup=ok', '/');
}catch(Throwable $e){
  // si el correo existe
  safe_redirect('/checkout.php?signup=exists', '/');
}
