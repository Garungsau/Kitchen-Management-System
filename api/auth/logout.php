<?php
require_once __DIR__ . '/../auth.php';
start_session_if_needed();
session_unset();
session_destroy();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'success']);
?>
