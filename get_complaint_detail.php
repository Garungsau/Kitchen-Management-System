<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;

try {
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.user_id,
            s.full_name as employee_name,
            s.employee_id,
            c.complaint_type,
            c.description,
            c.status,
            c.created_date
        FROM meal_complaints c
        JOIN students s ON c.user_id = s.user_id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$complaint) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy khiếu nại']);
        exit;
    }
    
    // Get images
    $stmt = $conn->prepare("
        SELECT file_path FROM complaint_files WHERE complaint_id = ?
    ");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $complaint['images'] = $images;
    echo json_encode(['status' => 'success', 'data' => $complaint]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
