<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$id = json_decode(file_get_contents('php://input'), true)['id'] ?? 0;

try {
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success', 'message' => 'Xóa thành công']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
