<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$complaint_id = $data['complaint_id'] ?? 0;
$status = $data['status'] ?? '';
$response_note = $data['response_note'] ?? '';

if (!in_array($status, ['resolved', 'rejected'])) {
    echo json_encode(['status' => 'error', 'message' => 'Status không hợp lệ']);
    exit;
}

try {
    $stmt = $conn->prepare("
        UPDATE meal_complaints
        SET status = ?, response_note = ?, resolved_date = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $response_note, $complaint_id]);
    echo json_encode(['status' => 'success', 'message' => 'Đã lưu xử lý']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
