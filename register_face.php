<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

const FACE_MIN_IMAGES = 5;
const FACE_MIN_EMBED_DIM = 64;
const FACE_MAX_EMBED_DIM = 512;

function ensure_face_table(PDO $conn): void {
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS user_face_data (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            face_encodings LONGTEXT NOT NULL,
            sample_images LONGTEXT NULL,
            registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $check = $conn->query("SHOW COLUMNS FROM user_face_data LIKE 'sample_images'");
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $conn->exec("ALTER TABLE user_face_data ADD COLUMN sample_images LONGTEXT NULL AFTER face_encodings");
    }
}

function decode_data_url_image(string $dataUrl, ?string &$ext = null): ?string {
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl, $m)) {
        return null;
    }
    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    $base64 = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $dataUrl);
    $binary = base64_decode((string)$base64, true);
    return ($binary === false) ? null : $binary;
}

function save_watermarked_image(string $dataUrl, string $userFolder, string $prefix): ?string {
    $ext = null;
    $binary = decode_data_url_image($dataUrl, $ext);
    if ($binary === null) {
        return null;
    }

    $image = @imagecreatefromstring($binary);
    if (!$image) {
        return null;
    }

    $w = imagesx($image);
    $h = imagesy($image);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($image);
        return null;
    }

    $timestamp = date('Y-m-d H:i:s');
    $label = 'TS ' . $timestamp;

    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 55);
    $text = imagecolorallocate($image, 255, 255, 255);

    $font = 3;
    $textX = 10;
    $textY = max(10, $h - 20);
    imagefilledrectangle($image, 6, $textY - 4, min($w - 6, 260), $textY + 16, $shadow);
    imagestring($image, $font, $textX, $textY, $label, $text);

    $dirAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces' . DIRECTORY_SEPARATOR . $userFolder;
    if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0777, true)) {
        imagedestroy($image);
        return null;
    }

    $name = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $fileAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
    $ok = imagejpeg($image, $fileAbs, 88);
    imagedestroy($image);

    if (!$ok) {
        return null;
    }

    return 'assets/uploads/faces/' . $userFolder . '/' . $name;
}

function extract_embedding_server_side(string $imageRelativePath): ?array {
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'face_embedding_server.py';
    if (!is_file($script)) {
        return null;
    }

    $imageAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imageRelativePath);
    $commands = [
        'python ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs),
        'py -3 ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs),
        'py ' . escapeshellarg($script) . ' --image ' . escapeshellarg($imageAbs)
    ];

    foreach ($commands as $cmd) {
        $output = @shell_exec($cmd . ' 2>&1');
        if (!is_string($output) || trim($output) === '') {
            continue;
        }
        $json = json_decode(trim($output), true);
        if (!is_array($json)) {
            continue;
        }
        if (($json['status'] ?? '') !== 'success' || !isset($json['embedding']) || !is_array($json['embedding'])) {
            continue;
        }
        $embedding = array_map('floatval', $json['embedding']);
        $dim = count($embedding);
        if ($dim < FACE_MIN_EMBED_DIM || $dim > FACE_MAX_EMBED_DIM) {
            continue;
        }
        return $embedding;
    }

    return null;
}

$userId = current_user_id();
$input = json_decode(file_get_contents('php://input'), true);
$faces = (isset($input['faces']) && is_array($input['faces'])) ? $input['faces'] : [];

if (count($faces) < FACE_MIN_IMAGES) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Can it nhat 5 anh khuon mat de dang ky.']);
    exit;
}

try {
    ensure_face_table($conn);

    $stmt = $conn->prepare('SELECT s.user_id FROM students s WHERE s.user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Khong tim thay ho so nhan vien.']);
        exit;
    }

    $encodings = [];
    $images = [];
    $userFolder = 'u' . $userId;

    foreach ($faces as $idx => $face) {
        if (!is_array($face)) {
            continue;
        }
        $rawImage = trim((string)($face['image'] ?? $face['snapshot_data_url'] ?? ''));
        if ($rawImage === '') {
            continue;
        }

        $savedPath = save_watermarked_image($rawImage, $userFolder, 'reg');
        if ($savedPath === null) {
            continue;
        }

        $embedding = extract_embedding_server_side($savedPath);
        if ($embedding === null) {
            continue;
        }

        $images[] = $savedPath;
        $encodings[] = $embedding;
    }

    if (count($encodings) < FACE_MIN_IMAGES) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'message' => 'Khong trich xuat du embedding tren server. Kiem tra Python/thu vien face_recognition va chat luong anh.'
        ]);
        exit;
    }

    $encJson = json_encode($encodings, JSON_UNESCAPED_UNICODE);
    $imgJson = json_encode($images, JSON_UNESCAPED_UNICODE);

    $upsert = $conn->prepare(
        'INSERT INTO user_face_data (user_id, face_encodings, sample_images) VALUES (?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE face_encodings = VALUES(face_encodings), sample_images = VALUES(sample_images), updated_date = CURRENT_TIMESTAMP'
    );
    $upsert->execute([$userId, $encJson, $imgJson]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Dang ky khuon mat thanh cong (embedding xu ly server-side).',
        'face_count' => count($encodings),
        'images_saved' => count($images)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?><?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'auth.php';

require_role(['employee', 'admin']);

$user_id = current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['faces']) || !is_array($input['faces']) || count($input['faces']) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No face data provided']);
    exit;
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

    // Extract face descriptors only (don't stored base64 images to save space)
    $faceDescriptors = array_map(function($face) {
        return $face['descriptor']; // Only keep the 128-dimensional descriptor array
    }, $input['faces']);

    // Check if user already has face data
    $stmt = $db->prepare("SELECT id FROM user_face_data WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing face data
        $stmt = $db->prepare("
            UPDATE user_face_data 
            SET face_encodings = ?, updated_date = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->execute([json_encode($faceDescriptors), $user_id]);
    } else {
        // Insert new face data
        $stmt = $db->prepare("
            INSERT INTO user_face_data (user_id, face_encodings)
            VALUES (?, ?)
        ");
        $stmt->execute([$user_id, json_encode($faceDescriptors)]);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Face registration successful',
        'face_count' => count($faceDescriptors),
        'registered_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
