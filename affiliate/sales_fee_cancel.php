<?php
require_once __DIR__ . '/../includes/affiliate_auth.php'; aff_require_login();
header("Location: sales.php");