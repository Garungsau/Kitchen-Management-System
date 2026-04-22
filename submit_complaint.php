<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
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

$isMultipart = !empty($_POST) || isset($_FILES['complaint_images']);
$input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
$user_id = current_user_id();
$meal_date = isset($input['meal_date']) ? trim($input['meal_date']) : date('Y-m-d');
$complaint_type = isset($input['complaint_type']) ? trim($input['complaint_type']) : 'Khác';
$content = isset($input['content']) ? trim($input['content']) : '';

if ($content === '') {
    echo json_encode(["status" => "error", "message" => "Nội dung khiếu nại không được để trống."]);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meal_date)) {
    echo json_encode(["status" => "error", "message" => "Ngày suất ăn không hợp lệ."]);
    exit();
}

// Hard rule: chỉ cho khiếu nại khi đã xác nhận check-in của đúng ngày suất ăn
$stmt = $conn->prepare("SELECT status, check_in_time FROM meal_attendance 
                         WHERE user_id = ? AND meal_date = ? 
                           AND (status = 'checked_in' OR check_in_time IS NOT NULL)");
$stmt->execute([$user_id, $meal_date]);
if (!$stmt->fetch()) {
    echo json_encode(["status" => "error", "message" => "Chỉ có thể gửi khiếu nại cho ngày đã xác nhận check-in."]);
    exit();
}

$uploadedPaths = [];

if (!isset($_FILES['complaint_images'])) {
    echo json_encode(["status" => "error", "message" => "Vui lòng tải lên ít nhất 1 ảnh minh chứng (JPEG)."]);
    exit();
}

$fileBag = $_FILES['complaint_images'];
$names = isset($fileBag['name']) ? (array)$fileBag['name'] : [];
$tmpNames = isset($fileBag['tmp_name']) ? (array)$fileBag['tmp_name'] : [];
$errors = isset($fileBag['error']) ? (array)$fileBag['error'] : [];
$sizes = isset($fileBag['size']) ? (array)$fileBag['size'] : [];

$totalFiles = count($names);
if ($totalFiles < 1) {
    echo json_encode(["status" => "error", "message" => "Vui lòng tải lên ít nhất 1 ảnh minh chứng (JPEG)."]);
    exit();
}
if ($totalFiles > 5) {
    echo json_encode(["status" => "error", "message" => "Chỉ được tải lên tối đa 5 ảnh JPEG."]);
    exit();
}

try {
    ensureComplaintSchema($conn);

    $uploadDir = dirname(__DIR__) . '/uploads/complaints/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Không thể tạo thư mục lưu ảnh minh chứng.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $totalFiles; $i++) {
        $errorCode = isset($errors[$i]) ? (int)$errors[$i] : UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new Exception('Tải ảnh lên thất bại. Vui lòng thử lại.');
        }

        $tmp = $tmpNames[$i] ?? '';
        $name = strtolower((string)($names[$i] ?? ''));
        $size = isset($sizes[$i]) ? (int)$sizes[$i] : 0;

        if (!is_uploaded_file($tmp)) {
            throw new Exception('Tệp ảnh không hợp lệ.');
        }
        if ($size <= 0 || $size > 6 * 1024 * 1024) {
            throw new Exception('Mỗi ảnh phải có dung lượng từ 1 byte đến 6MB.');
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!in_array($ext, ['jpg', 'jpeg'], true)) {
            throw new Exception('Chỉ chấp nhận ảnh định dạng JPEG/JPG.');
        }

        $mime = strtolower((string)$finfo->file($tmp));
        if ($mime !== 'image/jpeg') {
            throw new Exception('Ảnh minh chứng phải là JPEG hợp lệ.');
        }

        $filename = sprintf('complaint_%d_%s_%d.jpg', $user_id, date('YmdHis'), $i + 1);
        $targetPath = $uploadDir . $filename;
        $dbPath = 'uploads/complaints/' . $filename;

        if (!move_uploaded_file($tmp, $targetPath)) {
            throw new Exception('Không thể lưu ảnh minh chứng.');
        }

        $uploadedPaths[] = $dbPath;
    }

    if (count($uploadedPaths) < 1) {
        throw new Exception('Vui lòng tải lên ít nhất 1 ảnh minh chứng (JPEG).');
    }

    $stmt = $conn->prepare("INSERT INTO meal_complaints (user_id, meal_date, complaint_type, content, evidence_images)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $meal_date, $complaint_type, $content, json_encode($uploadedPaths, JSON_UNESCAPED_UNICODE)]);

    echo json_encode([
        "status" => "success",
        "message" => "Đã gửi khiếu nại thành công.",
        "uploaded_images" => $uploadedPaths
    ]);
} catch (Exception $e) {
    foreach ($uploadedPaths as $p) {
        $full = dirname(__DIR__) . '/' . ltrim($p, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>