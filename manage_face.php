<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$user_id = current_user_id();
$role = current_role();

// Get action from GET or POST
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = trim($_GET['action']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? trim($input['action']) : '';
}

try {
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create faces table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_face_data (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            face_encodings LONGTEXT NOT NULL COMMENT 'JSON array of face descriptors',
            registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Action: check (alias for check_status)
    if ($action === 'check' || $action === 'check_status') {
        $stmt = $db->prepare("SELECT id, registered_date, updated_date, face_encodings FROM user_face_data WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $faceData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($faceData) {
            $faceCount = 0;
            try {
                $encodings = json_decode($faceData['face_encodings'], true);
                $faceCount = is_array($encodings) ? count($encodings) : 0;
            } catch (Exception $e) {
                $faceCount = 0;
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'registered',
                'registered_date' => $faceData['registered_date'],
                'updated_date' => $faceData['updated_date'],
                'face_count' => $faceCount,
                'message' => 'Khuôn mặt đã được đăng ký'
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'status' => 'not_registered',
                'message' => 'Chưa đăng ký khuôn mặt'
            ]);
        }
    } 
    // Action: delete
    elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM user_face_data WHERE user_id = ?");
        $stmt->execute([$user_id]);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Đã xóa dữ liệu khuôn mặt thành công'
        ]);
    } 
    // Action: check_status (legacy)
    elseif ($action === 'check_status_legacy') {
        $stmt = $db->prepare("SELECT id, registered_date, updated_date FROM user_face_data WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $faceData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($faceData) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'has_face_data' => true,
                'registered_date' => $faceData['registered_date'],
                'updated_date' => $faceData['updated_date'],
                'message' => 'Tài khoản của bạn đã đăng ký khuôn mặt'
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'has_face_data' => false,
                'message' => 'Tài khoản của bạn chưa đăng ký khuôn mặt'
            ]);
        }
    } 
    // Action: get_total_registered (admin)
    elseif ($action === 'get_total_registered') {
        if ($role !== 'admin') {
            json_error('Forbidden', 'error', 403);
        }

        $stmt = $db->query("
            SELECT COUNT(*) as total_registered,
                   COUNT(CASE WHEN updated_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as registered_last_7_days
            FROM user_face_data
        ");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'total_registered' => (int)$stats['total_registered'],
            'registered_last_7_days' => (int)$stats['registered_last_7_days']
        ]);
    } 
    else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
