<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$valid = ['all', 'new', 'in_progress', 'resolved', 'rejected'];
if (!in_array($status, $valid, true)) {
    $status = 'all';
}

function ensureComplaintSchema(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS meal_complaints (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        meal_date DATE NOT NULL,
        complaint_type VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        evidence_images TEXT NULL,
        status ENUM('new','in_progress','resolved','rejected') DEFAULT 'new',
        admin_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $check = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meal_complaints' AND COLUMN_NAME = 'evidence_images'");
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $conn->exec("ALTER TABLE meal_complaints ADD COLUMN evidence_images TEXT NULL AFTER content");
    }
}

try {
    ensureComplaintSchema($conn);

    $where = '';
    $params = [];
    if ($status !== 'all') {
        $where = 'WHERE mc.status = ?';
        $params[] = $status;
    }

    $sql = "SELECT mc.id, mc.meal_date, mc.complaint_type, mc.content, mc.evidence_images, mc.status, mc.admin_note,
                   mc.created_at, mc.updated_at,
                   s.full_name, s.student_id_no, s.department
            FROM meal_complaints mc
            JOIN users u ON mc.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            $where
            ORDER BY mc.created_at DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $images = [];
        if (!empty($row['evidence_images'])) {
            $decoded = json_decode($row['evidence_images'], true);
            if (is_array($decoded)) {
                $images = array_values(array_filter($decoded, function ($v) {
                    return is_string($v) && trim($v) !== '';
                }));
            }
        }
        $row['evidence_images'] = $images;
    }
    unset($row);

    echo json_encode([
        "status" => "success",
        "filter" => $status,
        "data" => $rows
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>