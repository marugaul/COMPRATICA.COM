<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$cols = $pdo->query("PRAGMA table_info(affiliates)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
  echo $c['name'] . " (" . $c['type'] . ")\n";
}
