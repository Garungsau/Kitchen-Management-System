<?php
require_once 'config.php';
require_once 'auth.php';
require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$target_role = $input['target_role'] ?? 'all';

try {
    $stmt = $conn->prepare("INSERT INTO notices (title, message, posted_by, target_role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$input['title'], $input['message'], current_user_id(), $target_role]);
    echo json_encode(["status" => "success", "message" => "Thông báo đã đăng"]);
} catch (Exception $e) { 
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]); 
}
?>