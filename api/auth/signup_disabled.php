<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'error',
    'message' => 'Tính năng đăng ký mới đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên để tạo tài khoản.'
]);
?>
