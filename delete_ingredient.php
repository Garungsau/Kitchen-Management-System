<?php
require_once 'config.php';

$id = json_decode(file_get_contents('php://input'), true)['id'] ?? 0;

try {
    $stmt = $conn->prepare("DELETE FROM kitchen_ingredients WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success', 'message' => 'Xóa thành công']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
