<?php
require_once 'config.php';

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
            c.created_date,
            COUNT(DISTINCT f.id) as image_count
        FROM meal_complaints c
        JOIN students s ON c.user_id = s.user_id
        LEFT JOIN complaint_files f ON c.id = f.complaint_id
        GROUP BY c.id
        ORDER BY c.created_date DESC
        LIMIT 50
    ");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
