<?php
require_once 'config.php';
require_once 'auth.php';
require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$target_role = $input['target_role'] ?? 'all';

try {
    $stmt = $conn->prepare("UPDATE notices SET title = ?, message = ?, target_role = ? WHERE id = ?");
    $stmt->execute([$input['title'], $input['message'], $target_role, $input['id']]);
    echo json_encode(["status" => "success", "message" => "Đã cập nhật"]);
} catch (Exception $e) { 
    echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]); 
}
?>