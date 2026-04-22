<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$role = current_role();
$target_user_id = current_user_id();
if ($role === 'admin' && isset($_GET['target_user_id'])) {
    $target_user_id = intval($_GET['target_user_id']);
}

try {
    $stmt = $conn->prepare("SELECT s.full_name, s.student_id_no, s.department,
                                   fe.id AS face_id, fe.sample_image_path, fe.quality_score,
                                   fe.model_name, fe.updated_at, fe.created_at, fe.is_active
                            FROM students s
                            LEFT JOIN face_embeddings fe ON fe.user_id = s.user_id
                            WHERE s.user_id = ?");
    $stmt->execute([$target_user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["status" => "error", "message" => "Không tìm thấy hồ sơ nhân viên."]);
        exit();
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "full_name" => $row['full_name'],
            "student_id_no" => $row['student_id_no'],
            "department" => $row['department'],
            "registered" => !empty($row['face_id']) && intval($row['is_active']) === 1,
            "model_name" => $row['model_name'] ?: null,
            "quality_score" => $row['quality_score'] !== null ? floatval($row['quality_score']) : null,
            "sample_image_path" => $row['sample_image_path'] ?: null,
            "updated_at" => $row['updated_at'] ?: null,
            "created_at" => $row['created_at'] ?: null
        ]
    ]);
} catch (Exception $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'face_embeddings') !== false) {
        $msg = 'Thiếu bảng face_embeddings. Vui lòng chạy migration DATABASE_MIGRATION_FACE_EMBEDDINGS.sql trước.';
    }
    echo json_encode(["status" => "error", "message" => $msg]);
}
?>