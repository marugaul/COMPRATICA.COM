<?php
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'TEST OK', 'timestamp' => date('H:i:s')]);
